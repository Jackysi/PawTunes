/**
 * PawTunes Project - Aurora Template
 *
 * Responsive radio player: the music's spectrum rises behind the player as northern lights, tinted with the
 * current artwork's color. The layout follows the player's own size (CSS container queries), so the same page works
 * full screen, as a popup, on a phone, and as a small box on another website.
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 */
import PawTunes from "../player/ts/pawtunes";
import {CastChangeEvent, TrackExtra} from "../player/ts/types";
import AudioMotionAnalyzer from 'audiomotion-analyzer';

type Tab = 'now' | 'history' | 'stations' | 'listeners';

// Same width as the "player (min-width: 1040px)" container query in scss/_base.scss
const WIDE_LAYOUT = 1040;


export default class AuroraTpl {

    private pawtunes: PawTunes;
    private root: HTMLElement;
    private sky: AuroraSky | null = null;

    private playing: boolean = false;
    private statusTimeout: any = 0;

    // Volume slider drag: the pointer is held on the slider, and a message was already shown during this hold
    private volumeDragging: boolean = false;
    private dragMessageShown: boolean = false;

    // Rail state: which panel it shows, and (narrow players only) whether the sheet is up
    private wide: boolean = false;
    private panel: Exclude<Tab, 'now'> | null = null;
    private sheetOpen: boolean = false;

    // Listeners panel: fetched only while it is on screen and the browser tab visible, rows tick locally
    private listeners = {
        open      : false,
        timer     : 0 as any,
        tick      : 0 as any,
        fetchedAt : 0,
        rows      : [] as { connected: number, el: HTMLElement }[],
        controller: null as AbortController | null,
    };

    // Sleep timer picker: the minutes picked here (the core only knows the end time) and the countdown tick
    private sleepMinutes: number = 0;
    private sleepTick: any = 0;


    constructor(pawtunes: PawTunes) {

        this.pawtunes = pawtunes;
        this.root = document.querySelector('.pawtunes') as HTMLElement;

        this.pawtunes.on('init', () => {
            this.pawtunes._('.preloader', (el: HTMLElement) => el.classList.add('hidden'));
        });

        this.pawtunes.init();
        this.setup();

    }


    // ── Setup ─────────────────────────────────────────────────────────────────
    private setup() {

        if (this.pawtunes.channel) this.updateChannelName(this.pawtunes.channel);
        if (this.pawtunes.onAir.artist && this.pawtunes.onAir.title) this.updateTrackInfo(this.pawtunes.onAir);

        // Up next / live DJ may already be known (the first poll can answer before setup runs)
        this.updateTrackExtra(this.pawtunes.trackExtra);

        this.setupTabs();
        this.setupVisualizer();
        this.setupSongSearch();
        this.bindPawEvents();
        this.bindSleepTimer();
        this.bindVolumeKeys();
        this.bindVolumeDrag();

        if (this.optionOn('keyboardShortcuts', true)) {
            this.bindKeyboardShortcuts();
        }

        // No listener fetching while the browser tab is hidden; catch up as soon as it is visible again
        document.addEventListener('visibilitychange', () => {
            if (!this.listeners.open) return;
            if (document.hidden) clearTimeout(this.listeners.timer);
            else this.fetchListeners();
        });

    }

    private bindPawEvents() {

        this.pawtunes.on('channel.change', (channel: any) => {
            this.updateChannelName(channel);
            this.resetListenersView();
            this.updateListenersTab();
            if (!this.wide) this.selectTab('now');
        });

        this.pawtunes.on('track.change', (track: { artist: string; title: string }) => this.updateTrackInfo(track));

        // Queued track and live DJ (AzuraCast, SAM): emitted only when they change
        this.pawtunes.on('track.extra', (extra: TrackExtra) => this.updateTrackExtra(extra));

        this.pawtunes.on('status.change', (status: string) => this.showStatus(status));

        // While casting the analyser only hears silence (the audio plays on another device): the sky rests
        this.pawtunes.on('playing', () => {
            this.setPlaying(true);
            if (this.pawtunes.cast.state !== 'connected') this.sky?.start();
        });

        this.pawtunes.on('stopped', () => {
            this.setPlaying(false);
            this.sky?.stop();
        });

        this.pawtunes.on('cast.change', (event: CastChangeEvent) => {

            if (event.state === 'connected') {
                this.sky?.stop();
                return;
            }

            // AirPlay ends with the element still playing, so no "playing" event follows
            if (this.pawtunes.audio && !this.pawtunes.audio.paused) this.sky?.start();

        });

        // A new artwork starts a color transition in CSS; a resting sky follows it
        this.pawtunes.on('artwork.color', () => this.sky?.recolor());
        this.pawtunes.on('theme.change', () => this.sky?.recolor());

    }

    private setPlaying(playing: boolean) {

        this.playing = playing;
        this.root.classList.toggle('is-playing', playing);

    }


    // ── Tabs, rail and sheet ──────────────────────────────────────────────────
    /**
     * One set of tabs serves both layouts. Wide players show the rail as a column with one of its panels; narrow ones
     * hide it in a bottom sheet that a tab opens and "Now" closes. Tabs without content are removed up front.
     */
    private setupTabs() {

        const channels = this.pawtunes.channels;
        const singleStation = channels.length <= 1 && Object.keys(channels[0]?.streams || {}).length <= 1;

        if (!this.pawtunes.settings.history) this.removeTab('history');

        if (singleStation) {
            this.removeTab('stations');
            this.root.classList.add('single-station');
        }

        // Listeners need the template option and a channel the server marked as able to list them. With a single
        // channel that cannot change later, so the tab goes for good.
        const listenersPossible = this.optionOn('showListeners', false) && channels.some((c) => c.listeners === true);
        if (!listenersPossible && channels.length <= 1) this.removeTab('listeners');

        this.pawtunes._('.tabs .tab', (tab: HTMLElement) => {
            tab.addEventListener('click', () => this.selectTab(tab.dataset.tab as Tab));
        });

        this.pawtunes._('.station-picker', (el: HTMLElement) => {
            el.addEventListener('click', () => this.selectTab('stations'));
        });

        // Tapping the dimmed stage closes the sheet
        this.pawtunes._('.stage', (el: HTMLElement) => {
            el.addEventListener('click', (e) => {
                if (!this.sheetOpen) return;
                e.preventDefault();
                e.stopPropagation();
                this.selectTab('now');
            }, true);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.sheetOpen) this.selectTab('now');
        });

        // The layout switches with the player's own width, so this watches the player, not the window
        new ResizeObserver(() => this.onResize()).observe(this.root);

        this.panel = this.firstPanel();
        this.updateListenersTab();
        this.onResize();

    }

    private removeTab(tab: Tab) {

        this.pawtunes._(`.tabs .tab[data-tab="${tab}"]`, (el: HTMLElement) => el.remove());
        this.pawtunes._(`.rail .panel[data-panel="${tab}"]`, (el: HTMLElement) => el.remove());

    }

    /**
     * The first panel that still has a visible tab, or null when the rail has nothing to show
     */
    private firstPanel(): Exclude<Tab, 'now'> | null {

        for (const tab of ['history', 'stations', 'listeners'] as const) {
            const el = this.pawtunes._(`.tabs .tab[data-tab="${tab}"]`)[0] as HTMLElement | undefined;
            if (el && !el.classList.contains('hidden')) return tab;
        }

        return null;

    }

    private onResize() {

        const {width, height} = this.root.getBoundingClientRect();
        this.wide = width >= WIDE_LAYOUT && height > 300;

        // The wide rail is always open; going narrow starts with the sheet closed
        if (this.wide) this.sheetOpen = false;

        this.root.classList.toggle('no-rail', this.panel === null);
        this.renderTabs();

    }

    private selectTab(tab: Tab) {

        if (tab === 'now') {
            this.sheetOpen = false;
        } else {
            this.panel = tab;
            this.sheetOpen = !this.wide;
        }

        this.renderTabs();

    }

    private renderTabs() {

        const current: Tab | null = (this.wide || this.sheetOpen) ? this.panel : 'now';

        this.root.classList.toggle('sheet-open', this.sheetOpen);

        this.pawtunes._('.tabs .tab', (el: HTMLElement) => {
            const active = el.dataset.tab === current;
            el.classList.toggle('active', active);
            el.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        this.pawtunes._('.rail .panel', (el: HTMLElement) => {
            el.classList.toggle('active', el.dataset.panel === this.panel);
        });

        this.pawtunes._('.rail', (el: HTMLElement) => {
            el.setAttribute('aria-hidden', (this.wide || this.sheetOpen) ? 'false' : 'true');
        });

        // Listeners are the only panel that costs requests: fetch while it is on screen, never otherwise
        const listenersVisible = this.panel === 'listeners' && (this.wide || this.sheetOpen);
        if (listenersVisible && !this.listeners.open) this.startListenersPolling();
        else if (!listenersVisible && this.listeners.open) this.stopListenersPolling();

    }


    // ── Now playing ───────────────────────────────────────────────────────────
    private updateChannelName(channel: any) {

        this.pawtunes._('.current-channel', (el: HTMLElement) => {

            // Built with DOM APIs so the logo path or a channel name never becomes markup
            if (this.optionOn('useChannelLogos', false) && channel.logo) {

                const img = document.createElement('img');
                img.alt = channel.name ?? '';
                img.src = `./${channel.logo}`;
                el.replaceChildren(img);
                return;

            }

            el.textContent = channel.name ?? '';

        });

    }

    /**
     * Full names, not the core's shortened ones: the title may take two lines and CSS clamps whatever is left
     */
    private updateTrackInfo(track: { artist: string; title: string }) {

        this.pawtunes._('.track-title', (el: HTMLElement) => {
            el.textContent = track.title;
            el.title = track.title;
        });

        this.pawtunes._('.track-artist', (el: HTMLElement) => {
            el.textContent = track.artist;
            el.title = track.artist;
        });

    }

    /**
     * Live DJ badge above the title and "Up next" below the controls, both gated by template options. Upstream
     * strings are written as text only.
     */
    private updateTrackExtra(extra: TrackExtra) {

        const live = (this.optionOn('showLiveBadge', true) && extra.live?.isLive) ? extra.live : null;
        const next = (this.optionOn('showUpNext', true) && extra.next) ? extra.next : null;

        this.pawtunes._('.live-badge', (el: HTMLElement) => {
            el.classList.toggle('hidden', !live);
            const streamer = el.querySelector('.live-streamer');
            if (streamer) streamer.textContent = live ? live.streamer : '';
        });

        this.pawtunes._('.up-next', (el: HTMLElement) => {
            el.classList.toggle('hidden', !next);
            const track = el.querySelector('.up-next-track');
            if (track) track.textContent = next ? (next.artist ? `${next.artist} - ${next.title}` : next.title) : '';
        });

    }

    private setupSongSearch() {

        if (!this.optionOn('songSearchEnable', true) || !this.pawtunes.settings.tpl?.songSearch) return;

        this.pawtunes._('.cover', (el: HTMLElement) => {

            el.classList.add('searchable');
            el.addEventListener('click', () => {

                const {artist, title} = this.pawtunes.onAir;
                if (!artist || !title) return;

                // encodeURIComponent: "&" or "#" in a name must not end the query. A replacer function, so "$&"
                // in a title is not read as a replacement pattern.
                const query = encodeURIComponent(`${artist} - ${title}`);
                window.open(this.pawtunes.settings.tpl.songSearch.replace('{query}', () => query), '_blank', 'noopener');

            });

        });

    }

    private showStatus(status: string) {

        if (!status) return;

        this.pawtunes._('.player-message', (el: HTMLElement) => {

            const text = el.querySelector('.text');
            if (text) text.textContent = status;

            // Dragging the volume slider sends a message per step. The first one appears as usual (a plain click
            // sends just that one); the following ones only change the text, so the message stays put instead of
            // replaying its entrance animation on every step.
            const steady = this.volumeDragging && this.dragMessageShown && !el.classList.contains('hidden');
            if (this.volumeDragging) this.dragMessageShown = true;

            if (!steady) {

                // Re-adding the element restarts its entrance animation for back-to-back messages
                el.classList.add('hidden');
                void el.offsetWidth;
                el.classList.remove('hidden');

            }

            clearTimeout(this.statusTimeout);
            this.statusTimeout = setTimeout(() => el.classList.add('hidden'), 2600);

        });

    }


    // ── Keyboard ──────────────────────────────────────────────────────────────
    /**
     * Space (or K) plays and stops, M mutes, arrows up and down change the volume. Ignored while typing and while a
     * button has focus (Space already presses the button).
     */
    private bindKeyboardShortcuts() {

        document.addEventListener('keydown', (e) => {

            if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;

            const target = e.target as HTMLElement | null;
            if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT|BUTTON|A)$/.test(target.tagName))) return;

            switch (e.key) {

                case ' ':
                case 'k':
                case 'K':
                    e.preventDefault();
                    this.playing ? this.pawtunes.stop() : this.pawtunes.play();
                    break;

                case 'm':
                case 'M':
                    this.pawtunes.volume < 1 ? this.pawtunes.unmute() : this.pawtunes.mute();
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    this.nudgeVolume(5);
                    break;

                case 'ArrowDown':
                    e.preventDefault();
                    this.nudgeVolume(-5);
                    break;

            }

        });

    }

    /**
     * The volume handle is a slider for keyboard users too; the mute button works with Enter and Space
     */
    private bindVolumeKeys() {

        this.pawtunes._('.circle-control', (el: HTMLElement) => {
            el.addEventListener('keydown', (e) => {

                const steps: Record<string, number> = {ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5, PageUp: 20, PageDown: -20};

                if (e.key === 'Home' || e.key === 'End') {
                    e.preventDefault();
                    this.setVolume(e.key === 'Home' ? 0 : 100);
                } else if (steps[e.key]) {
                    e.preventDefault();
                    this.nudgeVolume(steps[e.key]);
                }

            });
        });

        // The core binds the two icons inside the button; a keyboard press lands on the button itself
        this.pawtunes._('.volume-icon', (el: HTMLElement) => {
            el.addEventListener('click', (e) => {
                if (e.target !== el) return;
                this.pawtunes.volume < 1 ? this.pawtunes.unmute() : this.pawtunes.mute();
            });
        });

    }

    /**
     * Tracks a pointer held on the volume slider, for showStatus(). The core handles the drag itself but keeps that
     * state private, so the same slider is watched here.
     */
    private bindVolumeDrag() {

        const start = () => {
            this.volumeDragging = true;
            this.dragMessageShown = false;
        };

        const end = () => {
            this.volumeDragging = false;
        };

        this.pawtunes._('.volume-slider .vol-progress', (el: HTMLElement) => {
            el.addEventListener('mousedown', start);
            el.addEventListener('touchstart', start, {passive: true});
        });

        document.addEventListener('mouseup', end);
        document.addEventListener('touchend', end);
        document.addEventListener('touchcancel', end);

    }

    private nudgeVolume(delta: number) {

        this.setVolume(this.pawtunes.volume + delta);

    }

    /**
     * Through the media element: its volumechange event updates the core's volume, the slider and storage
     */
    private setVolume(volume: number) {

        if (!this.pawtunes.audio || this.pawtunes.isNoVolume) return;
        this.pawtunes.audio.volume = Math.max(0, Math.min(100, volume)) / 100;

    }


    // ── Sleep timer picker ────────────────────────────────────────────────────
    /**
     * The moon opens a chip menu (Off, 15, 30, 60, 90 min) that drives the core's setSleepTimer(); the UI follows
     * the core's sleep.change event, so a stop or a cast session that ends the timer shows too. The menu closes on
     * a pick, an outside click or Escape.
     */
    private bindSleepTimer() {

        const button = this.pawtunes._('.topbar .sleep')[0] as HTMLElement | undefined;
        const menu = this.pawtunes._('.topbar .sleep-menu')[0] as HTMLElement | undefined;
        if (!button || !menu) return;

        button.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleSleepMenu(menu.classList.contains('hidden'));
        });

        menu.addEventListener('click', (e) => e.stopPropagation());

        this.pawtunes._('.sleep-menu .sleep-option', (option: HTMLElement) => {
            option.addEventListener('click', () => {

                // setSleepTimer() emits sleep.change synchronously (null first when it replaces a running timer,
                // which clears the picked minutes), so the pick is recorded after it returns
                const minutes = Number(option.dataset.minutes) || 0;
                this.pawtunes.setSleepTimer(minutes || null);
                this.sleepMinutes = minutes;
                this.updateSleepUI();
                this.toggleSleepMenu(false);

            });
        });

        document.addEventListener('click', () => this.toggleSleepMenu(false));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.toggleSleepMenu(false);
        });

        this.pawtunes.on('sleep.change', () => this.updateSleepUI());
        this.updateSleepUI();

    }

    private toggleSleepMenu(open: boolean) {

        this.pawtunes._('.topbar .sleep-menu', (el: HTMLElement) => el.classList.toggle('hidden', !open));
        this.pawtunes._('.topbar .sleep', (el: HTMLElement) => el.setAttribute('aria-expanded', open ? 'true' : 'false'));

    }

    /**
     * Button state, the active chip and the countdown (ticks once a second only while a timer runs)
     */
    private updateSleepUI() {

        const endsAt = this.pawtunes.getSleepTimerEnd();
        if (!endsAt) this.sleepMinutes = 0;

        // Off is highlighted only while no timer runs; a timer set elsewhere (a cast session) has no known
        // minutes, so no chip is highlighted for it
        this.pawtunes._('.topbar .sleep', (el: HTMLElement) => el.classList.toggle('active', !!endsAt));
        this.pawtunes._('.sleep-menu .sleep-option', (option: HTMLElement) => {
            const minutes = Number(option.dataset.minutes) || 0;
            const active = endsAt ? (this.sleepMinutes > 0 && minutes === this.sleepMinutes) : minutes === 0;
            option.classList.toggle('active', active);
            option.setAttribute('aria-checked', active ? 'true' : 'false');
        });

        clearInterval(this.sleepTick);

        const tick = () => {

            const clock = this.formatClock((this.pawtunes.getSleepTimerEnd() ?? 0) - Date.now());

            this.pawtunes._('.topbar .sleep-remaining', (el: HTMLElement) => {
                el.classList.toggle('hidden', !endsAt);
                el.textContent = endsAt ? clock : '';
            });

            this.pawtunes._('.sleep-menu .sleep-menu-status', (el: HTMLElement) => {
                el.classList.toggle('hidden', !endsAt);
                el.textContent = endsAt ? this.pawtunes.translate('sleep_remaining', 'TIME', clock) : '';
            });

        };

        tick();
        if (endsAt) this.sleepTick = setInterval(tick, 1000);

    }

    /**
     * "42:10", or "1:30:00" from one hour up
     */
    private formatClock(ms: number): string {

        const total = Math.max(0, Math.ceil(ms / 1000));
        const hours = Math.floor(total / 3600);
        const mm = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
        const ss = String(total % 60).padStart(2, '0');

        return hours > 0 ? `${hours}:${mm}:${ss}` : `${Number(mm)}:${ss}`;

    }


    // ── Listeners panel ───────────────────────────────────────────────────────
    /**
     * May the current channel show its listeners? The template option plus the server's per-channel flag (set only
     * when the method can list listeners and the credentials are there).
     */
    private listenersAllowed(): boolean {

        return this.optionOn('showListeners', false) && this.pawtunes.channel?.listeners === true;

    }

    /**
     * Shows or hides the tab for the current channel; leaves the panel when it is no longer allowed
     */
    private updateListenersTab() {

        const allowed = this.listenersAllowed();
        this.pawtunes._('.tabs .tab[data-tab="listeners"]', (el: HTMLElement) => el.classList.toggle('hidden', !allowed));

        if (!allowed && this.panel === 'listeners') {
            this.panel = this.firstPanel();
            if (!this.wide) this.sheetOpen = false;
        }

        this.root.classList.toggle('no-rail', this.panel === null);
        this.renderTabs();

    }

    private startListenersPolling() {

        this.listeners.open = true;
        this.fetchListeners();
        this.listeners.tick = setInterval(() => this.tickListeners(), 1000);

    }

    private stopListenersPolling() {

        this.listeners.open = false;
        clearTimeout(this.listeners.timer);
        clearInterval(this.listeners.tick);
        this.listeners.controller?.abort();
        this.listeners.controller = null;

    }

    /**
     * Back to the loading state with no rows (a channel switch: the old list belongs to another station)
     */
    private resetListenersView() {

        this.listeners.rows = [];
        this.pawtunes._('.listeners-list', (el: HTMLElement) => el.replaceChildren());
        this.pawtunes._('.listeners-total', (el: HTMLElement) => el.classList.add('hidden'));
        this.showListenersState('loading');

    }

    /**
     * One request to ?listeners&channel=<name>; the server's "refresh" sets the next delay. A 403/404 means the
     * server does not offer the list for this channel after all: the tab goes away.
     */
    private async fetchListeners() {

        if (!this.listeners.open || document.hidden) return;

        clearTimeout(this.listeners.timer);
        this.listeners.controller?.abort();
        this.listeners.controller = new AbortController();

        const channel = this.pawtunes.channel;
        let delay = 30000;

        try {

            const response = await fetch(`${this.pawtunes.settings.api}?listeners&channel=${encodeURIComponent(channel.name)}`, {signal: this.listeners.controller.signal});

            // Switched away or to another channel while waiting: this answer is no longer wanted
            if (!this.listeners.open || channel !== this.pawtunes.channel) return;

            if (response.status === 403 || response.status === 404) {
                channel.listeners = false;
                this.updateListenersTab();
                return;
            }

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            if (!this.listeners.open || channel !== this.pawtunes.channel) return;

            if (data.status === 'ok' && Array.isArray(data.listeners)) {
                this.renderListeners(data);
            } else if (data.status === 'pending') {
                // The server is fetching for someone else: keep the rows we have, otherwise keep waiting
                if (this.listeners.rows.length === 0) this.showListenersState('loading');
            } else {
                this.showListenersState('error');
            }

            // One second past the server's refresh: its Cache-Control max-age ends with the refresh window, so the
            // next poll must land after it or the browser answers from its own HTTP cache
            delay = (Math.max(2, Number(data.refresh) || 30) + 1) * 1000;

        } catch (e: any) {

            if (e?.name === 'AbortError') return;
            this.showListenersState('error');
            delay = 10000;

        }

        if (this.listeners.open) {
            this.listeners.timer = setTimeout(() => this.fetchListeners(), delay);
        }

    }

    /**
     * Rows are DOM-built: the device label and the masked address are upstream data, written as text
     */
    private renderListeners(data: { total: number, listeners: { ip: string, device: string, connected: number }[] }) {

        const list = this.pawtunes._('.listeners-list')[0] as HTMLElement | undefined;
        if (!list) return;

        const unknown = this.pawtunes.translate('listeners_unknown');
        const rows: { connected: number, el: HTMLElement }[] = [];
        const items: HTMLElement[] = [];

        for (const item of data.listeners) {

            const li = document.createElement('li');
            li.className = 'listener';

            const device = document.createElement('span');
            device.className = 'listener-device';
            device.textContent = item.device ? String(item.device) : unknown;

            const ip = document.createElement('span');
            ip.className = 'listener-ip';
            ip.textContent = item.ip ? String(item.ip) : unknown;

            const time = document.createElement('span');
            time.className = 'listener-time';

            li.append(device, ip, time);
            items.push(li);
            rows.push({connected: Math.max(0, Number(item.connected) || 0), el: time});

        }

        // The first list of a channel fades in, refreshes swap the rows in place
        list.classList.toggle('animate', this.listeners.rows.length === 0);
        list.replaceChildren(...items);

        this.listeners.rows = rows;
        this.listeners.fetchedAt = Date.now();
        this.tickListeners();

        this.pawtunes._('.listeners-total', (el: HTMLElement) => {
            el.textContent = String(Math.max(0, Number(data.total) || 0));
            el.classList.remove('hidden');
        });

        this.showListenersState(rows.length ? 'list' : 'empty');

    }

    /**
     * Listening times keep counting between refreshes
     */
    private tickListeners() {

        const elapsed = Math.floor((Date.now() - this.listeners.fetchedAt) / 1000);
        for (const row of this.listeners.rows) {
            row.el.textContent = this.formatDuration(row.connected + elapsed);
        }

    }

    /**
     * "1 h 23 min", "45 min", "12 s" with translated units
     */
    private formatDuration(seconds: number): string {

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const t = (key: string) => this.pawtunes.translate(key);

        if (hours >= 1) return `${hours} ${t('unit_hour_short')} ${minutes} ${t('unit_min_short')}`;
        if (minutes >= 1) return `${minutes} ${t('unit_min_short')}`;

        return `${Math.floor(seconds % 60)} ${t('unit_sec_short')}`;

    }

    private showListenersState(state: 'loading' | 'empty' | 'error' | 'list') {

        const blocks: Record<string, string> = {
            loading: '.listeners-loading',
            empty  : '.listeners-empty',
            error  : '.listeners-error',
            list   : '.listeners-list',
        };

        for (const [name, selector] of Object.entries(blocks)) {
            this.pawtunes._(selector, (el: HTMLElement) => el.classList.toggle('hidden', name !== state));
        }

        // The count belongs to a list (even an empty one), not to an error or a pending load
        if (state === 'error' || state === 'loading') {
            this.pawtunes._('.listeners-total', (el: HTMLElement) => el.classList.add('hidden'));
        }

    }


    // ── Visualizer ────────────────────────────────────────────────────────────
    private setupVisualizer() {

        const canvas = this.pawtunes._('.sky')[0] as HTMLCanvasElement | undefined;
        const shell = this.pawtunes._('.shell')[0] as HTMLElement | undefined;
        if (!canvas || !shell) return;

        this.sky = new AuroraSky(canvas, shell);

        // With the option off the sky still shows a resting aurora, it just never listens
        if (this.optionOn('visualizer', true) && this.pawtunes.audio) {
            this.sky.listen(this.pawtunes.audio);
        }

    }

    /**
     * Template option as a boolean. The panel stores checkboxes as true/false, but an option missing from a not yet
     * refreshed templates cache is undefined: then the manifest default applies.
     */
    private optionOn(name: string, fallback: boolean): boolean {

        const value = this.pawtunes.settings.tpl?.[name];
        if (value === undefined || value === null) return fallback;

        return value === true || value === 'true' || value === 1 || value === '1';

    }

}


/**
 * The aurora: the spectrum drawn as northern lights along the bottom of the stage.
 *
 * Like the real thing it is a curtain of thin rays: each rises from a bright, slowly undulating lower edge, as tall
 * as the music at its place in the spectrum (bass on the left, treble on the right), and fades upwards into the
 * second hue. It is drawn at half resolution: the browser's upscaling softens it into a glow for free, and it halves
 * the pixels painted per frame.
 *
 * The audio is analysed by audioMotion-analyzer (without its own canvas); this class only draws. Colors come from
 * the stage's CSS (--ac, --ac2, --aurora-blend), which the browser transitions on every artwork change, so the
 * aurora recolors together with the rest of the page without any color logic here.
 *
 * Not playing, casting, or with reduced motion: one resting frame, no animation loop.
 */
class AuroraSky {

    // Spectrum columns the rays sample from, and the rays themselves
    private static readonly POINTS = 48;
    private static readonly RAYS = 132;

    // Canvas pixels per CSS pixel: half resolution is the glow (see above)
    private static readonly SCALE = .5;

    private canvas: HTMLCanvasElement;
    private ctx: CanvasRenderingContext2D;
    private styles: HTMLElement;
    private analyzer: AudioMotionAnalyzer | null = null;

    private raf: number = 0;
    private live: boolean = false;
    private recolorUntil: number = 0;
    private frame: number = 0;

    private levels = new Float32Array(AuroraSky.POINTS);
    private width: number = 0;
    private height: number = 0;

    // Per-ray character (position jitter, width, height and brightness), fixed so the curtain keeps its folds
    private rays: { offset: number, width: number, height: number, alpha: number, phase: number }[] = [];

    // The ray: a soft-edged vertical streak, bright at its foot and fading upwards, redrawn when the colors change
    private sprite: HTMLCanvasElement = document.createElement('canvas');
    private blend: GlobalCompositeOperation = 'lighter';
    private colorKey: string = '';
    private probe: CanvasRenderingContext2D | null;

    private readonly reducedMotion: MediaQueryList = window.matchMedia('(prefers-reduced-motion: reduce)');


    constructor(canvas: HTMLCanvasElement, styles: HTMLElement) {

        this.canvas = canvas;
        this.ctx = canvas.getContext('2d') as CanvasRenderingContext2D;
        this.styles = styles;

        // 1x1 canvas that turns any CSS color (rgb, oklch, color-mix results) into RGB values
        const probe = document.createElement('canvas');
        probe.width = probe.height = 1;
        this.probe = probe.getContext('2d', {willReadFrequently: true});

        // A small seeded generator: the same curtain on every load
        let seed = 7;
        const random = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
        for (let i = 0; i < AuroraSky.RAYS; i++) {
            this.rays.push({offset: random(), width: 1.4 + random() * 2.2, height: .7 + random() * .55, alpha: .45 + random() * .55, phase: random() * Math.PI * 2});
        }

        new ResizeObserver(() => this.resize()).observe(canvas.parentElement ?? canvas);
        this.reducedMotion.addEventListener('change', () => this.live ? this.start() : this.rest());

        this.resize();

    }

    /**
     * Connect the analyser to the player's media element
     */
    listen(audio: HTMLAudioElement) {

        // audioMotion insists on a container even without a canvas of its own
        const container = document.createElement('div');
        container.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
        document.body.appendChild(container);

        this.analyzer = new AudioMotionAnalyzer(container, {
            source         : audio,
            useCanvas      : false,
            mode           : 3,      // 1/8th octave bands, resampled to POINTS below
            frequencyScale : 'log',
            weightingFilter: 'D',    // closer to how loud each band sounds, so treble shows up too
            fftSize        : 4096,
            minFreq        : 30,
            maxFreq        : 16000,
            minDecibels    : -85,
            maxDecibels    : -25,
            smoothing      : .7,
        });

    }

    start() {

        this.live = !!this.analyzer;

        // A context created before any user gesture starts suspended; playback itself follows a click
        if (this.analyzer && this.analyzer.audioCtx.state === 'suspended') {
            this.analyzer.audioCtx.resume().catch(() => undefined);
        }

        if (!this.live || this.reducedMotion.matches) {
            this.rest();
            return;
        }

        this.loop();

    }

    stop() {

        this.live = false;
        this.rest();

    }

    /**
     * The CSS colors are transitioning: a resting sky redraws until they settle (1.4 s in _base.scss)
     */
    recolor() {

        this.recolorUntil = performance.now() + 1600;
        if (!this.live && !this.reducedMotion.matches) this.loop();
        else if (!this.live) this.rest();

    }

    private loop() {

        if (this.raf) return;

        const step = (time: number) => {

            this.raf = 0;
            const keepGoing = (this.live && !this.reducedMotion.matches) || time < this.recolorUntil;

            this.draw(time, this.live);
            if (keepGoing) this.raf = requestAnimationFrame(step);

        };

        this.raf = requestAnimationFrame(step);

    }

    private rest() {

        if (this.raf) {
            cancelAnimationFrame(this.raf);
            this.raf = 0;
        }

        this.draw(0, false);

    }

    private resize() {

        const box = this.canvas.getBoundingClientRect();
        this.width = Math.max(1, Math.round(box.width * AuroraSky.SCALE));
        this.height = Math.max(1, Math.round(box.height * AuroraSky.SCALE));
        this.canvas.width = this.width;
        this.canvas.height = this.height;

        if (!this.raf) this.draw(0, false);

    }

    /**
     * Reads the colors from CSS every few frames (cheap, and enough to follow a transition smoothly) and redraws the
     * ray sprite when they changed
     */
    private readColors() {

        const styles = getComputedStyle(this.styles);
        const a = styles.getPropertyValue('--ac').trim();
        const b = styles.getPropertyValue('--ac2').trim();
        const blend = styles.getPropertyValue('--aurora-blend').trim();
        const key = `${a}|${b}|${blend}`;

        if (key === this.colorKey) return;
        this.colorKey = key;

        this.blend = (blend === 'multiply') ? 'multiply' : 'lighter';
        this.paintSprite(this.toRGB(a) ?? [61, 220, 192], this.toRGB(b) ?? [139, 123, 255]);

    }

    private paintSprite([ar, ag, ab]: number[], [br, bg, bb]: number[]) {

        const sprite = this.sprite;
        sprite.width = 16;
        sprite.height = 256;

        const ctx = sprite.getContext('2d') as CanvasRenderingContext2D;
        ctx.clearRect(0, 0, 16, 256);

        // Top to bottom: nothing, the second hue, the accent, a bright foot (the aurora's lower edge), a short fade
        const light = (v: number) => Math.min(255, v + 70);
        const vertical = ctx.createLinearGradient(0, 0, 0, 256);
        vertical.addColorStop(0, `rgba(${br}, ${bg}, ${bb}, 0)`);
        vertical.addColorStop(.45, `rgba(${br}, ${bg}, ${bb}, .28)`);
        vertical.addColorStop(.8, `rgba(${ar}, ${ag}, ${ab}, .75)`);
        vertical.addColorStop(.9, `rgba(${light(ar)}, ${light(ag)}, ${light(ab)}, 1)`);
        vertical.addColorStop(1, `rgba(${ar}, ${ag}, ${ab}, 0)`);
        ctx.fillStyle = vertical;
        ctx.fillRect(0, 0, 16, 256);

        // Soft sides: neighbouring rays blend into one curtain instead of reading as bars
        ctx.globalCompositeOperation = 'destination-in';
        const sides = ctx.createLinearGradient(0, 0, 16, 0);
        sides.addColorStop(0, 'rgba(0, 0, 0, 0)');
        sides.addColorStop(.5, 'rgba(0, 0, 0, 1)');
        sides.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = sides;
        ctx.fillRect(0, 0, 16, 256);

    }

    private toRGB(color: string): [number, number, number] | null {

        if (!color || !this.probe) return null;

        this.probe.clearRect(0, 0, 1, 1);
        this.probe.fillStyle = '#000';
        this.probe.fillStyle = color;
        this.probe.fillRect(0, 0, 1, 1);

        const [r, g, b, a] = this.probe.getImageData(0, 0, 1, 1).data;
        return a ? [r, g, b] : null;

    }

    /**
     * Fills this.levels (0..1 per spectrum column): live from the analyser with a quick rise and a slow fall, or a
     * low resting shape
     */
    private updateLevels(live: boolean) {

        const n = AuroraSky.POINTS;

        if (!live || !this.analyzer) {
            for (let i = 0; i < n; i++) {
                const x = i / (n - 1);
                this.levels[i] = .22 + .12 * Math.sin(x * Math.PI * 2.2 + .6) + .07 * Math.sin(x * Math.PI * 5.3);
            }
            return;
        }

        const bars = this.analyzer.getBars();
        if (!bars.length) return;

        for (let i = 0; i < n; i++) {

            // Resample the bands, and lift the upper end a little so the curtain spans the whole width
            const position = i / (n - 1) * (bars.length - 1);
            const low = Math.floor(position);
            const high = Math.min(bars.length - 1, low + 1);
            const mix = position - low;
            const value = bars[low].value[0] * (1 - mix) + bars[high].value[0] * mix;
            const target = Math.min(1, value * (.85 + .4 * (i / (n - 1))));

            const current = this.levels[i];
            this.levels[i] = target > current ? current + (target - current) * .5 : current * .94 + target * .06;

        }

    }

    /**
     * Level at a horizontal position (0..1), smoothed across neighbouring columns
     */
    private levelAt(position: number): number {

        const n = AuroraSky.POINTS;
        const x = Math.min(n - 1, Math.max(0, position * (n - 1)));
        const i = Math.floor(x);
        const j = Math.min(n - 1, i + 1);
        const level = this.levels[i] + (this.levels[j] - this.levels[i]) * (x - i);
        const around = (this.levels[Math.max(0, i - 2)] + this.levels[Math.min(n - 1, j + 2)]) / 2;

        return level * .7 + around * .3;

    }

    private draw(time: number, live: boolean) {

        if (++this.frame % 6 === 0 || !this.colorKey || time < this.recolorUntil) this.readColors();
        this.updateLevels(live);

        const {ctx, width: w, height: h} = this;
        const t = live ? time : 0;
        const spacing = w / AuroraSky.RAYS;

        // Landscape stages have room beside the artwork; portrait ones stack the controls in the lower third, so
        // there the aurora stays lower and its foot sits nearer the bottom
        const landscape = w > h;
        const maxHeight = h * (landscape ? .58 : .34);
        const foot = landscape ? .9 : .94;

        ctx.clearRect(0, 0, w, h);
        ctx.globalCompositeOperation = this.blend;

        // At rest the sky is quieter
        const strength = live ? 1 : .55;

        for (let r = 0; r < AuroraSky.RAYS; r++) {

            const ray = this.rays[r];
            const position = (r + ray.offset) / AuroraSky.RAYS;
            const level = this.levelAt(position);

            // The lower edge drifts slowly like a curtain in wind; the rays flicker a little on their own
            const edge = h * (foot + .035 * Math.sin(position * Math.PI * 1.6 + t * .00022) + .02 * Math.sin(position * Math.PI * 4.1 - t * .00031));
            const flicker = 1 + .14 * Math.sin(t * .0016 + ray.phase);
            const rise = maxHeight * (.1 + .9 * level) * ray.height * flicker;
            const width = spacing * ray.width;

            // The sprite's bright foot sits at 90% of its height: align it with the edge
            const top = edge - rise * .9;

            ctx.globalAlpha = Math.min(1, ray.alpha * (.3 + .7 * level) * strength);
            ctx.drawImage(this.sprite, position * w - width / 2, top, width, rise);

        }

        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';

    }

}
