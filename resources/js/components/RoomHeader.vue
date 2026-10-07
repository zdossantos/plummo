<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';
import type { Appearance } from '@/types';
const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations('rooms');
const themes: Appearance[] = ['light', 'dark', 'system'];
</script>
<template>
    <header class="flex flex-wrap items-center justify-between gap-4">
        <span class="text-3xl font-black tracking-tight text-primary"
            >plummo</span
        >
        <div role="group" :aria-label="t('theme')" class="flex gap-1">
            <Button
                v-for="theme in themes"
                :key="theme"
                size="sm"
                :variant="appearance === theme ? 'default' : 'ghost'"
                :aria-pressed="appearance === theme"
                @click="updateAppearance(theme)"
                >{{ t(theme) }}</Button
            >
        </div>
    </header>
</template>
