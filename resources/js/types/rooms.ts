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
export type GameState = {
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
