<script setup lang="ts">
import { computed } from 'vue';
import BonusIcon from '@/components/BonusIcon.vue';
import { bonusScenes } from '@/lib/bonus-scenes';
import type { BonusLaunchEvent } from '@/lib/bonus-launch';
import type { RoomState } from '@/types/rooms';
const props = defineProps<{
    event: BonusLaunchEvent;
    room: RoomState | null;
}>();
const kind = computed(() => props.event.kind);
const character = computed(() =>
    ['squatter', 'photobomb', 'singer', 'strike', 'missing_word'].includes(
        kind.value,
    ),
);
const scene = computed(() => bonusScenes[kind.value]);
const bubbles = computed(() => scene.value === 'bubbles');
const clock = computed(() => scene.value === 'clock');
const letters = computed(() => scene.value === 'letters');
const palette = ['#efe681', '#f1b5c8', '#b7e1c3', '#aa83ec', '#f05a78'];
</script>
<template>
    <div
        class="bonus-impact bonus-stage"
        :class="[
            'bonus-stage-' + kind,
            { 'bonus-stage-reaction': event.reaction },
        ]"
        data-testid="bonus-impact"
        :data-kind="kind"
        :data-scene="scene"
        aria-hidden="true"
    >
        <div class="bonus-stage-scene">
            <template v-if="kind === 'bolt'">
                <div class="vfx-storm" />
                <svg
                    class="vfx-lightning"
                    viewBox="0 0 1000 900"
                    preserveAspectRatio="none"
                >
                    <path
                        class="vfx-lightning-edge"
                        d="M530 -30 450 175 535 152 405 345 492 322 430 466 510 438 475 610 620 385 532 403 652 219 564 243 668 -30Z"
                    />
                    <path
                        class="vfx-lightning-core"
                        d="M570 -30 478 160 548 140 432 332 505 308 462 454 516 431 475 610 582 400 515 421 623 239 547 258 634 -30Z"
                    />
                    <path
                        class="vfx-lightning-fork"
                        d="m451 174-112 9 46 86-125-14M605 227l110 68-57 31 145 70"
                    />
                </svg>
            </template>
            <template v-else-if="kind === 'pinata'">
                <div class="vfx-pinata-drop">
                    <div class="vfx-pinata-swing">
                        <span class="vfx-rope" /><BonusIcon kind="pinata" />
                    </div>
                </div>
                <div class="vfx-confetti-field">
                    <i
                        v-for="n in 36"
                        :key="n"
                        class="vfx-confetti"
                        :style="{
                            '--x': ((n * 67) % 820) - 410 + 'px',
                            '--y': ((n * 43) % 380) + 90 + 'px',
                            '--turn': n * 137 + 'deg',
                            '--delay': (n % 5) * 30 + 'ms',
                            background: palette[n % 5],
                        }"
                    />
                </div>
                <span class="vfx-shockwave" />
            </template>
            <template v-else-if="kind === 'dice'">
                <div
                    v-for="n in 3"
                    :key="n"
                    class="vfx-die"
                    :style="{ '--die': n }"
                >
                    <BonusIcon kind="dice" /><span />
                </div>
            </template>
            <template v-else-if="kind === 'paint' || kind === 'stamp'">
                <svg class="vfx-splat" viewBox="0 0 600 500">
                    <path
                        d="M272 111C241 82 267 20 231 22S207 125 177 127 124 78 99 105s49 69 36 99S36 212 30 245s100 18 111 49-38 78-9 97 65-56 91-48-4 101 33 108 37-95 68-104 88 73 111 40-63-73-51-102 121 2 123-37-110-36-112-67 78-56 58-86-69 49-95 37S327 27 299 32s10 95-27 79Z"
                    />
                </svg>
                <div class="vfx-hero-object"><BonusIcon :kind="kind" /></div>
                <div v-if="kind === 'stamp'" class="vfx-stamp-mark">
                    <svg viewBox="0 0 100 100">
                        <circle
                            cx="50"
                            cy="50"
                            r="43"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="5"
                            stroke-dasharray="5 3"
                        />
                        <path
                            d="M29 57q21 29 42 0M33 38v3m34-3v3"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="7"
                            stroke-linecap="round"
                        />
                    </svg>
                </div>
            </template>
            <template v-else-if="kind === 'shield'">
                <div class="vfx-shield-dome" />
                <div class="vfx-hero-object"><BonusIcon kind="shield" /></div>
                <svg
                    v-if="event.reaction"
                    class="vfx-shield-crack"
                    viewBox="0 0 300 300"
                >
                    <path
                        d="m190 10-45 89 32 13-77 73 38 5-60 100M148 97 80 70m42 100 94 37"
                    />
                </svg>
                <span class="vfx-shockwave" />
            </template>
            <template v-else-if="kind === 'boomerang'">
                <svg class="vfx-return-trail" viewBox="0 0 1000 600">
                    <path
                        d="M-50 420C120-130 1020 40 840 340S120 730 430 330"
                    />
                </svg>
                <div class="vfx-hero-object"><BonusIcon :kind="kind" /></div>
            </template>
            <template v-else-if="kind === 'artist'">
                <div class="vfx-hero-object vfx-character vfx-incognito">
                    <img :src="'/plummo/base.svg'" alt="" draggable="false" />
                    <svg class="vfx-disguise" viewBox="0 0 512 512">
                        <path
                            d="M140 229Q255 199 361 238L355 306Q315 328 264 304Q207 322 145 298Z M161 263a34 39 0 1 0 68 0a34 39 0 1 0-68 0 M280 273a34 39 0 1 0 68 0a34 39 0 1 0-68 0"
                            fill="#aa83ec"
                            fill-rule="evenodd"
                            stroke="#21182f"
                            stroke-width="6"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M142 251Q116 263 110 290M358 258Q389 270 398 300"
                            fill="none"
                            stroke="#21182f"
                            stroke-width="6"
                            stroke-linecap="round"
                        />
                        <path
                            d="M251 329Q269 311 290 331Q319 349 335 316Q348 353 320 361Q284 369 255 344Q215 369 189 360Q161 351 174 317Q190 349 220 330Q237 313 251 329Z"
                            fill="#21182f"
                        />
                    </svg>
                </div>
            </template>
            <template v-else-if="kind === 'curtain'">
                <div class="vfx-theatre-curtain vfx-curtain-left" />
                <div class="vfx-theatre-curtain vfx-curtain-right" />
                <div class="vfx-curtain-tie" />
            </template>
            <template v-else>
                <template v-if="bubbles"
                    ><span
                        v-for="n in 12"
                        :key="n"
                        class="vfx-bubble"
                        :style="{ '--bubble': n }"
                /></template>
                <svg v-if="clock" class="vfx-clock-face" viewBox="0 0 400 400">
                    <circle cx="200" cy="200" r="170" />
                    <path
                        v-for="n in 12"
                        :key="n"
                        d="M200 46v20"
                        :transform="'rotate(' + n * 30 + ' 200 200)'"
                    />
                    <g class="vfx-clock-hand">
                        <path d="M200 200V90m0 110 72 42" />
                    </g>
                </svg>
                <template v-if="letters"
                    ><span
                        v-for="(letter, i) in [
                            'A',
                            'Z',
                            '?',
                            'B',
                            '!',
                            'R',
                            'M',
                            'X',
                        ]"
                        :key="i"
                        class="vfx-letter"
                        :style="{ '--letter': i, color: palette[i % 5] }"
                        >{{ letter }}</span
                    ></template
                >
                <div v-if="kind === 'photobomb'" class="vfx-photo-frame">
                    <i v-for="n in 4" :key="n" />
                </div>
                <div
                    class="vfx-hero-object"
                    :class="{ 'vfx-character': character }"
                >
                    <BonusIcon :kind="kind" />
                </div>
                <span
                    v-if="
                        !character &&
                        !bubbles &&
                        !letters &&
                        !clock &&
                        kind !== 'mirror'
                    "
                    class="vfx-shockwave"
                />
            </template>
        </div>
        <div class="bonus-stage-reduced"><BonusIcon :kind="kind" /></div>
    </div>
</template>
