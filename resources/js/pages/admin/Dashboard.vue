<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
const { t } = useTranslations('admin');
</script>
<template>
    <AdminLayout :title="t('title')">
        <p class="text-lg">{{ t('dashboard_intro') }}</p>
        <div class="grid flex-1 grid-cols-2 gap-3">
            <Link
                v-for="section in ['contents', 'tags', 'packs', 'imports']"
                :key="section"
                :href="`/admin/${section}`"
                class="flex items-center justify-center rounded-2xl border bg-accent p-3 text-lg font-bold"
                >{{ t(section) }}</Link
            >
        </div>
        <Link href="/" class="text-sm underline">{{ t('play') }}</Link>
    </AdminLayout>
</template>
