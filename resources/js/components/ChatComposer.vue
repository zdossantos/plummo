<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, Player } from '@/types/rooms';
const props = defineProps<{
    me: Player;
    game: GameState | null;
    serverNow: number;
    busy: boolean;
    connected: boolean;
    send: (values: Record<string, unknown>) => Promise<boolean>;
}>();
const { t } = useTranslations('rooms');
const message = ref('');
const sending = ref(false);
const remaining = computed(() =>
    Math.max(
        0,
        Math.ceil((props.me.chat?.expiresAt ?? 0) - 2 - props.serverNow),
    ),
);
const count = computed(() => Array.from(message.value).length);
function input(event: Event) {
    message.value = Array.from((event.target as HTMLInputElement).value)
        .slice(0, 80)
        .join('');
    (event.target as HTMLInputElement).value = message.value;
}
async function submit() {
    if (
        sending.value ||
        props.busy ||
        !props.connected ||
        remaining.value ||
        !message.value.trim()
    )
        return;
    sending.value = true;
    try {
        if (
            await props.send({
                message: message.value,
                game_id: props.game?.id ?? null,
                round: props.game?.round.number ?? null,
            })
        )
            message.value = '';
    } finally {
        sending.value = false;
    }
}
</script>
<template>
    <form class="my-6 rounded-2xl bg-card p-5" @submit.prevent="submit">
        <label for="chat-message" class="block font-bold">{{
            t('chat_label')
        }}</label>
        <p id="chat-hint" class="mt-1 text-sm text-muted-foreground">
            {{ t('chat_hint') }}
        </p>
        <input
            id="chat-message"
            :value="message"
            type="text"
            autocomplete="off"
            aria-describedby="chat-hint chat-count"
            :disabled="sending || !connected"
            class="mt-3 w-full rounded-xl border border-primary/20 bg-background px-4 py-3"
            @input="input"
        />
        <div class="mt-3 flex items-center justify-between gap-3">
            <span
                id="chat-count"
                class="text-sm tabular-nums text-muted-foreground"
                >{{ count }}/80</span
            >
            <Button
                type="submit"
                :disabled="
                    busy ||
                    sending ||
                    !connected ||
                    remaining > 0 ||
                    !message.trim()
                "
                >{{
                    remaining
                        ? t('chat_wait', { count: remaining })
                        : t('chat_send')
                }}</Button
            >
        </div>
    </form>
</template>
