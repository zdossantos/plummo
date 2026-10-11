<script setup lang="ts">
import type { BonusKind } from '@/types/rooms';
defineProps<{ kind: BonusKind }>();
</script>
<template>
    <img
        :src="`/bonuses/${kind}.svg`"
        class="bonus-icon"
        alt=""
        aria-hidden="true"
        draggable="false"
    />
</template>
