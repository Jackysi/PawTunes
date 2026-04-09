/**
 * PawTunes Project — Modern Template
 *
 * Mobile-first radio player with circular artwork and ring visualizer.
 * Uses Web Audio API AnalyserNode for radial frequency bars around the artwork.
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 */
import PawTunes from "../player/ts/pawtunes";

export default class ModernTpl {

    private pawtunes: PawTunes;
    private currentPage: string = 'main';
    private statusTimeout: any;

    // Visualizer
    private audioCtx: AudioContext | null = null;
    private analyser: AnalyserNode | null = null;
    private animationId: number = 0;
    private canvas: HTMLCanvasElement | null = null;
    private ctx: CanvasRenderingContext2D | null = null;
    private accentColor: string = '';
    private accentFrame: number = 0;


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

        // Visualizer
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
            '.nav-home': 'main',
            '.nav-history': 'history',
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
            'main': '.nav-home',
            'history': '.nav-history',
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
        if (this.pawtunes.settings.tpl?.useChannelLogos) {
            this.pawtunes._('.current-channel', (el: HTMLElement) => {
                el.innerHTML = '<img alt="Logo" height="50" src="./assets/img/logo.svg">';
            });
            this.pawtunes.off('channel.change', this.updateChannelName);
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

        if (!this.pawtunes.audio || !this.canvas || !this.ctx) return;

        try {
            this.audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
            this.analyser = this.audioCtx.createAnalyser();
            this.analyser.fftSize = 512;
            this.analyser.smoothingTimeConstant = 0.8;

            const source = this.audioCtx.createMediaElementSource(this.pawtunes.audio);
            source.connect(this.analyser);
            this.analyser.connect(this.audioCtx.destination);
        } catch (err) {
            console.warn('Visualizer init failed:', err);
        }

    }

    startVisualizerLoop() {

        if (!this.analyser || !this.ctx || !this.canvas) return;

        // Resume AudioContext if suspended (autoplay policy)
        if (this.audioCtx?.state === 'suspended') {
            this.audioCtx.resume();
        }

        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        const draw = () => {
            this.animationId = requestAnimationFrame(draw);
            if (!this.analyser || !this.ctx || !this.canvas) return;

            this.analyser.getByteFrequencyData(dataArray);
            this.drawRing(dataArray, bufferLength);
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

    private drawRing(dataArray: Uint8Array, bufferLength: number) {

        if (!this.ctx || !this.canvas) return;

        const width = this.canvas.width;
        const height = this.canvas.height;
        const cx = width / 2;
        const cy = height / 2;

        const innerRadius = width * 0.39;
        const maxBarLen = width * 0.1;

        // Use only the meaningful frequency bins (low-mid range has the energy)
        const usableBins = Math.min(bufferLength, 128);
        const barWidth = Math.max((2 * Math.PI * innerRadius) / usableBins * 0.6, 1.5);

        // Cache accent color (read once per ~60 frames to avoid layout thrash)
        if (!this.accentColor || ++this.accentFrame > 60) {
            const root = document.querySelector('.pawtunes');
            this.accentColor = root
                ? getComputedStyle(root).getPropertyValue('--accent-color').trim() || '#6C5CE7'
                : '#6C5CE7';
            this.accentFrame = 0;
        }

        this.ctx.clearRect(0, 0, width, height);
        this.ctx.strokeStyle = this.accentColor;
        this.ctx.lineWidth = barWidth;
        this.ctx.lineCap = 'round';

        // Single pass: each bin spans a portion of the full circle
        for (let i = 0; i < usableBins; i++) {

            const value = dataArray[i] / 255;
            const barLen = Math.max(value * maxBarLen, 1);
            this.ctx.globalAlpha = 0.3 + value * 0.7;

            const angle = (i / usableBins) * 2 * Math.PI - Math.PI / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(cx + Math.cos(angle) * innerRadius, cy + Math.sin(angle) * innerRadius);
            this.ctx.lineTo(cx + Math.cos(angle) * (innerRadius + barLen), cy + Math.sin(angle) * (innerRadius + barLen));
            this.ctx.stroke();

        }

        this.ctx.globalAlpha = 1;

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