import HTML5Audio from "../html5-audio";

export interface OnAir {
    artist: string,
    title: string,
    artwork: string | null,
}

export interface Settings {
    artworkTypes: {},
    channel: {},
    channels: {},
    history: [],
    player: HTML5Audio,
    prefix: string,
    quality: null,
    status: "online",
    temp: any[],
    timers: any[],
    url: string,
    ws: null,
}