export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';
export type SharedProps = {
    name: string;
    locale: 'fr' | 'en';
    translations: { home: Record<string, string> };
    [key: string]: unknown;
};
