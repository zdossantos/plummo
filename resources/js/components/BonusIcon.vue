<script setup lang="ts">
import {
    Zap,
    Dices,
    Ghost,
    EyeOff,
    MessageCircle,
    Wind,
    Stamp,
    PaintBucket,
} from '@lucide/vue';
import type { BonusKind } from '@/types/rooms';
defineProps<{ kind: BonusKind }>();
const icons = {
    bolt: Zap,
    dice: Dices,
    squatter: Ghost,
    artist: EyeOff,
    accent: MessageCircle,
    sneeze: Wind,
    stamp: Stamp,
    paint: PaintBucket,
};
</script>
<template><component :is="icons[kind]" aria-hidden="true" /></template>
