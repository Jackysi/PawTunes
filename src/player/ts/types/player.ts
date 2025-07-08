/**
 * PawTunes Project - Open Source Radio Player
 *
 * Written by: Jaka Prasnikar
 * Website: https://prahec.com
 * Project URL: https://prahec.com/pawtunes
 *
 * This file is part of the PawTunes open-source project.
 * Feel free to contribute or provide feedback via the project URL.
 */
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