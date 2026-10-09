<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import GamePlummoAvatar from '@/components/GamePlummoAvatar.vue';
import PlummoPicker from '@/components/PlummoPicker.vue';
import ChatComposer from '@/components/ChatComposer.vue';
import GamePlay from '@/components/GamePlay.vue';
import GameSetup from '@/components/GameSetup.vue';
import SessionSettings from '@/components/SessionSettings.vue';
import RoomRanking from '@/components/RoomRanking.vue';
import ViewportShell from '@/components/ViewportShell.vue';
import GameControls from '@/components/GameControls.vue';
import RoomNotice from '@/components/RoomNotice.vue';
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
const {
    room,
    me,
    game,
    seconds,
    serverNow,
    canChat,
    error,
    closed,
    busy,
    connected,
    request,
} = useRoom(props.code, undefined, true);
const name = ref(props.me?.name ?? '');
const color = ref(props.me?.color ?? 'violet');
const accessories = ref<string[]>(props.me?.accessories ?? []);
const editing = ref(false);
const returning = ref(!!props.me);
const target = ref('');
const view = ref('play');
const profileStage = ref(0);
const ui = useTranslations('interface');
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
    profileStage.value = 0;
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
watch([() => game.value?.id, () => game.value?.phase], () => {
    if (game.value && !['paused', 'results'].includes(game.value.phase))
        view.value = 'play';
});
watch(canChat, (available) => {
    if (!available && view.value === 'chat') view.value = 'play';
});
watch(chief, (value) => {
    if (!value && view.value === 'settings') view.value = 'play';
});
</script>
<template>
    <Head :title="t('join_title')" />
    <ViewportShell>
        <RoomNotice :message="error" />
        <section v-if="closed" class="game-panel justify-center text-center">
            <h1>{{ t('closed') }}</h1>
            <Link href="/join">{{ t('back') }}</Link>
        </section>
        <div v-else class="phone-scene">
            <form
                v-if="!code"
                class="game-panel justify-center"
                @submit.prevent="
                    manual
                        .transform((data) => ({
                            code: data.code.trim().toUpperCase(),
                        }))
                        .post('/join', { preserveState: false })
                "
            >
                <h1 class="text-4xl">{{ t('join_title') }}</h1>
                <p>{{ t('phone_hint') }}</p>
                <label for="room-code" class="font-bold">{{ t('code') }}</label>
                <input
                    id="room-code"
                    v-model="manual.code"
                    autocomplete="off"
                    autocapitalize="characters"
                    maxlength="6"
                    required
                    class="text-3xl tracking-widest uppercase"
                    :aria-invalid="!!manual.errors.code"
                />
                <p v-if="manual.errors.code" role="alert">
                    {{ manual.errors.code }}
                </p>
                <Button type="submit" size="lg" :disabled="manual.processing">{{
                    t('join')
                }}</Button>
            </form>
            <p v-else-if="returning" role="status">{{ t('loading') }}</p>
            <form
                v-else-if="!me || editing"
                class="game-panel"
                @submit.prevent="
                    profileStage === 0 ? (profileStage = 1) : submit()
                "
            >
                <p class="text-xs font-bold text-primary">
                    {{ t('code') }} · {{ code }}
                </p>
                <h1 class="text-3xl">{{ t('customize') }}</h1>
                <div
                    v-show="profileStage === 0"
                    class="flex flex-1 flex-col justify-center gap-3"
                >
                    <label for="player-name" class="font-bold">{{
                        t('name')
                    }}</label>
                    <input
                        id="player-name"
                        v-model="name"
                        maxlength="30"
                        required
                        autocomplete="nickname"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ t('name_hint') }}
                    </p>
                </div>
                <PlummoPicker
                    v-show="profileStage === 1"
                    v-model:color="color"
                    v-model:accessories="accessories"
                    :catalog="catalog"
                />
                <div class="profile-actions flex gap-2">
                    <Button
                        v-if="profileStage === 1"
                        type="button"
                        variant="outline"
                        @click="profileStage = 0"
                        >{{ t('back') }}</Button
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
                        :disabled="busy || !name.trim()"
                        >{{
                            profileStage === 0
                                ? ui.t('continue')
                                : busy
                                  ? t('joining')
                                  : editing
                                    ? t('save')
                                    : t('enter')
                        }}</Button
                    >
                </div>
                <Link
                    v-if="!editing && profileStage === 0"
                    href="/join"
                    class="text-center text-sm"
                    >{{ t('back') }}</Link
                >
            </form>
            <template v-else>
                <div class="phone-status">
                    <span class="text-summary"
                        >{{ me.name }} · {{ me.score }} {{ t('score') }}</span
                    ><span class="text-primary">{{ code }}</span>
                    <GameControls
                        v-if="me.status !== 'left'"
                        v-model="view"
                        :session="chief && !game"
                        :chat="canChat"
                    />
                </div>
                <p v-if="me.status === 'left'" role="status">{{ t('left') }}</p>
                <Button
                    v-if="me.status === 'left'"
                    :disabled="busy"
                    @click="request('return')"
                    >{{ t('return') }}</Button
                >
                <template v-else>
                    <div v-show="view === 'play'" class="phone-scene">
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
                            v-if="game && room && !game.exhausted"
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
                        <div
                            v-if="!game || me.status === 'waiting'"
                            v-show="
                                !chief ||
                                (room?.pointTarget !== null &&
                                    (room?.ranking[0]?.score ?? 0) >=
                                        (room?.pointTarget ?? 0))
                            "
                            class="game-panel justify-center"
                        >
                            <h1 class="text-3xl">
                                {{
                                    me.status === 'waiting'
                                        ? t('waiting')
                                        : chief
                                          ? t('session_results')
                                          : t('waiting_chief')
                                }}
                            </h1>
                            <p>
                                {{
                                    chief
                                        ? t('target_reached')
                                        : t('phone_hint')
                                }}
                            </p>
                            <GamePlummoAvatar
                                :game="game"
                                :player="me"
                                class="phone-plummo"
                            />
                        </div>
                        <p
                            v-if="chief && !game"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('chief_hint') }}
                        </p>
                    </div>
                    <SessionSettings
                        v-if="view === 'settings' && chief && room && !game"
                        :room="room"
                        :busy="busy"
                        @save="request('session', 'PATCH', $event)"
                    />
                    <RoomRanking
                        v-if="view === 'ranking' && room"
                        :room="room"
                    />
                    <ChatComposer
                        v-if="view === 'chat' && canChat"
                        :me="me"
                        :game="game"
                        :server-now="serverNow"
                        :busy="busy"
                        :connected="connected"
                        :send="(values) => request('chat', 'POST', values)"
                    />
                    <div
                        v-if="view === 'chat' && !canChat"
                        class="game-panel justify-center text-center"
                        role="status"
                    >
                        <h2>{{ t('chat_label') }}</h2>
                        <p>{{ t('chat_unavailable') }}</p>
                        <Button @click="view = 'play'">{{
                            ui.t('play')
                        }}</Button>
                    </div>
                    <div v-if="view === 'more'" class="game-panel">
                        <div class="room-command-board">
                            <div class="room-primary-actions">
                                <h2 class="text-2xl">{{ ui.t('more') }}</h2>
                                <Button
                                    v-if="!game"
                                    variant="outline"
                                    :disabled="busy"
                                    @click="edit"
                                    >{{ t('edit') }}</Button
                                ><Button
                                    variant="outline"
                                    :disabled="busy"
                                    @click="leave"
                                    >{{ t('leave') }}</Button
                                ><Button
                                    v-if="
                                        chief &&
                                        (!game ||
                                            game.phase === 'paused' ||
                                            game.phase === 'results')
                                    "
                                    variant="outline"
                                    :disabled="busy"
                                    @click="close"
                                    >{{ t('close') }}</Button
                                >
                            </div>
                            <div v-if="chief" class="chief-transfer">
                                <label for="new-chief">{{
                                    t('transfer_to')
                                }}</label
                                ><select id="new-chief" v-model="target">
                                    <option value="">
                                        {{ t('transfer_to') }}
                                    </option>
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
                                    </option></select
                                ><Button
                                    :disabled="busy || !target"
                                    @click="
                                        request('chief', 'POST', {
                                            playerId: Number(target),
                                        })
                                    "
                                    >{{ t('transfer') }}</Button
                                >
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </div>
        <template
            v-if="
                me &&
                game &&
                !editing &&
                view === 'play' &&
                game.phase !== 'results'
            "
            #footer
        >
            <GamePlummoAvatar
                :game="game"
                :player="me"
                class="phone-game-plummo"
            />
        </template>
    </ViewportShell>
</template>
