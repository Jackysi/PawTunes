/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */
interface EventListener {
    fn: Function;
    context: any;
    once: boolean;
}

export class PawTunesEvents {

    /**
     * Stores the event listeners for the instance, indexed by event names.
     * Each event can have a single listener or an array of listeners.
     * @private
     */
    private _events: Record<string | symbol, EventListener | EventListener[]> = {};

    /**
     * Counts the number of event listeners registered to this instance.
     * @private
     */
    private _eventsCount: number = 0;

    /**
     * Returns an array listing the events for which the emitter has registered listeners.
     *
     * @returns Array of event names.
     */
    public eventNames(): Array<string | symbol> {

        const names: Array<string | symbol> = [];
        if (this._eventsCount === 0) return names;

        for (const name in this._events) {
            if (Object.prototype.hasOwnProperty.call(this._events, name)) {
                names.push(name);
            }
        }

        if (typeof Object.getOwnPropertySymbols === 'function') {
            names.push(...Object.getOwnPropertySymbols(this._events));
        }

        return names;

    }

    /**
     * Returns the listeners registered for a given event.
     *
     * @param event The event name.
     * @returns Array of listener functions.
     */
    public listeners(event: string | symbol): Function[] {

        const handlers = this._events[event];

        if (!handlers) return [];
        if ((handlers as EventListener).fn) {

            return [(handlers as EventListener).fn];

        } else {

            return (handlers as EventListener[]).map((listener) => listener.fn);

        }
    }

    /**
     * Returns the number of listeners listening to a given event.
     *
     * @param event The event name.
     * @returns Number of listeners.
     */
    public listenerCount(event: string | symbol): number {

        const listeners = this._events[event];

        if (!listeners) return 0;
        if ((listeners as EventListener).fn) return 1;

        return (listeners as EventListener[]).length;

    }

    /**
     * Calls each of the listeners registered for a given event.
     *
     * @param event The event name.
     * @param args Arguments to pass to the listeners.
     * @returns `true` if the event had listeners, else `false`.
     */
    public emit(event: string | symbol, ...args: any[]): boolean {

        const evt = event;
        if (!this._events[evt]) return false;

        const listeners = this._events[evt];
        if ((listeners as EventListener).fn) {

            const listener = listeners as EventListener;
            if (listener.once) this.removeListener(event, listener.fn, undefined, true);
            listener.fn.apply(listener.context, args);

        } else {

            const listenersArray = (listeners as EventListener[]).slice();
            for (const listener of listenersArray) {
                if (listener.once) this.removeListener(event, listener.fn, undefined, true);
                listener.fn.apply(listener.context, args);
            }

        }

        return true;

    }

    /**
     * Adds a listener for a given event.
     *
     * @param event The event name.
     * @param fn The listener function.
     * @param context The context to invoke the listener with.
     * @returns The instance of PawTunesEvents.
     */
    public on(event: string | symbol, fn: Function, context?: any): this {

        return this.registerListener(event, fn, context, false);

    }

    /**
     * Adds a one-time listener for a given event.
     *
     * @param event The event name.
     * @param fn The listener function.
     * @param context The context to invoke the listener with.
     * @returns The instance of PawTunesEvents.
     */
    public once(event: string | symbol, fn: Function, context?: any): this {

        return this.registerListener(event, fn, context, true);

    }

    /**
     * Removes the listeners of a given event.
     *
     * @param event The event name.
     * @param fn Only remove the listeners that match this function.
     * @param context Only remove the listeners that have this context.
     * @param once Only remove one-time listeners.
     * @returns The instance of PawTunesEvents.
     */
    public removeListener(event: string | symbol, fn?: Function, context?: any, once?: boolean): this {

        const evt = event;
        if (!this._events[evt]) return this;

        if (!fn) {
            this.clearEvent(evt);
            return this;
        }

        const listeners = this._events[evt];
        if ((listeners as EventListener).fn) {

            const listener = listeners as EventListener;
            if (
                listener.fn === fn &&
                (!once || listener.once) &&
                (!context || listener.context === context)
            ) {
                this.clearEvent(evt);
            }

        } else {

            const events = (listeners as EventListener[]).filter(
                (listener) =>
                    listener.fn !== fn ||
                    (once && !listener.once) ||
                    (context && listener.context !== context)
            );

            if (events.length) {

                this._events[evt] = events.length === 1 ? events[0] : events;

            } else {

                this.clearEvent(evt);

            }
        }

        return this;
    }

    /**
     * Removes all listeners, or those of the specified event.
     *
     * @param event The event name.
     * @returns The instance of PawTunesEvents.
     */
    public removeAllListeners(event?: string | symbol): this {

        if (event) {

            const evt = event;
            if (this._events[evt]) this.clearEvent(evt);

        } else {

            this._events = {};
            this._eventsCount = 0;

        }

        return this;
    }

    // Aliases for compatibility
    public off(event: string | symbol, fn?: Function, context?: any, once?: boolean): this {

        return this.removeListener(event, fn, context, once);

    }

    public addListener(event: string | symbol, fn: Function, context?: any): this {

        return this.on(event, fn, context);

    }

    /**
     * Adds a listener for a given event.
     *
     * @param event The event name.
     * @param fn The listener function.
     * @param context The context to invoke the listener with.
     * @param once Specify if the listener is a one-time listener.
     * @returns The instance of PawTunesEvents.
     */
    private registerListener(event: string | symbol, fn: Function, context: any, once: boolean): this {

        if (typeof fn !== 'function') {
            throw new TypeError('The listener must be a function');
        }

        const listener: EventListener = {fn: fn, context: context || this, once: once};
        const evt = event;

        if (!this._events[evt]) {

            this._events[evt] = listener;
            this._eventsCount++;

        } else if (!((this._events[evt] as EventListener).fn)) {

            // It's an array of listeners
            (this._events[evt] as EventListener[]).push(listener);

        } else {

            // Convert a single listener to array
            this._events[evt] = [this._events[evt] as EventListener, listener];

        }

        return this;

    }

    /**
     * Clears an event by name.
     *
     * @param evt The event name.
     */
    private clearEvent(evt: string | symbol): void {
        if (--this._eventsCount === 0) {

            this._events = {};

        } else {

            delete this._events[evt];

        }
    }
}