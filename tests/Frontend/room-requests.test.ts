import { expect, test } from 'bun:test';
import { RoomRequests } from '../../resources/js/lib/room-requests';

test('presence does not block controls and a player action replaces it', () => {
    const requests = new RoomRequests();
    const presence = requests.begin(true)!;
    expect(requests.busy).toBe(false);
    expect(requests.begin(true)).toBeNull();
    const action = requests.begin(false)!;
    expect(presence.controller.signal.aborted).toBe(true);
    expect(requests.current(presence)).toBe(false);
    expect(requests.busy).toBe(true);
    requests.finish(presence);
    expect(requests.current(action)).toBe(true);
    expect(requests.busy).toBe(true);
    expect(requests.begin(false)).toBeNull();
    requests.finish(action);
    expect(requests.busy).toBe(false);
    expect(requests.begin(true)).not.toBeNull();
});

test('unmount aborts the current request and invalidates its snapshot', () => {
    const requests = new RoomRequests();
    const action = requests.begin(false)!;
    requests.abort();
    expect(action.controller.signal.aborted).toBe(true);
    expect(requests.current(action)).toBe(false);
    expect(requests.busy).toBe(false);
});
