<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { Tag } from '@/types/admin';
import { ref } from 'vue';
defineProps<{ tags: Tag[] }>();
const { t } = useTranslations('admin');
const editing = ref<number | null>(null);
const form = useForm({ name: '' });
function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editing.value = null;
        },
    };
    if (editing.value) form.patch(`/admin/tags/${editing.value}`, options);
    else form.post('/admin/tags', options);
}
function edit(tag: Tag) {
    editing.value = tag.id;
    form.name = tag.name;
    form.clearErrors();
}
function remove(tag: Tag) {
    if (window.confirm(t('confirm_delete_tag')))
        router.delete(`/admin/tags/${tag.id}`);
}
</script>
<template>
    <AdminLayout :title="t('tags')">
        <form
            class="mb-8 flex flex-wrap items-end gap-4"
            @submit.prevent="submit"
        >
            <label for="tag-name" class="grid gap-2"
                >{{ t('name')
                }}<input
                    id="tag-name"
                    v-model="form.name"
                    required
                    maxlength="100"
                    class="rounded-xl border bg-background p-3"
                /><span
                    v-if="form.errors.name"
                    role="alert"
                    class="text-destructive"
                    >{{ form.errors.name }}</span
                ></label
            ><Button :disabled="form.processing">{{
                t(editing ? 'save' : 'add_tag')
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
        </form>
        <p class="mb-5 text-muted-foreground">{{ t('tag_help') }}</p>
        <ul class="grid gap-3">
            <li
                v-for="tag in tags"
                :key="tag.id"
                class="flex flex-wrap items-center gap-4 rounded-xl border p-4"
            >
                <div class="mr-auto">
                    <strong>{{ tag.name }}</strong>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('tag_counts', {
                                contents: tag.contents_count ?? 0,
                                packs: tag.packs_count ?? 0,
                            })
                        }}
                    </p>
                </div>
                <Button variant="outline" @click="edit(tag)">{{
                    t('edit')
                }}</Button
                ><Button
                    variant="outline"
                    :disabled="!!tag.packs_count"
                    @click="remove(tag)"
                    >{{ t('delete') }}</Button
                >
            </li>
        </ul>
    </AdminLayout>
</template>
