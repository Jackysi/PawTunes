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

import AudioMotionAnalyzer from 'audiomotion-analyzer';
import PawTunes from "../player/ts/pawtunes";

export default class PawTunesTpl {

    protected pawtunes: PawTunes;
    private resizeCounter: number = 0;
    private firstResize: Date = new Date();


    constructor(PawTunes: PawTunes) {

        this.pawtunes = PawTunes;
        this.pawtunes.on('ready', () => {

            this.pawtunes._('.preloader', (el: HTMLElement) => el.classList.add('hidden'))
            if (this.pawtunes.channels.length <= 1) {
                this.pawtunes._('.btn-channels-list', (el: HTMLElement) => el.classList.add('hidden'));
            }

        });

        // disable volume control
        this.pawtunes.isNoVolume = true;
        this.pawtunes.init();
        this.initSpectrum();
        this.bindEvents();
        this.bindPagination();
        this.fitPopupWindow();

    }


    bindPagination() {

        this.pawtunes._('[data-page]', (el: HTMLElement) => {

            el.addEventListener('click', (event) => {

                event.preventDefault();
                const page = el.getAttribute('data-page');
                if (page) {
                    this.pawtunes.pagination(page);
                }

            });
        });

    }


    bindEvents() {

        // Open the main tab
        this.pawtunes.on('channel.change', () => this.pawtunes.pagination('main'))

        // Facebook
        this.pawtunes._('.share-area .facebook-share', (el: HTMLElement) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                window.open(`https://www.facebook.com/sharer/sharer.php?u=${this.pawtunes.url}`, 'fb_share', 'width=800, height=400')
            })
        })

        // X
        this.pawtunes._('.share-area .x-share', (el: HTMLElement) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                let track = this.pawtunes.onAir.artist + ' - ' + this.pawtunes.onAir.title;
                window.open(`https://x.com/share?url=${this.pawtunes.url}&text=${encodeURIComponent(this.pawtunes.translate('twitter_share', 'TRACK', track))}`, 'x_share', 'width=800, height=800');
            })
        })

        this.pawtunes.on('track.change', (track: { artist: string; title: string }) => {

            const artist = this.pawtunes._('.onair .track-artist')[0];
            const title = this.pawtunes._('.onair .track-title')[0];

            // No element found? QUIT!
            if (!artist || !title)
                return;

            // Set contents
            artist.innerText = track.artist;
            title.innerText = track.title;

            // Bind artist
            if (track.artist.length >= this.pawtunes.settings.trackInfo.artistMaxLen) {
                this.marquee(artist)
            }

            // Bind title
            if (track.title.length >= this.pawtunes.settings.trackInfo.titleMaxLen) {
                this.marquee(title)
            }

        });

    }


    fitPopupWindow() {

        if (!this.pawtunes?.settings.tpl.autoWindowResize) {
            return;
        }

        let main = document.querySelector('.main-container') as HTMLElement;
        if (!main) return;

        // We bind this, so we can use it in the resize function
        let resizeFunction = function (this: PawTunesTpl) {

            this.resizeCounter++;
            window.resizeTo(
                main.offsetWidth + (window.outerWidth - window.innerWidth),
                main.offsetHeight + (window.outerHeight - window.innerHeight)
            );

            // Let's make sure we're not annoying the user!
            if (this.resizeCounter > 50 && (this.firstResize.getTime() - new Date().getTime()) < 5000) {
                if (confirm("Abnormal Window Resize triggered, would you want to stop automatic window resize function?")) {

                    window.removeEventListener('resize', resizeFunction);
                    this.pawtunes.settings.tpl.autoWindowResize = false;

                }

                this.resizeCounter = 0;

                // Reset resize if more than 50, but it has taken 5 seconds to get here
            } else if (this.resizeCounter > 50 && (this.firstResize.getTime() - new Date().getTime()) > 5000) {

                this.firstResize = new Date();
                this.resizeCounter = 0;

            }

        }.bind(this);

        window.addEventListener('resize', resizeFunction)
        document.addEventListener('DOMContentLoaded', resizeFunction)

    }


    marquee(el: HTMLElement) {

        // Clone the content to create a seamless loop
        const content = el.innerHTML;
        el.innerHTML = `<div class="marquee-content">${content}</div><div class="marquee-content">${content}</div>`;

        // Apply necessary styles
        el.style.display = 'flex';
        el.style.overflow = 'hidden';
        el.style.position = 'relative'; // Ensure the container is positioned relative

        // Get the content blocks
        const marqueeContents = el.querySelectorAll('.marquee-content') as NodeListOf<HTMLElement>;

        // Wait for the browser to render the elements
        requestAnimationFrame(() => {

            const contentWidth = marqueeContents[0].offsetWidth;
            const gap = 20; // Adjust the gap between the contents as needed

            // Set the width of the content blocks explicitly
            marqueeContents[0].style.width = `${contentWidth}px`;
            marqueeContents[1].style.width = `${contentWidth}px`;

            // Add padding-right to create space between the content blocks
            marqueeContents[0].style.paddingRight = `${gap}px`;
            marqueeContents[1].style.paddingRight = `${gap}px`;

            // Position the second content block after the first one plus the gap
            marqueeContents[1].style.left = `${contentWidth + gap}px`;

            // Set CSS variable for single content width plus gap
            const singleDistance = contentWidth + gap;
            el.style.setProperty('--single-distance', `${singleDistance}px`);

            // Set animation duration based on single content width plus gap
            const duration = singleDistance / 20; // Adjust the divisor to change speed

            // Add animation styles
            const animationCSS = `marquee ${duration}s linear infinite`;

            marqueeContents.forEach((content) => {
                content.style.animation = animationCSS;
            });

            // Pause animation on hover
            for (let marquee of marqueeContents) {
                marquee.addEventListener('mouseenter', () => {
                    for (let elm of marqueeContents) {
                        elm.style.animationPlayState = 'paused';
                    }
                })
                marquee.addEventListener('mouseleave', () => {
                    for (let elm of marqueeContents) {
                        elm.style.animationPlayState = 'running';
                    }
                })
            }

        });
    }


    /**
     * Create Audio Motion Analyzer
     */
    initSpectrum() {

        const element = document.getElementById('analyzer');
        if (!this.pawtunes.audio || !element) return;

        // Empty
        this.pawtunes._('#analyzer', (el: HTMLElement) => el.innerHTML = '')

        // set the crossOrigin property in the media element
        this.pawtunes.audio.crossOrigin = 'anonymous';

        // create the analyzer using the media element as a source
        const audioMotion = new AudioMotionAnalyzer(element, {
            source         : this.pawtunes.audio,
            frequencyScale : 'linear',
            fftSize        : 4096,
            mode           : 10,
            linearAmplitude: true,
            linearBoost    : 4,
            alphaBars      : true,
            showPeaks      : false,
            maxFreq        : 16000,
            minFreq        : 25,
            fillAlpha      : .75,
            channelLayout  : 'single',
            barSpace       : .4,
            smoothing      : .9,
            showScaleX     : false,
            showBgColor    : false,
            lineWidth      : 1,
            overlay        : true,
            //weightingFilter: 'A',
        });

        // Register a gradient
        let accent = window.getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
        if (this.pawtunes.settings.tpl.visualizationColor !== 'true' || accent === '') accent = '#fff';

        // Add our own gradient
        audioMotion.registerGradient('myGradient', {bgColor: '#011a35', colorStops: [accent]});
        audioMotion.setOptions({gradient: 'myGradient'});


    }

}