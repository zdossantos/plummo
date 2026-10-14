<script setup lang="ts">
import { computed } from 'vue';
import TextReader from '@/components/TextReader.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { GameType, MiniTrial } from '@/types/rooms';
const props = defineProps<{ type: GameType; trials?: MiniTrial[] }>();
const { t } = useTranslations('rooms');
const names = {
    bluff: 'bluff_name',
    quiz: 'quiz_name',
    blind_test: 'blind_name',
    drawing: 'drawing_name',
    phrase: 'phrase_name',
    tribunal: 'tribunal_name',
    mini_games: 'mini_name',
};
const sections = computed(() => {
    const [goal, flow, play, points] = t('rules_' + props.type).split('\n');
    const trials =
        props.type === 'mini_games'
            ? (
                  props.trials ?? ['memory', 'hide', 'count', 'cups', 'author']
              ).map((trial) => t('rules_trial_' + trial))
            : [];
    return [
        { label: t('rules_flow'), text: [flow, goal].join('\n\n') },
        {
            label: t('rules_play'),
            text: [play, ...trials, points, t('rules_clock')].join('\n\n'),
        },
        { label: t('rules_bonuses'), text: t('rules_bonus_overview') },
    ];
});
const text = computed(() =>
    sections.value.map((section) => section.text).join('\n\n'),
);
</script>
<template>
    <TextReader
        :text="text"
        :sections="sections"
        :label="t(names[type])"
        :trigger-label="t('rules')"
        force
        section-labels
        data-game-rules
    />
</template>
