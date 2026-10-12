import { createApp, ref, h } from 'vue';
import GamePlay from '../../resources/js/components/GamePlay.vue';
import BonusPocket from '../../resources/js/components/BonusPocket.vue';
import GameControls from '../../resources/js/components/GameControls.vue';
import ViewportShell from '../../resources/js/components/ViewportShell.vue';
import '../../resources/css/app.css';
window.mountLayout = (game, phone = true) => {
    const app = createApp({
        setup() {
            const current = ref(game);
            window.updateGame = (g) => (current.value = g);
            const now = ref(100);
            window.setServerNow = (value) => (now.value = value);
            return () =>
                h(
                    ViewportShell,
                    {
                        class: phone ? 'controller-active' : 'display-screen',
                        displayOnly: !phone,
                    },
                    {
                        default: () => [
                            phone
                                ? h('div', { class: 'phone-status' }, [
                                      h(
                                          'span',
                                          { class: 'text-summary' },
                                          'Camille · 1020 Points',
                                      ),
                                      h(
                                          'b',
                                          { class: 'text-primary' },
                                          'MUSY36',
                                      ),
                                      h(GameControls, {
                                          modelValue: 'play',
                                          session: false,
                                          chat: true,
                                      }),
                                  ])
                                : null,
                            phone
                                ? h('div', { class: 'controller-inventory' }, [
                                      h(BonusPocket, {
                                          bonuses: current.value.bonuses,
                                          connected: true,
                                      }),
                                  ])
                                : null,
                            h('div', { class: 'phone-scene' }, [
                                h(GamePlay, {
                                    game: current.value,
                                    room: {
                                        ranking: [],
                                        players: [
                                            {
                                                id: 1,
                                                name: 'Camille',
                                                color: 'violet',
                                                accessories: [],
                                            },
                                            {
                                                id: 2,
                                                name: 'Alex',
                                                color: 'vert',
                                                accessories: [],
                                            },
                                        ],
                                    },
                                    phone,
                                    chief: phone,
                                    connected: true,
                                    seconds: 45,
                                    serverNow: now.value,
                                    sendDrawing: async () => true,
                                    sendPhrase: async () => true,
                                }),
                            ]),
                        ],
                        footer: () =>
                            phone
                                ? h('div', { class: 'phone-bonus-footer' })
                                : null,
                    },
                );
        },
    });
    app.mount('#app');
    window.unmountLayout = () => app.unmount();
};
