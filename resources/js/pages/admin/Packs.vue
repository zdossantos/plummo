<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { contentTypes } from '@/types/admin';
import type { Tag, Pack } from '@/types/admin';
defineProps<{ packs: Pack[]; tags: Tag[] }>();
const { t } = useTranslations('admin');
const editing = ref<number | null>(null);
const form = useForm({ name: '', tag_ids: [] as number[] });
function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editing.value = null;
        },
    };
    if (editing.value) form.patch(`/admin/packs/${editing.value}`, options);
    else form.post('/admin/packs', options);
}
function edit(pack: Pack) {
    editing.value = pack.id;
    form.name = pack.name;
    form.tag_ids = pack.tags.map((tag) => tag.id);
    form.clearErrors();
}
function remove(pack: Pack) {
    if (window.confirm(t('confirm_delete')))
        router.delete(`/admin/packs/${pack.id}`);
}
</script>
<template>
    <AdminLayout :title="t('packs')">
        <p class="mb-6 text-lg">{{ t('pack_help') }}</p>
        <form class="mb-10 grid max-w-3xl gap-4" @submit.prevent="submit">
            <label for="pack-name" class="grid gap-2"
                >{{ t('name')
                }}<input
                    id="pack-name"
                    v-model="form.name"
                    required
                    maxlength="100"
                    class="rounded-xl border bg-background p-3"
            /></label>
            <fieldset class="rounded-xl border p-4">
                <legend class="px-2">{{ t('required_tags') }}</legend>
                <p v-if="!tags.length">{{ t('no_tags') }}</p>
                <div class="flex flex-wrap gap-4">
                    <label
                        v-for="tag in tags"
                        :key="tag.id"
                        class="flex items-center gap-2"
                        ><input
                            v-model="form.tag_ids"
                            type="checkbox"
                            :value="tag.id"
                        />{{ tag.name }}</label
                    >
                </div>
            </fieldset>
            <ul
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-destructive"
            >
                <li v-for="error in form.errors" :key="error">{{ error }}</li>
            </ul>
            <div class="flex gap-4">
                <Button :disabled="form.processing">{{
                    t(editing ? 'save' : 'add_pack')
                }}</Button
                ><Button
                    v-if="editing"
                    type="button"
                    variant="outline"
                    @click="
                        editing = null;
                        form.reset();
                    "
                    >{{ t('cancel') }}</Button
                >
            </div>
        </form>
        <ul class="grid gap-4">
            <li
                v-for="pack in packs"
                :key="pack.id"
                class="rounded-2xl border p-5"
            >
                <div class="flex flex-wrap items-center gap-4">
                    <strong class="mr-auto text-xl">{{ pack.name }}</strong
                    ><Button variant="outline" @click="edit(pack)">{{
                        t('edit')
                    }}</Button
                    ><Button variant="outline" @click="remove(pack)">{{
                        t('delete')
                    }}</Button>
                </div>
                <p class="mt-2">
                    {{ pack.tags.map((tag) => tag.name).join(' + ') }}
                </p>
                <dl class="mt-4 flex flex-wrap gap-5">
                    <div v-for="type in contentTypes" :key="type">
                        <dt class="text-sm text-muted-foreground">
                            {{ t(type) }}
                        </dt>
                        <dd class="font-bold">{{ pack.counts[type] }}</dd>
                    </div>
                </dl>
            </li>
        </ul>
    </AdminLayout>
</template>
