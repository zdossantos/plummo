export type BonusKind =
    | 'bolt'
    | 'dice'
    | 'squatter'
    | 'artist'
    | 'accent'
    | 'sneeze'
    | 'stamp'
    | 'paint';
export type BonusItem = { id: string; kind: BonusKind };
export type BonusEffect = BonusItem & {
    actorId: number;
    startedAt: number;
    expiresAt: number | null;
};
export type BonusReceipt = BonusItem & { recipientId: number; at: number };
export type BonusState = {
    enabled: boolean;
    inventory: BonusItem[];
    canUse: boolean;
    effects: BonusEffect[];
    receipts: BonusReceipt[];
    pending?: BonusItem | null;
    launches?: (BonusItem & { at: number; actorId: number })[];
};
export type Player = {
    id: number;
    name: string;
    color: string;
    accessories: string[];
    score: number;
    chat: { message: string; expiresAt: number } | null;
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
    bonuses?: BonusState;
    id: number;
    settings: {
        packs: number[];
        rounds: number;
        duration: number;
        allow_repeats?: boolean;
        bonuses?: boolean;
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
        answers?: Record<number, number>;
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
        guesses?: { playerId: number; text: string | null; found: boolean }[];
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
        canGuess: boolean;
    } | null;
};
export type PhraseGameState = Omit<
    ChoiceGame,
    'type' | 'phase' | 'round' | 'me'
> & {
    type: 'phrase';
    phase:
        | 'writing'
        | 'presenting'
        | 'voting'
        | 'reveal'
        | 'paused'
        | 'resuming'
        | 'results';
    round: {
        number: number;
        total: number;
        prompt: string;
        entries: {
            id: string;
            text: string;
            author?: number;
            votes?: number;
            points?: number;
        }[];
        awards: Record<number, number>;
    };
    me: {
        eligible: boolean;
        draft: string;
        submitted: boolean;
        ownEntry: string | null;
        voted: boolean;
        choice: string | null;
        points: number | null;
    } | null;
};
export type GameState = ChoiceGame | DrawingGameState | PhraseGameState;
export type DrawingSender = (
    action: string,
    values: Record<string, unknown>,
) => Promise<boolean>;
export type Snapshot = {
    canChat?: boolean;
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
