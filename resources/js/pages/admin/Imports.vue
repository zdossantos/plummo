<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
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
        <p class="mb-6 text-muted-foreground">{{ t('import_help') }}</p>
        <form
            class="grid gap-5 rounded-2xl border p-6"
            @submit.prevent="form.post('/admin/imports')"
        >
            <label class="grid gap-2"
                >{{ t('type')
                }}<select
                    id="import-type"
                    v-model="form.type"
                    class="rounded-xl border bg-background p-3"
                >
                    <option
                        v-for="type in contentTypes"
                        :key="type"
                        :value="type"
                    >
                        {{ t(type) }}
                    </option>
                </select></label
            >
            <a
                :href="`/admin/imports/template/${form.type}`"
                class="underline"
                >{{ t('import_template') }}</a
            >
            <p class="text-sm text-muted-foreground">
                {{ t('import_conventions') }}
            </p>
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
                    @change="audiosChanged"
                /><span class="text-sm text-muted-foreground">{{
                    t('audio_help')
                }}</span></label
            >
            <ul v-if="Object.keys(form.errors).length" class="text-destructive">
                <li v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </li>
            </ul>
            <Button type="submit" :disabled="form.processing">{{
                t('import_preview')
            }}</Button>
        </form>
        <section v-if="batch" class="mt-8 grid gap-5">
            <h2 class="text-2xl font-bold">{{ t('import_preview') }}</h2>
            <p v-if="batch.preview.unused.length" class="rounded-xl border p-4">
                {{
                    t('import_unused', {
                        names: batch.preview.unused.join(', '),
                    })
                }}
            </p>
            <div
                v-if="batch.result"
                role="status"
                class="rounded-xl bg-muted p-5"
            >
                {{ t('import_result', batch.result) }}
                <Link href="/admin/contents" class="underline">{{
                    t('contents')
                }}</Link>
            </div>
            <template v-else>
                <p>{{ t('import_valid', { count: valid }) }}</p>
                <ul
                    v-if="Object.keys(confirmation.errors).length"
                    class="text-destructive"
                >
                    <li v-for="(error, key) in confirmation.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
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
                </div>
            </template>
            <a :href="`/admin/imports/${batch.id}/errors`" class="underline">{{
                t('import_export')
            }}</a>
            <ol class="grid gap-4">
                <li
                    v-for="row in batch.preview.rows"
                    :key="row.line"
                    class="rounded-2xl border p-5"
                >
                    <h3 class="mb-3 font-bold">
                        {{ t('import_line', { line: row.line }) }}
                    </h3>
                    <dl class="grid gap-2 sm:grid-cols-2">
                        <div v-for="(value, key) in row.values" :key="key">
                            <dt class="text-sm text-muted-foreground">
                                {{ key }}
                            </dt>
                            <dd class="whitespace-pre-wrap break-words">
                                {{ value || '—' }}
                            </dd>
                        </div>
                    </dl>
                    <audio
                        v-if="row.audio && !batch.result"
                        class="mt-4 w-full"
                        controls
                        preload="none"
                        :src="`/admin/imports/${batch.id}/audio/${row.line}`"
                    />
                    <ul class="mt-3 text-destructive">
                        <li v-for="error in row.errors" :key="error">
                            {{ error }}
                        </li>
                    </ul>
                    <ul class="mt-3 text-muted-foreground">
                        <li v-for="warning in row.warnings" :key="warning">
                            {{ warning }}
                        </li>
                    </ul>
                </li>
            </ol>
        </section>
    </AdminLayout>
</template>
