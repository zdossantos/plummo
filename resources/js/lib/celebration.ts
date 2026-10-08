import type { GameState } from '@/types/rooms';
export function roundCelebration(
    game: GameState | null,
    previous: string,
): { key: string; gains: Record<number, number> } | null {
    if (!game || game.phase !== 'reveal') return null;
    const key = `${game.id}:${game.round.number}`;
    if (key === previous) return null;
    return {
        key,
        gains: Object.fromEntries(
            Object.entries(game.round.awards).filter(
                ([, points]) => points > 0,
            ),
        ),
    };
}
