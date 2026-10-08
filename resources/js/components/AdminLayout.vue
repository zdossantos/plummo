<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ViewportShell from '@/components/ViewportShell.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
defineProps<{ title: string }>();
const { t } = useTranslations('admin');
</script>
<template>
    <Head :title="title" />
    <ViewportShell>
        <template #header
            ><Button
                variant="outline"
                size="sm"
                @click="router.post('/admin/logout')"
                >{{ t('logout') }}</Button
            ></template
        >
        <nav class="admin-nav" :aria-label="t('title')">
            <Link href="/admin">{{ t('title') }}</Link
            ><Link href="/admin/contents">{{ t('contents') }}</Link
            ><Link href="/admin/tags">{{ t('tags') }}</Link
            ><Link href="/admin/packs">{{ t('packs') }}</Link
            ><Link href="/admin/imports">{{ t('imports') }}</Link>
        </nav>
        <div class="game-panel mt-3">
            <h1 class="admin-title">{{ title }}</h1>
            <div class="admin-content"><slot /></div>
        </div>
    </ViewportShell>
</template>
