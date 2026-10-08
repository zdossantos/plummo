<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
defineProps<{ message: string }>();
</script>
<template>
    <div v-if="message" class="room-notice" role="alert" aria-atomic="true">
        <TriangleAlert aria-hidden="true" :size="20" />
        <p>{{ message }}</p>
    </div>
</template>
