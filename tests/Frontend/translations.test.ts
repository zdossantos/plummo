import { expect, test } from 'bun:test';
import { translate } from '../../resources/js/lib/translations';
test('keeps missing translations visible for diagnosis', () => {
    expect(translate({}, 'missing')).toBe('missing');
    expect(translate({ title: 'Bonjour' }, 'title')).toBe('Bonjour');
});

test('resolves nested catalog labels without rendering objects', () => {
    expect(translate({ items: { cap: 'Casquette' } }, 'items.cap')).toBe('Casquette');
    expect(translate({ items: { cap: 'Casquette' } }, 'items')).toBe('items');
});
