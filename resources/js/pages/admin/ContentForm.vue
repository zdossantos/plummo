<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/components/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { contentTypes } from '@/types/admin';
import type { Content, ContentType, Tag } from '@/types/admin';
const props = defineProps<{ content: Content | null; tags: Tag[] }>();
const { t } = useTranslations('admin');
const form = useForm({
    type: props.content?.type ?? ('quiz' as ContentType),
    published: props.content?.published ?? false,
    payload: {
        question: '',
        choices: ['', '', '', ''],
        correct: 0,
        title: '',
        artist: '',
        word: '',
        prompt: '',
        ...props.content?.payload,
    },
    tag_ids: props.content?.tags.map((tag) => tag.id) ?? [],
    audio: null as File | null,
});
const errors = computed(() => Object.values(form.errors));
function submit(published: boolean) {
    form.published = published;
    const url = props.content
        ? `/admin/contents/${props.content.id}`
        : '/admin/contents';
    if (props.content && !form.audio) {
        form.transform((data) => data).patch(url);
    } else {
        form.transform((data) => ({
            ...data,
            ...(props.content ? { _method: 'patch' } : {}),
        })).post(url);
    }
}
function upload(event: Event) {
    form.audio = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>
<template>
    <AdminLayout :title="t(content ? 'edit_content' : 'new_content')">
        <Link href="/admin/contents" class="mb-6 inline-block underline">{{
            t('back_contents')
        }}</Link>
        <form
            class="grid max-w-3xl gap-5"
            @submit.prevent="submit(form.published)"
        >
            <ul
                v-if="errors.length"
                role="alert"
                class="rounded-xl border border-destructive p-4 text-destructive"
            >
                <li v-for="error in errors" :key="error">{{ error }}</li>
            </ul>
            <label class="grid gap-2"
                >{{ t('type')
                }}<select
                    id="content-type"
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
            <template v-if="form.type === 'quiz'">
                <label class="grid gap-2"
                    >{{ t('question')
                    }}<textarea
                        v-model="form.payload.question"
                        maxlength="500"
                        class="rounded-xl border bg-background p-3"
                    />
                </label>
                <label
                    v-for="(_, index) in form.payload.choices"
                    :key="index"
                    class="grid gap-2"
                    >{{ t('choice', { number: index + 1 })
                    }}<input
                        v-model="form.payload.choices[index]"
                        maxlength="200"
                        class="rounded-xl border bg-background p-3"
                /></label>
                <label class="grid gap-2"
                    >{{ t('correct')
                    }}<select
                        v-model="form.payload.correct"
                        class="rounded-xl border bg-background p-3"
                    >
                        <option
                            v-for="(_, index) in form.payload.choices"
                            :key="index"
                            :value="index"
                        >
                            {{ t('choice', { number: index + 1 }) }}
                        </option>
                    </select></label
                >
            </template>
            <template v-else-if="form.type === 'blind_test'">
                <label class="grid gap-2"
                    >{{ t('song_title')
                    }}<input
                        v-model="form.payload.title"
                        maxlength="200"
                        class="rounded-xl border bg-background p-3"
                /></label>
                <label class="grid gap-2"
                    >{{ t('artist')
                    }}<input
                        v-model="form.payload.artist"
                        maxlength="200"
                        class="rounded-xl border bg-background p-3"
                /></label>
                <label class="grid gap-2"
                    >{{ t('audio')
                    }}<input
                        type="file"
                        accept=".mp3,.wav,.ogg,.m4a"
                        @change="upload"
                /></label>
                <p class="text-sm text-muted-foreground">
                    {{ t('audio_help') }}
                </p>
                <audio
                    v-if="content?.payload.audio_path"
                    :src="`/admin/contents/${content.id}/audio`"
                    controls
                    class="w-full"
                />
            </template>
            <label
                v-else-if="form.type === 'drawing'"
                for="content-word"
                class="grid gap-2"
                >{{ t('word')
                }}<input
                    id="content-word"
                    v-model="form.payload.word"
                    maxlength="100"
                    class="rounded-xl border bg-background p-3"
                /><span class="text-sm text-muted-foreground">{{
                    t('word_help')
                }}</span></label
            >
            <label v-else class="grid gap-2"
                >{{ t('prompt')
                }}<textarea
                    v-model="form.payload.prompt"
                    maxlength="240"
                    class="rounded-xl border bg-background p-3"
                />
            </label>
            <fieldset class="rounded-xl border p-4">
                <legend class="px-2 font-bold">{{ t('tags') }}</legend>
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
            <p class="text-sm text-muted-foreground">{{ t('publish_help') }}</p>
            <div class="flex flex-wrap gap-4">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="submit(false)"
                    >{{ t('save_draft') }}</Button
                ><Button
                    type="button"
                    :disabled="form.processing"
                    @click="submit(true)"
                    >{{ t('publish') }}</Button
                >
            </div>
        </form>
    </AdminLayout>
</template>
