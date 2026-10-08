<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageDeck from '@/components/PageDeck.vue';
import ViewportShell from '@/components/ViewportShell.vue';
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
    <ViewportShell>
        <main class="game-panel mx-auto w-full max-w-md">
            <h1 class="text-3xl font-bold">{{ t('login') }}</h1>
            <p class="mt-3 text-muted-foreground">{{ t('login_intro') }}</p>
            <form
                class="flex flex-1 min-h-0 flex-col gap-3"
                @submit.prevent="submit"
            >
                <PageDeck
                    ><label for="admin-email" class="grid gap-2">
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
                    </label> </PageDeck
                ><Button type="submit" :disabled="form.processing">{{
                    t('sign_in')
                }}</Button>
            </form>
        </main></ViewportShell
    >
</template>
