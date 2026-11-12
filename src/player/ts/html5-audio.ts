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
import {AudioStatus, Format, PawMediaSource, ReconnectEvent} from './types';
import {PawTunesEvents} from "./pawtunes-events";

export default class HTML5Audio extends PawTunesEvents {

    autoplay: boolean = false;
    volume: number = 50;
    muteVolume: number = 0;
    ready: boolean = false;
    audio: HTMLAudioElement | null = null;
    media: PawMediaSource[] = [];
    debug: boolean = false;

    /**
     * Listener intent: true from a play() until a stop(), pause() or destroy().
     * A network drop never clears it, which is how the reconnect logic knows playback should continue.
     *
     * @var {boolean}
     */
    wantsPlayback: boolean = false;

    /**
     * Remote playback seam (Cast/AirPlay are not implemented yet).
     * While audio plays on a remote device the local <audio> element is idle, so its error, ended and
     * stall events are not drops. A future cast feature calls setReconnectSuspended(true) when playback
     * leaves this device and setReconnectSuspended(false) when it comes back.
     *
     * @var {boolean}
     */
    reconnectSuspended: boolean = false;

    /**
     * Reconnect tuning in milliseconds. The delay doubles from minDelay up to maxDelay with +-25% jitter,
     * so the listeners of a restarted server do not all reconnect in the same second.
     * There is no attempt cap on purpose: a radio listener expects the stream to come back by itself,
     * and one request every 15 seconds per idle listener costs nothing.
     *
     * @var {object}
     */
    reconnectOptions = {
        minDelay         : 1000,  // first retry
        maxDelay         : 15000, // backoff cap
        stallTimeout     : 10000, // no playback progress this long after waiting/stalled counts as a drop
        stableAfter      : 20000, // this much uninterrupted playback resets the backoff
        attemptsPerSource: 3,     // failed retries before falling back to the next source of the stream group
    };

    /**
     * Reconnect state. attempt drives the backoff, failures the source fallback, source is the index into
     * this.media in use, pending is true from a drop until playback resumes (the UI stays "connecting").
     * Timers are setTimeout ids, 0 when not armed.
     *
     * @var {object}
     */
    protected reconnect = {
        attempt         : 0,
        failures        : 0,
        source          : 0,
        pending         : false,
        waitingForOnline: false,
        timer           : 0,
        stallTimer      : 0,
        stableTimer     : 0,
    };

    /**
     * Set while the page unloads: the browser stops the audio itself and nothing may reconnect
     *
     * @var {boolean}
     */
    protected unloading: boolean = false;

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
        oga  : {
            codec: 'audio/ogg',
            media: 'audio'
        },
        flac : { // FLAC
            codec: 'audio/flac',
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


    isAndroid: boolean = this.matchBrowser({mobile: /(android)/});
    isMobile: boolean = this.matchBrowser({mobile: /(mobile)/});
    isNoVolume: boolean = this.matchBrowser(this.noVolumeControl);

    /**
     * Creates a new instance of HTML5Audio.
     *
     * @param {string} [elm=""] - The CSS selector for the container element.
     */
    constructor(elm: string = "") {

        super();

        // Default if isn't specified
        if (elm === "")
            elm = this.selectorsPrefix;

        // Make sure we have an element
        const container = document.querySelector(elm) as HTMLObjectElement;
        if (!container) {

            // Unable to continue
            throw new Error('HTML5 Audio container element not found: ' + elm);

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
        this.audio = new Audio();
        this.audio.preload = "none";
        this.audio.controls = false;
        this.audio.crossOrigin = "anonymous";

        // Different for devices with no volume control
        if (!this.isNoVolume) {
            if (this.volume >= 1 && this.volume <= 100) {

                this.audio.volume = this.volume / 100;

            } else {

                this.audio.muted = true;
                this.audio.volume = 0;

            }
        }

        // No volume control, we set to 100%
        if (this.isNoVolume) {

            this.volume = 100;
            this.audio.volume = 1;
            this.updateUI('disable-volume');

        }

        // If no sources defined, we will not play anything
        if (this.media.length >= 1) {
            this.setMedia(this.media)
        }

        this.updateUI('stopped')
        this.updateUI('volumechange')
        this.bindStreamEvents()

        // We are ready!
        this.trigger('init')

    }

    /**
     * Sets the media sources for the audio player.
     *
     * @param {PawMediaSource[]} sources - An array of media source objects.
     */
    async setMedia(sources: PawMediaSource[]): Promise<void> {

        // No audio object? uff... cannot add sources
        if (!this.audio) {
            throw new Error('No audio element found!');
        }

        // New media (channel or stream switch): pending retries belong to the old stream
        this.clearReconnect();

        // If we're placing new media, clear old
        if (this.media.length > 0) {
            this.media = [];
        }

        let playable: number = 0;

        // Check sources
        for (const source of sources as PawMediaSource[]) {
            if (this.formats[source.type]) {

                // Make sure we can play
                const canPlay = this.audio.canPlayType(this.formats[source.type].codec);
                if (canPlay !== 'probably' && canPlay !== 'maybe') {
                    continue;
                }

                /*const sourceElement = document.createElement( 'source' );
                sourceElement.src = source.src;
                sourceElement.type = this.formats[ source.type ].codec;
                this.audio.appendChild( sourceElement );*/
                this.media.push(source);
                playable++;

            }
        }

        // Hack to append src
        if (playable > 0) {
            this.audio.src = this.media[0].src;
        }

        if (playable < 1) {
            this.ready = false;
            this.trigger('error', {code: 4, message: 'No playable sources found'});
            return;
        }

        // We are ready!
        this.ready = true
        this.trigger('ready')

    }

    /**
     * Binds HTML5 audio events to the internal event handler.
     */
    bindStreamEvents() {

        // Add event listeners
        this.streamEvents.forEach(event => {

            if (!this.audio) return;
            this.audio.addEventListener(event, () => {

                // When ERROR occurs, pass data
                if (event === 'error') {
                    this.trigger(event, this.audio?.error);
                    return;
                }

                this.trigger(event);

            });

        });

    }

    /**
     * Retrieves the current status of the audio player.
     *
     * @returns {AudioStatus | null} - The current audio status or null if the audio element is not initialized.
     */
    status(): AudioStatus | null {

        if (!this.audio) {
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
     * Plays the audio. This is the listener's (or the API's) intent: a fresh start with a fresh backoff.
     *
     * @returns {Promise<void>}
     */
    async play(): Promise<void> {

        this.wantsPlayback = true;

        // Pressing play during a reconnect backoff (or on an element left in an error state by a mid-stream
        // drop or a hung stall) must reload the stream: the element is paused or holds a dead connection, so
        // a bare audio.play() would fire no events and arm no watchdog, leaving the "reconnecting" UI stuck.
        const reload = this.reconnect.pending || !!this.audio?.error;

        this.clearReconnect();
        await this.startPlayback(reload);

    }

    /**
     * Starts the <audio> element. Shared by play() and the reconnect retries.
     *
     * @param {boolean} reload - Re-fetch the stream from scratch (reconnect retries)
     */
    protected async startPlayback(reload: boolean = false): Promise<void> {

        try {

            // If media not set, try to set it
            if (!this.ready && this.media.length >= 1) {
                this.updateUI('play');
                await this.setMedia(Object.assign([], this.media));
            }

            if (!this.audio || !this.ready) {
                return;
            }

            if (reload) {

                // Load the current (or fallback) source again. No cache busters: Shoutcast URLs end with
                // "/;" and would break, and load() already drops the stale connection and buffer.
                this.audio.src = this.media[this.reconnect.source].src;
                this.audio.load();

            }

            await this.audio.play();

        } catch (e) {

            this.handlePlayRejection(e);

        }

    }

    /**
     * Classifies a rejected audio.play() promise
     *
     * @param {*} e - The rejection reason
     */
    protected handlePlayRejection(e: any): void {

        const name = (e instanceof DOMException) ? e.name : '';

        // Our own pause(), load() or src change interrupted a pending play(): nothing failed
        if (name === 'AbortError') {
            return;
        }

        // Autoplay policy: the browser wants a user gesture first. Retrying cannot fix that,
        // so the intent is dropped and the play button comes back.
        if (name === 'NotAllowedError') {
            this.wantsPlayback = false;
            this.clearReconnect();
        }

        this.trigger('error', {code: 4, message: e});

    }

    /**
     * Stops the audio playback and resets the current time.
     * This is the listener's intent: nothing may reconnect after it.
     *
     * @returns {Promise<void>}
     */
    async stop(): Promise<void> {

        this.wantsPlayback = false;
        this.clearReconnect();

        if (this.audio && this.ready) {

            this.trigger('stop');
            this.audio.pause();
            this.audio.autoplay = false;
            this.audio.currentTime = 0;

            // Special case for radio streams, we stop buffering/loading in the background
            this.clearMedia();
            if (this.media.length >= 1) {
                await this.setMedia(Object.assign([], this.media));
            }

        }
    }

    /**
     * Pauses the audio playback (listener intent, like stop).
     */
    pause() {

        this.wantsPlayback = false;
        this.clearReconnect();

        if (this.audio) {
            this.audio.pause();
        }

    }

    /**
     * Mutes the audio by setting the volume to zero.
     */
    mute() {
        if (this.audio) {
            this.muteVolume = this.volume;
            this.audio.volume = 0;
        }
    }

    /**
     * Unmutes the audio by restoring the previous volume level.
     */
    unmute() {
        if (this.audio) {
            this.audio.volume = this.muteVolume / 100;
            this.muteVolume = 0;
        }
    }

    /**
     * Checks if the user agent matches any of the specified device patterns.
     *
     * @param {Object} devices - An object with device names as keys and regular expressions as values.
     * @returns {boolean} - True if a match is found; otherwise, false.
     */
    matchBrowser(devices: { [key: string]: RegExp }): boolean {

        const ua = navigator.userAgent.toLowerCase();
        return Object.values(devices).some((regex) => regex.test(ua));

    }

    /**
     * Clears the current media sources and stops the audio playback.
     */
    clearMedia() {

        if (this.audio) {

            this.audio.pause();
            this.audio.src = "";

            // Removes all child elements, ignored currently
            for (const child of this.audio.children) {
                if (child.tagName === 'SOURCE') {
                    this.audio.removeChild(child);
                }
            }

        }

        this.ready = false;

    }

    /**
     * Destroys the audio player instance and releases resources.
     */
    destroy() {

        this.wantsPlayback = false;
        this.clearReconnect();

        if (this.audio) {

            // IF HTML ELEMENT: this.container.removeChild( this.audio );
            this.audio.pause();
            this.audio.src = "";
            this.audio.currentTime = 0;

        }

        this.audio = null;
        this.ready = false;

    }

    /**
     * True from a drop until playback has resumed (a retry is scheduled, deferred or in flight).
     *
     * @returns {boolean}
     */
    isReconnecting(): boolean {
        return this.reconnect.pending;
    }

    /**
     * Remote playback seam, see reconnectSuspended. Suspending also cancels a reconnect in progress.
     *
     * @param {boolean} suspended
     */
    setReconnectSuspended(suspended: boolean): void {

        this.reconnectSuspended = suspended;
        if (suspended) {
            this.clearReconnect();
        }

    }

    /**
     * The network is back: run the retry that was deferred while offline, or a scheduled one right away
     * instead of waiting out its backoff. Nothing happens when playback is not reconnecting.
     */
    resumeReconnect(): void {

        if (!this.wantsPlayback || !this.reconnect.pending) {
            return;
        }

        // Neither deferred nor scheduled means a retry is already in flight
        if (!this.reconnect.timer && !this.reconnect.waitingForOnline) {
            return;
        }

        clearTimeout(this.reconnect.timer);
        this.reconnect.timer = 0;
        this.reconnect.waitingForOnline = false;
        this.startPlayback(true);

    }

    /**
     * Called on a media error, the end of the stream or a stall. While the listener wants playback
     * the drop is handled here and the caller must not treat it as a stop or an error.
     *
     * @param {string} reason - What was detected
     * @returns {boolean} true when a reconnect is (or already was) scheduled for it
     */
    protected handleDrop(reason: ReconnectEvent['reason']): boolean {

        if (!this.wantsPlayback || this.reconnectSuspended || this.unloading || this.media.length < 1) {
            return false;
        }

        // Never two concurrent retries: the pending one covers this drop as well
        if (this.reconnect.timer || this.reconnect.waitingForOnline) {
            return true;
        }

        this.scheduleReconnect(reason);
        return true;

    }

    /**
     * Schedules the next retry with exponential backoff and jitter, falling back to the next source
     * of the stream group when the current one keeps failing.
     *
     * @param {string} reason - What was detected
     */
    protected scheduleReconnect(reason: ReconnectEvent['reason']): void {

        this.clearStallWatchdog();
        this.clearStableTimer();

        // The current source already failed attemptsPerSource times: move to the next one of the
        // stream group for this retry. Checked before counting this failure, so a source gets its full
        // quota (e.g. attempts 1-3 on source A, attempt 4 on source B).
        if (this.media.length > 1 && this.reconnect.failures >= this.reconnectOptions.attemptsPerSource) {
            this.reconnect.failures = 0;
            this.reconnect.source = (this.reconnect.source + 1) % this.media.length;
        }

        this.reconnect.attempt++;
        this.reconnect.failures++;
        this.reconnect.pending = true;

        // 1, 2, 4, 8 ... seconds, capped, with +-25% jitter (the exponent is capped to keep the math finite)
        const exponent = Math.min(this.reconnect.attempt - 1, 10);
        const base = Math.min(this.reconnectOptions.minDelay * 2 ** exponent, this.reconnectOptions.maxDelay);
        const delay = Math.round(base * (0.75 + Math.random() * 0.5));

        // The drop may have shown the play button already: back to the connecting state
        this.updateUI('play');
        this.updateUI('seeking');
        this.emit('reconnecting', {attempt: this.reconnect.attempt, delay: delay, reason: reason});

        // Offline: do not burn attempts against a dead network, resumeReconnect() runs this one on 'online'
        if (!navigator.onLine) {
            this.reconnect.waitingForOnline = true;
            return;
        }

        this.reconnect.timer = window.setTimeout(() => {

            this.reconnect.timer = 0;
            this.startPlayback(true);

        }, delay);

    }

    /**
     * Playback is running again: the retry succeeded (or the old connection recovered by itself).
     * The backoff only resets after sustained playback, so a stream that drops every few seconds keeps
     * its longer delays instead of hammering the server.
     */
    protected onPlaybackResumed(): void {

        this.clearStallWatchdog();
        clearTimeout(this.reconnect.timer);
        this.reconnect.timer = 0;
        this.reconnect.pending = false;
        this.reconnect.waitingForOnline = false;

        if (this.reconnect.attempt > 0 && !this.reconnect.stableTimer) {
            this.reconnect.stableTimer = window.setTimeout(() => {

                this.reconnect.stableTimer = 0;
                this.reconnect.attempt = 0;
                this.reconnect.failures = 0;

            }, this.reconnectOptions.stableAfter);
        }

    }

    /**
     * Arms the stall watchdog after waiting/stalled: a live stream that makes no progress for
     * stallTimeout is treated as dropped, because browsers wait forever on a hung connection.
     * Cleared by timeupdate (playback progresses) and playing.
     */
    protected armStallWatchdog(): void {

        if (!this.wantsPlayback || this.reconnect.stallTimer) {
            return;
        }

        this.reconnect.stallTimer = window.setTimeout(() => {

            this.reconnect.stallTimer = 0;
            this.handleDrop('stalled');

        }, this.reconnectOptions.stallTimeout);

    }

    protected clearStallWatchdog(): void {
        clearTimeout(this.reconnect.stallTimer);
        this.reconnect.stallTimer = 0;
    }

    protected clearStableTimer(): void {
        clearTimeout(this.reconnect.stableTimer);
        this.reconnect.stableTimer = 0;
    }

    /**
     * Cancels every reconnect timer and forgets the backoff: used on play, stop, new media and destroy
     */
    protected clearReconnect(): void {

        clearTimeout(this.reconnect.timer);
        this.clearStallWatchdog();
        this.clearStableTimer();

        this.reconnect = {
            attempt         : 0,
            failures        : 0,
            source          : 0,
            pending         : false,
            waitingForOnline: false,
            timer           : 0,
            stallTimer      : 0,
            stableTimer     : 0,
        };

    }

    /**
     * Updates the user interface based on the specified event.
     *
     * @protected
     * @param {string} _event - The event name.
     */
    protected updateUI(_event: string) {
        return;
    }

    /**
     * Triggers an internal event and handles associated UI updates.
     *
     * @protected
     * @param {string} event - The event name.
     * @param {*} [data=null] - Additional data associated with the event.
     */
    protected trigger(event: string, data: any = null) {

        if (this.debug) {
            console.log("Event logging:", event, data, this.status());
        }

        // Trigger internal event
        switch (event) {

            case 'loadstart':
                if (this.audio?.autoplay && event === 'loadstart') {
                    this.updateUI('play');
                    this.updateUI('seeking');
                }
                break;

            case 'play':
            case 'waiting':
                const status = this.status();
                if (status && status.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    this.updateUI('seeking');
                }

                this.updateUI('play');
                if (event === 'waiting') {
                    this.armStallWatchdog();
                }
                break;

            case 'stalled':
            case 'seeking':
                this.updateUI('seeking');
                if (event === 'stalled') {
                    this.armStallWatchdog();
                }
                break;

            case 'playing':
                this.updateUI('playing');
                this.onPlaybackResumed();
                break;

            case 'timeupdate':
                // The playhead moves: whatever stalled has recovered
                if (this.audio && !this.audio.paused) {
                    this.clearStallWatchdog();
                }
                break;

            case 'abort':
            case 'pause':
            case 'ended':
            case 'stop':

                // The stream ended while the listener wants playback: a drop, not a stop. The browser fires
                // pause (with ended already true) before ended, handling it there avoids a flash of the play button.
                if (event !== 'stop' && this.audio?.ended && this.handleDrop('ended')) {
                    return;
                }

                // Our own load() during a reconnect pauses and aborts the element too: keep the connecting state
                if (this.reconnect.pending) {
                    return;
                }

                // A browser-initiated pause (Android audio-focus loss, iOS call interruption) reaches here
                // with the listener intent still set: the player's own stop()/pause() clear it before pausing,
                // and a remote (cast) pause is filtered out earlier. Clear it, or a later stall or error on the
                // idle connection would silently resume audio the listener never asked to hear again.
                if (event === 'pause' && this.wantsPlayback && !this.audio?.ended && !this.remotePlayback) {
                    this.wantsPlayback = false;
                    this.clearReconnect();
                }

                this.emit('stopped');
                this.updateUI('stopped');
                return;

            // Case when autoplay fails as we don't have any content preloaded yet
            case 'suspend':
                if (this.status()?.readyState === 0) {
                    this.trigger('stop');
                }
                break;

            case 'error':

                // A drop while the listener wants playback: the reconnect logic takes over and no error is emitted
                if (this.handleDrop('error')) {
                    return;
                }

                this.stop();
                if (!data) {
                    return;
                }
                break;

            case 'volumechange':

                // UI should react based on Media Volume, not internal value
                if (!this.audio) return;
                this.volume = Math.round(this.audio.volume * 100);

                // If volume is 0, mute else unmute
                this.audio.muted = this.volume < 1;
                this.updateUI('volumechange');
                break;

            default:
                break;
        }

        // If data is undefined/empty, respond with status, useful for debugging/various info
        if (!data) {
            data = this.status();
        }

        // Trigger class event
        this.emit(event, data);

    }

}