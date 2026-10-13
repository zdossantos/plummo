import { createApp, ref, h } from 'vue';
import GameSetup from '../../resources/js/components/GameSetup.vue';
import GameReadiness from '../../resources/js/components/GameReadiness.vue';
import GameRules from '../../resources/js/components/GameRules.vue';
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
                                      h(GameRules, {
                                          type: current.value.type,
                                      }),
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
                                        ranking: [
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
                                    sendBluff: async (action, values) => {
                                        if (window.bluffSlow)
                                            await new Promise((resolve) =>
                                                setTimeout(resolve, 700),
                                            );
                                        window.lastBluffAction = {
                                            action,
                                            values,
                                        };
                                        return true;
                                    },
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

window.mountPreparation = (type, chief = true) => {
    const setup = {
        type,
        packs: [1],
        trials: ['memory', 'hide', 'count', 'cups', 'author'],
        rounds: ['drawing', 'phrase', 'tribunal'].includes(type) ? 1 : 5,
        duration: 30,
    };
    const players = Array.from({ length: 8 }, (_, i) => ({
        id: i + 1,
        name: 'Alexandre Plummo ' + i,
        status: 'connected',
        ready: i < 6,
    }));
    const app = createApp({
        setup() {
            return () =>
                h(
                    ViewportShell,
                    {},
                    {
                        default: () =>
                            h('div', { class: 'phone-scene' }, [
                                h('div', { class: 'phone-status' }, [
                                    h('span', {}, 'Camille · 1020 Points'),
                                    h(GameControls, {
                                        modelValue: 'play',
                                        session: chief,
                                        chat: true,
                                    }),
                                ]),
                                chief
                                    ? h(
                                          GameSetup,
                                          {
                                              code: 'ABCDEF',
                                              busy: false,
                                              canStart: false,
                                              initialSetup: setup,
                                              playerCount: 8,
                                              audioReady: true,
                                          },
                                          {
                                              readiness: () =>
                                                  h(GameReadiness, {
                                                      players,
                                                      me: players[0],
                                                      disabled: false,
                                                  }),
                                          },
                                      )
                                    : h(
                                          'div',
                                          {
                                              class: 'game-panel justify-center',
                                          },
                                          [
                                              h(
                                                  'h1',
                                                  {},
                                                  window.translations.rooms
                                                      .waiting_chief,
                                              ),
                                              h(GameRules, {
                                                  type,
                                                  trials: setup.trials,
                                              }),
                                              h(GameReadiness, {
                                                  players,
                                                  me: players[7],
                                                  disabled: false,
                                              }),
                                          ],
                                      ),
                            ]),
                    },
                );
        },
    });
    app.mount('#app');
    window.unmountLayout = () => app.unmount();
};
