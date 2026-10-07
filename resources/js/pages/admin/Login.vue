<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import RoomHeader from '@/components/RoomHeader.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const { t } = useTranslations('admin');
const form = useForm({ email: '', password: '' });
function submit() {
    form.post('/admin/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('login')" />
    <RoomHeader />
    <main class="mx-auto max-w-md px-6 py-16">
        <h1 class="text-3xl font-bold">{{ t('login') }}</h1>
        <p class="mt-3 text-muted-foreground">{{ t('login_intro') }}</p>
        <form class="mt-8 grid gap-5" @submit.prevent="submit">
            <label for="admin-email" class="grid gap-2">
                {{ t('email') }}
                <input
                    id="admin-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="rounded-xl border bg-background p-3"
                    :aria-invalid="!!form.errors.email"
                />
                <span
                    v-if="form.errors.email"
                    role="alert"
                    class="text-destructive"
                    >{{ form.errors.email }}</span
                >
            </label>
            <label for="admin-password" class="grid gap-2">
                {{ t('password') }}
                <input
                    id="admin-password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="rounded-xl border bg-background p-3"
                    :aria-invalid="!!form.errors.password"
                />
                <span
                    v-if="form.errors.password"
                    role="alert"
                    class="text-destructive"
                    >{{ form.errors.password }}</span
                >
            </label>
            <Button type="submit" :disabled="form.processing">{{
                t('sign_in')
            }}</Button>
        </form>
    </main>
</template>
