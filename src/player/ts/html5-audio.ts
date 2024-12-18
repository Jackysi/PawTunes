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

import { AudioStatus, Format, PawMediaSource } from './types';
import { PawTunesEvents } from "./pawtunes-events";

export default class HTML5Audio extends PawTunesEvents {

    autoplay: boolean              = false;
    volume: number                 = 50;
    muteVolume: number             = 0;
    ready: boolean                 = false;
    audio: HTMLAudioElement | null = null;
    media: PawMediaSource[]        = [];
    debug: boolean                 = false;

    /**
     * List of all HTML5 Audio events that can be listened to
     *
     * @var {string[]}
     */
    streamEvents: string[] = [
        "loadstart",
        "durationchange",
        "loadedmetadata",
        "loadeddata",
        "progress",
        "canplay",
        "canplaythrough",
        "play",
        "playing",
        "timeupdate",
        "seeking",
        "seeked",
        "ratechange",
        "volumechange",
        "pause",
        "ended",
        "waiting",
        "stalled",
        "suspend",
        "emptied",
        "abort",
        "error"
    ];

    /**
     * List of possible audio formats supported by this player
     *
     * @var {object}
     */
    formats: Record<string, Format> = {
        mp3  : {
            codec: 'audio/mpeg',
            media: 'audio'
        },
        m4a  : { // AAC / MP4
            codec: 'audio/mp4; codecs="mp4a.40.2"',
            media: 'audio'
        },
        m3u8a: { // AAC / MP4 / Apple HLS
            codec: 'application/vnd.apple.mpegurl; codecs="mp4a.40.2"',
            media: 'audio'
        },
        m3ua : { // M3U
            codec: 'audio/mpegurl',
            media: 'audio'
        },
        oga  : { // OGG
            codec: 'audio/ogg; codecs="vorbis, opus"',
            media: 'audio'
        },
        flac : { // FLAC
            codec: 'audio/x-flac',
            media: 'audio'
        },
        wav  : { // PCM
            codec: 'audio/wav; codecs="1"',
            media: 'audio'
        },
        webma: { // WEBM
            codec: 'audio/webm; codecs="vorbis"',
            media: 'audio'
        },
        fla  : { // FLV / F4A
            codec: 'audio/x-flv',
            media: 'audio'
        },
        rtmpa: { // RTMP AUDIO
            codec: 'audio/rtmp; codecs="rtmp"',
            media: 'audio'
        }
    }

    /**
     * Selector prefix
     *
     * @var {string}
     */
    selectorsPrefix: string = '.pawtunes';

    /**
     * List of all selectors required by the player
     *
     * @var {object}
     */
    selectors: Record<string, string> = {
        player: '.player',

        play: '.play',
        stop: '.stop',

        mute           : '.volume-icon .volume',
        unmute         : '.volume-icon .muted',
        volumeContainer: '.volume-control',
        volumeBar      : '.volume-slider .vol-progress',
        volumeValue    : '.volume-slider .vol-progress .vol-bar',
        volumeHandle   : '.volume-slider .vol-progress .vol-bar .circle-control',
    }

    /**
     * List of browsers that do not support volume control
     *
     * @var {object}
     */
    noVolumeControl: Record<string, RegExp> = {
        ipad         : /ipad/,
        iphone       : /iphone/,
        ipod         : /ipod/,
        android_pad  : /android(?!.*?mobile)/,
        android_phone: /android.*?mobile/,
        blackberry   : /blackberry/,
        windows_ce   : /windows ce/,
        iemobile     : /iemobile/,
        webos        : /webos/,
        playbook     : /playbook/
    }


    isAndroid: boolean  = this.matchBrowser( { mobile: /(android)/ } );
    isMobile: boolean   = this.matchBrowser( { mobile: /(mobile)/ } );
    isNoVolume: boolean = this.matchBrowser( this.noVolumeControl );

    /**
     * Creates a new instance of HTML5Audio.
     *
     * @param {string} [elm=""] - The CSS selector for the container element.
     */
    constructor( elm: string = "" ) {

        super();

        // Default if not specified
        if ( elm === "" )
            elm = this.selectorsPrefix;

        // Make sure we have an element
        const container = document.querySelector( elm ) as HTMLObjectElement;
        if ( !container ) {

            // Unable to continue
            throw new Error( 'HTML5 Audio container element not found: ' + elm );

        }

        // Selector is now the container
        this.selectorsPrefix = elm;

    }

    /**
     * Initializes the audio player by setting up the audio element and binding events.
     */
    setup() {

        // Always call first, just in case
        this.destroy();

        // Create Media element
        this.audio             = new Audio();
        this.audio.preload     = "none";
        this.audio.controls    = false;
        this.audio.crossOrigin = "anonymous";

        // Different for devices with no volume control
        if ( !this.isNoVolume ) {
            if ( this.volume >= 1 && this.volume <= 100 ) {

                this.audio.volume = this.volume / 100;

            } else {

                this.audio.muted  = true;
                this.audio.volume = 0;

            }
        }

        // No volume control, we set to 100%
        if ( this.isNoVolume ) {

            this.volume       = 100;
            this.audio.volume = 1;
            this.updateUI( 'disable-volume' );

        }

        // If no sources defined, we will not play anything
        if ( this.media.length >= 1 ) {
            this.setMedia( this.media )
        }

        this.updateUI( 'stopped' )
        this.updateUI( 'volumechange' )
        this.bindStreamEvents()

        // We are ready!
        this.trigger( 'init' )

    }

    /**
     * Sets the media sources for the audio player.
     *
     * @param {PawMediaSource[]} sources - An array of media source objects.
     */
    async setMedia( sources: PawMediaSource[] ): Promise<void> {

        // No audio object? uff... cannot add sources
        if ( !this.audio ) {
            throw new Error( 'No audio element found!' );
        }

        // If we're placing new media, clear old
        if ( this.media.length > 0 ) {
            this.media = [];
        }

        let playable: number = 0;

        // Check sources
        for ( const source of sources as PawMediaSource[] ) {
            if ( this.formats[ source.type ] ) {

                // Make sure we can play
                const canPlay = this.audio.canPlayType( this.formats[ source.type ].codec );
                if ( canPlay !== 'probably' && canPlay !== 'maybe' ) {
                    continue;
                }

                /*const sourceElement = document.createElement( 'source' );
                sourceElement.src = source.src;
                sourceElement.type = this.formats[ source.type ].codec;
                this.audio.appendChild( sourceElement );*/
                this.media.push( source );
                playable++;

            }
        }

        // Hack to append src
        if ( playable > 0 ) {
            this.audio.src = this.media[ 0 ].src;
        }

        if ( playable < 1 ) {
            this.ready = false;
            this.trigger( 'error', { code: 4, message: 'No playable sources found' } );
            return;
        }

        // We are ready!
        this.ready = true
        this.trigger( 'ready' )

    }

    /**
     * Binds HTML5 audio events to the internal event handler.
     */
    bindStreamEvents() {

        // Add event listeners
        this.streamEvents.forEach( event => {

            if ( !this.audio ) return;
            this.audio.addEventListener( event, () => {

                // When ERROR occurs, pass data
                if ( event === 'error' ) {
                    this.trigger( event, this.audio?.error );
                    return;
                }

                this.trigger( event );

            } );

        } );

    }

    /**
     * Retrieves the current status of the audio player.
     *
     * @returns {AudioStatus | null} - The current audio status or null if the audio element is not initialized.
     */
    status(): AudioStatus | null {

        if ( !this.audio ) {
            return null;
        }

        return {
            currentTime : this.audio.currentTime,
            duration    : this.audio.duration,
            paused      : this.audio.paused,
            ended       : this.audio.ended,
            playbackRate: this.audio.playbackRate,
            volume      : this.audio.volume,
            muted       : this.audio.muted,
            seeking     : this.audio.seeking,
            buffered    : this.audio.buffered,
            readyState  : this.audio.readyState,
            networkState: this.audio.networkState,
            loop        : this.audio.loop,
            preload     : this.audio.preload,
            autoplay    : this.audio.autoplay,
            src         : this.audio.currentSrc || this.audio.src,
            textTracks  : this.audio.textTracks,
            error       : this.audio.error,
        };

    }

    /**
     * Plays the audio.
     *
     * @returns {Promise<void>}
     */
    async play(): Promise<void> {

        try {

            // If media not set, try to set it
            if ( !this.ready && this.media.length >= 1 ) {

                this.updateUI( 'play' );
                await this.setMedia( Object.assign( [], this.media ) );
                this.ready = true;

                await this.play();

            }

            if ( this.audio && this.ready ) {
                await this.audio.play();
            }

        } catch ( e ) {

            this.trigger( 'error', { code: 4, message: e } );

        }

    }

    /**
     * Stops the audio playback and resets the current time.
     *
     * @returns {Promise<void>}
     */
    async stop(): Promise<void> {
        if ( this.audio && this.ready ) {

            this.trigger( 'stop' );
            this.audio.pause();
            this.audio.autoplay    = false;
            this.audio.currentTime = 0;

            // Special case for radio streams, we stop buffering/loading in the background
            this.clearMedia();
            if ( this.media.length >= 1 ) {
                await this.setMedia( Object.assign( [], this.media ) );
            }

        }
    }

    /**
     * Pauses the audio playback.
     */
    pause() {
        if ( this.audio ) {
            this.audio.pause();
        }
    }

    /**
     * Mutes the audio by setting the volume to zero.
     */
    mute() {
        if ( this.audio ) {
            this.muteVolume   = this.volume;
            this.audio.volume = 0;
        }
    }

    /**
     * Unmutes the audio by restoring the previous volume level.
     */
    unmute() {
        if ( this.audio ) {
            this.audio.volume = this.muteVolume / 100;
            this.muteVolume   = 0;
        }
    }

    /**
     * Checks if the user agent matches any of the specified device patterns.
     *
     * @param {Object} devices - An object with device names as keys and regular expressions as values.
     * @returns {boolean} - True if a match is found; otherwise, false.
     */
    matchBrowser( devices: { [ key: string ]: RegExp } ): boolean {

        const ua = navigator.userAgent.toLowerCase();
        return Object.values( devices ).some( ( regex ) => regex.test( ua ) );

    }

    /**
     * Clears the current media sources and stops the audio playback.
     */
    clearMedia() {

        if ( this.audio ) {

            this.audio.pause();
            this.audio.src = "";

            // Removes all child elements, ignored currently
            for ( const child of this.audio.children ) {
                if ( child.tagName === 'SOURCE' ) {
                    this.audio.removeChild( child );
                }
            }

        }

        this.ready = false;

    }

    /**
     * Destroys the audio player instance and releases resources.
     */
    destroy() {

        if ( this.audio ) {

            // IF HTML ELEMENT: this.container.removeChild( this.audio );
            this.audio.pause();
            this.audio.src         = "";
            this.audio.currentTime = 0;

        }

        this.audio = null;
        this.ready = false;

    }

    /**
     * Updates the user interface based on the specified event.
     *
     * @protected
     * @param {string} _event - The event name.
     */
    protected updateUI( _event: string ) {
        return;
    }

    /**
     * Triggers an internal event and handles associated UI updates.
     *
     * @protected
     * @param {string} event - The event name.
     * @param {*} [data=null] - Additional data associated with the event.
     */
    protected trigger( event: string, data: any = null ) {

        if ( this.debug ) {
            console.log( "Event logging:", event, data, this.status() );
        }

        // Trigger internal event
        switch ( event ) {

            case 'loadstart':
                if ( this.audio?.autoplay && event === 'loadstart' ) {
                    this.updateUI( 'play' );
                    this.updateUI( 'seeking' );
                }
                break;

            case 'play':
            case 'waiting':
                const status = this.status();
                if ( status && status.readyState < HTMLMediaElement.HAVE_CURRENT_DATA ) {
                    this.updateUI( 'seeking' );
                }

                this.updateUI( 'play' );
                break;

            case 'stalled':
            case 'seeking':
                this.updateUI( 'seeking' );
                break;

            case 'playing':
                this.updateUI( 'playing' );
                break;

            case 'abort':
            case 'pause':
            case 'ended':
            case 'stop':
                this.emit( 'stopped' );
                this.updateUI( 'stopped' );
                return;

            // Case when autoplay fails as we don't have any content preloaded yet
            case 'suspend':
                if ( this.status()?.readyState === 0 ) {
                    this.trigger( 'stop' );
                }
                break;

            case 'error':
                this.stop();
                if ( data.length < 1 ) {
                    return;
                }
                break;

            case 'volumechange':

                // UI should react based on Media Volume, not internal value
                if ( !this.audio ) return;
                this.volume = Math.round( this.audio.volume * 100 );

                // If volume is 0, mute else unmute
                this.audio.muted = this.volume === 0;
                this.updateUI( 'volumechange' );

                break;

            default:
                break;
        }

        // If data is undefined/empty, respond with status, useful for debugging/various info
        if ( !data ) {
            data = this.status();
        }

        // Trigger class event
        this.emit( event, data );

    }

}