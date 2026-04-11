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

        // Update visualizer accent color on channel change
        this.pawtunes.on('channel.change', () => this.changeSpectrumColor());

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

        // Skip if disabled
        if (this.pawtunes.settings.tpl?.spectrumVisualizer !== true) return;

        const element = document.getElementById('analyzer');
        if (!this.pawtunes.audio || !element) return;

        // Empty
        this.pawtunes._('#analyzer', (el: HTMLElement) => el.innerHTML = '')

        // set the crossOrigin property in the media element
        this.pawtunes.audio.crossOrigin = 'anonymous';

        // create the analyser using the media element as a source
        this.audioMotion = new AudioMotionAnalyzer(element, {
            source        : this.pawtunes.audio,
            radial        : true,
            radius        : 0.78,
            frequencyScale: 'bark',
            fftSize       : 8192,
            mode          : 1,
            minFreq       : 20,
            maxFreq       : 20000,
            minDecibels   : -80,
            maxDecibels   : -15,
            smoothing     : 0.9,
            fillAlpha     : 0.9,
            barSpace      : 0.25,
            roundBars     : true,
            showPeaks     : false,
            showScaleX    : false,
            showBgColor   : false,
            overlay       : true,
            lineWidth     : .1,
            roundBars     : true,
            alphaBars     : this.pawtunes.settings.tpl?.alphaBars ?? false
        });

        this.changeSpectrumColor();
    }


    changeSpectrumColor() {

        if (!this.audioMotion) return;

        let accent = "";
        let element = document.querySelector('.pawtunes');
        if (element) {
            accent = window.getComputedStyle(element).getPropertyValue('--accent-color');
        }

        if (accent === '') accent = '#6C5CE7';

        this.audioMotion.registerGradient('myGradient', {bgColor: 'transparent', colorStops: [accent]});
        this.audioMotion.setOptions({gradient: 'myGradient'});
    }

}