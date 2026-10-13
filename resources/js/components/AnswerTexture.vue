<script setup lang="ts">
defineProps<{ variant: number }>();
</script>
<template>
    <svg
        class="answer-texture"
        viewBox="0 0 200 140"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <template v-if="variant === 0">
            <path
                class="texture-light"
                d="M118 0h82v58c-24 7-41-3-44-24S133 11 118 0Z"
            />
            <path
                class="texture-shade"
                d="M0 92c25-8 29 8 31 24s18 18 33 24H0Z"
            />
            <g class="texture-line">
                <path
                    d="M140-5c7 22 23 14 30 32s12 25 35 18M-5 110c25-7 19 20 39 27"
                />
                <circle cx="179" cy="18" r="8" />
            </g>
            <g class="texture-dots">
                <circle
                    v-for="dot in 12"
                    :key="dot"
                    :cx="13 + ((dot - 1) % 4) * 8"
                    :cy="12 + Math.floor((dot - 1) / 4) * 8"
                    r="1.8"
                />
            </g>
        </template>
        <template v-else-if="variant === 1">
            <path class="texture-light" d="M0 0h66L0 67Zm200 88v52h-57Z" />
            <path
                class="texture-shade"
                d="m0 49 49-49h16L0 66Zm200 68v17l-55 55-8-9Z"
            />
            <g class="texture-line">
                <path d="m-8 86 86-94M160 140l40-40M174 140l26-26" />
                <path d="m170 14 9 9-9 9-9-9Z" />
            </g>
            <g class="texture-dots">
                <circle
                    v-for="dot in 12"
                    :key="dot"
                    :cx="13 + ((dot - 1) % 4) * 8"
                    :cy="112 + Math.floor((dot - 1) / 4) * 8"
                    r="1.8"
                />
            </g>
        </template>
        <template v-else-if="variant === 2">
            <path
                class="texture-light"
                d="M135 140c-2-18 15-19 17-34s20-11 26-26 16-17 22-16v76Z"
            />
            <path
                class="texture-shade"
                d="M0 0h49c7 17-6 25-19 23S14 43 0 45Z"
            />
            <g class="texture-line">
                <path
                    d="M147 145c-2-23 18-14 18-36s20-4 20-24 16-12 20-12M-5 31c20 5 12-19 32-19s14-9 13-18"
                />
                <circle cx="181" cy="18" r="7" />
                <circle cx="164" cy="31" r="3" />
            </g>
            <g class="texture-dots">
                <circle
                    v-for="dot in 12"
                    :key="dot"
                    :cx="13 + ((dot - 1) % 4) * 8"
                    :cy="112 + Math.floor((dot - 1) / 4) * 8"
                    r="1.8"
                />
            </g>
        </template>
        <template v-else-if="variant === 3">
            <path
                class="texture-light"
                d="M0 89c28-6 54 17 52 51H0ZM151 0h49v47c-26-1-47-22-49-47Z"
            />
            <path
                class="texture-shade"
                d="M0 118c16-3 26 7 25 22H0ZM179 0h21v22c-12-1-21-10-21-22Z"
            />
            <g class="texture-line">
                <path
                    d="M0 105c22-3 37 15 36 35M139-5c1 36 27 60 66 61M155-5c0 27 19 44 50 44"
                />
                <path d="m21 14 4 8 9 1-6 7 1 9-8-4-8 4 1-9-6-7 9-1Z" />
            </g>
            <g class="texture-dots">
                <circle
                    v-for="dot in 12"
                    :key="dot"
                    :cx="162 + ((dot - 1) % 4) * 8"
                    :cy="112 + Math.floor((dot - 1) / 4) * 8"
                    r="1.8"
                />
            </g>
        </template>
        <template v-else-if="variant === 4">
            <g class="texture-line">
                <path d="M-8 30h60M22 0v60M148 110h60M178 80v60" />
                <circle cx="22" cy="30" r="20" />
                <circle cx="178" cy="110" r="20" />
            </g>
            <path class="texture-light" d="M150 0h50v45h-25V22h-25Z" />
        </template>
        <template v-else-if="variant === 5">
            <path
                class="texture-light"
                d="m0 0 50 0-25 44ZM150 140l25-44 25 44Z"
            />
            <g class="texture-line">
                <path
                    d="m-10 65 20-30 20 30 20-30 20 30M140 115l15-25 15 25 15-25 15 25"
                />
            </g>
        </template>
        <template v-else-if="variant === 6">
            <g class="texture-line">
                <path
                    d="M-10 20Q5 0 20 20T50 20T80 20M120 120q15-20 30 0t30 0t30 0M-10 35Q5 15 20 35T50 35"
                />
            </g>
            <circle class="texture-light" cx="182" cy="20" r="22" />
        </template>
        <template v-else-if="variant === 7">
            <g class="texture-line">
                <path
                    d="m15 5 12 14-12 14L3 19ZM178 103l14 16-14 16-14-16ZM158 0l10 12-10 12-10-12Z"
                />
            </g>
            <path class="texture-light" d="m0 95 45 45H0ZM155 0h45v45Z" />
            <circle class="texture-shade" cx="42" cy="12" r="6" />
        </template>
        <template v-else>
            <g class="texture-line">
                <path d="M0 10h40v30H10v30M160 140v-40h40M170 0v20h30" />
                <circle cx="178" cy="60" r="12" />
                <path d="M12 112h36M30 94v36" />
            </g>
            <path
                class="texture-light"
                d="M0 0h55v12H12v43H0ZM155 140v-12h33v-33h12v45Z"
            />
        </template>
    </svg>
</template>
