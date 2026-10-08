<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
const page = defineModel<number>({ required: true });
defineProps<{ total: number }>();
const { t } = useTranslations('interface');
</script>
<template>
    <nav
        v-if="total > 1"
        class="page-controls"
        :aria-label="t('page', { current: page + 1, total })"
    >
        <button
            type="button"
            :disabled="page === 0"
            :aria-label="t('previous')"
            @click="page--"
        >
            ←
        </button>
        <span aria-live="polite">{{
            t('page', { current: page + 1, total })
        }}</span>
        <button
            type="button"
            :disabled="page >= total - 1"
            :aria-label="t('next')"
            @click="page++"
        >
            →
        </button>
    </nav>
</template>
