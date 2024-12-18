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

import { OnAir, PawMediaSource, Channel } from "./types";
import PawTunesWS from "./pawtunes-ws";
import HTML5Audio from "./html5-audio";
import Storage from "./storage";

export default class PawTunes extends HTML5Audio {

    /**
     * @var {object} onAir
     */
    onAir: OnAir = {
        artist : "",
        title  : "",
        artwork: null,
        time   : 0
    };

    /**
     * Mostly used for navigator.mediaSession
     *
     * @var {object} artworkTypes
     */
    artworkTypes = {
        'jpeg': 'image/jpeg',
        'jpg' : 'image/jpeg',
        'png' : 'image/png',
        'gif' : 'image/gif',
        'webp': 'image/webp',
        'svg' : 'image/svg+xml'
    };

    /**
     * @var {boolean} fatal
     */
    fatal: boolean = false;

    /**
     * Logs debug logs to the browser, useful when debugging
     *
     * @var {boolean} debug
     */
    debug = false;

    /**
     * Pagination function, state
     *
     * @var {string} currentPage
     */
    currentPage: string = 'main';

    /**
     * List of all channels
     *
     * @var {object} channels
     */
    channels: Channel[];

    /**
     * Currently picked channel, changes on channel change
     *
     * @var {object} channel
     */
    channel: Channel = { name: "", logo: null, skin: "", ws: {}, streams: {} };

    /**
     * @var {array} history
     */
    history: OnAir[] = [];

    /**
     * HTML row template for history
     *
     * @var {object} historyTemplate
     */
    historyTemplate: Element | undefined;

    /**
     * Player Prefix used for localStorage and cache
     *
     * @var {string} prefix
     */
    prefix: string = btoa( window.location.pathname ).replace( /=/g, '' ) + "_";

    /**
     * Current picked stream (can be one of multiple qualities)
     *
     * @var {object} stream
     */
    stream: string | null = null;

    /**
     * Player state, used in combination with event online/offline, useful for websockets
     *
     * @var {string} state
     */
    state = "online";

    /**
     * Storage class, generally localStorage
     *
     * @var {object} storage
     */
    storage?: Storage;

    /**
     * Temporary variables, like RAM cache, for application
     *
     * @var {object} temp
     */
    temp: any = [];

    /**
     * References to timers for global control, like a stop of track info interval
     *
     * @var {object} timers
     */
    timers: { [ key: string ]: any } = {};

    /**
     * URL of the player without channel hash
     *
     * @var {string} url
     */
    url = window.location.href.split( '#' )[ 0 ];

    /**
     * Websocket class, used as reference for open web socket session
     *
     * @var {object} ws
     */
    ws: PawTunesWS;

    /**
     * Player translations can be changed
     * Defined here as defaults.
     *
     * @var {object} language
     */
    language: { [ key: string ]: string } = {
        error               : "ERROR",
        error_create        : "Unable to find channels, please create one!",
        error_defined       : "NO CHANNELS DEFINED",
        error_invalid       : "Invalid Channel!",
        error_network       : "ERROR: Network error occurred!",
        error_playback      : "ERROR: Playback failed, loading stream failed!",
        error_stream        : "ERROR: The specified or selected stream does not exist!",
        history_added       : "Added",
        history_artist_title: "Artist/Title",
        history_hour_ago    : "hr ago",
        history_just_now    : "just now",
        history_min_ago     : "min ago",
        history_sec_ago     : "sec ago",
        loading_message     : "Loading, please wait...",
        history_no_history  : "No history available at this time.",
        share               : "Share",
        song_history        : "Song History",
        status_init         : "Loading {STREAM}...",
        status_muted        : "Player muted.",
        status_playing      : "Playing {STREAM}...",
        status_stopped      : "Player stopped.",
        status_volume       : "Volume: {LEVEL}",
        twitter_share       : "I am listening to {TRACK}!",
        ui_back             : "Back",
        ui_channels         : "Channels list",
        ui_history          : "Show Track History",
        ui_play             : "Start playing",
        ui_playlists        : "Listen in your favorite player",
        ui_settings         : "Select stream quality",
        ui_stop             : "Stop playing",
        ui_volume_circle    : "Drag to change volume"
    };

    /**
     * Default settings for the PawTunes player
     *
     * @var {object} settings
     */
    settings: { [ p: string ]: any } = {
        api          : '',
        analytics    : false,
        defaults     : {
            autoplay      : false,
            channel       : "",
            default_volume: 50,
        },
        dynamicTitle : true,
        history      : true,
        historyMaxLen: 20,
        refreshRate  : 10,
        template     : 'pawtunes',
        tpl          : {},
        title        : "PawTunes",
        trackInfo    : {
            artistMaxLen    : 24,
            titleMaxLen     : 28,
            lazyLoadArtworks: false,
            lazyLoadURL     : './index.php?artwork&artist={ARTIST}&title={TITLE}',
            default         : {
                artist : "Various Artists",
                title  : "Unknown Track",
                artwork: "./data/images/default.png",
            },
        },
        showTimer    : true,
    };


    /**
     * Class constructor. Requires container and settings for the PawTunes player
     *
     * @param container HTMLElement
     * @param settings any
     */
    constructor( container: string, settings: any ) {

        super( container );

        // Storage requires unified prefix
        this.prefix  = settings.prefix ?? this.prefix;
        this.storage = new Storage( this.prefix );

        // Some basic settings
        this.volume = ( settings.defaults.default_volume ) ? settings.defaults.default_volume : 50;
        if ( this.storage.get( 'last-volume' ) !== false ) {
            if ( this.storage.get( 'last-volume' ) >= 0 && this.storage.get( 'last-volume' ) <= 100 ) {

                this.volume = parseInt( this.storage.get( 'last-volume' ) );

            }
        }

        // Override internal settings
        this.settings = this.deepCloneLeftSide( this.settings, settings );

        // Template options are always dynamic and just passed through, so we accept anything
        this.settings.tpl = ( settings.tpl ) ? settings.tpl : this.settings.tpl;

        // Options
        this.autoplay = this.settings.defaults.autoplay;
        this.language = Object.assign( {}, this.language, settings.language ?? {} );
        this.channels = settings.channels ?? [];

        // Debug?
        this.debug = settings.debug ?? false;

        // Inject web socket "plugin"
        this.ws = new PawTunesWS( this );

        // Make sure the settings are OK
        if ( this.channels.length < 1 ) {

            this.renderFatalError( this.translate( 'error_create' ) );

        }

        // No streams in channel defined
        if ( this.channels.length >= 1 ) {

            let foundStream: boolean = false;
            for ( let i: number = 0; i < this.channels.length; i++ ) {

                if ( Object.keys( this.channels[ i ].streams ).length > 0 ) {
                    foundStream = true;
                    break;
                }
            }

            if ( !foundStream ) {
                this.renderFatalError( this.translate( 'error_stream' ) );
            }

        }

    }


    /**
     * Start the player.
     * This method is needed for various templates and other event bindings on the player from outside class
     */
    public async init(): Promise<void> {

        if ( this.fatal ) {
            return;
        }

        // HTML Audio
        this.setup();
        this.bindPlayerControls();
        this.bindPlayerEvents();
        this.bindEvents();

        // History
        this.setupHistory();

        // Media Source, also listen to the hash change
        let hashChannel = decodeURIComponent( window.location.href.split( "#" )[ 1 ] || '' );
        window.addEventListener( 'hashchange', () => {

            hashChannel = decodeURIComponent( window.location.href.split( "#" )[ 1 ] || '' );
            this.setChannel( hashChannel );
            this.setStream();
            this.play();

        } )

        // Channel, if hash detected use that as priority
        this.setChannel( hashChannel );
        this.setStream();

        // DOM
        this.generateChannelsDOM();
        this.generateStreamsDOM();
        this.dropdownMenus();

        // Analytics
        this.createGoogleAnalytics();
    }

    /**
     * Generate HTML for current playing song timer (resets on stop/play)
     */
    public onAirTimer(): void {

        // Check if showing time is enabled, otherwise exit
        if ( !this.settings.showTimer ) {
            return;
        }

        // Exit if "start" time is empty
        if ( this.onAir.time <= 0 ) {
            return;
        }

        // Set var for easier management
        let ctime = ( ( new Date().getTime() - this.onAir.time ) / 1000 );

        // Divide, etc. to show time with format
        let hour = Math.floor( ( ctime / 3600 ) % 60 );
        let min  = Math.floor( ( ctime / 60 ) % 60 );
        let sec  = Math.floor( ctime % 60 );
        let timer;

        // Display only active timer (1 h 2 min 3 sec)
        if ( hour >= 1 ) { // hour:min:sec

            timer = `${hour < 10 ? '0' : ''}${hour}:${min < 10 ? '0' : ''}${min}:${sec < 10 ? '0' : ''}${sec}`;

        } else { // min:sec

            timer = `${min < 10 ? '0' : ''}${min}:${sec < 10 ? '0' : ''}${sec}`;

        }

        this.writeText( '.onair .time', timer );

    }

    /**
     * Starts interval to request track info from API or sets up websocket
     * If overridden, this function can use anything to request track info
     *
     * @return {void}
     */
    public async trackInfoInit(): Promise<void> {

        // API Interval? Stop it.
        if ( this.timers.trackInfo ) {
            clearInterval( this.timers.trackInfo );
        }

        // Already connected to a web socket? Close it.
        if ( this.ws.isWebSocketActive() ) {
            await this.ws.close();
        }

        // Invalid channel config?
        if ( !this.channel || !this.channel.name ) {
            return;
        }

        // Web Sockets - Azuracast
        if ( this.channel.ws && this.channel.ws.url ) {

            this.ws.connectToSocket( this.channel.ws.url, this.channel.ws.station );
            return;

        }

        // Every other method
        let pawTunesAPI = () => {

            fetch( `${this.settings.api}?channel=${this.channel.name}` )
                .then( response => {

                    if ( !response.ok ) {
                        throw new Error( 'Network response was not ok' );
                    }

                    return response.json();

                } )
                .then( data => {

                    this.handleOnAirResponse( data );

                } )
                .catch( error => {

                    console.error( 'There was a problem with the fetch operation:', error );

                } );

        }

        pawTunesAPI();
        this.timers.trackInfo = setInterval( pawTunesAPI, this.settings.refreshRate * 1000 )

    }

    /**
     * Handles track information, works with API calls, web sockets or anything else.
     * Updates player with new data if it has changed since last check.
     *
     * @param data
     */
    public handleOnAirResponse( data: any ): void {

        if ( !data.artist || !data.title ) {
            console.error( 'Invalid data or no data received from the server.' );
            return;
        }

        // Nothing has changed since last call/check
        if ( data.artist == this.onAir.artist && data.title == this.onAir.title && data.artwork == this.onAir.artwork ) {
            return;
        }

        this.writeHTML(
            '.onair .artist',
            `<span class="pointer css-hint" data-title="${data.artist}">${this.shorten( data.artist, this.settings.trackInfo.artistMaxLen )}</span>`
        );

        this.writeHTML(
            '.onair .title',
            `<span class="pointer css-hint" data-title="${data.title}">${this.shorten( data.title, this.settings.trackInfo.titleMaxLen )}</span>`
        );

        // If enabled, we will also update window title on each song change
        if ( this.settings.dynamicTitle ) {

            if ( this.temp.title == null ) this.temp.title = document.title;
            document.title = `${data.artist} - ${data.title} | ${this.temp.title}`;

        }

        // Set ON AIR
        this.onAir = {
            artist : data.artist,
            title  : data.title,
            artwork: data.artwork ?? null,
            time   : new Date().getTime()
        };

        this.loadArtwork( data.artwork );
        this.handleMetaChange();
        this.emit( 'track.change', this.onAir );

        // History - full history from API
        if ( data.history && data.history.length >= 1 ) {

            // Reverse sort when adding
            data.history.sort( ( a: { time: number }, b: { time: number } ) => {
                    return a.time - b.time;
                }
            );

            // Loop through and add to history
            this.history = [];
            for ( const history of data.history ) {
                this.addHistoryEntry( history );
            }

            // UI Update with a new history list
            this.updateHistoryDOM();

        } else {

            // Basic history - track onair songs
            this.addHistoryEntry( this.onAir )
            this.updateHistoryDOM();

        }

    }

    /**
     * Load artwork
     *
     * @param URL string|null
     */
    public loadArtwork( URL: string | null ): void {

        if ( !URL && !this.settings.trackInfo.default.artwork ) {
            this.hideLoading();
            return;
        }

        this._( '.artwork', ( el: HTMLElement ) => {

            this.showLoading();
            el.setAttribute( 'src', URL ?? this.settings.trackInfo.default.artwork );

            el.addEventListener( 'load', () => {
                this.hideLoading();
            } )

            el.addEventListener( 'error', () => {
                if ( el.getAttribute( 'src' ) == this.settings.trackInfo.default.artwork ) return;
                el.setAttribute( 'src', this.settings.trackInfo.default.artwork );
            } )

        } );

    }

    /**
     * Show Artwork Preloader
     */
    public showLoading() {

        let preloader = this.getElm( `.artwork-preloader` );
        if ( preloader ) preloader.classList.remove( 'hidden' );

    }

    /**
     * Hide Artwork Preloader
     */
    public hideLoading() {

        let preloader = this.getElm( `.artwork-preloader` );
        if ( preloader ) preloader.classList.add( 'hidden' );

    }

    /**
     * Generate the artwork URL from setting
     */
    public pawArtworkURL( artist: string, title: string ): string {

        return this.settings.trackInfo.lazyLoadURL
            .replace( '{ARTIST}', artist )
            .replace( '{TITLE}', title );

    }

    /**
     * Convenience method to select DOM elements in the container.
     * @TODO Use caching for selectors so we don't need to query every time
     *
     * @param selector standard CSS selector
     * @param fn callback function to run on each element
     * @param elm parent element to search in, defaults to document
     * @param usePrefix whether to prefix the selector with selectorsPrefix
     *
     * @returns array of matching elements
     */
    public _( selector: string, fn: Function | null = () => { }, elm: HTMLElement | Document = document, usePrefix: boolean = true ) {

        // Shortcut for custom selectors
        if ( selector in this.selectors ) {
            selector = this.selectors[ selector ];
        }

        if ( usePrefix ) {
            selector = `${this.selectorsPrefix} ${selector}`;
        }

        const elements = ( elm || document ).querySelectorAll( selector );

        let list: HTMLElement[] = [];
        for ( const element of elements ) {
            if ( fn ) fn( element );
            list.push( <HTMLElement>element );
        }

        return list;

    }

    /**
     * Find a specific object in an array using a key
     *
     * @param key - Key to search
     * @param string
     * @param replacement
     * @returns {any} - Found object or null
     */
    translate( key: string, string?: string, replacement?: string ): string {

        if ( !string || !replacement ) return this.language[ key ] ?? "";
        return this.language[ key ].replace( `{${string}}`, replacement );

    }

    /**
     * Handles pagination between views. You can also pass resizeEvent binding true/false
     */
    pagination( page: string = "", animation: boolean = true ) {

        let pages = this._( '.main-container .view' );
        if ( pages.length < 1 ) {
            document.removeEventListener( 'resize', this.temp.resizePaginationEvent );
            return;
        }

        if ( page === "" ) page = this.currentPage;
        let pageWidth  = pages[ 0 ].offsetWidth;
        let totalPages = pages.length;
        let pageNumber = 0;

        // For Loop to find proper page
        for ( let i = 0; i < totalPages; i++ ) {
            if ( pages[ i ].classList.contains( page ) ) {
                this.currentPage = page;
                pageNumber       = i;
                break;
            }
        }

        // Now calculate the margin required to get to that page and move.
        if ( !animation ) {

            // Set transition to none
            pages[ 0 ].style.transition = "none";

            // After JS render/event-loop add back animation
            setTimeout( () => { pages[ 0 ].style.transition = ''; }, 0 );

        }

        // Finally, move the element
        pages[ 0 ].style.marginLeft = '-' + ( ( pageNumber !== 0 ) ? pageWidth * pageNumber : 0 ) + 'px';

    }

    /**
     * Bind various window and document events
     */
    protected async bindEvents(): Promise<void> {

        // We need a reference, so we can remove it if we need to.
        this.temp.resizePaginationEvent = () => this.pagination( "", false )
        window.addEventListener( 'resize', this.temp.resizePaginationEvent );

        // Network status change, when online start playback and reconnect to websocket
        window.addEventListener( 'online', () => {

            this.state = 'online'
            this.emit( 'status', 'online' );

            this.trackInfoInit();
            if ( this.temp.lastState?.paused === false ) {
                this.play();
            }

            console.log( "We are online..." )

        } );

        // Network status change, when offline stop playback
        window.addEventListener( 'offline', () => {

            this.state = 'offline'
            this.emit( 'status', 'offline' );
            this.temp.lastState = this.status();
            console.warn( "We are offline!" );

        } );

        // Unload event before leaving the website
        window.addEventListener( 'beforeunload', () => {
            if ( this.ws && this.ws.isWebSocketActive() ) {
                this.ws.close();
            }
        } )

    }

    /**
     * Bind various player events, PawTunes specific
     */
    protected bindPlayerEvents(): void {

        // PLAY: This is called when "play" button is clicked
        this.on( 'play', () => {

            if ( 'mediaSession' in navigator ) {
                navigator.mediaSession.playbackState = 'playing';
            }

            this.toast( this.translate( 'status_init', 'STREAM', this.channel.name ), true );

            if ( this.settings.showTimer ) {
                this.onAir.time = new Date().getTime();
                this.onAirTimer();
            }

        } );

        // PLAYING: This is called when media is playing
        this.on( 'playing', () => {

            this.handleMetaChange();
            if ( 'mediaSession' in navigator ) {
                navigator.mediaSession.playbackState = 'playing';
            }

            this.toast( this.translate( 'status_playing', 'STREAM', this.channel.name ), true );

        } )

        // STOPPED: Custom PawTunes event when player is stopped
        this.on( 'stopped', () => {

            this.toast( this.translate( 'status_stopped' ), false );

            if ( 'mediaSession' in navigator ) {
                navigator.mediaSession.playbackState = 'paused';
            }

            if ( this.settings.showTimer ) {
                this.onAir.time = new Date().getTime();
                this.writeText( '.onair .time', '00:00' );
            }

        } )

        // ERROR: An error occurred
        this.on( 'error', ( err ) => {

            // Ignore autoplay errors
            if ( err.code && err.code === 4 && ( this.status()?.networkState ?? 0 ) >= HTMLMediaElement.HAVE_CURRENT_DATA ) {
                return;
            }

            this.toast( this.translate( 'error_network' ), true );

            // If we don't have anything to play
            if ( this.status()?.readyState === HTMLMediaElement.HAVE_NOTHING ) {
                this.toast( this.translate( 'error_playback' ), true )
            }

        } );

        // VOLUMECHANGE: Simple volume change binding, replaces default with a storage option
        this.on( 'volumechange', () => {

            // Change main volume icons
            if ( this.volume <= 1 ) {

                this.toast( this.translate( 'status_muted' ) );

            } else {

                this.toast( this.translate( 'status_volume', 'LEVEL', `${this.volume}%` ) );

            }

            this.storage!.set( 'last-volume', this.volume );

        } );

        this.on( 'timeupdate', () => {

            if ( this.settings.showTimer ) {
                this.onAirTimer();
            }

        } )

        // Set custom events for media navigation (though not the best solution)
        if ( 'mediaSession' in navigator ) {

            navigator.mediaSession.setActionHandler( 'play', () => this.play() );
            navigator.mediaSession.setActionHandler( 'pause', () => {

                if ( 'mediaSession' in navigator ) {
                    navigator.mediaSession.playbackState = 'paused';
                }

                this.stop();
                this.toast( this.translate( 'status_stopped' ), true );

            } );

        }

    }

    /**
     * Generate the channels in a dropdown menu
     */
    protected generateChannelsDOM(): void {

        if ( this.channels.length < 1 ) {

            alert( this.translate( 'error_defined' ) );
            this.toast( this.translate( 'error_defined' ) );
            return;

        }

        let channelsContainer = this.getElm( ' .channels' ) as HTMLElement
        let channelsList      = this.getElm( '.channel-list', channelsContainer ) as HTMLElement
        if ( !channelsContainer || !channelsList ) {
            return;
        }

        channelsList.innerHTML = '';

        if ( this.channels.length <= 1 ) {

            channelsContainer.classList.add( 'hidden' );
            return;

        }

        for ( let channel of this.channels ) {

            let li = document.createElement( 'li' );
            li.classList.add( 'channel' );
            li.innerHTML = `<a href="#" data-channel="${channel.name}">${channel.name}</a>`;
            li.addEventListener( 'click', async( event ) => {

                event.preventDefault();

                this._( '.channel', ( elm: HTMLElement ) => elm.classList.remove( 'active' ), channelsList );

                li.classList.add( 'active' );
                channelsContainer.classList.remove( 'active' );

                this.setChannel( channel.name )
                this.setStream();
                this.generateStreamsDOM();
                await this.play();

            } )

            if ( this.channel && this.channel.name === channel.name ) {
                li.classList.add( 'active' );
            }

            channelsList.appendChild( li );

        }

        channelsContainer.classList.remove( 'hidden' );

    }

    /**
     * Generate DOM elements for the stream dropdown
     */
    protected generateStreamsDOM() {

        let streamsContainer = this.getElm( ' .settings' ) as HTMLElement
        let streamsList      = this.getElm( '.streams-list', streamsContainer ) as HTMLElement
        if ( !streamsContainer || !streamsList ) {
            return;
        }

        streamsList.innerHTML = '';

        // When dealing with a single channel, we don't need to show streams
        if ( !this.channel || typeof this.channel.streams !== 'object' || Object.keys( this.channel.streams ).length <= 1 ) {

            streamsContainer.classList.add( 'hidden' );
            return null;

        }

        for ( let stream of Object.keys( this.channel.streams ) ) {

            let li = document.createElement( 'li' );
            li.classList.add( 'stream' );
            li.innerHTML = `<a data-stream="${stream}" href="#">${stream}</a>`;
            li.addEventListener( 'click', async( event ) => {

                event.preventDefault();

                this._( '.stream', ( el: HTMLElement ) => el.classList.remove( 'active' ), streamsList );

                li.classList.add( 'active' );
                streamsContainer.classList.remove( 'active' );

                this.setStream( stream );
                await this.play();

            } )

            if ( this.stream && this.stream === stream ) {
                li.classList.add( 'active' );
            }

            streamsList.appendChild( li );

        }

        streamsContainer.classList.remove( 'hidden' );

    }

    /**
     * Sets up the history functionality for the player.
     * This method checks if the history setting is enabled and initializes
     * the history template for displaying past streams or tracks.
     */
    protected setupHistory() {

        if ( !this.settings.history ) {
            return;
        }

        // Save History HTML template
        const historyTpl = this._( '.history-list-container .history-item' )
        if ( historyTpl.length >= 1 ) {
            this.historyTemplate = historyTpl[ 0 ];
            this.historyTemplate.classList.remove( 'hidden' )
        }

        this._( '.history-toggle', ( el: HTMLElement ) => {

            el.classList.remove( 'hidden' );
            const historyBtn = this.getElm( 'a', el ) as HTMLElement;
            let historyState = false;

            historyBtn.addEventListener( 'click', ( e ) => {

                e.preventDefault();
                if ( !historyState ) {

                    historyState = true;
                    el.classList.add( 'active' );
                    this.pagination( 'history' );
                    return;

                }

                historyState = false;
                el.classList.remove( 'active' );
                this.pagination( 'main' );


            } );

        } )

        // Update time-ago every 5 seconds
        this.timers.historyTimeAgo = setInterval( () => this.historyTimeTrack(), 1000 );

    }

    /**
     * Update History DOM
     */
    protected updateHistoryDOM(): void {

        const historyContainer = this.getElm( ' .history-content .history-list-container' );
        const unavailable      = this.getElm( ' .history-content .history-unavailable' )
        const historyList      = this.getElm( ' .history-content .history-list' )

        if ( historyContainer )
            historyContainer.innerHTML = "";

        if ( historyContainer && this.historyTemplate && this.history.length >= 1 ) {
            for ( const history of this.history ) {

                let at = new Date( history.time );

                let item = this.historyTemplate.cloneNode( true ) as HTMLElement;
                item.setAttribute( 'data-title', at.getHours() + ':' + ( ( at.getMinutes() <= 9 ) ? '0' + at.getMinutes() : at.getMinutes() ) )
                item.setAttribute( 'data-unix', String( at.getTime() ) );

                this.writeText( '.history-track', `${history.artist} - ${history.title}`, item );
                this.writeText( '.history-time-ago', this.ago( history.time ), item );

                let artwork = ( !history.artwork ) ? this.settings.trackInfo.default.artwork : history.artwork;
                this.writeHTML( '.history-artwork', '<div class="artwork-preloader"></div>', item );

                let img    = new Image();
                img.src    = artwork;
                img.onload = () => this.writeHTML( '.history-artwork', `<img src="${artwork}" alt="image" class="history-artwork-image">`, item );

                // On ERROR, we will revert to default
                img.onerror = () => {
                    if ( img.src === this.settings.trackInfo.default.artwork ) return;
                    this.writeHTML( '.history-artwork', `<img src="${this.settings.trackInfo.default.artwork}" alt="image" class="history-artwork-image">`, item );
                }

                historyContainer.append( item );

            }

            unavailable?.classList.add( 'hidden' );
            historyList?.classList.remove( 'hidden' );

        } else {

            unavailable?.classList.remove( 'hidden' );
            historyList?.classList.add( 'hidden' );

        }

        this.emit( 'history.change', { history: this.history } );

    }

    /**
     * Populate a history list with parsed data from server.
     *
     * @returns {void}
     */
    protected historyTimeTrack(): void {

        let historyItems = this._( ' .history-content .history-list-container .history-item' );
        if ( historyItems.length > 0 ) {

            // Update whole table
            historyItems.forEach( ( element: Element ) => {

                const elm   = element as HTMLElement;
                let timeElm = this.getElm( '.history-time-ago', elm );
                if ( timeElm && elm.dataset.unix ) {
                    timeElm.textContent = this.ago( parseInt( elm.dataset.unix ) );
                }

            } );

        }

    }

    /**
     * Adds a new entry to the history.
     * Removes the oldest entry if the history exceeds the maximum length.
     *
     * @param entry - The current track information to be added to history.
     */
    protected addHistoryEntry( entry: OnAir ) {


        // Delete the oldest record if the total exceeds 20
        if ( this.history.length >= this.settings.historyMaxLen ) {
            this.history.pop();
        }

        this.history.unshift( entry );

    }

    /**
     * Set channel
     *
     * @param specific string
     */
    protected setChannel( specific: string = '' ) {

        this.showLoading();
        this.findChannel( specific );
        this.storage!.set( 'last-channel', this.channel.name );
        this.emit( 'channel.change', this.channel );
        this.channelDOMChange( this.channel.skin );

        this.history = [];
        this.updateHistoryDOM();
        this.trackInfoInit();

    }

    /**
     * Create a script element and load Google Analytics with specified tag ID
     */
    protected createGoogleAnalytics() {

        if ( !this.settings.analytics || typeof this.settings.analytics !== 'string' )
            return false;

        //console.log("Creating Google Analytics with ID: " + s.analytics );

        // @ts-ignore
        window.dataLayer = window.dataLayer || [];

        let script    = document.createElement( 'script' );
        script.src    = 'https://www.googletagmanager.com/gtag/js?id=' + this.settings.analytics;
        script.async  = true;
        script.onload = function() {

            function gtag() {
                // @ts-ignore
                window.dataLayer.push( arguments );
            }

            // @ts-ignore
            gtag( 'js', new Date() );
            // @ts-ignore
            gtag( 'config', s.analytics );

        }

        window.document.body.appendChild( script );

    }

    /**
     * Send a temporary Toast to the player, useful for various playback states and issues
     */
    protected toast( text: string | null = null, permanent: boolean = false ): string | null {

        // Emit no matter what!
        this.emit( 'status.change', text );

        // Now select an element if it doesn't exist quit
        let status = this._( '.player-status' )[ 0 ] as HTMLElement;
        if ( status == null ) return null;

        if ( !text ) return status.textContent;

        if ( permanent ) {
            this.temp.notification = text;
        }

        if ( this.timers.notify ) {
            clearTimeout( this.timers.notify );
        }

        status.textContent = text ?? '';

        if ( !permanent ) {

            status.classList.remove( 'text-animate' );
            this.timers.notify = setTimeout( () => {

                this.emit( 'status.change', this.temp.notification );
                status.textContent = this.temp.notification ?? '';
                status.classList.add( 'text-animate' );

            }, 2000 );
        }

        return null;

    }

    /**
     * Shorten text
     */
    protected shorten( text: string = "", length: number = 0 ) {

        // Skip if max length defined zero
        if ( text === "" || length === 0 ) return text;

        // Do the magic
        let $length = length || 10;
        if ( text.length > $length ) {
            text = text.substring( 0, $length ) + '&hellip;';
        }

        return text;

    }

    /**
     * Converts a timestamp to "x minutes/hours ago" string
     *
     * @param {number} timestamp - Timestamp in milliseconds
     *
     * @returns {string} Human readable string
     */
    protected ago( timestamp: number ): string {

        let seconds = Math.floor( ( ( new Date().getTime() + new Date().getTimezoneOffset() ) - timestamp ) / 1000 );

        // Hours
        if ( Math.floor( seconds / 3600 ) >= 1 )
            return `${Math.floor( seconds / 3600 )} ${this.translate( 'history_hour_ago' )}`;

        // Minutes
        if ( Math.floor( seconds / 60 ) >= 1 )
            return `${Math.floor( seconds / 60 )} ${this.translate( 'history_min_ago' )}`;

        // Seconds
        if ( seconds === 0 )
            return this.translate( 'history_just_now' );

        return `${Math.floor( seconds )} ${this.translate( 'history_sec_ago' )}`;

    }

    /**
     * Controls Browser or Bluetooth device screen
     */
    protected handleMetaChange(): void {

        if ( 'mediaSession' in navigator ) {

            let images: { src: string; sizes: string; type: any; }[] = [];
            if ( this.onAir.artwork ) {

                let url: string = this.onAir.artwork;
                let ext: string = this.onAir.artwork.split( /[#?]/ )[ 0 ]?.split( '.' )?.pop()?.trim() ?? "";
                let imageMime   = '';

                const sizes = [ '96x96', '128x128', '192x192', '256x256', '384x384', '320x180', '512x512' ];

                // Find mimetype
                for ( const [ index, mimetype ] of Object.entries( this.artworkTypes ) ) {
                    if ( index === ext ) {
                        imageMime = mimetype;
                        break;
                    }
                }

                // Define images
                for ( let i = 0; i < sizes.length; i++ ) {
                    images.push( {
                        src  : url,
                        sizes: sizes[ i ],
                        type : ( imageMime !== '' && imageMime !== '/' ) ? imageMime : null
                    } )
                }

            }

            navigator.mediaSession.metadata = new window.MediaMetadata(
                {
                    artist : this.onAir.artist,
                    title  : this.onAir.title,
                    album  : '',
                    artwork: images
                }
            );

        }

    }

    /**
     * Deep clone object keeping only left keys
     *
     * @param obj1
     * @param obj2
     */
    protected deepCloneLeftSide( obj1: any, obj2: any ) {

        const result: { [ key: string ]: any } = {};
        for ( const key in obj1 ) {

            if ( typeof obj1[ key ] === 'object' && obj1[ key ] !== undefined ) {

                if ( key in obj2 ) {
                    result[ key ] = this.deepCloneLeftSide( obj1[ key ], obj2[ key ] );
                    continue;
                }

                result[ key ] = this.deepCloneLeftSide( obj1[ key ], {} );
                continue;

            }

            // Not object, just value
            result[ key ] = ( typeof obj2[ key ] === 'undefined' ) ? obj1[ key ] : obj2[ key ];

        }

        return result;

    }

    /**
     * Write HTML is a simple method that checks if an element exists prior attempting to write
     *
     * @param element
     * @param content
     * @param dom
     */
    protected writeHTML( element: string, content: string, dom: HTMLElement | Document = document ) {

        this._( element, ( el: HTMLElement ) => {
            el.innerHTML = content;
        }, dom, false );

    }

    /**
     * Write Text is a simple method that checks if an element exists prior attempting to write
     *
     * @param element
     * @param content
     * @param dom
     */
    protected writeText( element: string, content: string, dom: HTMLElement | Document = document ) {

        this._( element, ( el: HTMLElement ) => {
            el.textContent = content;
        }, dom, false );

    }

    /**
     * Return a single element from the DOM
     *
     * @param element
     * @param dom
     * @param usePrefix
     */
    protected getElm( element: string, dom: HTMLElement | Document = document, usePrefix: boolean = true ) {

        return this._( element, null, dom, usePrefix )[ 0 ];

    }

    /**
     * Sets the stream of the current channel
     *
     * @param specific - Optional specific stream to set
     */
    protected setStream( specific: string = '' ) {

        if ( !this.channel.streams ) {
            return;
        }

        // Make sure stream exists
        if ( specific === '' || !this.channel.streams[ specific ] ) {

            // Maybe storage?
            const streamStorage = this.storage!.get( `last-stream-${this.channel.name}` );
            if ( streamStorage && this.channel.streams[ streamStorage ] ) {

                specific = streamStorage;

            } else { // Use first

                specific = Object.keys( this.channel.streams )[ 0 ];

            }

        }

        if ( this.channel.streams[ specific ] ) {

            this.stream = specific;
            this.streamToMedia();
            this.storage!.set( `last-stream-${this.channel.name}`, specific );
            this.emit( 'stream.change', specific );
            return;

        }

        // @todo ERROR?
        this.toast( this.translate( 'error_stream' ) )

    }

    /**
     * Render a fatal error message on the UI
     *
     * @param message - The error message to display
     */
    protected renderFatalError( message: string ) {

        this.fatal = true;
        this._( '#no-js-hide', ( el: HTMLElement ) => {
            el.innerHTML = `<span style="color:red; font-weight: 500; font-size: 16px;">ERROR: ${message}</span>`;
        } )

    }

    /**
     * Bind all player controls (play, stop, mute, unmute, volume slider)
     */
    protected bindPlayerControls() {

        // Play
        this._( 'play', ( elm: HTMLElement ) => elm.addEventListener( 'click', () => this.play() ) );
        this._( 'stop', ( elm: HTMLElement ) => elm.addEventListener( 'click', () => this.stop() ) );

        // Mute/Unmute
        this._( 'mute', ( elm: HTMLElement ) => elm.addEventListener( 'click', () => this.mute() ) );
        this._( 'unmute', ( elm: HTMLElement ) => elm.addEventListener( 'click', () => this.unmute() ) );

        // Volume
        this.handleVolumeUI();

    }

    /**
     * Bind all player controls (play, stop, mute, unmute, volume slider)
     * @protected
     */
    protected handleVolumeUI() {

        // If any of the elements are missing, do nothing
        let isDragging     = false;
        const updateVolume = ( event: MouseEvent | TouchEvent ) => {

            event.preventDefault();

            const elm = this._( 'volumeBar' )[ 0 ];
            if ( !this.audio || !elm ) {
                return;
            }

            let clientX;
            const rect = elm.getBoundingClientRect();
            if ( "touches" in event && event.touches && event.touches.length > 0 ) {

                clientX = event.touches[ 0 ].clientX;

            } else if ( "clientX" in event && event.clientX !== undefined ) {

                clientX = event.clientX;

            } else {

                return;

            }

            let volume        = ( clientX - rect.left ) / rect.width * 100;
            volume            = Math.max( 0, Math.min( 100, volume ) );
            this.audio.volume = volume / 100;

        }

        const startDrag = ( event: MouseEvent | TouchEvent ) => {
            isDragging = true;
            updateVolume( event );
        }

        const onDrag = ( event: MouseEvent | TouchEvent ) => {
            if ( !isDragging ) return;
            updateVolume( event );
        }

        const endDrag = ( event: MouseEvent | TouchEvent ) => {
            if ( !isDragging ) return;
            isDragging = false;
            updateVolume( event );
        }

        // Mousewheel
        const onWheelChange = ( event: WheelEvent ) => {

            event.preventDefault();
            if ( event.deltaY < 0 ) {
                this.volume = Math.max( 0, Math.min( 100, this.volume + 2 ) );
            } else {
                this.volume = Math.max( 0, Math.min( 100, this.volume - 2 ) );
            }

            if ( this.audio ) {
                this.audio.volume = this.volume / 100;
            }

        }

        // Event listeners
        this._( 'volumeHandle', ( elm: HTMLElement ) => elm.addEventListener( 'mousedown', startDrag ) )
        this._( "volumeBar", ( elm: HTMLElement ) => elm.addEventListener( 'mousedown', startDrag ) )
        document.addEventListener( 'mousemove', onDrag );
        document.addEventListener( 'mouseup', endDrag );

        // For touch devices
        this._( "volumeHandle", ( elm: HTMLElement ) => elm.addEventListener( 'touchstart', startDrag ) )
        document.addEventListener( 'touchmove', onDrag );
        document.addEventListener( 'touchend', endDrag );

        // Mousewheel interaction
        this._( "volumeContainer", ( elm: HTMLElement ) => elm.addEventListener( 'wheel', onWheelChange ) )

    }

    /**
     * Updates the UI elements based on the provided event type.
     *
     * @param event - A string representing the type of event that triggers the UI update.
     * Possible values include 'seeking', 'playing', and others.
     */
    protected updateUI( event: string ) {

        switch ( event ) {

            case 'seeking':
                this._( 'player', ( el: HTMLElement ) => el.classList.add( 'paw-seeking' ) )
                break;

            // @ts-ignore
            case 'playing':
                this._( 'player', ( el: HTMLElement ) => el.classList.remove( 'paw-seeking' ) )
            // fallthrough

            case 'play':
                this._( 'play', ( el: HTMLElement ) => el.classList.add( 'hidden' ) )
                this._( 'stop', ( el: HTMLElement ) => el.classList.remove( 'hidden' ) )
                break;

            case 'stopped':
                this._( 'play', ( el: HTMLElement ) => el.classList.remove( 'hidden' ) )
                this._( 'stop', ( el: HTMLElement ) => el.classList.add( 'hidden' ) )
                this._( 'player', ( el: HTMLElement ) => el.classList.remove( 'paw-seeking' ) )
                break;

            case 'volumechange':

                this._( 'volumeValue', ( el: HTMLElement ) => el.style.width = `${this.volume}%` )
                this._( 'volumeHandle', ( el: HTMLElement ) => el.setAttribute( 'aria-valuenow', `${this.volume}` ) )

                // If volume is 0, mute
                if ( !this.isNoVolume ) {
                    if ( this.volume === 0 ) {

                        this._( 'mute', ( el: HTMLElement ) => el.classList.add( 'hidden' ) )
                        this._( 'unmute', ( el: HTMLElement ) => el.classList.remove( 'hidden' ) )

                    } else {

                        this._( 'mute', ( el: HTMLElement ) => el.classList.remove( 'hidden' ) )
                        this._( 'unmute', ( el: HTMLElement ) => el.classList.add( 'hidden' ) )

                    }
                }
                break;

            case 'disable-volume':
                this._( "player", ( elm: HTMLElement ) => elm.classList.add( 'no-volume' ) );
                this._( "volumeBar", ( elm: HTMLElement ) => elm.classList.add( 'hidden' ) );
                this._( "mute", ( elm: HTMLElement ) => elm.classList.add( 'hidden' ) );
                this._( "unmute", ( elm: HTMLElement ) => elm.classList.add( 'hidden' ) );
                break;
        }

    }

    /**
     * Updates the DOM based on the current channel's logo and theme.
     * If a channel logo is provided, it updates the logo image source.
     * If no logo is provided, it reverts to a temporary logo if available.
     * Also updates the main theme stylesheet link based on the specified theme.
     * Emits a 'theme.change' event if the theme is changed.
     *
     * @param theme - Optional theme to set for the main stylesheet.
     *
     * @returns The updated HTMLElement or null if no update is made.
     */
    private channelDOMChange( theme: string ) {

        if ( this.channel.logo ) {
            this._( '.logo img', ( el: HTMLElement ) => {

                if ( !this.temp.logo ) {
                    this.temp.logo = el.getAttribute( 'src' );
                }

                let logo     = new Image();
                logo.src     = <string>this.channel.logo;
                logo.onload  = () => el.setAttribute( 'src', logo.src );
                logo.onerror = () => el.setAttribute( 'src', this.temp.logo );

            } )
        }

        if ( !this.channel.logo && this.temp.logo ) {
            this._( '.logo img', ( el: HTMLElement ) => {
                el.setAttribute( 'src', this.temp.logo );
            } )
        }

        return this._( '#main_theme', ( el: HTMLElement ) => {

            if ( !this.temp.skin ) {
                this.temp.skin = el.getAttribute( 'href' );
            }

            if ( theme ) {
                el.setAttribute( 'href', `templates/${this.settings.template}/${theme}` );
                this.emit( 'theme.change' )
                return;
            }

            if ( !theme && this.temp.skin && el.getAttribute( 'href' ) !== this.temp.skin ) {
                el.setAttribute( 'href', this.temp.skin );
                this.emit( 'theme.change' )
            }

        }, document, false )
    }

    /**
     * Bind all possible dropdown menus
     */
    private dropdownMenus() {

        const elements = this._( '[data-toggle="dropdown"]' )
        for ( const element of elements ) {

            element.addEventListener( 'click', ( event ) => {

                event.preventDefault();

                let elm = element.parentNode as HTMLElement;
                if ( elm ) {

                    // If the dropdown is already active, close it.
                    if ( elm.classList.contains( 'active' ) ) {
                        elm.classList.remove( 'active' );
                        return false;
                    }

                    // Close all before opening new
                    this._( '[data-toggle="dropdown"]' ).forEach( function( el ) {
                        let parent = el.parentNode?.querySelector( '.dropdown' );
                        parent?.classList.remove( 'active' );
                    } );

                    // Open
                    elm.classList.add( 'active' );

                    // When the body clicked, close this dropdown
                    document.addEventListener( 'click', function( event ) {
                        if ( event.target !== elm && !elm.contains( event.target as Node ) ) {
                            elm.classList.remove( 'active' );
                        }
                    } );

                }

            } );
        }

    }

    /**
     * Use Quality/Stream of a channel to generate audio media
     */
    private async streamToMedia() {

        if ( !this.stream || !this.channel.streams[ this.stream ] ) return;

        const streams: PawMediaSource[] = [];
        for ( const key of Object.keys( this.channel.streams[ this.stream ] ) ) {
            streams.push( { type: key, src: this.channel.streams[ this.stream ][ key ] } );
        }

        try {

            this.setMedia( streams );
            if ( this.autoplay ) {

                await this.play();
                this.updateUI( 'play' )

                // Once done, stop it.
                this.autoplay = false;

            }

        } catch ( error ) {

            this.emit( 'error', error );

        }

    }

    /**
     * Find specific channel used to play on air
     */
    private findChannel( specific: string = '' ) {

        if ( specific !== '' && this.findObjectByKey( this.channels, 'name', specific ) ) {
            this.channel = this.findObjectByKey( this.channels, 'name', specific );
            return;
        }

        // Use storage
        let defaultChannel = this.storage!.get( 'last-channel' );
        if ( defaultChannel && this.findObjectByKey( this.channels, 'name', defaultChannel ) ) {
            this.channel = this.findObjectByKey( this.channels, 'name', defaultChannel );
            return;
        }

        // Use settings
        if ( this.settings.defaults.channel !== "" && this.findObjectByKey( this.channels, 'name', this.settings.defaults.channel ) ) {
            this.channel = this.findObjectByKey( this.channels, 'name', this.settings.defaults.channel );
            return;
        }

        // No default found, use the first channel
        this.channel = this.channels[ 0 ];

    }

    /**
     * Helper function to find a specific object in an array using a key
     */
    private findObjectByKey( array: any, key: string, value: string ): any {

        for ( let i = 0; i < array.length; i++ ) {
            if ( array[ i ][ key ] === value ) return array[ i ];
        }

        return null;

    }
};