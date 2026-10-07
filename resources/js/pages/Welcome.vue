<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Monitor, Smartphone } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';
import type { Appearance } from '@/types';
const { t } = useTranslations();
const { appearance, updateAppearance } = useAppearance();
const themes: Appearance[] = ['light', 'dark', 'system'];
</script>
<template>
    <Head :title="t('title')" />
    <main
        class="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 md:px-16"
    >
        <header class="flex flex-wrap items-center justify-between gap-6">
            <span class="text-3xl font-black tracking-tight text-primary"
                >Plummo</span
            >
            <div
                class="flex items-center gap-2"
                role="group"
                :aria-label="t('theme')"
            >
                <Button
                    v-for="theme in themes"
                    :key="theme"
                    :variant="appearance === theme ? 'default' : 'ghost'"
                    :aria-pressed="appearance === theme"
                    @click="updateAppearance(theme)"
                    >{{ t(theme) }}</Button
                >
            </div>
        </header>
        <section
            class="grid flex-1 items-center gap-12 py-16 md:grid-cols-[1.2fr_1fr]"
        >
            <div>
                <p class="mb-5 font-semibold text-primary">{{ t('status') }}</p>
                <h1
                    class="max-w-xl text-5xl leading-tight font-black tracking-tight lg:text-7xl"
                >
                    {{ t('title') }}
                </h1>
                <p class="mt-6 max-w-lg text-xl leading-relaxed">
                    {{ t('intro') }}
                </p>
                <p class="mt-6 max-w-lg text-muted-foreground">
                    {{ t('pending') }}
                </p>
                <Button
                    as="a"
                    href="https://www.figma.com/design/6kioGGc1qTiFtyAjvsHSqw/Projet-jeu-tel---pc"
                    class="mt-8"
                    size="lg"
                    >{{ t('design') }}</Button
                >
            </div>
            <div class="grid gap-5">
                <article class="rounded-3xl bg-card p-8 shadow-sm">
                    <Monitor
                        class="mb-6 size-10 text-primary"
                        aria-hidden="true"
                    />
                    <h2 class="text-2xl font-bold">{{ t('screen') }}</h2>
                    <p class="mt-3 leading-relaxed text-muted-foreground">
                        {{ t('screen_detail') }}
                    </p>
                </article>
                <article class="rounded-3xl bg-card p-8 shadow-sm">
                    <Smartphone
                        class="mb-6 size-10 text-primary"
                        aria-hidden="true"
                    />
                    <h2 class="text-2xl font-bold">{{ t('phone') }}</h2>
                    <p class="mt-3 leading-relaxed text-muted-foreground">
                        {{ t('phone_detail') }}
                    </p>
                </article>
            </div>
        </section>
    </main>
</template>
