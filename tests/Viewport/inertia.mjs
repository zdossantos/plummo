export function usePage() {
    return { props: { translations: window.translations } };
}
export const Link = { template: '<a><slot /></a>' };
