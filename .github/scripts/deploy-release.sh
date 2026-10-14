#!/usr/bin/env bash
set -euo pipefail

if [[ "${RELEASE_CREATED:-false}" != true ]]; then
    echo 'No new release; skipping deployment.'
    exit 0
fi

if [[ "${COOLIFY_API_URL:-}" != https://coolify.zdossantos.fr/api/v1 ]]; then
    echo 'Use the intended HTTPS Coolify API URL.' >&2
    exit 1
fi
: "${COOLIFY_APPLICATION_UUID:?COOLIFY_APPLICATION_UUID is required}"
: "${COOLIFY_TOKEN:?COOLIFY_TOKEN is required}"
if [[ ! "$COOLIFY_APPLICATION_UUID" =~ ^[a-zA-Z0-9_-]+$ ]]; then
    echo 'Invalid Coolify application UUID.' >&2
    exit 1
fi

if [[ ! "${APP_IMAGE:-}" =~ ^ghcr\.io/zdossantos/plummo@sha256:[a-f0-9]{64}$ ]]; then
    echo 'A published GHCR image digest is required.' >&2
    exit 1
fi
if [[ ! "${RELEASE_SHA:-}" =~ ^[a-f0-9]{40}$ ]]; then
    echo 'An exact release commit is required.' >&2
    exit 1
fi

poll_attempts="${COOLIFY_POLL_ATTEMPTS:-90}"
poll_interval="${COOLIFY_POLL_INTERVAL:-10}"
[[ "$poll_attempts" =~ ^[0-9]+$ && "$poll_attempts" -ge 1 && "$poll_attempts" -le 120 ]]
[[ "$poll_interval" =~ ^[0-9]+$ && "$poll_interval" -le 30 ]]
migration_marker="PLUMMO_MIGRATIONS_OK_$RELEASE_SHA"
migration_command="php artisan migrate --force --no-interaction && printf '%s\n' '$migration_marker'"

response_file="$(mktemp)"
trap 'rm -f "$response_file"' EXIT
api_url="${COOLIFY_API_URL%/}"

# Discard API response bodies, which can contain runtime configuration.
# Do not retry mutations automatically: a timeout may hide a successful request.
request() {
    curl --fail --silent --show-error --connect-timeout 10 --max-time 60 \
        --request "$1" "$api_url/$2" \
        --header "Authorization: Bearer $COOLIFY_TOKEN" \
        --header 'Content-Type: application/json' \
        --data "$3" --output "$response_file"
}

# Coolify also interpolates Compose during its preparation/build phase.
# This public reference must be available there even without build services.
request PATCH "applications/$COOLIFY_APPLICATION_UUID/envs/bulk" \
    "$(jq -nc --arg image "$APP_IMAGE" '{data: [{key: "APP_IMAGE", value: $image,
        is_preview: false, is_literal: true, is_buildtime: true, is_runtime: true}]}')"

# Pin the Compose file too, even if main has advanced since this release.
request PATCH "applications/$COOLIFY_APPLICATION_UUID" \
    "$(jq -nc --arg sha "$RELEASE_SHA" --arg command "$migration_command" \
        '{git_commit_sha: $sha, post_deployment_command: $command, post_deployment_command_container: "web"}')"

request POST deploy \
    "$(jq -nc --arg uuid "$COOLIFY_APPLICATION_UUID" '{uuid: $uuid, force: false}')"

jq -e --arg uuid "$COOLIFY_APPLICATION_UUID" \
    '.deployments | any(.resource_uuid == $uuid and (.deployment_uuid | type == "string" and length > 0))' \
    "$response_file" >/dev/null
deployment_uuid="$(jq -er --arg uuid "$COOLIFY_APPLICATION_UUID" \
    '.deployments[] | select(.resource_uuid == $uuid) | .deployment_uuid' "$response_file")"
[[ "$deployment_uuid" =~ ^[a-zA-Z0-9_-]+$ ]]

# Coolify 4.3 marks FINISHED before running post-deployment commands and can
# swallow their failures. Require a standalone stdout marker from this release,
# never the echoed command or the deployment acknowledgement alone.
for ((attempt = 1; attempt <= poll_attempts; attempt++)); do
    curl --fail --silent --show-error --connect-timeout 10 --max-time 60 \
        --request GET "$api_url/deployments/$deployment_uuid" \
        --header "Authorization: Bearer $COOLIFY_TOKEN" --output "$response_file"
    jq -e --arg uuid "$deployment_uuid" '.deployment_uuid == $uuid' "$response_file" >/dev/null
    status="$(jq -er '.status' "$response_file")"
    case "$status" in
        failed|cancelled-by-user)
            echo 'Coolify deployment failed or was cancelled.' >&2
            exit 1
            ;;
        queued|in_progress|finished) ;;
        *) echo 'Unexpected Coolify deployment status.' >&2; exit 1 ;;
    esac
    if [[ "$status" == finished ]]; then
        jq -e --arg sha "$RELEASE_SHA" '.commit == $sha' "$response_file" >/dev/null
        if ! jq -e '.logs | type == "string"' "$response_file" >/dev/null; then
            echo 'Migration confirmation requires deployment logs (Coolify token read:sensitive permission).' >&2
            exit 1
        fi
        if jq -e --arg marker "$migration_marker" \
            '.logs | fromjson | any(.[]; .type == "stdout" and ((.output // "") | split("\n") | any(. == $marker)))' \
            "$response_file" >/dev/null; then
            echo 'Coolify deployed the exact release and completed its database migrations.'
            exit 0
        fi
        if jq -e '.logs | fromjson | any(.[]; .output == "Post-deployment command failed.")' \
            "$response_file" >/dev/null; then
            echo 'Post-deployment migrations failed.' >&2
            exit 1
        fi
    fi
    if ((attempt < poll_attempts)); then sleep "$poll_interval"; fi
done
echo 'Timed out waiting for deployment and successful database migrations. Inspect Coolify; do not retry deployment blindly.' >&2
exit 1
