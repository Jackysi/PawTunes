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
export interface PawMediaSource {
    src: string;
    type: string;
}

export interface OnAir {
    artist: string,
    title: string,
    artwork: string | null,
    time: number
}

/**
 * Optional now-playing extras some sources provide (AzuraCast, SAM): the queued track and the live DJ.
 * Payload of the `track.extra` event and the shape of PawTunes.trackExtra; a field is null when the
 * current source does not provide it (or nothing is queued / nobody is live).
 */
export interface TrackExtra {
    next: {artist: string, title: string, artwork: string | null} | null;
    live: {isLive: boolean, streamer: string} | null;
}

export interface Channel {
    name: string,
    logo: string | null,
    skin: string,
    ws: {
        [key: string]: string
    },
    streams: {
        [key: string]: {
            [key: string]: string
        }
    }
}

export interface Format {
    codec: string;
    media: string;
}

/**
 * Payload of the `reconnecting` event, emitted when a retry is scheduled after a stream drop
 */
export interface ReconnectEvent {
    attempt: number;                           // 1-based retry number since the last stable playback
    delay: number;                             // ms until the retry runs (deferred to the online event while offline)
    reason: 'error' | 'ended' | 'stalled';     // what was detected
}

/**
 * Casting path of this browser: Google Cast (Chromium) or AirPlay (WebKit)
 */
export type CastKind = 'chromecast' | 'airplay';

/**
 * Casting state shown by the template's .cast button
 */
export type CastState = 'unavailable' | 'available' | 'connecting' | 'connected';

/**
 * Payload of the `cast.change` event, emitted whenever the casting state or device changes
 */
export interface CastChangeEvent {
    kind: CastKind;
    state: CastState;
    device?: string;                           // receiver name while connected (Chromecast only)
}

export enum ReadyState {
    HAVE_NOTHING = 0,
    HAVE_METADATA = 1,
    HAVE_CURRENT_DATA = 2,
    HAVE_FUTURE_DATA = 3,
    HAVE_ENOUGH_DATA = 4,
}

export enum NetworkState {
    NETWORK_EMPTY = 0,
    NETWORK_IDLE = 1,
    NETWORK_LOADING = 2,
    NETWORK_NO_SOURCE = 3,
}

export interface AudioStatus {
    currentTime: number;
    duration: number;
    paused: boolean;
    ended: boolean;
    playbackRate: number;
    volume: number;
    muted: boolean;
    seeking: boolean;
    buffered: TimeRanges;
    readyState: ReadyState;
    networkState: NetworkState;
    loop: boolean;
    preload: string;
    autoplay: boolean;
    src: string;
    textTracks: TextTrackList;
    error: MediaError | null;
}