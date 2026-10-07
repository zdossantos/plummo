export function translate(
    catalog: Record<string, unknown>,
    key: string,
): string {
    const value = key
        .split('.')
        .reduce<unknown>(
            (entry, part) =>
                entry && typeof entry === 'object'
                    ? (entry as Record<string, unknown>)[part]
                    : undefined,
            catalog,
        );
    return typeof value === 'string' ? value : key;
}
