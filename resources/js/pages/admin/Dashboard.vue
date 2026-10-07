<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { useTranslations } from '@/composables/useTranslations';
const { t } = useTranslations('admin');
</script>
<template>
    <AdminLayout :title="t('title')">
        <p class="text-lg">{{ t('dashboard_intro') }}</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-3">
            <Link
                v-for="section in ['contents', 'tags', 'packs']"
                :key="section"
                :href="`/admin/${section}`"
                class="rounded-2xl border p-6 text-xl font-bold hover:bg-muted"
                >{{ t(section) }}</Link
            >
        </div>
        <Link href="/" class="mt-8 inline-block underline">{{
            t('play')
        }}</Link>
    </AdminLayout>
</template>
