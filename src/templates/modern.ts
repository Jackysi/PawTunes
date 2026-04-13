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

        // Visualizer canvas
        this.canvas = document.querySelector('.visualizer-canvas') as HTMLCanvasElement;
        if (this.canvas) {
            this.ctx = this.canvas.getContext('2d');
            this.resizeCanvas();
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

        // Hide entire tab bar if there's only the home tab left
        const singleStream = isSingleChannel && Object.keys(this.pawtunes.channels[0]?.streams || {}).length <= 1;
        if (singleStream && !this.pawtunes.settings.history) {
            console.log("no history)")
            this.pawtunes._('.tab-bar', (el: HTMLElement) => el.remove());
            this.pawtunes._('', (el: HTMLElement) => el.classList.add('no-tab-bar'));
        }

        // Artwork search on tap
        if (this.pawtunes.settings.tpl?.songSearchEnable) {
            this.pawtunes._('.artwork-circle', (el: HTMLElement) => {
                el.style.cursor = 'pointer';
                el.addEventListener('click', () => {
                    const {artist, title} = this.pawtunes.onAir;
                    if (artist && title) {
                        window.open(this.pawtunes.settings.tpl.songSearch.replace('{query}', encodeURI(artist + ' - ' + title)));
                    }
                });
            });
        }

        this.bindPawEvents();
        this.bindButtons();
        this.switchPage('main');

        window.addEventListener('resize', () => {
            this.switchPage('', false);
            this.resizeCanvas();
        });

    }


    // ── Events ────────────────────────────────────────────────────────────────
    bindPawEvents() {

        this.pawtunes.on('channel.change', (channel: any) => {
            this.updateChannelName(channel);
            this.switchPage('main');
        });

        this.pawtunes.on('track.change', (track: { artist: string; title: string }) => {
            this.updateTrackInfo(track);
        });

        this.pawtunes.on('status.change', (status: string) => {
            this.updateStatusInfo(status);
        });

        // Start/stop visualizer with playback
        this.pawtunes.on('playing', () => this.startVisualizerLoop());
        this.pawtunes.on('stopped', () => this.stopVisualizerLoop());

    }


    bindButtons() {

        // Tab bar items
        const tabMap: Record<string, string> = {
            '.nav-home'    : 'main',
            '.nav-history' : 'history',
            '.nav-channels': 'settings-page',
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
            'settings-page': '.nav-channels',
        };

        this.pawtunes._('.tab-bar .tab-item', (el: HTMLElement) => el.classList.remove('active'));
        const activeSelector = pageToTab[pageClass];
        if (activeSelector) {
            this.pawtunes._(activeSelector, (el: HTMLElement) => el.classList.add('active'));
        }

    }


    // ── UI updates ────────────────────────────────────────────────────────────
    updateChannelName(channel: any) {

        // Show logotype instead of channel name
        if (this.pawtunes.settings.tpl?.useChannelLogos && channel.logo) {
            this.pawtunes._('.current-channel', (el: HTMLElement) => {
                el.innerHTML = `<img alt="Logo" height="68" src="./${channel.logo}">`;
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

        if (!this.audioMotion || !this.ctx || !this.canvas) return;

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