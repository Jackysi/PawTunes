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