type Ticket = { controller: AbortController; background: boolean };
export class RoomRequests {
    private active: Ticket | null = null;
    get busy() {
        return this.active !== null && !this.active.background;
    }
    begin(background: boolean): Ticket | null {
        if (this.active && (background || !this.active.background)) return null;
        this.active?.controller.abort();
        return (this.active = {
            controller: new AbortController(),
            background,
        });
    }
    current(ticket: Ticket) {
        return this.active === ticket;
    }
    finish(ticket: Ticket) {
        if (this.current(ticket)) this.active = null;
    }
    abort() {
        this.active?.controller.abort();
        this.active = null;
    }
}
