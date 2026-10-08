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
} from 'vue';
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
    setup(_, { slots }) {
        const page = ref(0);
        const stage = ref<HTMLElement>();
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
            const nodes = flatten(slots.default?.() ?? []);
            page.value = Math.min(page.value, Math.max(0, nodes.length - 1));
            return h('div', { class: 'page-deck' }, [
                h(
                    'div',
                    {
                        class: 'deck-stage',
                        ref: stage,
                        onInvalidCapture: invalid,
                    },
                    nodes.map((node, index) =>
                        cloneVNode(node, {
                            style:
                                index === page.value ? {} : { display: 'none' },
                            inert: index !== page.value ? true : undefined,
                        }),
                    ),
                ),
                h(PageControls, {
                    modelValue: page.value,
                    total: nodes.length,
                    'onUpdate:modelValue': (value: number) => {
                        page.value = value;
                    },
                }),
            ]);
        };
    },
});
</script>
