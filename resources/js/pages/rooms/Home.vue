<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import ViewportShell from '@/components/ViewportShell.vue';
import { useTranslations } from '@/composables/useTranslations';
import { initialHomePath } from '@/lib/home-mode';
const { t } = useTranslations('rooms');
onMounted(() => {
    router.visit(initialHomePath(window.innerWidth, window.innerHeight), {
        replace: true,
    });
});
</script>
<template>
    <Head :title="t('title')" />
    <ViewportShell>
        <section class="game-panel justify-center text-center">
            <h1 class="text-4xl">{{ t('title') }}</h1>
            <Button as="a" href="/host" size="lg">{{ t('host_room') }}</Button>
            <Button as="a" href="/join" variant="secondary" size="lg">{{
                t('join_room')
            }}</Button>
        </section>
    </ViewportShell>
</template>
