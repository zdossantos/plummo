<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PageDeck from '@/components/PageDeck.vue';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { contentTypes } from '@/types/admin';
import type { ContentType } from '@/types/admin';
type ImportRow = {
    line: number;
    values: Record<string, string>;
    errors: string[];
    warnings: string[];
    audio: { name: string } | null;
};
type Batch = {
    id: number;
    type: ContentType;
    preview: { rows: ImportRow[]; unused: string[] };
    result: { added: number; published: number; refused: number } | null;
};
const props = defineProps<{ batch: Batch | null }>();
const { t } = useTranslations('admin');
const showUpload = ref(!props.batch);
const form = useForm({
    type: props.batch?.type ?? 'quiz',
    table: null as File | null,
    audios: [] as File[],
});
const confirmation = useForm({ published: false });
const valid = computed(
    () =>
        props.batch?.preview.rows.filter((row) => !row.errors.length).length ??
        0,
);
function tableChanged(event: Event) {
    form.table = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function audiosChanged(event: Event) {
    form.audios = Array.from((event.target as HTMLInputElement).files ?? []);
}
function confirm(published: boolean) {
    confirmation.published = published;
    confirmation.post(`/admin/imports/${props.batch?.id}/confirm`);
}
</script>
<template>
    <AdminLayout :title="t('imports')">
        <Button
            v-if="batch"
            variant="outline"
            @click="showUpload = !showUpload"
            >{{ t(showUpload ? 'import_preview' : 'imports') }}</Button
        >
        <form
            v-if="showUpload"
            class="flex min-h-0 flex-1 flex-col gap-3"
            @submit.prevent="form.post('/admin/imports')"
        >
            <PageDeck>
                <div class="flex flex-col gap-3">
                    <p>{{ t('import_help') }}</p>
                    <label class="grid gap-2"
                        >{{ t('type')
                        }}<select id="import-type" v-model="form.type">
                            <option
                                v-for="type in contentTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ t(type) }}
                            </option>
                        </select></label
                    ><a
                        :href="`/admin/imports/template/${form.type}`"
                        class="underline"
                        >{{ t('import_template') }}</a
                    >
                </div>
                <div>
                    <p class="text-summary">{{ t('import_conventions') }}</p>
                    <TextReader :text="t('import_conventions')" />
                </div>
                <label class="grid gap-2"
                    >{{ t('import_table')
                    }}<input
                        id="import-table"
                        type="file"
                        accept=".csv,.xlsx"
                        required
                        @change="tableChanged"
                /></label>
                <label v-if="form.type === 'blind_test'" class="grid gap-2"
                    >{{ t('import_audios')
                    }}<input
                        type="file"
                        accept=".mp3,.wav,.ogg,.m4a"
                        multiple
                        @change="audiosChanged" /><span
                        class="text-summary text-sm"
                        >{{ t('audio_help') }}</span
                    ><TextReader :text="t('audio_help')"
                /></label>
            </PageDeck>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-summary"
            >
                {{ Object.values(form.errors).join(' · ')
                }}<TextReader :text="Object.values(form.errors).join(' · ')" />
            </p>
            <Button type="submit" :disabled="form.processing">{{
                t('import_preview')
            }}</Button>
        </form>
        <section v-else-if="batch" class="flex min-h-0 flex-1 flex-col gap-3">
            <PageDeck>
                <div class="flex flex-col gap-3">
                    <h2>{{ t('import_preview') }}</h2>
                    <div v-if="batch.result" role="status">
                        {{ t('import_result', batch.result) }}
                        <Link href="/admin/contents">{{ t('contents') }}</Link>
                    </div>
                    <template v-else
                        ><p>{{ t('import_valid', { count: valid }) }}</p>
                        <p
                            v-if="Object.keys(confirmation.errors).length"
                            role="alert"
                        >
                            {{ Object.values(confirmation.errors).join(' · ') }}
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <Button
                                variant="outline"
                                :disabled="!valid || confirmation.processing"
                                @click="confirm(false)"
                                >{{ t('import_drafts') }}</Button
                            ><Button
                                :disabled="!valid || confirmation.processing"
                                @click="confirm(true)"
                                >{{ t('import_publish') }}</Button
                            >
                        </div></template
                    ><a
                        :href="`/admin/imports/${batch.id}/errors`"
                        class="underline"
                        >{{ t('import_export') }}</a
                    >
                    <div v-if="batch.preview.unused.length">
                        <p class="text-summary">
                            {{
                                t('import_unused', {
                                    names: batch.preview.unused.join(', '),
                                })
                            }}
                        </p>
                        <TextReader
                            :text="
                                t('import_unused', {
                                    names: batch.preview.unused.join(', '),
                                })
                            "
                        />
                    </div>
                </div>
                <PagedList :items="batch.preview.rows" :row-height="240"
                    ><template #default="{ item: row }"
                        ><article
                            class="flex flex-col gap-2 rounded-xl border p-3"
                        >
                            <h3>{{ t('import_line', { line: row.line }) }}</h3>
                            <p class="text-summary">
                                {{ Object.values(row.values).join(' · ') }}
                            </p>
                            <TextReader
                                :text="
                                    Object.entries(row.values)
                                        .map(
                                            ([key, value]) =>
                                                key + ': ' + value,
                                        )
                                        .join(' · ')
                                "
                            /><audio
                                v-if="row.audio && !batch.result"
                                controls
                                preload="none"
                                :src="`/admin/imports/${batch.id}/audio/${row.line}`"
                                class="h-8 w-full"
                            />
                            <p
                                v-if="row.errors.length || row.warnings.length"
                                class="text-summary text-sm"
                            >
                                {{
                                    [...row.errors, ...row.warnings].join(' · ')
                                }}
                            </p>
                            <TextReader
                                v-if="row.errors.length || row.warnings.length"
                                :text="
                                    [...row.errors, ...row.warnings].join(' · ')
                                "
                            /></article></template
                ></PagedList>
            </PageDeck>
        </section>
    </AdminLayout>
</template>
