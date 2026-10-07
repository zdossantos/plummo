export type ContentType = 'quiz' | 'blind_test' | 'drawing' | 'phrase';
export type Tag = {
    id: number;
    name: string;
    contents_count?: number;
    packs_count?: number;
};
export type Content = {
    id: number;
    type: ContentType;
    published: boolean;
    payload: {
        question?: string;
        choices?: string[];
        correct?: number;
        title?: string;
        artist?: string;
        audio_path?: string;
        word?: string;
        prompt?: string;
    };
    tags: Tag[];
};
export type Pack = {
    id: number;
    name: string;
    tags: Tag[];
    counts: Record<ContentType, number>;
};
export const contentTypes: ContentType[] = [
    'quiz',
    'blind_test',
    'drawing',
    'phrase',
];
