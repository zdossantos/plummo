import { usePage } from '@inertiajs/vue3';
import { translate } from '@/lib/translations';
import type { SharedProps } from '@/types';
export function useTranslations() {
    const page = usePage<SharedProps>();
    return { t: (key: string) => translate(page.props.translations.home, key) };
}
