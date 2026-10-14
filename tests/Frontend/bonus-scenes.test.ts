import { expect, test } from 'bun:test';
import { execFileSync } from 'node:child_process';
import { bonusScenes } from '../../resources/js/lib/bonus-scenes';

test('every server bonus has an explicit visual scene', () => {
    const kinds: string[] = JSON.parse(
        execFileSync(
            process.env.PHP_BINARY || 'php',
            [
                '-r',
                `require 'app/Services/BonusCatalog.php'; $catalog = new App\\Services\\BonusCatalog(); $kinds = []; foreach (['quiz','blind_test','drawing','phrase','mini_games','tribunal','bluff'] as $type) $kinds = array_merge($kinds, $catalog->kinds($type)); echo json_encode(array_values(array_unique($kinds)));`,
            ],
            { encoding: 'utf8' },
        ),
    );
    expect(Object.keys(bonusScenes).sort()).toEqual(kinds.sort());
    expect(new Set(Object.values(bonusScenes))).not.toContain(undefined);
});
