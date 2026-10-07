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
};
export type Snapshot = { room: RoomState; me: Player | null };
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
