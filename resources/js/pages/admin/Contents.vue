<script setup lang="ts">
import { ref } from 'vue';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { contentTypes } from '@/types/admin';
import type { Content } from '@/types/admin';
const props = defineProps<{
    contents: {
        data: Content[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
    filters: { type?: string; search?: string; status?: string };
}>();
const { t } = useTranslations('admin');
const ui = useTranslations('interface');
const showFilters = ref(false);
const filters = useForm({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});
function remove(content: Content) {
    if (window.confirm(t('confirm_delete')))
        router.delete(`/admin/contents/${content.id}`);
}
function title(content: Content) {
    return (
        content.payload.question ||
        content.payload.title ||
        content.payload.word ||
        content.payload.prompt ||
        t('untitled')
    );
}
</script>
<template>
    <AdminLayout :title="t('contents')">
        <div class="flex items-center justify-between gap-2">
            <Button variant="outline" @click="showFilters = !showFilters">{{
                t('filter')
            }}</Button>
            <form
                v-if="showFilters"
                class="contents-filters"
                @submit.prevent="
                    filters.get('/admin/contents', {
                        onSuccess: () => (showFilters = false),
                    })
                "
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="showFilters = false"
                    >{{ ui.t('close') }}</Button
                >
                <label class="grid gap-1"
                    >{{ t('type')
                    }}<select
                        v-model="filters.type"
                        class="rounded-xl border bg-background p-2"
                    >
                        <option value="">{{ t('all_types') }}</option>
                        <option
                            v-for="type in contentTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ t(type) }}
                        </option>
                    </select></label
                >
                <label class="grid gap-1"
                    >{{ t('status')
                    }}<select
                        v-model="filters.status"
                        class="rounded-xl border bg-background p-2"
                    >
                        <option value="">{{ t('all_statuses') }}</option>
                        <option value="draft">{{ t('draft') }}</option>
                        <option value="published">{{ t('published') }}</option>
                    </select></label
                >
                <label class="grid gap-1"
                    >{{ t('search')
                    }}<input
                        v-model="filters.search"
                        type="search"
                        class="rounded-xl border bg-background p-2"
                /></label>
                <Button type="submit" class="self-end">{{
                    t('filter')
                }}</Button>
            </form>
            <Link
                href="/admin/contents/create"
                class="self-end rounded-xl bg-primary px-5 py-2 text-primary-foreground"
                >{{ t('new_content') }}</Link
            >
        </div>
        <p
            v-if="!contents.data.length"
            class="rounded-2xl border p-8 text-muted-foreground"
        >
            {{ t('no_content') }}
        </p>
        <PagedList v-else :items="contents.data" :row-height="110"
            ><template #default="{ item: content }">
                <li
                    class="flex items-center justify-between gap-2 rounded-2xl border p-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-muted-foreground">
                            {{ t(content.type) }} ·
                            {{ t(content.published ? 'published' : 'draft') }}
                        </p>
                        <Link
                            :href="`/admin/contents/${content.id}/edit`"
                            class="block truncate text-lg font-bold underline"
                            >{{ title(content) }}</Link
                        >
                        <TextReader :text="String(title(content))" />
                        <p class="text-summary text-xs text-muted-foreground">
                            {{
                                content.tags.map((tag) => tag.name).join(' · ')
                            }}
                        </p>
                    </div>
                    <Button variant="outline" @click="remove(content)">{{
                        t('delete')
                    }}</Button>
                </li>
            </template></PagedList
        >
        <div class="mt-2 flex items-center justify-between gap-4">
            <Link
                v-if="contents.prev_page_url"
                :href="contents.prev_page_url"
                class="underline"
                >{{ t('previous') }}</Link
            ><span>{{
                t('page', {
                    current: contents.current_page,
                    total: contents.last_page,
                })
            }}</span
            ><Link
                v-if="contents.next_page_url"
                :href="contents.next_page_url"
                class="underline"
                >{{ t('next') }}</Link
            >
        </div>
    </AdminLayout>
</template>
