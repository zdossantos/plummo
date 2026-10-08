import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { RoomRequests } from '@/lib/room-requests';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, Player, RoomState, Snapshot } from '@/types/rooms';

export function useRoom(
    code: string | null,
    initial?: Snapshot,
    phone = false,
) {
    const room = ref<RoomState | null>(initial?.room ?? null);
    const game = ref<GameState | null>(initial?.game ?? null);
    const clock = ref(Date.now() / 1000);
    let offset = (initial?.serverTime ?? clock.value) - clock.value;
    const seconds = computed(() =>
        Math.max(
            0,
            Math.ceil((game.value?.deadline ?? 0) - clock.value - offset),
        ),
    );
    const serverNow = computed(() => clock.value + offset);
    const canChat = ref(initial?.canChat ?? false);
    const page = usePage();
    let echo: Echo<'reverb'> | undefined;
    let clockTimer: ReturnType<typeof setInterval> | undefined;
    const me = ref<Player | null>(initial?.me ?? null);
    const error = ref('');
    const closed = ref(false);
    const connected = ref(true);
    const offline = () => {
        connected.value = false;
    };
    const online = () => {
        void request(
            phone ? 'presence' : 'screen-presence',
            'POST',
            undefined,
            true,
        );
    };
    const busy = ref(false);
    const errors = ref<Record<string, string[]>>({});
    const { t } = useTranslations('rooms');
    let timer: ReturnType<typeof setTimeout> | undefined;
    const requests = new RoomRequests();
    let stopped = false;

    async function request(
        action: string,
        method = 'POST',
        body?: unknown,
        background = false,
    ): Promise<boolean> {
        if (!code || busy.value || closed.value || stopped) return false;
        const ticket = requests.begin(background);
        if (!ticket) return false;
        busy.value = requests.busy;
        if (!background) errors.value = {};
        const timeout = setTimeout(() => ticket.controller.abort(), 8000);
        try {
            const response = await fetch(
                `/rooms/${code}${action ? '/' + action : ''}`,
                {
                    method,
                    signal: ticket.controller.signal,
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
            if (stopped || !requests.current(ticket)) return false;
            connected.value = response.status < 500;
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
                if (!requests.current(ticket)) return false;
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
            if (stopped || !requests.current(ticket)) return false;
            canChat.value = data.canChat ?? false;
            room.value = data.room;
            me.value = data.me;
            game.value = data.game ?? null;
            if (data.serverTime) offset = data.serverTime - Date.now() / 1000;
            return true;
        } catch {
            if (!stopped && requests.current(ticket)) {
                connected.value = false;
                error.value = t('network');
            }
            return false;
        } finally {
            clearTimeout(timeout);
            requests.finish(ticket);
            busy.value = requests.busy;
        }
    }
    async function poll() {
        if (stopped || closed.value) return;
        if (
            !busy.value &&
            (!phone || (me.value && me.value.status !== 'left'))
        ) {
            await request(
                phone ? 'presence' : 'screen-presence',
                'POST',
                undefined,
                true,
            );
        }
        timer = setTimeout(
            () => void poll(),
            !phone &&
                game.value?.type === 'drawing' &&
                game.value.phase === 'drawing'
                ? 300
                : 2000,
        );
    }
    onMounted(() => {
        connected.value = navigator.onLine;
        window.addEventListener('offline', offline);
        window.addEventListener('online', online);
        clockTimer = setInterval(() => (clock.value = Date.now() / 1000), 100);
        const realtime = page.props.realtime as {
            key: string;
            host: string;
            port: number;
            scheme: string;
        } | null;
        if (code && realtime?.key) {
            echo = new Echo({
                broadcaster: 'reverb',
                client: new Pusher(realtime.key, {
                    wsHost: realtime.host,
                    wsPort: realtime.port,
                    wssPort: realtime.port,
                    forceTLS: realtime.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    cluster: '',
                    disableStats: true,
                    channelAuthorization: {
                        endpoint: `/rooms/${code}/broadcast-auth`,
                        transport: 'ajax',
                        headers: {
                            'X-CSRF-TOKEN':
                                document.querySelector<HTMLMetaElement>(
                                    'meta[name="csrf-token"]',
                                )?.content ?? '',
                        },
                    },
                }),
            });
            const subscribe = () => {
                if (phone && me.value?.status !== 'connected') return;
                echo?.private(`room.${code}`).listen('.room.changed', () => {
                    if (
                        !busy.value &&
                        (!phone || me.value?.status === 'connected')
                    )
                        void request(
                            phone ? 'presence' : 'screen-presence',
                            'POST',
                            undefined,
                            true,
                        );
                });
            };
            subscribe();
            watch(
                () => me.value?.status,
                (status, previous) => {
                    if (!phone || status === previous) return;
                    echo?.leave(`room.${code}`);
                    subscribe();
                },
            );
        }
        void poll();
    });
    onUnmounted(() => {
        stopped = true;
        window.removeEventListener('offline', offline);
        window.removeEventListener('online', online);
        clearTimeout(timer);
        clearInterval(clockTimer);
        echo?.disconnect();
        requests.abort();
    });
    return {
        room,
        me,
        game,
        seconds,
        serverNow,
        canChat,
        error,
        errors,
        closed,
        busy,
        connected,
        request,
    };
}
