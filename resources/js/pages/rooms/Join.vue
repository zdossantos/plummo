<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import PlummoPicker from '@/components/PlummoPicker.vue';
import GamePlay from '@/components/GamePlay.vue';
import GameSetup from '@/components/GameSetup.vue';
import SessionSettings from '@/components/SessionSettings.vue';
import RoomRanking from '@/components/RoomRanking.vue';
import RoomHeader from '@/components/RoomHeader.vue';
import { useRoom } from '@/composables/useRoom';
import { useTranslations } from '@/composables/useTranslations';
import type { Catalog, Player } from '@/types/rooms';
const props = defineProps<{
    code: string | null;
    me: Player | null;
    catalog: Catalog;
}>();
const { t } = useTranslations('rooms');
const manual = useForm({ code: '' });
const { room, me, game, seconds, error, closed, busy, connected, request } =
    useRoom(props.code, undefined, true);
const name = ref(props.me?.name ?? '');
const color = ref(props.me?.color ?? 'violet');
const accessories = ref<string[]>(props.me?.accessories ?? []);
const editing = ref(false);
const returning = ref(!!props.me);
const target = ref('');
const chief = computed(
    () =>
        me.value?.status === 'connected' && room.value?.chiefId === me.value.id,
);
const profile = computed(() => ({
    name: name.value,
    color: color.value,
    accessories: accessories.value,
}));
async function submit() {
    if (
        await request(
            editing.value ? 'me' : 'players',
            editing.value ? 'PATCH' : 'POST',
            profile.value,
        )
    )
        editing.value = false;
}
function edit() {
    if (!me.value) return;
    name.value = me.value.name;
    color.value = me.value.color;
    accessories.value = [...me.value.accessories];
    editing.value = true;
}
async function leave() {
    if (window.confirm(t('confirm_leave'))) await request('leave');
}
async function close() {
    if (window.confirm(t('confirm_close')))
        await request('', 'DELETE', { confirm: true });
}
onMounted(async () => {
    if (props.me) {
        await request('return');
        returning.value = false;
    }
});
</script>
<template>
    <Head :title="t('join_title')" />
    <main class="mx-auto min-h-screen max-w-4xl px-5 py-6 md:px-10 md:py-8">
        <RoomHeader />
        <section v-if="closed" class="py-20 text-center">
            <h1 class="text-3xl font-black">{{ t('closed') }}</h1>
            <Link
                href="/join"
                class="mt-8 inline-block font-bold text-primary"
                >{{ t('back') }}</Link
            >
        </section>
        <template v-else>
            <p
                v-if="error"
                role="alert"
                class="mt-5 rounded-2xl bg-accent p-4 font-semibold"
            >
                {{ error }}
            </p>
            <form
                v-if="!code"
                class="mx-auto max-w-md py-16"
                @submit.prevent="
                    manual
                        .transform((data) => ({
                            code: data.code.trim().toUpperCase(),
                        }))
                        .post('/join', { preserveState: false })
                "
            >
                <h1 class="mb-4 text-4xl font-black tracking-tight">
                    {{ t('join_title') }}
                </h1>
                <p class="mb-8 text-muted-foreground">{{ t('phone_hint') }}</p>
                <label for="room-code" class="mb-2 block font-bold">{{
                    t('code')
                }}</label>
                <input
                    id="room-code"
                    v-model="manual.code"
                    autocomplete="off"
                    autocapitalize="characters"
                    maxlength="6"
                    required
                    class="w-full rounded-2xl border-2 border-primary/25 bg-card px-4 py-4 font-mono text-3xl tracking-widest uppercase focus:border-primary focus:outline-none"
                    :aria-invalid="!!manual.errors.code"
                />
                <p
                    v-if="manual.errors.code"
                    role="alert"
                    class="mt-3 text-sm font-semibold text-destructive"
                >
                    {{ manual.errors.code }}
                </p>
                <Button
                    type="submit"
                    class="mt-6 w-full"
                    size="lg"
                    :disabled="manual.processing"
                    >{{ t('join') }}</Button
                >
            </form>
            <p v-else-if="returning" role="status" class="py-16 text-center">
                {{ t('loading') }}
            </p>
            <form
                v-else-if="!me || editing"
                class="py-8"
                @submit.prevent="submit"
            >
                <div class="mb-7">
                    <p class="mb-2 text-sm font-bold text-primary">
                        {{ t('code') }} · {{ code }}
                    </p>
                    <h1 class="text-3xl font-black tracking-tight">
                        {{ t('customize') }}
                    </h1>
                    <p class="mt-2 text-muted-foreground">
                        {{ t('customize_intro') }}
                    </p>
                </div>
                <label for="player-name" class="mb-2 block font-bold">{{
                    t('name')
                }}</label>
                <input
                    id="player-name"
                    v-model="name"
                    maxlength="30"
                    required
                    autocomplete="nickname"
                    class="w-full rounded-xl border-2 border-primary/20 bg-card px-4 py-3 focus:border-primary focus:outline-none"
                />
                <p class="mt-2 mb-7 text-sm text-muted-foreground">
                    {{ t('name_hint') }}
                </p>
                <PlummoPicker
                    v-model:color="color"
                    v-model:accessories="accessories"
                    :catalog="catalog"
                />
                <div
                    class="sticky bottom-0 mt-6 flex gap-3 bg-background/95 py-4"
                >
                    <Button
                        v-if="editing"
                        type="button"
                        variant="ghost"
                        :disabled="busy"
                        @click="editing = false"
                        >{{ t('cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        class="flex-1"
                        size="lg"
                        :disabled="busy || !name.trim()"
                        >{{
                            busy
                                ? t('joining')
                                : editing
                                  ? t('save')
                                  : t('enter')
                        }}</Button
                    >
                </div>
                <Link
                    v-if="!editing"
                    href="/join"
                    class="block pb-4 text-center text-sm font-semibold text-primary"
                    >{{ t('back') }}</Link
                >
            </form>
            <section v-else class="py-8">
                <p class="text-center text-sm font-bold text-primary">
                    {{ t('code') }} · {{ code }}
                </p>
                <PlummoAvatar
                    :color="me.color"
                    :accessories="me.accessories"
                    :label="me.name"
                    class="mx-auto mt-5 w-52"
                />
                <h1 class="mt-2 text-center text-3xl font-black break-words">
                    {{ me.name }}
                </h1>
                <p class="mt-2 text-center text-muted-foreground">
                    {{ me.score }} {{ t('score') }}
                </p>
                <div
                    class="mt-6 rounded-2xl bg-card p-5 text-center"
                    role="status"
                >
                    <template v-if="me.status === 'waiting'">{{
                        t('waiting')
                    }}</template>
                    <template v-else-if="me.status === 'left'">{{
                        t('left')
                    }}</template>
                    <template v-else>{{
                        chief ? t('chief_hint') : t('waiting_chief')
                    }}</template>
                </div>
                <Button
                    v-if="me.status === 'left'"
                    class="mt-5 w-full"
                    :disabled="busy"
                    @click="request('return')"
                    >{{ t('return') }}</Button
                >
                <template v-else>
                    <SessionSettings
                        v-if="chief && room && !game"
                        :key="room.chiefId ?? 0"
                        :room="room"
                        :busy="busy"
                        @save="request('session', 'PATCH', $event)"
                    />
                    <GameSetup
                        v-if="
                            chief &&
                            code &&
                            (!game || game.exhausted) &&
                            (game?.exhausted ||
                                room?.pointTarget === null ||
                                (room?.ranking[0]?.score ?? 0) <
                                    (room?.pointTarget ?? 0))
                        "
                        :key="game?.id ?? 0"
                        :code="code"
                        :busy="busy"
                        :recover="game?.exhausted"
                        :initial-packs="game?.settings.packs"
                        :initial-type="game?.type"
                        @start="
                            request(
                                game?.exhausted ? 'game-recovery' : 'games',
                                'POST',
                                $event,
                            )
                        "
                    />
                    <GamePlay
                        v-if="game && room"
                        :game="game"
                        :room="room"
                        :seconds="seconds"
                        :chief="chief"
                        :busy="busy"
                        phone
                        :connected="connected"
                        :send-phrase="
                            (action, values) =>
                                request('phrases/' + action, 'POST', values)
                        "
                        :send-drawing="
                            (action, values) =>
                                request('drawing/' + action, 'POST', values)
                        "
                        @answer="
                            request('answer', 'POST', {
                                choice: $event,
                                game_id: game.id,
                                round: game.round.number,
                            })
                        "
                        @control="request('game/' + $event)"
                    />
                    <RoomRanking v-if="room" :room="room" />
                    <p class="mt-5 text-center text-sm text-muted-foreground">
                        {{ t('phone_hint') }}
                    </p>
                    <Button
                        variant="outline"
                        class="mt-5 w-full"
                        :disabled="busy"
                        v-if="!game"
                        @click="edit"
                        >{{ t('edit') }}</Button
                    >
                    <div v-if="chief" class="mt-6 rounded-2xl bg-accent/40 p-5">
                        <label for="new-chief" class="mb-3 block font-bold">{{
                            t('transfer_to')
                        }}</label>
                        <select
                            id="new-chief"
                            v-model="target"
                            class="w-full rounded-xl bg-card px-3 py-3"
                        >
                            <option value="">{{ t('transfer_to') }}</option>
                            <option
                                v-for="player in room?.players.filter(
                                    (player) =>
                                        player.id !== me?.id &&
                                        player.status === 'connected',
                                )"
                                :key="player.id"
                                :value="player.id"
                            >
                                {{ player.name }}
                            </option>
                        </select>
                        <Button
                            class="mt-3 w-full"
                            :disabled="busy || !target"
                            @click="
                                request('chief', 'POST', {
                                    playerId: Number(target),
                                })
                            "
                            >{{ t('transfer') }}</Button
                        >
                        <Button
                            variant="ghost"
                            class="mt-4 w-full text-destructive"
                            :disabled="busy"
                            v-if="
                                !game ||
                                game.phase === 'paused' ||
                                game.phase === 'results'
                            "
                            @click="close"
                            >{{ t('close') }}</Button
                        >
                    </div>
                    <Button
                        variant="ghost"
                        class="mt-6 w-full"
                        :disabled="busy"
                        @click="leave"
                        >{{ t('leave') }}</Button
                    >
                </template>
            </section>
        </template>
    </main>
</template>
