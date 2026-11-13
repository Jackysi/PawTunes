/**
 * PawTunes Project — Modern Template
 *
 * Mobile-first radio player with circular artwork and ring visualizer.
 * Uses audioMotion-analyzer for radial frequency visualization around the artwork.
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 */
import PawTunes from "../player/ts/pawtunes";
import {CastChangeEvent, TrackExtra} from "../player/ts/types";
import AudioMotionAnalyzer from 'audiomotion-analyzer';

export default class ModernTpl {

    private pawtunes: PawTunes;
    private currentPage: string = 'main';
    private statusTimeout: any;

    // Visualizer
    private audioMotion: AudioMotionAnalyzer | null = null;
    private canvas: HTMLCanvasElement | null = null;
    private ctx: CanvasRenderingContext2D | null = null;
    private animationId: number = 0;
    private accentColor: string = '';
    private accentFrame: number = 0;
    private isDarkMode: boolean = false;

    // Listeners page: fetched only while the page is open and the tab visible, rows tick locally
    private listeners = {
        open      : false,
        timer     : 0 as any,
        tick      : 0 as any,
        fetchedAt : 0,
        rows      : [] as { connected: number, el: HTMLElement }[],
        controller: null as AbortController | null,
    };


    constructor(PawTunes: PawTunes) {

        this.pawtunes = PawTunes;

        this.pawtunes.on('init', () => {
            this.pawtunes._('.preloader', (el: HTMLElement) => el.classList.add('hidden'));
        });

        this.pawtunes.init();
        this.setup();

    }


    // ── Setup ─────────────────────────────────────────────────────────────────
    setup() {

        // Channel name
        if (this.pawtunes.channel) {
            this.updateChannelName(this.pawtunes.channel);
        }

        // Track info
        if (this.pawtunes.onAir.artist && this.pawtunes.onAir.title) {
            this.updateTrackInfo(this.pawtunes.onAir);
        }

        // Up next / live DJ already known (the first poll may have answered before setup ran)
        this.updateTrackExtra(this.pawtunes.trackExtra);

        // Visualizer canvas
        this.canvas = document.querySelector('.visualizer-canvas') as HTMLCanvasElement;
        if (this.canvas) {
            this.ctx = this.canvas.getContext('2d');
            this.resizeCanvas();

            // The artwork area animates its width (see fitArtwork): the canvas resolution follows at the end
            this.canvas.parentElement?.addEventListener('transitionend', (e) => {
                if (e.target === this.canvas?.parentElement) this.resizeCanvas();
            });
        }

        if (this.pawtunes.audio) {
            this.initVisualizer();
        }

        // Hide history tab if disabled
        if (!this.pawtunes.settings.history) {
            this.pawtunes._('.tab-bar .nav-history', (el: HTMLElement) => el.remove());
            this.pawtunes._('.page.history', (el: HTMLElement) => el.remove());
        }

        // Hide channels' tab if single channel with single stream
        const isSingleChannel = this.pawtunes.channels.length <= 1;
        if (isSingleChannel && Object.keys(this.pawtunes.channels[0]?.streams || {}).length <= 1) {
            this.pawtunes._('.tab-bar .nav-channels', (el: HTMLElement) => el.remove());
            this.pawtunes._('.page.settings-page', (el: HTMLElement) => el.remove());
        }

        // Listeners tab: needs the template option and a channel the server marked as able to list them.
        // With one channel that cannot change, so the tab goes the same way as the channels tab.
        const listenersPossible = this.optionOn('showListeners', false) && this.pawtunes.channels.some((c) => c.listeners === true);
        if (!listenersPossible && isSingleChannel) {
            this.pawtunes._('.tab-bar .nav-listeners', (el: HTMLElement) => el.remove());
            this.pawtunes._('.page.listeners', (el: HTMLElement) => el.remove());
        }
        this.updateListenersTab();

        // Hide entire tab bar if there's only the home tab left
        const singleStream = isSingleChannel && Object.keys(this.pawtunes.channels[0]?.streams || {}).length <= 1;
        if (singleStream && !this.pawtunes.settings.history && !listenersPossible) {
            this.pawtunes._('.tab-bar', (el: HTMLElement) => el.remove());
            this.pawtunes._('', (el: HTMLElement) => el.classList.add('no-tab-bar'));
        }

        // No fetching while the browser tab is hidden; catch up as soon as it is visible again
        document.addEventListener('visibilitychange', () => {
            if (!this.listeners.open) return;
            if (document.hidden) {
                clearTimeout(this.listeners.timer);
            } else {
                this.fetchListeners();
            }
        });

        // Artwork search on tap
        if (this.pawtunes.settings.tpl?.songSearchEnable) {
            this.pawtunes._('.artwork-circle', (el: HTMLElement) => {
                el.style.cursor = 'pointer';
                el.addEventListener('click', () => {
                    const {artist, title} = this.pawtunes.onAir;
                    if (artist && title) {
                        // encodeURIComponent: "&" or "#" in a name must not end the query. A replacer function,
                        // so "$&" in a title is not read as a replacement pattern.
                        const query = encodeURIComponent(artist + ' - ' + title);
                        window.open(this.pawtunes.settings.tpl.songSearch.replace('{query}', () => query));
                    }
                });
            });
        }

        this.bindPawEvents();
        this.bindButtons();
        this.switchPage('main');
        this.fitArtwork();

        window.addEventListener('resize', () => {
            this.switchPage('', false);
            this.fitArtwork();
        });

        // The web font arrives later and changes the text heights the artwork size depends on
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => this.fitArtwork());
        }

    }


    /**
     * The main page never scrolls, so when the live badge or the up next line take vertical room (or
     * the player is embedded short) the artwork gives way instead of the controls being clipped: its
     * width is the smaller of the CSS size (72%, max 360px) and the height left by everything else.
     */
    private fitArtwork() {

        const page = this.pawtunes._('.page.main')[0] as HTMLElement | undefined;
        const area = this.pawtunes._('.artwork-area')[0] as HTMLElement | undefined;
        if (!page || !area) return;

        const style = getComputedStyle(page);
        let used = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);

        for (const child of Array.from(page.children) as HTMLElement[]) {
            if (child === area) continue;
            const cs = getComputedStyle(child);
            used += child.offsetHeight + parseFloat(cs.marginTop) + parseFloat(cs.marginBottom);
        }

        const innerWidth = page.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const byWidth = Math.min(innerWidth * 0.72, 360);
        const byHeight = page.clientHeight - used;

        area.style.width = `${Math.round(Math.max(140, Math.min(byWidth, byHeight)))}px`;
        this.resizeCanvas();

    }


    // ── Events ────────────────────────────────────────────────────────────────
    bindPawEvents() {

        this.pawtunes.on('channel.change', (channel: any) => {
            this.updateChannelName(channel);
            this.switchPage('main');
            this.resetListenersView();
            this.updateListenersTab();
        });

        this.pawtunes.on('track.change', (track: { artist: string; title: string }) => {
            this.updateTrackInfo(track);
        });

        // Queued track and live DJ (AzuraCast, SAM): emitted only when they change
        this.pawtunes.on('track.extra', (extra: TrackExtra) => {
            this.updateTrackExtra(extra);
        });

        this.pawtunes.on('status.change', (status: string) => {
            this.updateStatusInfo(status);
        });

        // Start/stop visualizer with playback. While casting the analyser only sees silence (the audio
        // plays on another device), so the ring stays off until the session ends.
        this.pawtunes.on('playing', () => {
            if (this.pawtunes.cast.state !== 'connected') this.startVisualizerLoop();
        });
        this.pawtunes.on('stopped', () => this.stopVisualizerLoop());
        this.pawtunes.on('cast.change', (event: CastChangeEvent) => {

            if (event.state === 'connected') {
                this.stopVisualizerLoop();
                return;
            }

            // AirPlay ends with the element still playing, so no "playing" event follows
            if (this.pawtunes.audio && !this.pawtunes.audio.paused) {
                this.startVisualizerLoop();
            }

        });

    }


    bindButtons() {

        // Tab bar items
        const tabMap: Record<string, string> = {
            '.nav-home'     : 'main',
            '.nav-history'  : 'history',
            '.nav-listeners': 'listeners',
            '.nav-channels' : 'settings-page',
        };

        for (const [selector, page] of Object.entries(tabMap)) {
            this.pawtunes._(selector, (el: HTMLElement) => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.switchPage(page);
                });
            });
        }

    }


    // ── Page slides ───────────────────────────────────────────────────────────
    switchPage(pageClass: string = '', animation: boolean = true) {

        if (pageClass === '') pageClass = this.currentPage;

        const pages = this.pawtunes._('.container .page');
        const innerContainer = this.pawtunes._('.container .container-inner');
        if (!pages.length || !innerContainer.length) return;

        let targetPage = pages[0];

        for (let i = 0; i < pages.length; i++) {
            if (pages[i].classList.contains(pageClass)) {
                targetPage = pages[i];
                break;
            }
        }

        innerContainer[0].style.transform = `translate3d(0, -${targetPage.offsetTop}px, 0)`;
        this.currentPage = pageClass;

        if (!animation) {
            innerContainer[0].style.transition = 'none';
            setTimeout(() => {
                innerContainer[0].style.transition = '';
            }, 0);
        }

        // Update active tab
        const pageToTab: Record<string, string> = {
            'main'         : '.nav-home',
            'history'      : '.nav-history',
            'listeners'    : '.nav-listeners',
            'settings-page': '.nav-channels',
        };

        this.pawtunes._('.tab-bar .tab-item', (el: HTMLElement) => el.classList.remove('active'));
        const activeSelector = pageToTab[pageClass];
        if (activeSelector) {
            this.pawtunes._(activeSelector, (el: HTMLElement) => el.classList.add('active'));
        }

        // The listeners page is the only one that costs requests: fetch while it is open, never otherwise
        if (pageClass === 'listeners' && !this.listeners.open) {
            this.startListenersPolling();
        } else if (pageClass !== 'listeners' && this.listeners.open) {
            this.stopListenersPolling();
        }

    }


    // ── Listeners page ────────────────────────────────────────────────────
    /**
     * May the current channel show its listeners? The template option plus the server's per-channel
     * flag (set only when the method can list listeners and the credentials are there).
     */
    private listenersAllowed(): boolean {

        return this.optionOn('showListeners', false) && this.pawtunes.channel.listeners === true;

    }

    /**
     * Shows or hides the tab for the current channel; leaves the page when it is no longer allowed
     */
    private updateListenersTab() {

        const allowed = this.listenersAllowed();
        this.pawtunes._('.tab-bar .nav-listeners', (el: HTMLElement) => el.classList.toggle('hidden', !allowed));

        if (!allowed && this.currentPage === 'listeners') {
            this.switchPage('main');
        }

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
     * One request to ?listeners&channel=<name>; the server's "refresh" sets the next delay. A 403/404
     * means the server does not offer the page for this channel after all: the tab goes away.
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

            // One second past the server's refresh: its Cache-Control max-age ends with the refresh window,
            // so the next poll must land after it or the browser answers from its own HTTP cache
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

        // First list of this channel fades in, refreshes swap the rows in place
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


    // ── UI updates ────────────────────────────────────────────────────────────
    updateChannelName(channel: any) {

        // Show logotype instead of channel name
        if (this.pawtunes.settings.tpl?.useChannelLogos && channel.logo) {
            this.pawtunes._('.current-channel', (el: HTMLElement) => {

                // Built with DOM APIs so the logo path never becomes markup
                const img = document.createElement('img');
                img.alt = 'Logo';
                img.height = 68;
                img.src = `./${channel.logo}`;

                el.textContent = '';
                el.appendChild(img);

            });
            return;
        }

        this.pawtunes._('.current-channel', (el: HTMLElement) => {
            el.textContent = channel.name ?? '';
        });

    }

    updateTrackInfo(track: { artist: string; title: string }) {

        this.pawtunes._('.onair .artist', (el: HTMLElement) => this.setMarquee(el, track.artist));
        this.pawtunes._('.onair .title', (el: HTMLElement) => this.setMarquee(el, track.title));

    }

    /**
     * Live DJ badge above the track and "Up next" line below the volume, both gated by template options
     * (showLiveBadge, showUpNext). Upstream strings are written as text only.
     */
    updateTrackExtra(extra: TrackExtra) {

        const live = (this.optionOn('showLiveBadge', true) && extra.live?.isLive) ? extra.live : null;
        const next = (this.optionOn('showUpNext', true) && extra.next) ? extra.next : null;

        this.pawtunes._('.onair .live-badge', (el: HTMLElement) => {
            el.classList.toggle('hidden', !live);
            const streamer = el.querySelector('.live-streamer');
            if (streamer) streamer.textContent = live ? live.streamer : '';
        });

        this.pawtunes._('.player .up-next', (el: HTMLElement) => {
            el.classList.toggle('hidden', !next);
            const track = el.querySelector('.up-next-track');
            if (track) track.textContent = next ? (next.artist ? `${next.artist} - ${next.title}` : next.title) : '';
        });

        // Both lines take vertical room on the main page
        this.fitArtwork();

    }

    /**
     * Template option as a boolean. The panel stores checkboxes as true/false, but an option missing
     * from a not yet refreshed templates cache is undefined: then the manifest default applies.
     */
    private optionOn(name: string, fallback: boolean): boolean {

        const value = this.pawtunes.settings.tpl?.[name];
        if (value === undefined || value === null) return fallback;

        return value === true || value === 'true' || value === 1 || value === '1';

    }

    /**
     * Sets text on an element. If the text overflows, wraps it in two
     * duplicate spans and adds .marquee for a seamless CSS scroll loop.
     */
    private setMarquee(el: HTMLElement, text: string) {

        el.classList.remove('marquee');
        el.textContent = text;

        // Wait one frame for layout to settle, then check overflow
        requestAnimationFrame(() => {

            if (el.scrollWidth <= el.clientWidth) return;

            // Text overflows — set up the marquee
            const speed = 40; // pixels per second
            const duration = el.scrollWidth / speed;

            el.innerHTML = '';
            el.classList.add('marquee');
            el.style.setProperty('--marquee-duration', `${duration}s`);

            // Two identical spans side-by-side for seamless loop
            const span1 = document.createElement('span');
            span1.textContent = text;
            const span2 = document.createElement('span');
            span2.classList.add('marquee-copy');
            span2.textContent = text;

            el.appendChild(span1);
            el.appendChild(span2);

        });

    }

    updateStatusInfo(status: string) {

        if (!status) return;

        this.pawtunes._('.player-message', (el: HTMLElement) => {
            el.classList.remove('hidden');
            const text = el.querySelector('.text');
            if (text) text.textContent = status;

            clearTimeout(this.statusTimeout);
            this.statusTimeout = setTimeout(() => {
                el.classList.add('hidden');
            }, 2500);
        });

    }


    // ── Ring Visualizer ───────────────────────────────────────────────────────
    initVisualizer() {

        // Skip if disabled
        if (this.pawtunes.settings.tpl?.spectrumVisualizer !== true) return;

        if (!this.pawtunes.audio || !this.canvas || !this.ctx) return;

        // set the crossOrigin property in the media element
        this.pawtunes.audio.crossOrigin = 'anonymous';

        // audioMotion processes audio only (useCanvas: false), we draw the ring ourselves
        const container = document.createElement('div');
        container.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
        document.body.appendChild(container);

        this.audioMotion = new AudioMotionAnalyzer(container, {
            source        : this.pawtunes.audio,
            useCanvas     : false,
            frequencyScale: 'bark',
            fftSize       : 8192,
            mode          : 2,
            minFreq       : 20,
            maxFreq       : 20000,
            minDecibels   : -80,
            maxDecibels   : -20,
            smoothing     : 0.8,
        });

    }

    startVisualizerLoop() {

        if (!this.audioMotion || !this.ctx || !this.canvas || this.animationId) return;

        const draw = () => {
            this.animationId = requestAnimationFrame(draw);
            if (!this.audioMotion || !this.ctx || !this.canvas) return;

            const bars = this.audioMotion.getBars();
            if (bars.length) this.drawRing(bars);
        };

        draw();

    }

    stopVisualizerLoop() {

        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = 0;
        }

        // Clear canvas
        if (this.ctx && this.canvas) {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }

    }

    private drawRing(bars: { value: number[] }[]) {

        if (!this.ctx || !this.canvas) return;

        // Cache accent colour + theme mode (read once per ~60 frames to avoid layout thrash)
        if (!this.accentColor || ++this.accentFrame > 60) {
            const root = document.querySelector('.pawtunes');
            if (root) {
                const styles = getComputedStyle(root);
                this.accentColor = styles.getPropertyValue('--accent-color').trim() || '#6C5CE7';
                this.isDarkMode = styles.getPropertyValue('--waveform-mode').trim() === 'dark';
            }
            this.accentFrame = 0;
        }

        // Dark mode: blob waveform
        if (this.isDarkMode) return this.drawRingDark(bars);

        const width = this.canvas.width;
        const height = this.canvas.height;
        const cx = width / 2;
        const cy = height / 2;

        const innerRadius = width * 0.39;
        const maxBarLen = width * 0.15;
        const numBars = bars.length;
        const barWidth = Math.max((2 * Math.PI * innerRadius) / numBars * 0.6, 1.5);

        this.ctx.clearRect(0, 0, width, height);
        this.ctx.strokeStyle = this.accentColor;
        this.ctx.lineWidth = barWidth;
        this.ctx.lineCap = 'round';

        for (let i = 0; i < numBars; i++) {

            const value = bars[i].value[0];
            const barLen = Math.max(value * maxBarLen, 1);
            this.ctx.globalAlpha = 0.3 + value * 0.7;

            const angle = (i / numBars) * 2 * Math.PI - Math.PI / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(cx + Math.cos(angle) * innerRadius, cy + Math.sin(angle) * innerRadius);
            this.ctx.lineTo(cx + Math.cos(angle) * (innerRadius + barLen), cy + Math.sin(angle) * (innerRadius + barLen));
            this.ctx.stroke();

        }

        this.ctx.globalAlpha = 1;

    }


    /**
     * Dark-mode waveform: 3 organic blob-circles around the artwork,
     * each driven by a different frequency band (bass / mid / treble)
     * so they deform independently.  White fill with softened edges.
     */
    private drawRingDark(bars: { value: number[] }[]) {

        if (!this.ctx || !this.canvas) return;

        const width  = this.canvas.width;
        const height = this.canvas.height;
        const cx = width / 2;
        const cy = height / 2;

        const baseRadius = width * 0.38;
        const numBars    = bars.length;
        const bandSize   = Math.floor(numBars / 3);

        this.ctx.clearRect(0, 0, width, height);
        this.ctx.lineJoin = 'round';

        // Each blob is driven by a separate frequency band
        const blobs = [
            { from: 0,            to: bandSize,     fillA: 0.16, bulge: width * 0.18 },  // bass
            { from: bandSize,     to: bandSize * 2,  fillA: 0.14, bulge: width * 0.14 },  // mids
            { from: bandSize * 2, to: numBars,       fillA: 0.11, bulge: width * 0.11 },  // treble
        ];

        for (const blob of blobs) {

            const band      = bars.slice(blob.from, blob.to);
            const numPoints = Math.min(48, band.length);
            const step      = band.length / numPoints;
            const pts: { x: number; y: number }[] = [];
            let   maxR      = baseRadius;

            for (let i = 0; i < numPoints; i++) {
                const idx    = Math.min(Math.floor(i * step), band.length - 1);
                const value  = band[idx].value[0];
                const radius = baseRadius + value * blob.bulge;
                if (radius > maxR) maxR = radius;
                const angle  = (i / numPoints) * 2 * Math.PI - Math.PI / 2;
                pts.push({
                    x: cx + Math.cos(angle) * radius,
                    y: cy + Math.sin(angle) * radius,
                });
            }

            // Smooth closed curve via midpoint quadratic bézier
            const n = pts.length;
            this.ctx.beginPath();
            this.ctx.moveTo(
                (pts[n - 1].x + pts[0].x) / 2,
                (pts[n - 1].y + pts[0].y) / 2,
            );

            for (let i = 0; i < n; i++) {
                const next = (i + 1) % n;
                this.ctx.quadraticCurveTo(
                    pts[i].x, pts[i].y,
                    (pts[i].x + pts[next].x) / 2,
                    (pts[i].y + pts[next].y) / 2,
                );
            }

            this.ctx.closePath();

            // Radial gradient sized to actual blob extent (not theoretical max)
            const gradOuter = Math.max(maxR, baseRadius + 2);
            const grad = this.ctx.createRadialGradient(cx, cy, baseRadius * 0.95, cx, cy, gradOuter);
            grad.addColorStop(0, `rgba(255, 255, 255, ${blob.fillA})`);
            grad.addColorStop(0.5, `rgba(255, 255, 255, ${blob.fillA * 0.55})`);
            grad.addColorStop(1, `rgba(255, 255, 255, ${blob.fillA * 0.12})`);

            this.ctx.fillStyle = grad;
            this.ctx.fill();

        }

    }

    resizeCanvas() {

        if (!this.canvas) return;

        const container = this.canvas.parentElement;
        if (!container) return;

        const size = container.offsetWidth;
        const dpr = window.devicePixelRatio || 1;

        this.canvas.width = size * dpr;
        this.canvas.height = size * dpr;
        this.canvas.style.width = `${size}px`;
        this.canvas.style.height = `${size}px`;

    }

}