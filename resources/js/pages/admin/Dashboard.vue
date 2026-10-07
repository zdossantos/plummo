<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import RoomHeader from '@/components/RoomHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const { t } = useTranslations('admin');
</script>

<template>
    <Head :title="t('title')" />
    <RoomHeader />
    <main class="mx-auto max-w-5xl px-6 py-16">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-bold">{{ t('title') }}</h1>
            <Button variant="outline" @click="router.post('/admin/logout')">{{
                t('logout')
            }}</Button>
        </div>
        <p class="mt-8 text-lg">{{ t('dashboard_intro') }}</p>
        <p class="mt-3 text-muted-foreground">{{ t('dashboard_pending') }}</p>
        <Link href="/" class="mt-8 inline-block underline">{{
            t('play')
        }}</Link>
    </main>
</template>
