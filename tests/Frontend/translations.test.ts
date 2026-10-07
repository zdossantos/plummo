import { expect, test } from 'bun:test';
import { translate } from '../../resources/js/lib/translations';
test('keeps missing translations visible for diagnosis', () => {
    expect(translate({}, 'missing')).toBe('missing');
    expect(translate({ title: 'Bonjour' }, 'title')).toBe('Bonjour');
});
