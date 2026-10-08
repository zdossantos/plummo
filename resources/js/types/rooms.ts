export type Player = {
    id: number;
    name: string;
    color: string;
    accessories: string[];
    score: number;
    status: 'connected' | 'disconnected' | 'waiting' | 'left';
};
export type RoomState = {
    code: string;
    capacity: number;
    occupied: number;
    chiefId: number | null;
    players: Player[];
    pointTarget: number | null;
    ranking: (Player & { rank: number })[];
};
export type ChoiceGame = {
    id: number;
    settings: {
        packs: number[];
        rounds: number;
        duration: number;
        allow_repeats?: boolean;
    };
    exhausted: boolean;
    targetReached: boolean;
    type: 'quiz' | 'blind_test';
    phase: 'answer' | 'reveal' | 'paused' | 'resuming' | 'results';
    deadline: number;
    scores: Record<number, number>;
    round: {
        number: number;
        total: number;
        question: string;
        audio: string | null;
        choices: string[];
        correct: number | null;
        awards: Record<number, number>;
    };
    me: {
        eligible: boolean;
        answered: boolean;
        choice: number | null;
        points: number | null;
    } | null;
};
export type Stroke = {
    id: number;
    color: string;
    width: number;
    points: [number, number][];
};
export type DrawingGameState = Omit<
    ChoiceGame,
    'type' | 'phase' | 'round' | 'me'
> & {
    type: 'drawing';
    phase:
        | 'selecting'
        | 'drawing'
        | 'artist_missing'
        | 'waiting'
        | 'reveal'
        | 'paused'
        | 'resuming'
        | 'results';
    round: {
        number: number;
        total: number;
        tour: number;
        artistId: number;
        word: string | null;
        canvas: Stroke[];
        revision: number;
        awards: Record<number, number>;
    };
    me: {
        eligible: boolean;
        found: boolean;
        near: boolean;
        points: number;
        words: string[];
        word: string | null;
        canDraw: boolean;
        canSkip: boolean;
        votedSkip: boolean;
    } | null;
};
export type GameState = ChoiceGame | DrawingGameState;
export type DrawingSender = (
    action: string,
    values: Record<string, unknown>,
) => Promise<boolean>;
export type Snapshot = {
    room: RoomState;
    me: Player | null;
    game?: GameState | null;
    serverTime?: number;
};
export type Accessory = {
    id: string;
    slot: string;
    front: string;
    back?: string;
    coversPlumes?: boolean;
};
export type Catalog = {
    colors: { id: string; color: string; light: string; dark: string }[];
    accessories: Accessory[];
    slots?: string[];
};
