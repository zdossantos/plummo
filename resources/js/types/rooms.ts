export type BonusKind =
    | 'bolt'
    | 'dice'
    | 'squatter'
    | 'artist'
    | 'accent'
    | 'sneeze'
    | 'stamp'
    | 'paint'
    | 'shield'
    | 'soap'
    | 'boomerang'
    | 'pinata'
    | 'washer'
    | 'lock'
    | 'mystery'
    | 'bubbles'
    | 'photobomb'
    | 'jelly'
    | 'strike'
    | 'leak'
    | 'freeze'
    | 'extension'
    | 'watch'
    | 'overtime'
    | 'last_breath'
    | 'alarm'
    | 'borrow'
    | 'translator'
    | 'advert'
    | 'scratch'
    | 'speed'
    | 'singer'
    | 'missing_word'
    | 'alphabet'
    | 'curtain'
    | 'giant'
    | 'mirror'
    | 'clumsy';
export type BonusItem = { id: string; kind: BonusKind; canUse?: boolean };
export type BonusEffect = BonusItem & {
    actorId: number;
    startedAt: number;
    expiresAt: number | null;
};
export type BonusReceipt = BonusItem & { recipientId: number; at: number };
export type BonusState = {
    enabled: boolean;
    clock?: { frozenValue: number } | null;
    inventory: BonusItem[];
    canUse: boolean;
    effects: BonusEffect[];
    receipts: BonusReceipt[];
    pending?: BonusItem | null;
    launches?: (BonusItem & {
        at: number;
        actorId: number;
        reaction?: boolean;
        deflected?: boolean;
    })[];
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
    audioReady?: boolean;
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
    clumsy?: boolean;
    bonusWidth?: number;
    tool?: 'brush' | 'fill' | 'erase';
    spans?: [number, number, number][];
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
export type MiniTrial = 'memory' | 'hide' | 'count' | 'cups' | 'author';
export type CupSwap = {
    at: number;
    duration: number;
    positions: [number, number];
};
export type MiniScene = {
    residents?: string[];
    people?: { color: string; accessories: string[]; number: number }[];
    events?: { at: number; direction: number; color: string }[];
    swaps?: CupSwap[];
    under?: number;
    color?: string;
};
export type MiniGameState = Omit<
    ChoiceGame,
    'type' | 'phase' | 'round' | 'me'
> & {
    type: 'mini_games';
    settings: ChoiceGame['settings'] & { trials: MiniTrial[] };
    phase: ChoiceGame['phase'] | 'observing' | 'writing' | 'waiting';
    round: Omit<ChoiceGame['round'], 'audio'> & {
        trial: MiniTrial;
        text: string | null;
        scene: MiniScene | null;
        elapsed: number;
        observeDuration: number;
        scenePhase: string;
        participants: number[];
    };
    me:
        | (NonNullable<ChoiceGame['me']> & {
              authorNumber: number | null;
              draft: string;
              submitted: boolean;
          })
        | null;
};
export type TribunalGameState = Omit<
    ChoiceGame,
    'type' | 'phase' | 'round' | 'me'
> & {
    type: 'tribunal';
    phase:
        | 'preparing'
        | 'speaking_first'
        | 'speaking_second'
        | 'voting'
        | 'waiting'
        | 'reveal'
        | 'paused'
        | 'resuming'
        | 'results';
    round: {
        number: number;
        total: number;
        tour: number;
        tours: number;
        question: string;
        advocates: [number, number];
        speakerId: number | null;
        votes: Record<number, number>;
        counts: number[];
        awards: Record<number, number>;
        canceled: boolean;
    };
    me: {
        eligible: boolean;
        camp: number | null;
        canSpeak: boolean;
        canVote: boolean;
        answered: boolean;
        choice: number | null;
        points: number | null;
    } | null;
};
export type GameState =
    | ChoiceGame
    | DrawingGameState
    | PhraseGameState
    | MiniGameState
    | TribunalGameState;
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
