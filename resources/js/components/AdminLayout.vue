<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import RoomHeader from '@/components/RoomHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
defineProps<{ title: string }>();
const { t } = useTranslations('admin');
</script>
<template>
    <Head :title="title" />
    <RoomHeader />
    <main class="mx-auto max-w-6xl px-6 py-10">
        <nav
            class="mb-8 flex flex-wrap items-center gap-5 border-b pb-5"
            :aria-label="t('title')"
        >
            <Link href="/admin" class="font-bold">{{ t('title') }}</Link>
            <Link href="/admin/contents" class="underline">{{
                t('contents')
            }}</Link>
            <Link href="/admin/tags" class="underline">{{ t('tags') }}</Link>
            <Link href="/admin/packs" class="underline">{{ t('packs') }}</Link>
            <Link href="/admin/imports" class="underline">{{
                t('imports')
            }}</Link>
            <Button
                variant="outline"
                class="ml-auto"
                @click="router.post('/admin/logout')"
                >{{ t('logout') }}</Button
            >
        </nav>
        <h1 class="mb-6 text-3xl font-bold">{{ title }}</h1>
        <slot />
    </main>
</template>
