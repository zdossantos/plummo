<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import BonusLaunch from '@/components/BonusLaunch.vue';
import BonusPocket from '@/components/BonusPocket.vue';
import BonusReception from '@/components/BonusReception.vue';
import GamePlummoAvatar from '@/components/GamePlummoAvatar.vue';
import PlummoPicker from '@/components/PlummoPicker.vue';
import ChatComposer from '@/components/ChatComposer.vue';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
import { X } from '@lucide/vue';
import GamePlay from '@/components/GamePlay.vue';
import GameSetup from '@/components/GameSetup.vue';
import GameReadiness from '@/components/GameReadiness.vue';
import GameRules from '@/components/GameRules.vue';
import SessionSettings from '@/components/SessionSettings.vue';
import RoomRanking from '@/components/RoomRanking.vue';
import ViewportShell from '@/components/ViewportShell.vue';
import GameControls from '@/components/GameControls.vue';
import RoomNotice from '@/components/RoomNotice.vue';
import PhonePointsFeedback from '@/components/PhonePointsFeedback.vue';
import { useRoom } from '@/composables/useRoom';
import { useTranslations } from '@/composables/useTranslations';
import type { Catalog, Player, GameSetupState } from '@/types/rooms';
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
const chatOpen = ref(false);
const chatDraft = ref('');
async function sendChat(values: Record<string, unknown>) {
    const sent = await request('chat', 'POST', values);
    if (sent && chatDraft.value === values.message) chatDraft.value = '';
    return sent;
}
const profileStage = ref(0);
const ui = useTranslations('interface');
const chief = computed(
    () =>
        me.value?.status === 'connected' && room.value?.chiefId === me.value.id,
);
const pendingSetup = ref<GameSetupState | null>(null);
const setupFailed = ref(false);
const savingSetup = ref(false);
let setupTimer: ReturnType<typeof setTimeout> | undefined;
const setupPending = computed(() => !!pendingSetup.value || savingSetup.value);
const allReady = computed(() => {
    const players =
        room.value?.players.filter((player) => player.status === 'connected') ??
        [];
    return (
        !!room.value?.gameSetup &&
        !setupPending.value &&
        players.length > 0 &&
        players.every(
            (player) => player.id === room.value?.chiefId || player.ready,
        )
    );
});
function changeSetup(settings: GameSetupState) {
    pendingSetup.value = JSON.parse(JSON.stringify(settings)) as GameSetupState;
    setupFailed.value = false;
    clearTimeout(setupTimer);
    setupTimer = setTimeout(() => void saveSetup(), 250);
}
async function saveSetup() {
    if (
        busy.value ||
        savingSetup.value ||
        !chief.value ||
        game.value ||
        !pendingSetup.value
    )
        return;
    savingSetup.value = true;
    while (pendingSetup.value && chief.value && !game.value) {
        const settings: GameSetupState = pendingSetup.value;
        const saved = await request(
            'game-setup',
            'POST',
            settings,
            false,
            true,
        );
        if (!saved) {
            setupFailed.value = true;
            break;
        }
        if (pendingSetup.value === settings) pendingSetup.value = null;
    }
    savingSetup.value = false;
}
watch(busy, (value) => {
    if (!value && !savingSetup.value && !setupFailed.value) void saveSetup();
});
watch([chief, () => game.value?.id], () => {
    if (!chief.value || game.value) {
        pendingSetup.value = null;
        setupFailed.value = false;
    }
});
onUnmounted(() => clearTimeout(setupTimer));
function confirmReady(ready: boolean) {
    if (!setupPending.value)
        void request('ready', 'POST', {
            ready,
            revision: room.value?.setupRevision,
        });
}
const profile = computed(() => ({
    name: name.value,
    color: color.value,
    accessories: accessories.value,
}));
function replaceBonus(itemId: string, replaceId: string | null) {
    if (!game.value) return;
    void request('bonuses/replace', 'POST', {
        game_id: game.value.id,
        round: game.value.round.number,
        item_id: itemId,
        replace_id: replaceId,
    });
}
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
watch(chief, (value) => {
    if (!value && view.value === 'settings') view.value = 'play';
});
</script>
<template>
    <Head :title="t('join_title')" />
    <ViewportShell
        :class="{
            'controller-active': !!me && !!game && !editing && view === 'play',
            'chat-open': chatOpen && !!me && !closed && !editing,
        }"
    >
        <RoomNotice :message="error" />
        <PhonePointsFeedback
            v-if="me && !closed && !editing"
            :game="game"
            :connected="connected"
        />
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
                <Button
                    as="a"
                    variant="secondary"
                    size="lg"
                    href="/host"
                    data-testid="host-room"
                    >{{ t('host_room') }}</Button
                >
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
                    <GameRules
                        v-if="game"
                        :type="game.type"
                        :trials="
                            game.type === 'mini_games'
                                ? game.settings.trials
                                : undefined
                        "
                    />
                    <GameControls
                        v-if="me.status !== 'left'"
                        v-model="view"
                        :session="chief && !game"
                        :chat="canChat"
                        @chat="chatOpen = true"
                    />
                </div>
                <div
                    v-if="
                        game &&
                        !editing &&
                        view === 'play' &&
                        game.phase !== 'results'
                    "
                    class="controller-inventory"
                >
                    <BonusPocket
                        v-if="game.bonuses"
                        :bonuses="game.bonuses"
                        :busy="busy"
                        :connected="connected"
                        @replace="replaceBonus"
                        @launch="
                            request('bonuses', 'POST', {
                                game_id: game.id,
                                round: game.round.number,
                                item_id: $event,
                            })
                        "
                    />
                    <BonusReception
                        :game="game"
                        :server-now="serverNow"
                        :connected="connected"
                        :recipient-id="me.id"
                        phone
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
                            :audio-ready="room?.audioReady"
                            :busy="busy || setupPending"
                            :can-start="allReady"
                            :initial-setup="room?.gameSetup"
                            :recover="game?.exhausted"
                            :initial-packs="game?.settings.packs"
                            :initial-type="game?.type"
                            :player-count="
                                room?.players.filter(
                                    (player) => player.status === 'connected',
                                ).length
                            "
                            @change="changeSetup"
                            @start="
                                request(
                                    game?.exhausted ? 'game-recovery' : 'games',
                                    'POST',
                                    $event,
                                )
                            "
                        >
                            <template #readiness>
                                <GameReadiness
                                    v-if="room"
                                    :players="room.players"
                                    :chief-id="room.chiefId"
                                    :me="me"
                                    :revision="room.setupRevision"
                                    :disabled="
                                        busy || setupPending || !room.gameSetup
                                    "
                                    @ready="confirmReady"
                                />
                                <Button
                                    v-if="setupFailed"
                                    type="button"
                                    variant="outline"
                                    @click="
                                        setupFailed = false;
                                        saveSetup();
                                    "
                                    >{{ t('retry') }}</Button
                                >
                            </template>
                        </GameSetup>
                        <GamePlay
                            v-if="game && room && !game.exhausted"
                            :game="game"
                            :room="room"
                            :seconds="seconds"
                            :server-now="serverNow"
                            :chief="chief"
                            :busy="busy"
                            phone
                            :connected="connected"
                            :send-bluff="
                                (action, values) =>
                                    request('bluff/' + action, 'POST', values)
                            "
                            :send-tribunal="
                                (action, values) =>
                                    request(
                                        'tribunal/' + action,
                                        'POST',
                                        values,
                                    )
                            "
                            :send-mini="
                                (_action, values) =>
                                    request('mini-write', 'POST', values)
                            "
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
                            <template
                                v-if="
                                    !game &&
                                    room &&
                                    !chief &&
                                    me.status === 'connected'
                                "
                            >
                                <GameRules
                                    v-if="room.gameSetup"
                                    :type="room.gameSetup.type"
                                    :trials="room.gameSetup.trials"
                                />
                                <GameReadiness
                                    :players="room.players"
                                    :chief-id="room.chiefId"
                                    :me="me"
                                    :revision="room.setupRevision"
                                    :disabled="busy || !room.gameSetup"
                                    @ready="confirmReady"
                                />
                            </template>
                            <GamePlummoAvatar
                                :connected="connected"
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
        <Drawer
            v-if="me && !closed && !editing && me.status !== 'left'"
            v-model:open="chatOpen"
            :modal="false"
        >
            <DrawerContent
                v-if="chatOpen"
                inline
                class="game-drawer chat-drawer"
                :initial-focus="false"
                @interact-outside.prevent
            >
                <div class="chat-drawer-heading">
                    <DrawerTitle>{{ t('chat_label') }}</DrawerTitle>
                    <DrawerClose as-child
                        ><Button
                            size="icon-sm"
                            variant="outline"
                            :aria-label="ui.t('close')"
                            ><X /></Button
                    ></DrawerClose>
                </div>
                <DrawerDescription class="sr-only">{{
                    t('chat_drawer_hint')
                }}</DrawerDescription>
                <ChatComposer
                    v-model="chatDraft"
                    :me="me"
                    :game="game"
                    :server-now="serverNow"
                    :busy="busy"
                    :connected="connected"
                    :can-chat="canChat"
                    :send="sendChat"
                />
            </DrawerContent>
        </Drawer>
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
            <BonusLaunch
                :game="game"
                :room="room"
                :server-now="serverNow"
                :connected="connected"
                :closed="closed"
            />
            <div class="phone-bonus-footer">
                <GamePlummoAvatar
                    :connected="connected"
                    :game="game"
                    :player="me"
                    class="phone-game-plummo"
                />
            </div>
        </template>
    </ViewportShell>
</template>
