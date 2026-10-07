import { usePage } from '@inertiajs/vue3';
import { translate } from '@/lib/translations';
import type { SharedProps } from '@/types';
export function useTranslations(namespace = 'home') {
    const page = usePage<SharedProps>();
    return {
        t: (key: string, values: Record<string, string | number> = {}) =>
            Object.entries(values).reduce(
                (text, [name, value]) =>
                    text.replaceAll(`{${name}}`, String(value)),
                translate(page.props.translations[namespace] ?? {}, key),
            ),
    };
}
