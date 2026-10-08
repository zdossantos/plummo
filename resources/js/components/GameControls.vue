<script setup lang="ts">
import { ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import {
    Gamepad2,
    SlidersHorizontal,
    Trophy,
    MessageCircle,
    DoorOpen,
    ArrowLeft,
} from '@lucide/vue';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const view = defineModel<string>({ required: true });
defineProps<{ session: boolean; chat: boolean }>();
const { t } = useTranslations('interface');
const rooms = useTranslations('rooms');
const open = ref(false);
const desktop = useMediaQuery('(min-width: 768px)');
function choose(next: string) {
    view.value = next;
    open.value = false;
}
</script>
<template>
    <div class="game-hud-controls">
        <Button
            v-if="view !== 'play'"
            size="icon-sm"
            variant="outline"
            :aria-label="t('play')"
            @click="choose('play')"
            ><ArrowLeft
        /></Button>
        <Button
            variant="secondary"
            class="command-trigger"
            :aria-label="t('controls')"
            @click="open = true"
            ><Gamepad2 /><span>{{ t('controls') }}</span></Button
        >
        <Button
            variant="outline"
            class="message-shortcut"
            :aria-label="rooms.t('chat_label')"
            @click="choose('chat')"
            ><MessageCircle /><span>{{ t('chat') }}</span></Button
        >
        <Drawer
            v-model:open="open"
            :swipe-direction="desktop ? 'right' : 'down'"
        >
            <DrawerContent v-if="open" class="game-drawer command-drawer">
                <DrawerTitle>{{ t('controls') }}</DrawerTitle>
                <DrawerDescription class="sr-only">{{
                    t('controls_hint')
                }}</DrawerDescription>
                <div class="command-grid">
                    <Button class="command-play" @click="choose('play')"
                        ><Gamepad2 />{{ t('play') }}</Button
                    >
                    <Button
                        v-if="session"
                        variant="outline"
                        @click="choose('settings')"
                        ><SlidersHorizontal />{{ t('settings') }}</Button
                    >
                    <Button variant="outline" @click="choose('ranking')"
                        ><Trophy />{{ rooms.t('ranking') }}</Button
                    >
                    <Button
                        v-if="chat"
                        variant="outline"
                        @click="choose('chat')"
                        ><MessageCircle />{{ t('chat') }}</Button
                    >
                    <Button variant="outline" @click="choose('more')"
                        ><DoorOpen />{{ t('more') }}</Button
                    >
                </div>
                <DrawerClose as-child
                    ><Button variant="secondary">{{
                        t('close')
                    }}</Button></DrawerClose
                >
            </DrawerContent>
        </Drawer>
    </div>
</template>
