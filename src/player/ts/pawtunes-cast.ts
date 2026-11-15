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
import PawTunes from "./pawtunes";
import {CastKind, CastState} from "./types";

/**
 * WebKit addition to the media element: the legacy AirPlay picker. Only used to detect WebKit, the
 * picker itself is opened through the standard Remote Playback API (element.remote, Safari 13.1+).
 */
interface WebKitMediaElement extends HTMLMediaElement {
    webkitShowPlaybackTargetPicker?: () => void;
}

/**
 * Casting plugin, composed into PawTunes as `pawtunes.cast` like the web socket plugin.
 *
 * One path per browser: Google Cast (Chromecast) through the Web Sender SDK in Chromium browsers,
 * AirPlay through WebKit's remote playback API in Safari and every iOS browser. Firefox has neither,
 * so it gets no button. The template only ships a hidden `.cast` button; this class shows, hides and
 * styles it (see renderButton) and emits `cast.change` so templates can react, e.g. hide a visualizer
 * that only sees silence while audio plays elsewhere.
 *
 * Chromecast: the receiver fetches the stream itself, so the local <audio> element is released for
 * the length of the session (HTML5Audio.setRemotePlayback) and the play, stop and volume controls
 * drive the receiver instead. The page keeps polling track info as usual.
 * AirPlay: the element keeps playing and the OS routes its output, nothing else changes.
 */
export default class PawTunesCast {

    /**
     * Google Cast Web Sender SDK, loaded on demand (Chromium only, see init)
     */
    static readonly SDK_URL = 'https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1';

    /**
     * Stream formats the Default Media Receiver plays, most reliable first: MP3 and AAC are the safe
     * set on every Cast device, FLAC, Ogg and WebM are documented for audio devices only.
     * Plain M3U playlists, FLV and RTMP are not castable.
     */
    static readonly CASTABLE_FORMATS = ['mp3', 'm4a', 'm3u8a', 'flac', 'oga', 'webma', 'wav'];

    /**
     * How often a receiver that lost its stream is reloaded (mirrors the local reconnect, bounded)
     */
    static readonly RELOAD_ATTEMPTS = 5;

    /**
     * Uninterrupted receiver playback this long (ms) resets the reload counter, like the local reconnect's
     * stableAfter: a stream that drops every few seconds runs out of attempts instead of reloading forever
     */
    static readonly STABLE_AFTER = 20000;

    kind: CastKind | null = null;
    state: CastState = 'unavailable';
    device: string = '';

    private pawtunes: PawTunes;
    private button: HTMLElement[] = [];

    // Chromecast session state
    private context: cast.framework.CastContext | null = null;
    private player: cast.framework.RemotePlayer | null = null;
    private controller: cast.framework.RemotePlayerController | null = null;
    private loadSeq: number = 0;              // every load() gets a number: results of superseded loads are ignored
    private loading: boolean = false;         // a load is in flight
    private loadedUrl: string = '';           // stream URL the receiver currently holds
    private remoteState: string = '';         // last chrome.cast.media.PlayerState seen
    private hadPlayback: boolean = false;     // the current stream reached PLAYING at least once in this session
    private reloads: number = 0;              // reloads since playback was last stable
    private playingSince: number = 0;         // when the receiver entered PLAYING, 0 while it is not playing
    private reloadTimer: number = 0;
    private timeTimer: number = 0;            // drives the elapsed-time display, which timeupdate does locally
    private localVolume: number | null = null;   // the listener's volume before the session, restored after it
    private resumeLocal: boolean | null = null;  // set by endSession(); null = decide from the receiver state
    private failMessage: string = '';            // shown once the failed session has ended and the UI has settled


    constructor(pawtunes: PawTunes) {

        this.pawtunes = pawtunes;

    }


    /**
     * Picks the casting path for this browser and binds the template's .cast button.
     * Called once from PawTunes.init(), after the audio element exists.
     */
    init(): void {

        if (!this.pawtunes.settings.cast || !this.pawtunes.audio) {
            return;
        }

        this.button = this.pawtunes._('cast');
        for (const el of this.button) {
            el.addEventListener('click', (event) => {
                event.preventDefault();
                this.onClick();
            });
        }

        // WebKit (Safari on macOS and iOS, and every browser on iOS): AirPlay through the element itself
        if (this.isWebKit()) {
            this.kind = 'airplay';
            this.setupAirPlay();
            return;
        }

        // Chromium on desktop and Android: the Cast SDK. It needs a secure context (https or localhost),
        // does not exist on iOS and is only fetched when the browser looks like Chromium, so Firefox never
        // contacts gstatic.com and the player never waits for the script.
        if (window.isSecureContext && !this.isIOS() && this.isChromium()) {
            this.kind = 'chromecast';
            this.loadSdk();
        }

    }


    /**
     * True while a Chromecast session owns playback: play, stop and volume go to the receiver
     */
    isRemote(): boolean {

        return this.kind === 'chromecast' && this.state === 'connected';

    }


    /**
     * Play on the receiver (PawTunes.play while a session runs): loads the current stream when the
     * receiver holds nothing, another stream or stopped playing, otherwise there is nothing to do.
     */
    play(): void {

        const PlayerState = chrome.cast.media.PlayerState;
        const source = this.castableSource();
        const sameStream = source !== null && source.url === this.loadedUrl;

        if (sameStream && this.loading) {
            return;
        }

        if (sameStream && (this.remoteState === PlayerState.PLAYING || this.remoteState === PlayerState.BUFFERING)) {
            return;
        }

        this.load();

    }


    /**
     * Stop the receiver (PawTunes.stop while a session runs). The session stays open, so the next play
     * goes to the same device.
     */
    stop(): void {

        this.cancelReload();
        this.loadSeq++;             // a load still in flight is ignored when it settles
        this.loading = false;

        if (this.controller && this.player && this.player.isMediaLoaded) {
            this.controller.stop();
        }

        this.pawtunes.setRemoteState('stopped');

    }


    /* ================================================================================================
     * Browser detection
     * ============================================================================================== */

    private isWebKit(): boolean {

        const audio = this.pawtunes.audio as WebKitMediaElement | null;
        return audio !== null && typeof audio.webkitShowPlaybackTargetPicker === 'function';

    }

    private isIOS(): boolean {

        // iPadOS reports itself as a Mac, the touch points give it away
        return /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    }

    private isChromium(): boolean {

        // Chrome, Edge, Opera, Brave and friends expose window.chrome; Firefox and Safari do not
        return typeof window.chrome === 'object' && window.chrome !== null;

    }


    /* ================================================================================================
     * Button
     * ============================================================================================== */

    private onClick(): void {

        if (this.kind === 'airplay') {
            this.promptAirPlay();
            return;
        }

        if (this.kind === 'chromecast' && this.context) {

            // Opens the device picker, or the "stop casting" dialog while connected. Must run inside the
            // click handler (user gesture). Closing the dialog rejects with CANCEL: nothing to do.
            this.context.requestSession().catch(() => {});

        }

    }


    /**
     * Records a state change, updates the button, the container class and tells the templates
     *
     * @param state
     */
    private setState(state: CastState): void {

        const device = (state === 'connected') ? this.deviceName() : '';
        if (state === this.state && device === this.device) {
            return;
        }

        this.state = state;
        this.device = device;
        this.renderButton();

        // Templates style the container while audio plays elsewhere (visualizers, hints)
        this.pawtunes._('', (el: HTMLElement) => el.classList.toggle('casting', state === 'connected'));
        this.pawtunes.emit('cast.change', {kind: this.kind, state: state, device: device || undefined});

    }


    /**
     * The button is the template's: this only toggles classes the template CSS keys on
     * (hidden, cast-chromecast / cast-airplay, connecting, connected) and the accessible name.
     */
    private renderButton(): void {

        const label = this.pawtunes.translate(this.kind === 'airplay' ? 'ui_airplay' : 'ui_cast');
        let title = label;

        if (this.state === 'connecting') {
            title = this.pawtunes.translate('cast_connecting');
        } else if (this.state === 'connected') {
            title = this.pawtunes.translate('cast_playing_on', 'DEVICE', this.device || label);
        }

        for (const el of this.button) {

            el.classList.toggle('hidden', this.state === 'unavailable');
            el.classList.toggle('cast-chromecast', this.kind === 'chromecast');
            el.classList.toggle('cast-airplay', this.kind === 'airplay');
            el.classList.toggle('connecting', this.state === 'connecting');
            el.classList.toggle('connected', this.state === 'connected');
            el.setAttribute('title', title);
            el.setAttribute('aria-label', title);

        }

    }


    private deviceName(): string {

        const session = this.context ? this.context.getCurrentSession() : null;
        return (session && session.getCastDevice()) ? (session.getCastDevice().friendlyName || '') : '';

    }


    /* ================================================================================================
     * AirPlay (WebKit)
     * ============================================================================================== */

    private setupAirPlay(): void {

        const audio = this.pawtunes.audio as WebKitMediaElement;

        // iOS only scans for AirPlay routes while this listener is registered on the element, so it stays
        // registered for the life of the page.
        audio.addEventListener('webkitplaybacktargetavailabilitychanged', (event: Event) => {
            const availability = (event as Event & { availability?: string }).availability;
            if (this.state !== 'connected' && this.state !== 'connecting') {
                this.setState(availability === 'available' ? 'available' : 'unavailable');
            }
        });

        if (audio.remote) {

            audio.remote.addEventListener('connecting', () => this.setState('connecting'));
            audio.remote.addEventListener('connect', () => this.setState('connected'));
            audio.remote.addEventListener('disconnect', () => this.setState('available'));

            // A browser that cannot monitor availability rejects: then the button is shown and prompt() decides
            audio.remote.watchAvailability((available: boolean) => {
                if (this.state !== 'connected' && this.state !== 'connecting') {
                    this.setState(available ? 'available' : 'unavailable');
                }
            }).catch(() => this.setState('available'));

        }

        // No legacy fallback (webkitcurrentplaybacktargetiswirelesschanged, webkitShowPlaybackTargetPicker):
        // WebKit without element.remote is Safari < 13.1 / iOS < 13.4, which cannot parse the ES2020 bundle
        // (optional chaining, ??) in the first place.

    }


    /**
     * Opens the AirPlay picker. Must be called synchronously inside the click handler (user gesture).
     */
    private promptAirPlay(): void {

        const audio = this.pawtunes.audio as WebKitMediaElement | null;
        if (!audio) {
            return;
        }

        if (audio.remote) {

            // WebKit rejects before showing the picker when AirPlay is off (InvalidStateError), when the element
            // has no metadata yet on macOS (NotSupportedError) or once the picker is dismissed (NotAllowedError).
            // Only the metadata case is actionable for the listener: start playback first.
            audio.remote.prompt().catch((error: DOMException) => {

                if (error && error.name === 'NotSupportedError') {
                    this.pawtunes.toast(this.pawtunes.translate('cast_airplay_hint'));
                } else if (this.pawtunes.debug) {
                    console.log('AirPlay picker:', error);
                }

            });

        }

    }


    /* ================================================================================================
     * Chromecast (Google Cast Web Sender SDK)
     * ============================================================================================== */

    private loadSdk(): void {

        // Already on the page (another script loaded it): no second download
        if (window.cast && window.cast.framework && window.chrome && window.chrome.cast) {
            this.setupChromecast();
            return;
        }

        // The SDK calls this once it knows whether the Cast API is usable here. Defined before the script tag.
        window.__onGCastApiAvailable = (available: boolean) => {

            if (available) {
                this.setupChromecast();
            } else if (this.pawtunes.debug) {
                console.log('Cast API not available in this browser');
            }

        };

        const script = document.createElement('script');
        script.src = PawTunesCast.SDK_URL;
        script.async = true;
        script.onerror = () => {
            if (this.pawtunes.debug) console.warn('Cast SDK failed to load');
        };

        document.head.appendChild(script);

    }


    private setupChromecast(): void {

        this.context = cast.framework.CastContext.getInstance();
        this.context.setOptions({
            receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
            autoJoinPolicy       : chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED, // rejoin a running session after a reload
        });

        this.player = new cast.framework.RemotePlayer();
        this.controller = new cast.framework.RemotePlayerController(this.player);

        const contextEvents = cast.framework.CastContextEventType;
        this.context.addEventListener(contextEvents.CAST_STATE_CHANGED, (event: cast.framework.CastStateEventData) => {
            this.onCastState(event.castState);
        });
        this.context.addEventListener(contextEvents.SESSION_STATE_CHANGED, (event: cast.framework.SessionStateEventData) => {
            this.onSessionState(event);
        });

        const playerEvents = cast.framework.RemotePlayerEventType;
        this.controller.addEventListener(playerEvents.PLAYER_STATE_CHANGED, () => this.onRemotePlayerState());
        this.controller.addEventListener(playerEvents.VOLUME_LEVEL_CHANGED, () => this.onRemoteVolume());
        this.controller.addEventListener(playerEvents.IS_MUTED_CHANGED, () => this.onRemoteVolume());

        // The slider and mute still go through the local element (silent while casting), mirrored here
        this.pawtunes.on('volumechange', () => this.syncVolumeToReceiver());

        this.onCastState(this.context.getCastState());

    }


    /**
     * Device availability and connection state of the Cast framework
     *
     * @param castState cast.framework.CastState
     */
    private onCastState(castState: string): void {

        const CastStates = cast.framework.CastState;

        switch (castState) {

            case CastStates.NO_DEVICES_AVAILABLE:
                this.setState('unavailable');
                break;

            case CastStates.NOT_CONNECTED:
                this.setState('available');
                break;

            case CastStates.CONNECTING:
                this.setState('connecting');
                break;

            case CastStates.CONNECTED:
                this.setState('connected');
                break;

        }

    }


    private onSessionState(event: cast.framework.SessionStateEventData): void {

        const SessionStates = cast.framework.SessionState;

        switch (event.sessionState) {

            case SessionStates.SESSION_STARTED:
                this.onSessionStarted(false);
                break;

            case SessionStates.SESSION_RESUMED:
                this.onSessionStarted(true);
                break;

            case SessionStates.SESSION_ENDED:
                this.onSessionEnded();
                break;

            // The picker was closed or the device refused: CAST_STATE_CHANGED restores the idle button, no toast
            case SessionStates.SESSION_START_FAILED:
                if (this.pawtunes.debug) console.log('Cast session failed to start:', event.errorCode);
                break;

        }

    }


    /**
     * A session is up: the receiver owns playback from here on.
     *
     * @param resumed true when an existing session was rejoined after a page reload (ORIGIN_SCOPED auto-join)
     */
    private onSessionStarted(resumed: boolean): void {

        const PlayerState = chrome.cast.media.PlayerState;

        this.resumeLocal = null;
        this.reloads = 0;
        this.hadPlayback = false;
        this.remoteState = (this.player && this.player.playerState) ? this.player.playerState : '';
        this.loadedUrl = (this.player && this.player.mediaInfo) ? this.player.mediaInfo.contentId : '';
        this.setState('connected');

        // Silence the local element and park the reconnect logic; the slider will show the receiver volume
        if (!this.pawtunes.isNoVolume) {
            this.localVolume = this.pawtunes.volume;
        }
        this.pawtunes.setRemotePlayback(true);

        if (resumed) {

            // Mirror what the receiver is doing instead of reloading media that may already be playing
            const playing = this.remoteState === PlayerState.PLAYING || this.remoteState === PlayerState.BUFFERING;
            this.pawtunes.wantsPlayback = playing;
            this.hadPlayback = playing;
            this.onRemotePlayerState();
            this.onRemoteVolume();
            return;

        }

        // A fresh session: the listener picked a device to listen on, so the current stream starts there
        this.pawtunes.wantsPlayback = true;
        this.onRemoteVolume();
        this.load();

    }


    /**
     * The session is gone (stopped from the Cast dialog, the device went away, or endSession() after a
     * failure). Local playback resumes when the listener wants audio and the receiver was playing, so a
     * device that disappears does not leave the listener in silence; after a deliberate stop it stays quiet.
     */
    private onSessionEnded(): void {

        const PlayerState = chrome.cast.media.PlayerState;
        const wasPlaying = this.loading || this.remoteState === PlayerState.PLAYING || this.remoteState === PlayerState.BUFFERING;
        const resume = (this.resumeLocal !== null) ? this.resumeLocal : (this.pawtunes.wantsPlayback && wasPlaying);

        this.cancelReload();
        this.clearTimeTimer();
        this.resumeLocal = null;
        this.loading = false;
        this.loadedUrl = '';
        this.remoteState = '';
        this.hadPlayback = false;
        this.playingSince = 0;

        const message = this.failMessage;
        this.failMessage = '';

        // Back to the local element; CAST_STATE_CHANGED refines the button to available/unavailable
        this.setState('available');
        this.pawtunes.setRemotePlayback(false);
        this.restoreLocalVolume();

        if (resume) {

            // A failure message is shown once the local player has settled: shown now, its "Loading" status would cover it
            if (message) {
                this.toastWhenSettled(message);
            }

            this.pawtunes.play();
            return;

        }

        this.pawtunes.wantsPlayback = false;
        this.pawtunes.setRemoteState('stopped');

        if (message) {
            this.pawtunes.toast(message, true);
        }

    }


    /**
     * Loads the current channel's castable stream on the receiver.
     *
     * Metadata is static on purpose (channel name, station title, channel logo or default artwork): the
     * Default Media Receiver cannot change the metadata of a live stream without a reload, and a reload
     * interrupts the audio. Track names stay on the page, where they update without interruption.
     */
    private load(): void {

        const session = this.context ? this.context.getCurrentSession() : null;
        if (!session) {
            return;
        }

        const source = this.castableSource();
        if (!source) {
            this.fail('cast_unsupported');
            return;
        }

        const media = new chrome.cast.media.MediaInfo(source.url, source.contentType);
        media.streamType = chrome.cast.media.StreamType.LIVE;
        media.metadata = new chrome.cast.media.MusicTrackMediaMetadata();
        media.metadata.title = this.pawtunes.channel.name;
        media.metadata.artist = this.pawtunes.settings.title;

        const artwork = this.artworkUrl();
        if (artwork) {
            media.metadata.images = [new chrome.cast.Image(artwork)];
        }

        const request = new chrome.cast.media.LoadRequest(media);
        request.autoplay = true;

        // Another stream (channel or quality switch) starts over: it has to prove it plays before a drop is
        // retried. A reload of the same stream keeps both, otherwise the backoff could never pass its first step.
        if (source.url !== this.loadedUrl) {
            this.hadPlayback = false;
            this.reloads = 0;
        }

        const seq = ++this.loadSeq;
        this.cancelReload();
        this.loading = true;
        this.loadedUrl = source.url;
        this.playingSince = 0;
        this.pawtunes.setRemoteState('buffering');

        session.loadMedia(request).then(() => {

            // A newer load (channel switch) or a stop superseded this one
            if (seq !== this.loadSeq) {
                return;
            }

            this.loading = false;

            // The listener pressed stop while the receiver was still loading
            if (!this.pawtunes.wantsPlayback) {
                this.stop();
                return;
            }

            this.onRemotePlayerState();

        }, (code: string) => {

            if (seq !== this.loadSeq) {
                return;
            }

            if (this.pawtunes.debug) console.warn('Cast load failed:', code);
            this.loading = false;

            // A reload while the stream server restarts is rejected too: keep backing off, it played before
            if (this.pawtunes.wantsPlayback && this.scheduleReload()) {
                return;
            }

            this.fail('cast_failed');

        });

    }


    /**
     * The receiver cannot play the stream (unsupported format, unreachable URL): the session ends,
     * playback continues on this device if the listener wanted audio, and one message explains it
     * (delivered by onSessionEnded, after the play/stop status of the local player).
     *
     * @param key locale key of the message
     */
    private fail(key: string): void {

        this.failMessage = this.pawtunes.translate(key);
        this.endSession(this.pawtunes.wantsPlayback);

    }


    /**
     * Shows a message after the local player's next playing, error or stopped event, whichever comes
     * first, so the player's own status does not cover it. One of them follows a play() within seconds;
     * when nothing follows at all (no source the browser can play either) a timer shows it anyway.
     *
     * @param message
     */
    private toastWhenSettled(message: string): void {

        const events = ['playing', 'error', 'stopped'];
        let timer = 0;

        const show = () => {

            clearTimeout(timer);
            for (const event of events) {
                this.pawtunes.off(event, show);
            }

            this.pawtunes.toast(message);

        };

        for (const event of events) {
            this.pawtunes.on(event, show);
        }

        timer = window.setTimeout(show, 3000);

    }


    private endSession(resumeLocal: boolean): void {

        this.resumeLocal = resumeLocal;
        if (this.context) {
            this.context.endCurrentSession(true);
        }

    }


    /**
     * Receiver player state -> play/stop UI, elapsed time, reload after a lost stream
     */
    private onRemotePlayerState(): void {

        if (!this.player || !this.isRemote() || !this.player.playerState) {
            return;
        }

        const PlayerState = chrome.cast.media.PlayerState;
        const state = this.player.playerState;
        this.remoteState = state;

        // Leaving PLAYING: a run that lasted STABLE_AFTER earns a fresh set of reload attempts. Checked
        // here, before handleIdle schedules the next reload, so the counter is current when it is read.
        if (state !== PlayerState.PLAYING && this.playingSince) {
            if (Date.now() - this.playingSince >= PawTunesCast.STABLE_AFTER) {
                this.reloads = 0;
            }
            this.playingSince = 0;
        }

        switch (state) {

            case PlayerState.PLAYING:
                // The reload counter resets only after sustained playback (see above), not on the first PLAYING
                if (!this.playingSince) {
                    this.playingSince = Date.now();
                }
                this.hadPlayback = true;
                this.pawtunes.setRemoteState('playing');
                this.pawtunes.toast(this.pawtunes.translate('cast_playing_on', 'DEVICE', this.device), true);
                this.startTimeTimer();
                break;

            case PlayerState.BUFFERING:
                this.clearTimeTimer();
                this.pawtunes.setRemoteState('buffering');
                break;

            case PlayerState.PAUSED:
            case PlayerState.IDLE:

                // Our own load replaces the previous media: its IDLE (INTERRUPTED) is not a stop
                if (this.loading) {
                    break;
                }

                this.clearTimeTimer();
                if (state === PlayerState.IDLE && this.handleIdle()) {
                    break;
                }

                this.pawtunes.setRemoteState('stopped');
                break;

        }

    }


    /**
     * IDLE after a load: FINISHED means the server closed a live stream, ERROR that the receiver could
     * not play it. While the listener wants audio and the stream did play before, the receiver is
     * reloaded with a growing delay (1, 2, 4, 8, 15 s), like the local reconnect; a stream that never
     * played is a permanent failure (format, URL). A deliberate stop (CANCELLED) is just a stop.
     *
     * @returns true when the idle state was handled here (reload scheduled or session ended)
     */
    private handleIdle(): boolean {

        const IdleReason = chrome.cast.media.IdleReason;
        const session = this.context ? this.context.getCurrentSession() : null;
        const mediaSession = session ? session.getMediaSession() : null;
        const reason = mediaSession ? mediaSession.idleReason : null;

        if (reason !== IdleReason.FINISHED && reason !== IdleReason.ERROR) {
            return false;
        }

        if (!this.pawtunes.wantsPlayback) {
            return false;
        }

        if (!this.scheduleReload()) {
            this.fail('cast_failed');
        }

        return true;

    }


    /**
     * Reloads the receiver after a growing delay (1, 2, 4, 8, 15 s). Only for a stream that played in
     * this session and while attempts are left; the counter starts over after STABLE_AFTER of playback
     * (see onRemotePlayerState).
     *
     * @returns false when no reload is allowed (the caller ends the session)
     */
    private scheduleReload(): boolean {

        // The IDLE event and the rejected load of the same failure must not schedule (and count) twice
        if (this.reloadTimer) {
            return true;
        }

        if (!this.hadPlayback || this.reloads >= PawTunesCast.RELOAD_ATTEMPTS) {
            return false;
        }

        const delay = Math.min(1000 * 2 ** this.reloads, 15000);
        this.reloads++;
        this.pawtunes.setRemoteState('buffering');
        this.pawtunes.toast(this.pawtunes.translate('status_reconnecting'), true);
        this.reloadTimer = window.setTimeout(() => {
            this.reloadTimer = 0;
            this.load();
        }, delay);

        return true;

    }


    private cancelReload(): void {

        clearTimeout(this.reloadTimer);
        this.reloadTimer = 0;

    }


    /**
     * The receiver volume changed (TV remote, another sender, session start): show it on the slider.
     * The element is silent while casting, so changing its volume only moves the UI.
     */
    private onRemoteVolume(): void {

        if (!this.player || !this.isRemote() || this.pawtunes.isNoVolume || !this.pawtunes.audio) {
            return;
        }

        const level = this.player.isMuted ? 0 : this.player.volumeLevel;
        if (Math.abs(this.pawtunes.audio.volume - level) > 0.01) {
            this.pawtunes.audio.volume = level;
        }

    }


    /**
     * The slider, mute or the sleep timer fade changed the local volume: push it to the receiver.
     * Skipped when the receiver is already there, which is how a change coming from the receiver
     * (onRemoteVolume) does not bounce back.
     */
    private syncVolumeToReceiver(): void {

        if (!this.player || !this.controller || !this.isRemote() || this.pawtunes.isNoVolume) {
            return;
        }

        const level = this.pawtunes.volume / 100;
        const remote = this.player.isMuted ? 0 : this.player.volumeLevel;
        if (Math.abs(remote - level) <= 0.01) {
            return;
        }

        if (this.player.isMuted && level > 0) {
            this.controller.muteOrUnmute();
        }

        this.player.volumeLevel = level;
        this.controller.setVolumeLevel();

    }


    private restoreLocalVolume(): void {

        if (this.localVolume !== null && this.pawtunes.audio) {
            this.pawtunes.audio.volume = this.localVolume / 100;
        }

        this.localVolume = null;

    }


    /**
     * The elapsed-time display is driven by timeupdate during local playback; the receiver sends none
     */
    private startTimeTimer(): void {

        this.clearTimeTimer();
        this.timeTimer = window.setInterval(() => this.pawtunes.onAirTimer(), 1000);

    }


    private clearTimeTimer(): void {

        clearInterval(this.timeTimer);
        this.timeTimer = 0;

    }


    /**
     * The source of the current stream group the receiver can play, by format preference.
     * The URL is made absolute (the receiver fetches it itself) and the codecs parameter of the
     * player's MIME string is dropped, the receiver wants a plain content type.
     */
    castableSource(): { url: string, contentType: string } | null {

        const group = this.pawtunes.stream ? this.pawtunes.channel.streams[this.pawtunes.stream] : null;
        if (!group) {
            return null;
        }

        for (const format of PawTunesCast.CASTABLE_FORMATS) {
            if (group[format] && this.pawtunes.formats[format]) {
                return {
                    url        : new URL(group[format], this.pawtunes.url).href,
                    contentType: this.pawtunes.formats[format].codec.split(';')[0].trim(),
                };
            }
        }

        return null;

    }


    /**
     * Absolute URL of the channel logo, or of the default artwork, for the receiver screen
     */
    private artworkUrl(): string {

        const image = this.pawtunes.channel.logo || this.pawtunes.settings.trackInfo.default.artwork;
        return image ? new URL(image, this.pawtunes.url).href : '';

    }

}
