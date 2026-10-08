<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { Tag } from '@/types/admin';
import { ref } from 'vue';
defineProps<{ tags: Tag[] }>();
const { t } = useTranslations('admin');
const editing = ref<number | null>(null);
const creating = ref(false);
const form = useForm({ name: '' });
function toggleCreate() {
    const next = !creating.value;
    editing.value = null;
    form.reset();
    form.clearErrors();
    creating.value = next;
}
function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editing.value = null;
            creating.value = false;
        },
    };
    if (editing.value) form.patch(`/admin/tags/${editing.value}`, options);
    else form.post('/admin/tags', options);
}
function edit(tag: Tag) {
    editing.value = tag.id;
    creating.value = true;
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
        <Button type="button" variant="outline" @click="toggleCreate">{{
            t(creating ? 'cancel' : 'add_tag')
        }}</Button>
        <form
            v-if="creating"
            class="flex min-h-0 flex-1 flex-col gap-3"
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
                @click="toggleCreate()"
                >{{ t('cancel') }}</Button
            >
        </form>
        <p class="text-summary text-sm text-muted-foreground">
            {{ t('tag_help') }}
        </p>
        <PagedList v-if="!creating" :items="tags" :row-height="95"
            ><template #default="{ item: tag }">
                <li class="flex items-center gap-2 rounded-xl border p-3">
                    <div class="mr-auto min-w-0">
                        <strong class="text-summary">{{ tag.name }}</strong
                        ><TextReader :text="tag.name" />
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
            </template></PagedList
        >
    </AdminLayout>
</template>
