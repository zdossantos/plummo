<script lang="ts">
import {
    defineComponent,
    h,
    ref,
    Fragment,
    cloneVNode,
    type VNode,
    Comment,
    Text,
    nextTick,
    onUnmounted,
} from 'vue';
import { useResizeObserver } from '@vueuse/core';
import PageControls from '@/components/PageControls.vue';
function flatten(nodes: VNode[]): VNode[] {
    return nodes.flatMap((node) =>
        node.type === Fragment
            ? flatten(node.children as VNode[])
            : node.type === Comment ||
                (node.type === Text && !String(node.children).trim())
              ? []
              : [node],
    );
}
export default defineComponent({
    props: { combineAbove: { type: Number, default: 0 } },
    setup(props, { slots }) {
        const page = ref(0);
        const stage = ref<HTMLElement>();
        const deck = ref<HTMLElement>();
        const height = ref(0);
        let resizeFrame = 0;
        if (props.combineAbove > 0) {
            useResizeObserver(deck, ([entry]) => {
                if (!entry) return;
                cancelAnimationFrame(resizeFrame);
                // Apply layout changes after the observer finishes this frame.
                resizeFrame = requestAnimationFrame(() => {
                    height.value = entry.contentRect.height;
                });
            });
        }
        onUnmounted(() => cancelAnimationFrame(resizeFrame));
        let revealing = false;
        function invalid(event: Event) {
            event.preventDefault();
            if (revealing) return;
            revealing = true;
            const field = event.target as HTMLElement;
            const index = Array.from(stage.value?.children ?? []).findIndex(
                (child) => child.contains(field),
            );
            if (index >= 0) page.value = index;
            void nextTick(() => {
                field.focus();
                revealing = false;
            });
        }
        return () => {
            const combined =
                props.combineAbove > 0 && height.value >= props.combineAbove;
            const nodes = flatten(slots.default?.() ?? []);
            page.value = Math.min(page.value, Math.max(0, nodes.length - 1));
            return h('div', { class: 'page-deck', ref: deck }, [
                h(
                    'div',
                    {
                        class: 'deck-stage',
                        ref: stage,
                        onInvalidCapture: invalid,
                        onFocusinCapture: (event: FocusEvent) => {
                            const index = Array.from(
                                stage.value?.children ?? [],
                            ).findIndex((child) =>
                                child.contains(event.target as Node),
                            );
                            if (index >= 0) page.value = index;
                        },
                    },
                    nodes.map((node, index) =>
                        cloneVNode(node, {
                            style:
                                combined || index === page.value
                                    ? {}
                                    : { display: 'none' },
                            inert:
                                !combined && index !== page.value
                                    ? true
                                    : undefined,
                        }),
                    ),
                ),
                h(PageControls, {
                    modelValue: page.value,
                    total: combined ? 1 : nodes.length,
                    'onUpdate:modelValue': (value: number) => {
                        page.value = value;
                    },
                }),
            ]);
        };
    },
});
</script>
