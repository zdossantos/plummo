export function translate(
    catalog: Record<string, string>,
    key: string,
): string {
    return catalog[key] ?? key;
}
