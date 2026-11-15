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

/**
 * Minimal declarations for the Google Cast Web Sender SDK, which is loaded at runtime from gstatic.com
 * (there is no npm package for it), limited to what pawtunes-cast.ts uses. Enum-like objects are typed
 * as string maps because the SDK exposes plain string constants.
 *
 * Reference: https://developers.google.com/cast/docs/reference/web_sender
 */
declare namespace chrome.cast {

    const AutoJoinPolicy: {
        TAB_AND_ORIGIN_SCOPED: string;
        ORIGIN_SCOPED: string;
        PAGE_SCOPED: string;
    };

    const ErrorCode: {
        CANCEL: string;
        TIMEOUT: string;
        API_NOT_INITIALIZED: string;
        INVALID_PARAMETER: string;
        EXTENSION_NOT_COMPATIBLE: string;
        EXTENSION_MISSING: string;
        RECEIVER_UNAVAILABLE: string;
        SESSION_ERROR: string;
        CHANNEL_ERROR: string;
        LOAD_MEDIA_FAILED: string;
    };

    class Image {
        constructor(url: string);

        url: string;
    }

    interface Receiver {
        friendlyName: string;
    }

    namespace media {

        const DEFAULT_MEDIA_RECEIVER_APP_ID: string;

        const StreamType: {
            BUFFERED: string;
            LIVE: string;
            OTHER: string;
        };

        const PlayerState: {
            IDLE: string;
            PLAYING: string;
            PAUSED: string;
            BUFFERING: string;
        };

        const IdleReason: {
            CANCELLED: string;
            INTERRUPTED: string;
            FINISHED: string;
            ERROR: string;
        };

        class MusicTrackMediaMetadata {
            title?: string;
            artist?: string;
            albumName?: string;
            images?: chrome.cast.Image[];
        }

        class MediaInfo {
            constructor(contentId: string, contentType: string);

            contentId: string;
            contentType: string;
            streamType: string;
            metadata: MusicTrackMediaMetadata | null;
        }

        class LoadRequest {
            constructor(mediaInfo: MediaInfo);

            autoplay: boolean;
        }

        /**
         * The media session of a cast session (chrome.cast.media.Media)
         */
        interface Media {
            playerState: string;
            idleReason: string | null;
            media: MediaInfo | null;
        }

    }

}

declare namespace cast.framework {

    const CastContextEventType: {
        CAST_STATE_CHANGED: string;
        SESSION_STATE_CHANGED: string;
    };

    const CastState: {
        NO_DEVICES_AVAILABLE: string;
        NOT_CONNECTED: string;
        CONNECTING: string;
        CONNECTED: string;
    };

    const SessionState: {
        NO_SESSION: string;
        SESSION_STARTING: string;
        SESSION_STARTED: string;
        SESSION_START_FAILED: string;
        SESSION_ENDING: string;
        SESSION_ENDED: string;
        SESSION_RESUMED: string;
    };

    const RemotePlayerEventType: {
        ANY_CHANGE: string;
        IS_CONNECTED_CHANGED: string;
        IS_MEDIA_LOADED_CHANGED: string;
        PLAYER_STATE_CHANGED: string;
        IS_PAUSED_CHANGED: string;
        IS_MUTED_CHANGED: string;
        VOLUME_LEVEL_CHANGED: string;
        MEDIA_INFO_CHANGED: string;
    };

    interface CastStateEventData {
        castState: string;
    }

    interface SessionStateEventData {
        sessionState: string;
        session: CastSession | null;
        errorCode: string | null;
    }

    interface RemotePlayerChangedEvent {
        type: string;
        field: string;
        value: any;
    }

    interface CastOptions {
        receiverApplicationId: string;
        autoJoinPolicy?: string;
    }

    class CastContext {
        static getInstance(): CastContext;

        setOptions(options: CastOptions): void;

        addEventListener(type: string, handler: (event: any) => void): void;

        removeEventListener(type: string, handler: (event: any) => void): void;

        getCastState(): string;

        getCurrentSession(): CastSession | null;

        /** Opens the device picker; rejects with a chrome.cast.ErrorCode when cancelled or failed */
        requestSession(): Promise<string | undefined>;

        endCurrentSession(stopCasting: boolean): void;
    }

    interface CastSession {
        getCastDevice(): chrome.cast.Receiver;

        getMediaSession(): chrome.cast.media.Media | null;

        getSessionState(): string;

        /** Resolves once the receiver accepted the load; rejects with a chrome.cast.ErrorCode */
        loadMedia(request: chrome.cast.media.LoadRequest): Promise<string | undefined>;
    }

    class RemotePlayer {
        isConnected: boolean;
        isMediaLoaded: boolean;
        isMuted: boolean;
        volumeLevel: number;
        playerState: string | null;
        mediaInfo: chrome.cast.media.MediaInfo | null;
    }

    class RemotePlayerController {
        constructor(player: RemotePlayer);

        addEventListener(type: string, handler: (event: RemotePlayerChangedEvent) => void): void;

        removeEventListener(type: string, handler: (event: RemotePlayerChangedEvent) => void): void;

        playOrPause(): void;

        stop(): void;

        /** Applies player.volumeLevel to the receiver */
        setVolumeLevel(): void;

        muteOrUnmute(): void;
    }

}

interface Window {
    /** Called by the Cast SDK once it knows whether the Cast API can be used in this browser */
    __onGCastApiAvailable?: (available: boolean) => void;
    /** Present in Chromium browsers; carries the Cast API once the SDK has loaded */
    chrome?: typeof chrome;
    /** The Cast Application Framework, set by the SDK */
    cast?: typeof cast;
}
