import { createApp, h } from 'vue';
import BonusImpact from '../../resources/js/components/BonusImpact.vue';
import { bonusScenes } from '../../resources/js/lib/bonus-scenes';
import { bonusSoundFile } from '../../resources/js/lib/soundscape';
import '../../resources/css/bonus-stage.css';
let app;
window.bonusPresentation = { bonusScenes, bonusSoundFile };
window.mountBonus = (kind, reaction = false) => {
    app?.unmount();
    app = createApp({
        render: () =>
            h(BonusImpact, {
                event: { id: 'ci', kind, actorId: 1, at: 100, reaction },
                room: null,
            }),
    });
    app.mount('#app');
};
