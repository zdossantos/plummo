<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import PageDeck from '@/components/PageDeck.vue';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { contentTypes } from '@/types/admin';
import type { Tag, Pack } from '@/types/admin';
defineProps<{ packs: Pack[]; tags: Tag[] }>();
const { t } = useTranslations('admin');
const editing = ref<number | null>(null);
const creating = ref(false);
const form = useForm({ name: '', tag_ids: [] as number[] });
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
    if (editing.value) form.patch(`/admin/packs/${editing.value}`, options);
    else form.post('/admin/packs', options);
}
function edit(pack: Pack) {
    editing.value = pack.id;
    creating.value = true;
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
        <Button type="button" variant="outline" @click="toggleCreate">{{
            t(creating ? 'cancel' : 'add_pack')
        }}</Button>
        <p class="text-summary text-sm">{{ t('pack_help') }}</p>
        <form
            v-if="creating"
            class="flex min-h-0 flex-1 flex-col gap-3"
            @submit.prevent="submit"
        >
            <PageDeck
                ><label for="pack-name" class="grid gap-2"
                    >{{ t('name')
                    }}<input
                        id="pack-name"
                        v-model="form.name"
                        required
                        maxlength="100"
                        class="rounded-xl border bg-background p-3"
                /></label>
                <fieldset
                    class="flex min-h-0 flex-1 flex-col gap-3 rounded-xl border p-4"
                >
                    <legend class="px-2">{{ t('required_tags') }}</legend>
                    <p v-if="!tags.length">{{ t('no_tags') }}</p>
                    <PagedList :items="tags" :row-height="72"
                        ><template #default="{ item: tag }">
                            <label class="flex items-center gap-2"
                                ><input
                                    v-model="form.tag_ids"
                                    type="checkbox"
                                    :value="tag.id" /><span
                                    class="text-summary flex-1 min-w-0"
                                    >{{ tag.name }}</span
                                ><TextReader :text="tag.name"
                            /></label> </template
                    ></PagedList>
                </fieldset>
            </PageDeck>
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="flex items-center gap-2 text-destructive"
            >
                <p class="text-summary">
                    {{ Object.values(form.errors).join(' · ') }}
                </p>
                <TextReader :text="Object.values(form.errors).join(' · ')" />
            </div>
            <div class="flex gap-4">
                <Button :disabled="form.processing">{{
                    t(editing ? 'save' : 'add_pack')
                }}</Button
                ><Button
                    v-if="editing"
                    type="button"
                    variant="outline"
                    @click="toggleCreate()"
                    >{{ t('cancel') }}</Button
                >
            </div>
        </form>
        <PagedList v-if="!creating" :items="packs" :row-height="165"
            ><template #default="{ item: pack }">
                <li class="rounded-2xl border p-3">
                    <div class="flex items-center gap-2">
                        <strong
                            class="mr-auto min-w-0 flex-1 truncate text-base"
                            >{{ pack.name }}</strong
                        ><Button variant="outline" @click="edit(pack)">{{
                            t('edit')
                        }}</Button
                        ><Button variant="outline" @click="remove(pack)">{{
                            t('delete')
                        }}</Button>
                    </div>
                    <TextReader
                        :text="
                            pack.name +
                            ' · ' +
                            pack.tags.map((tag) => tag.name).join(' + ')
                        "
                    />
                    <p class="text-summary mt-1 text-sm">
                        {{ pack.tags.map((tag) => tag.name).join(' + ') }}
                    </p>
                    <dl class="mt-2 flex gap-3">
                        <div v-for="type in contentTypes" :key="type">
                            <dt class="text-sm text-muted-foreground">
                                {{ t(type) }}
                            </dt>
                            <dd class="font-bold">{{ pack.counts[type] }}</dd>
                        </div>
                    </dl>
                </li>
            </template></PagedList
        >
    </AdminLayout>
</template>
