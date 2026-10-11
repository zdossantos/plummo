<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { cupPositions, sceneElapsed } from '@/lib/mini-games';
import type { MiniGameState } from '@/types/rooms';
const props = defineProps<{ game: MiniGameState; now: number }>();
const clock = ref(props.now);
let frame = 0;
let offset = props.now - Date.now() / 1000;
function animate() {
    clock.value = Date.now() / 1000 + offset;
    if (props.game.phase === 'observing')
        frame = requestAnimationFrame(animate);
}
watch(
    () => props.now,
    (value) => {
        offset = value - Date.now() / 1000;
    },
);
watch(
    () => props.game.phase,
    () => {
        cancelAnimationFrame(frame);
        clock.value = props.now;
        if (props.game.phase === 'observing')
            frame = requestAnimationFrame(animate);
    },
    { immediate: true },
);
onUnmounted(() => cancelAnimationFrame(frame));
const elapsed = computed(() =>
    sceneElapsed(
        props.game.phase,
        props.game.round.observeDuration,
        props.game.round.elapsed,
        props.game.deadline,
        clock.value,
    ),
);
const cups = computed(() =>
    cupPositions(props.game.round.scene?.swaps ?? [], elapsed.value),
);
const revealed = computed(() => props.game.round.scenePhase === 'reveal');
const lifted = computed(() => revealed.value || elapsed.value < 1.8);
const actor = computed(() =>
    props.game.round.scene?.events?.find(
        (event) => elapsed.value >= event.at && elapsed.value < event.at + 1.1,
    ),
);
const actorProgress = computed(() =>
    actor.value ? (elapsed.value - actor.value.at) / 1.1 : 0,
);
const actorX = computed(() =>
    actor.value?.direction === 1
        ? 5 + actorProgress.value * 73
        : 78 - actorProgress.value * 73,
);
</script>
<template>
    <div
        v-if="game.round.scene"
        class="mini-stage"
        :data-mini-scene="game.round.trial"
    >
        <div
            v-if="game.round.scene.people"
            class="mini-cast"
            :class="{ 'mini-crowd': game.round.trial === 'hide' }"
        >
            <div
                v-for="person in game.round.scene.people"
                :key="person.number"
                class="mini-cast-member"
                :class="{
                    'mini-found':
                        revealed &&
                        game.round.trial === 'hide' &&
                        person.number === (game.round.correct ?? -1) + 1,
                }"
            >
                <PlummoAvatar
                    :color="person.color"
                    :accessories="person.accessories"
                    :label="String(person.number)"
                />
                <strong class="mini-number">{{ person.number }}</strong>
            </div>
        </div>
        <div v-else-if="game.round.trial === 'cups'" class="mini-cup-stage">
            <div class="mini-cup-floor" aria-hidden="true"></div>
            <div
                v-for="(cup, id) in cups"
                :key="id"
                class="mini-cup"
                :style="{
                    left: 18 + cup.x * 32 + '%',
                    top: 45 + cup.y * 14 + '%',
                    zIndex: cup.y > 0 ? 3 : 1,
                }"
            >
                <PlummoAvatar
                    v-if="id === game.round.scene.under && lifted"
                    :color="game.round.scene.color"
                    class="mini-cup-plummo"
                />
                <svg
                    viewBox="0 0 180 200"
                    aria-hidden="true"
                    :style="{
                        transform:
                            id === game.round.scene.under && lifted
                                ? 'translateY(-65%)'
                                : 'none',
                    }"
                >
                    <path
                        d="M40 26H140L157 161Q157 179 90 179Q23 179 23 161Z"
                        fill="var(--purple)"
                        stroke="var(--edge)"
                        stroke-width="7"
                    />
                    <path
                        d="M45 40L37 153M61 42L55 159"
                        fill="none"
                        stroke="var(--paper)"
                        stroke-width="6"
                        stroke-linecap="round"
                        opacity=".5"
                    />
                    <ellipse
                        cx="90"
                        cy="25"
                        rx="50"
                        ry="14"
                        fill="var(--mint)"
                        stroke="var(--edge)"
                        stroke-width="7"
                    />
                    <path
                        d="M28 164Q90 189 151 164"
                        fill="none"
                        stroke="var(--edge)"
                        stroke-width="7"
                    />
                </svg>
            </div>
            <span
                v-for="n in 3"
                :key="n"
                class="mini-cup-slot mini-number"
                :style="{ left: 18 + (n - 1) * 32 + '%' }"
                >{{ n }}</span
            >
        </div>
        <div v-else-if="game.round.trial === 'count'" class="mini-house-stage">
            <div class="mini-house-path" aria-hidden="true"></div>
            <PlummoAvatar
                v-if="actor && !revealed"
                :color="actor.color"
                class="mini-house-actor"
                :style="{
                    left: actorX + '%',
                    opacity:
                        actorProgress > 0.9
                            ? (1 - actorProgress) * 10
                            : Math.min(1, actorProgress * 10),
                }"
            />
            <svg class="mini-house" viewBox="0 0 340 330" aria-hidden="true">
                <path
                    d="M55 128H284V304H55Z"
                    fill="var(--mint)"
                    stroke="var(--edge)"
                    stroke-width="9"
                    stroke-linejoin="round"
                />
                <path
                    d="M22 135L168 20L317 135Z"
                    fill="var(--pink)"
                    stroke="var(--edge)"
                    stroke-width="9"
                    stroke-linejoin="round"
                />
                <path
                    d="M65 113L168 37L265 113"
                    fill="none"
                    stroke="var(--paper)"
                    stroke-width="7"
                    stroke-linecap="round"
                    opacity=".6"
                />
                <path
                    d="M170 304V200Q170 163 207 163Q244 163 244 200V304"
                    fill="var(--background)"
                    stroke="var(--edge)"
                    stroke-width="8"
                />
                <rect
                    x="80"
                    y="168"
                    width="58"
                    height="58"
                    rx="9"
                    fill="var(--primary)"
                    stroke="var(--edge)"
                    stroke-width="7"
                />
                <path
                    d="M109 171V223M83 197H135"
                    stroke="var(--edge)"
                    stroke-width="6"
                />
                <path
                    d="M39 308H300"
                    stroke="var(--edge)"
                    stroke-width="12"
                    stroke-linecap="round"
                />
            </svg>
            <div v-if="revealed" class="mini-house-residents">
                <PlummoAvatar
                    v-for="n in game.round.correct ?? 0"
                    :key="n"
                    :color="game.round.scene.residents?.[n - 1]"
                />
            </div>
        </div>
    </div>
</template>
