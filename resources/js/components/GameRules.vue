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
const text = computed(() =>
    [
        t('rules_' + props.type),
        ...(props.type === 'mini_games'
            ? (
                  props.trials ?? ['memory', 'hide', 'count', 'cups', 'author']
              ).map((trial) => t('rules_trial_' + trial))
            : []),
        t('rules_clock'),
    ].join('\n\n'),
);
</script>
<template>
    <TextReader
        :text="text"
        :label="t(names[type])"
        :trigger-label="t('rules')"
        force
        data-game-rules
    />
</template>
