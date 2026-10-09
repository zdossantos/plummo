#!/usr/bin/env python3
"""Exercise deployment failure boundaries using a local fake curl, never Coolify."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).with_name("deploy-release.sh").resolve()
FAKE_CURL = r"""#!/usr/bin/env python3
import json, os, sys
args = sys.argv[1:]
with open(os.environ['REQUEST_LOG'], 'a') as log:
    log.write(json.dumps({'method': args[args.index('--request') + 1],
                         'url': args[args.index('--request') + 2],
                         'data': json.loads(args[args.index('--data') + 1])}) + '\n')
count = len(open(os.environ['REQUEST_LOG']).readlines())
if count == int(os.environ.get('FAIL_REQUEST', '0')):
    sys.exit(22)
result = {'deployments': [{'resource_uuid': os.environ['COOLIFY_APPLICATION_UUID'],
                          'deployment_uuid': 'test-deployment'}]}
if os.environ.get('WRONG_RESOURCE'):
    result['deployments'][0]['resource_uuid'] = 'another-app'
with open(args[args.index('--output') + 1], 'w') as output:
    json.dump(result, output)
"""

class DeploymentTest(unittest.TestCase):
    def invoke(self, **overrides):
        with tempfile.TemporaryDirectory() as directory:
            fake = Path(directory, 'curl')
            fake.write_text(FAKE_CURL)
            fake.chmod(0o755)
            log = Path(directory, 'requests.jsonl')
            env = dict(os.environ, PATH=directory + os.pathsep + os.environ['PATH'],
                       RELEASE_CREATED='true', COOLIFY_API_URL='https://coolify.zdossantos.fr/api/v1',
                       COOLIFY_APPLICATION_UUID='plummo-test', COOLIFY_TOKEN='fake-test-token',
                       APP_IMAGE='ghcr.io/zdossantos/plummo@sha256:' + 'a' * 64,
                       RELEASE_SHA='b' * 40, REQUEST_LOG=str(log))
            env.update(overrides)
            result = subprocess.run(['bash', str(SCRIPT)], env=env, capture_output=True, text=True)
            requests = [json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []
            self.assertNotIn('fake-test-token', result.stdout + result.stderr)
            return result, requests

    def test_no_release_has_no_side_effect(self):
        result, requests = self.invoke(RELEASE_CREATED='false')
        self.assertEqual(result.returncode, 0)
        self.assertEqual(requests, [])

    def test_invalid_references_never_contact_coolify(self):
        for override in [dict(APP_IMAGE='ghcr.io/zdossantos/plummo:latest'),
                         dict(APP_IMAGE='ghcr.io/zdossantos/dlp-friends@sha256:' + 'a' * 64),
                         dict(RELEASE_SHA='main'), dict(COOLIFY_API_URL='https://other.example/api/v1'),
                         dict(COOLIFY_APPLICATION_UUID='../other')]:
            with self.subTest(override=override):
                result, requests = self.invoke(**override)
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(requests, [])

    def test_failure_stops_subsequent_mutations_without_retry(self):
        for step in [1, 2, 3]:
            with self.subTest(step=step):
                result, requests = self.invoke(FAIL_REQUEST=str(step))
                self.assertNotEqual(result.returncode, 0)
                self.assertEqual(len(requests), step)

    def test_pins_image_and_compose_before_deploying_only_this_app(self):
        result, requests = self.invoke()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([r['method'] for r in requests], ['PATCH', 'PATCH', 'POST'])
        self.assertTrue(requests[0]['url'].endswith('/applications/plummo-test/envs/bulk'))
        image = requests[0]['data']['data'][0]
        self.assertEqual(image['key'], 'APP_IMAGE')
        self.assertTrue(image['is_buildtime'] and image['is_runtime'])
        self.assertEqual(requests[1]['data'], {'git_commit_sha': 'b' * 40})
        self.assertEqual(requests[2]['data'], {'uuid': 'plummo-test', 'force': False})

    def test_rejects_confirmation_for_a_different_application(self):
        result, requests = self.invoke(WRONG_RESOURCE='yes')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(len(requests), 3)

if __name__ == '__main__':
    unittest.main()
