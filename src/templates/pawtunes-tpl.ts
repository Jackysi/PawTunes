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
    private currentPage                             = 'main';
    private statusTimeout: any;
    private audioMotion: AudioMotionAnalyzer | null = null;


    constructor( PawTunes: PawTunes ) {

        this.pawtunes = PawTunes;
        this.pawtunes.on( 'ready', () => this.pawtunes._( '.preloader', ( el: HTMLElement ) => el.classList.add( 'hidden' ) ) );

        // Initially happens in init method
        this.pawtunes.on( 'theme.change', () => {
            this.changeSpectrumColor();
        } )

        this.pawtunes.init();
        this.setup();

    }


    /**
     * Initialize functions and default variables
     */
    setup() {

        // Channel should be already set by now
        if ( this.pawtunes.channel ) {
            this.updateChannelName( this.pawtunes.channel );
        }

        // Maybe we already have On Air info?
        if ( this.pawtunes.onAir.artist && this.pawtunes.onAir.title ) {
            this.updateTrackInfo( this.pawtunes.onAir );
        }

        // Track should be already set by now
        if ( this.pawtunes.audio ) {
            this.initSpectrum();
        }

        // If history Disabled, delete button and page
        if ( !this.pawtunes.settings.history ) {

            this.pawtunes._( '.open-history', ( el: HTMLElement ) => el.remove() );
            this.pawtunes._( '.page.history', ( el: HTMLElement ) => el.remove() );

        }

        // Check if settings are disabled || one channel with one stream
        const isSingleChannel = this.pawtunes.channels.length <= 1;
        if ( this.pawtunes.settings.tpl.disableSettings ||
             ( isSingleChannel && Object.keys( this.pawtunes.channels[ 0 ].streams ).length <= 1 ) ) {

            this.disablePawSettings();

        }

        // Check if Artwork blur is disabled
        if ( this.pawtunes.settings.tpl.hideBlurredArtwork ) {

            this.pawtunes._( '.artwork-image .background-blur', ( el: HTMLElement ) => el.remove() );

        }

        /**
         * PawTunes Events
         */
        this.bindPawEvents();
        this.bindButtons();

        // Page initialization before channel change (it may disturb body calculation)
        this.switchPage( 'main' );
        window.addEventListener( 'resize', () => {
            this.switchPage( "", false );
        } )

    }


    /**
     * Initialize Various PawTunes events
     */
    bindPawEvents() {

        // Paw Events
        this.pawtunes.on( 'channel.change', ( channel ) => this.updateChannelName( channel ) )
        this.pawtunes.on( 'channel.change', () => this.switchPage( 'main' ) )
        this.pawtunes.on( 'track.change', ( track ) => this.updateTrackInfo( track ) )
        this.pawtunes.on( 'status.change', ( status ) => this.updateStatusInfo( status ) )

        /**
         * When Artwork is loaded, also replace background blur image
         */
        this.pawtunes._( '.artwork', ( el: HTMLElement ) => {
            el.addEventListener( 'load', () => {

                let src = el.getAttribute( 'src' );
                if ( src != null )
                    this.pawtunes._( '.background-blur img', ( el: HTMLElement ) => el.setAttribute( 'src', src ) );

            } )
        } );

        /**
         * Bind Artwork -> iTunes (if enabled)
         */
        if ( this.pawtunes.settings.tpl.songSearchEnable ) {

            this.pawtunes._( '.artwork-img', ( el: HTMLElement ) => {

                el.classList.add( 'cursor-pointer' );
                el.addEventListener( 'click', ( e ) => {
                    if ( this.pawtunes.onAir.artist && this.pawtunes.onAir.title ) {

                        e.preventDefault();
                        window.open( this.pawtunes.settings.tpl.songSearch.replace( '{query}', encodeURI( this.pawtunes.onAir.artist + ' - ' + this.pawtunes.onAir.title ) ) );

                    }
                } )

            } );

        }

    }


    /**
     * Just bind various events to buttons
     */
    bindButtons() {

        this.pawtunes._( '.open-history', ( el: HTMLElement ) => {
            el.addEventListener( 'click', ( e ) => {

                e.preventDefault();
                if ( this.currentPage !== 'history' ) {
                    this.switchPage( 'history' );
                    return false;
                }

                this.switchPage( 'main' );

            } )
        } );


        this.pawtunes._( '.open-settings', ( el: HTMLElement ) => {
            el.addEventListener( 'click', ( e ) => {

                e.preventDefault();
                if ( this.currentPage !== 'settings-page' ) {
                    this.switchPage( 'settings-page' );
                    return false;
                }

                this.switchPage( 'main' );

            } )
        } )

        this.pawtunes._( '.btn-back', ( el: HTMLElement ) => {
            el.addEventListener( 'click', ( e ) => {

                this.switchPage( 'main' );
                e.preventDefault();

            } )
        } );

    }


    /**
     * Update channel name
     *
     * @param channel
     */
    updateChannelName( channel: any ) {

        // If we're using channel logos, let's not show channel name
        if ( this.pawtunes.settings.tpl.useChannelLogos && this.pawtunes.settings.tpl.useChannelLogos === true ) {

            this.pawtunes._( '.onair .current-channel', ( el: HTMLElement ) => {
                el.innerHTML = '<div class="logo"><img alt="Logo" height="40" src="./assets/img/logo.svg"></div>'
            } );

            this.pawtunes.off( 'channel.change', this.updateChannelName );
            return false;

        }

        this.pawtunes._( '.onair .current-channel', ( el: HTMLElement ) => el.textContent = channel.name );

    }


    /**
     * Update track info
     *
     * @param track
     */
    updateTrackInfo( track: any ) {

        this.pawtunes._( '.onair .current-track', ( el: HTMLElement ) => el.textContent = `${track.artist} - ${track.title}` );

    }


    /**
     * Update status info
     *
     * @param status
     */
    updateStatusInfo( status: any ) {

        if ( !status || status === "" ) return;
        this.pawtunes._( '.player-message', ( el: HTMLElement ) => {

            el.classList.remove( 'hidden' );
            const getText = el.querySelector( '.text' );
            if ( getText ) getText.textContent = status;

            clearTimeout( this.statusTimeout );
            this.statusTimeout = setTimeout( () => {
                el.classList.add( 'hidden' );
            }, 2500 );

        } );

    }


    disablePawSettings() {

        this.pawtunes._( '.player .open-history', ( el: HTMLElement ) => el.style.marginRight = "0" );
        this.pawtunes._( '.open-settings', ( el: HTMLElement ) => el.remove() );

    }


    changeSpectrumColor() {

        if ( !this.audioMotion ) return;

        let accent  = "";
        let element = document.querySelector( '.pawtunes' );
        if ( element ) {
            accent = window.getComputedStyle( element ).getPropertyValue( '--accent-color' );
        }

        if ( accent === '' ) accent = '#fff';

        this.audioMotion.registerGradient( 'myGradient', { bgColor: '#011a35', colorStops: [ accent ] } );
        this.audioMotion.setOptions( { gradient: 'myGradient' } );
    }


    /**
     * Create Audio Motion Analyzer
     */
    initSpectrum() {

        const element = document.getElementById( 'analyzer' );
        if ( !this.pawtunes.audio || !element ) return;

        // Empty
        this.pawtunes._( '#analyzer', ( el: HTMLElement ) => el.innerHTML = '' )

        // set the crossOrigin property in the media element
        this.pawtunes.audio.crossOrigin = 'anonymous';

        // create the analyzer using the media element as a source
        this.audioMotion = new AudioMotionAnalyzer( element, {
            source: this.pawtunes.audio,
            //  mirror         : -1,
            frequencyScale : 'linear',
            fftSize        : 8192,
            mode           : 10,
            lineWidth      : 1,
            maxFreq        : 16000,
            linearAmplitude: true,
            linearBoost    : 2,
            minFreq        : 20,
            fillAlpha      : .4,
            barSpace       : .3,
            smoothing      : .92,
            reflexRatio    : .2,
            reflexAlpha    : .3,
            reflexBright   : 1,
            reflexFit      : true,
            showPeaks      : false,
            showScaleX     : false,
            showBgColor    : false,
            overlay        : true,
            channelLayout  : 'dual-combined'
            //weightingFilter: 'C',
        } );

        this.changeSpectrumColor();
    }


    /**
     * Similar to pagination from AIO, but this one has some differences
     * @param pageClass
     * @param animation
     */
    switchPage( pageClass: string = "", animation: boolean = true ) {

        // No page provided? Use current (resize events usually)
        if ( pageClass === "" )
            pageClass = this.currentPage;

        // Some vars
        let pages          = this.pawtunes._( '.main-container > .container .page' );
        let pageHeight     = pages[ 0 ].offsetHeight;
        let innerContainer = this.pawtunes._( '.main-container > .container .container-inner' );
        let totalPages     = pages.length;
        let pageNumber     = 0;

        // For Loop to find proper page
        for ( let i = 0; i < totalPages; i++ ) {
            if ( pages[ i ].classList.contains( pageClass ) ) {
                pageNumber = i;
                break;
            }
        }

        // Finally, move the element
        innerContainer[ 0 ].style.transform = 'translate3d(0, -' + ( pageHeight * pageNumber ) + 'px, 0)';
        this.currentPage                    = pageClass;

        // Now calculate the margin required to get to that page and move.
        if ( !animation ) {

            // Set transition to none
            innerContainer[ 0 ].style.transition = 'none';

            // After render delay add back animation
            setTimeout( function() {
                innerContainer[ 0 ].style.transition = '';
            }, 0 );

        }

    }

}