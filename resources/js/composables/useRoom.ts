import { onMounted, onUnmounted, ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Player, RoomState, Snapshot } from '@/types/rooms';

export function useRoom(
    code: string | null,
    initial?: Snapshot,
    phone = false,
) {
    const room = ref<RoomState | null>(initial?.room ?? null);
    const me = ref<Player | null>(initial?.me ?? null);
    const error = ref('');
    const closed = ref(false);
    const busy = ref(false);
    const errors = ref<Record<string, string[]>>({});
    const { t } = useTranslations('rooms');
    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;
    let stopped = false;

    async function request(
        action: string,
        method = 'POST',
        body?: unknown,
        background = false,
    ): Promise<boolean> {
        if (!code || busy.value || closed.value || stopped) return false;
        busy.value = true;
        if (!background) errors.value = {};
        controller = new AbortController();
        const timeout = setTimeout(() => controller?.abort(), 8000);
        try {
            const response = await fetch(
                `/rooms/${code}${action ? '/' + action : ''}`,
                {
                    method,
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? '',
                    },
                    body: body === undefined ? undefined : JSON.stringify(body),
                },
            );
            if (stopped) return false;
            if (response.status === 404) {
                closed.value = true;
                error.value = t('closed');
                return false;
            }
            if (response.status === 403) {
                error.value = t('forbidden');
                return false;
            }
            if (response.status === 422) {
                const data = await response.json();
                errors.value = data.errors;
                error.value =
                    Object.values(errors.value).flat()[0] ?? t('room_error');
                return false;
            }
            if (!response.ok) {
                error.value = t('room_error');
                return false;
            }
            if (!background || !Object.keys(errors.value).length)
                error.value = '';
            if (response.status === 204) {
                closed.value = true;
                return true;
            }
            const data: Snapshot = await response.json();
            room.value = data.room;
            me.value = data.me;
            return true;
        } catch {
            if (!stopped) error.value = t('network');
            return false;
        } finally {
            clearTimeout(timeout);
            busy.value = false;
        }
    }
    async function poll() {
        if (stopped || closed.value) return;
        if (
            !busy.value &&
            (!phone || (me.value && me.value.status !== 'left'))
        ) {
            await request(
                phone ? 'presence' : 'state',
                phone ? 'POST' : 'GET',
                undefined,
                true,
            );
        }
        timer = setTimeout(() => void poll(), 5000);
    }
    onMounted(() => void poll());
    onUnmounted(() => {
        stopped = true;
        clearTimeout(timer);
        controller?.abort();
    });
    return { room, me, error, errors, closed, busy, request };
}
