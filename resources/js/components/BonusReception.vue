<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import BonusIcon from '@/components/BonusIcon.vue';
import { BonusReceiptTracker } from '@/lib/bonuses';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, BonusReceipt } from '@/types/rooms';
const props = defineProps<{
    game: GameState | null;
    serverNow: number;
    connected: boolean;
    recipientId: number;
    phone?: boolean;
}>();
const { t } = useTranslations('rooms');
const tracker = new BonusReceiptTracker();
const receipt = ref<BonusReceipt | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    [() => props.game, () => props.connected],
    () => {
        if (!props.game) {
            receipt.value = null;
            return;
        }
        const fresh = tracker.observe(
            props.game.id,
            (props.game.bonuses?.receipts ?? []).filter(
                (item) => item.recipientId === props.recipientId,
            ),
            props.serverNow,
            props.connected,
        );
        if (!props.connected) receipt.value = null;
        const last = fresh.at(-1);
        if (!last) return;
        receipt.value = last;
        clearTimeout(timer);
        timer = setTimeout(() => {
            receipt.value = null;
        }, 2300);
    },
    { immediate: true },
);
onUnmounted(() => clearTimeout(timer));
</script>
<template>
    <div
        v-if="receipt"
        :key="receipt.id"
        class="bonus-reception"
        :class="{ 'bonus-reception-phone': phone }"
        role="status"
    >
        <BonusIcon :kind="receipt.kind" /><span v-if="phone">{{
            t('bonus_received', { name: t('bonus_' + receipt.kind) })
        }}</span>
    </div>
</template>
