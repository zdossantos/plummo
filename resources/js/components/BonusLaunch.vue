<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import BonusIcon from '@/components/BonusIcon.vue';
import BonusImpact from '@/components/BonusImpact.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import {
    BonusLaunchTracker,
    selectImpactEvents,
    type BonusLaunchEvent,
} from '@/lib/bonus-launch';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState } from '@/types/rooms';

const props = defineProps<{
    screen?: boolean;
    game: GameState | null;
    room: RoomState | null;
    serverNow: number;
    connected: boolean;
    closed: boolean;
}>();
const { t } = useTranslations('rooms');
const tracker = new BonusLaunchTracker();
const active = ref<BonusLaunchEvent[]>([]);
const timers = new Set<ReturnType<typeof setTimeout>>();
let context: number | undefined;
function clear() {
    for (const timer of timers) clearTimeout(timer);
    timers.clear();
    active.value = [];
}
watch(
    [() => props.game, () => props.connected, () => props.closed],
    () => {
        const restored = context !== props.game?.id;
        if (restored || !props.connected || props.closed) clear();
        context = props.game?.id;
        const fresh = tracker.observe(
            props.game,
            props.serverNow,
            props.connected,
        );
        if (props.closed) return;
        for (const event of fresh) {
            active.value.push(event);
            const timer = setTimeout(
                () => {
                    active.value = active.value.filter(
                        (item) => item.id !== event.id,
                    );
                    timers.delete(timer);
                },
                props.screen ? 2800 : 1800,
            );
            timers.add(timer);
        }
    },
    { immediate: true },
);
onUnmounted(clear);
function actor(id: number) {
    return props.room?.players.find((player) => player.id === id);
}
</script>
<template>
    <div class="bonus-launch-layer" aria-live="polite" aria-atomic="false">
        <BonusImpact
            v-for="event in screen ? selectImpactEvents(active) : []"
            :key="'impact-' + event.id"
            :event="event"
            :room="room"
        />

        <div
            v-for="event in active"
            :key="event.id"
            class="bonus-launch"
            :class="[
                'bonus-launch-' + event.kind,
                { 'bonus-launch-reaction': event.reaction },
            ]"
            data-testid="bonus-launch"
            :data-launch-id="event.id"
        >
            <PlummoAvatar
                v-if="actor(event.actorId)"
                :color="actor(event.actorId)!.color"
                :accessories="actor(event.actorId)!.accessories"
                class="bonus-launch-actor"
                motion="hello"
                :motion-key="event.id"
            />
            <span class="bonus-launch-projectile"
                ><BonusIcon :kind="event.kind"
            /></span>
            <span class="bonus-launch-caption"
                ><small>{{ actor(event.actorId)?.name }}</small
                ><strong>{{
                    event.reaction && event.kind === 'shield'
                        ? t('bonus_shield_broken')
                        : event.reaction && event.kind === 'boomerang'
                          ? t(
                                event.deflected
                                    ? 'bonus_deflected'
                                    : 'bonus_reflected',
                            )
                          : event.kind === 'soap'
                            ? t('bonus_clean')
                            : t('bonus_launched', {
                                  name: t('bonus_' + event.kind),
                              })
                }}</strong></span
            >
        </div>
    </div>
</template>
