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

import {PawMediaSource} from "./types";
import HTML5Audio from "./html5-audio";

export default class HTML5AudioMSE extends HTML5Audio {

    reader: ReadableStreamDefaultReader | null | undefined;
    customMediaSource: MediaSource | undefined;
    sourceBuffer: SourceBuffer | undefined;
    fetchController: AbortController | undefined;
    icyMetaInt: number | undefined;
    bufferRemoveInterval: number | null | undefined;

    constructor(container: string) {
        super();
    }

    async setMedia(sources: PawMediaSource[]): Promise<void> {

        if (!this.audio) {
            throw new Error('No audio element found!');
        }

        if (!('MediaSource' in window)) {
            throw new Error('Custom MediaSource not supported!');
        }

        // If we're placing new media, clear old
        if (this.media.length > 0) {
            this.media = [];
        }

        // Find a supported media
        let firstSupported;
        for (const source of sources) {
            if (MediaSource.isTypeSupported(this.formats[source.type].codec)) {
                firstSupported = source;
                this.media.push(source);
                break;
            }
        }

        if (!firstSupported) {
            throw new Error('No supported media source found!');
        }

        // @ts-ignore
        this.mse = {};
        this.customMediaSource = new MediaSource();
        this.audio.src = URL.createObjectURL(this.customMediaSource);

        // Trigger open
        this.customMediaSource.addEventListener('sourceopen', (): Promise<void> => this.openCustomMediaSource());

        // We are ready!
        this.ready = true
        this.trigger('ready')

    }


    private async readStreamWithoutMetadata() {

        if (!this.reader) return;
        while (true) {
            const {done, value} = await this.reader.read();
            if (done) {

                if (this.customMediaSource && this.customMediaSource.readyState === 'open') {

                    this.customMediaSource.endOfStream();

                }
                break;

            }

            this.appendToSourceBuffer(value!);

        }
    }

    private appendToSourceBuffer(chunk: Uint8Array) {

        if (!chunk || chunk.length === 0 || !this.sourceBuffer || !this.customMediaSource) return;

        if (this.sourceBuffer.updating || this.customMediaSource.readyState !== 'open') {

            this.sourceBuffer.addEventListener('updateend', () => this.appendToSourceBuffer(chunk), {once: true});

        } else {

            try {

                this.sourceBuffer.appendBuffer(chunk);

            } catch (e) {

                console.error('Error appending to SourceBuffer:', e);

            }
        }
    }

    private parseMetadata(metaString: string) {

        const matches = /StreamTitle='([^']*)';/.exec(metaString);
        if (matches && matches[1]) {

            const trackTitle = matches[1];
            this.trigger('metadata', trackTitle);

        }

    }

    private startBufferManagement() {

        const BUFFER_THRESHOLD = 30; // seconds
        this.bufferRemoveInterval = window.setInterval(() => {
            if (!this.sourceBuffer || !this.audio) return;

            const currentTime = this.audio.currentTime;
            const bufferThreshold = currentTime - BUFFER_THRESHOLD;

            if (bufferThreshold > 0 && !this.sourceBuffer.updating) {

                try {

                    this.sourceBuffer.remove(0, bufferThreshold);

                } catch (e) {

                    console.error('Error removing buffered data:', e);

                }
            }

        }, 5000); // Check every 5 seconds
    }

    private async openCustomMediaSource() {

        if (!this.customMediaSource) return;
        try {

            this.sourceBuffer = this.customMediaSource.addSourceBuffer(this.formats[this.media[0].type].codec);
            this.sourceBuffer.mode = 'sequence';

        } catch (e) {

            console.error('Custom MediaSource: Exception calling addSourceBuffer:', e);

        }

        await this.fetchAndStream();
        this.startBufferManagement();

    }

    private async fetchAndStream(): Promise<void> {

        if (this.media.length === 0) {
            throw new Error('Custom MediaSource: No media sources provided!');
        }

        const streamUrl = this.media[0].src;

        this.fetchController = new AbortController();
        const signal = this.fetchController.signal;

        try {
            const response = await fetch(streamUrl, {
                method: 'GET',
                headers: {
                    'Icy-MetaData': '1', // Request metadata
                },
                signal: signal,
            });

            if (!response.ok) {
                throw new Error(`Custom MediaSource: HTTP error! Status: ${response.status}`);
            }

            this.icyMetaInt = parseInt(response.headers.get('icy-metaint') || '0');
            if (this.icyMetaInt > 0) {

                this.reader = response.body!.getReader();
                await this.readStreamWithMetadata();

            } else {

                console.error('Stream does not provide ICY metadata.');
                this.reader = response.body!.getReader();
                await this.readStreamWithoutMetadata();

            }

        } catch (error: any) {

            if (error.name !== 'AbortError') {
                console.error('Fetch error:', error);
            }

        }

    }

    private async readStreamWithMetadata() {

        if (!this.reader || !this.sourceBuffer || !this.icyMetaInt) return;
        let bytesRead = 0;

        while (true) {

            // Read audio data
            let {done, value} = await this.reader.read();
            if (done) {
                if (this.customMediaSource && this.customMediaSource.readyState === 'open') {
                    this.customMediaSource.endOfStream();
                }
                break;
            }

            let offset = 0;
            while (offset < value!.length) {

                const remainingUntilMeta = this.icyMetaInt - (bytesRead % this.icyMetaInt);
                const chunkSize = Math.min(remainingUntilMeta, value!.length - offset);

                // Extract audio data
                const audioChunk = value!.slice(offset, offset + chunkSize);
                this.appendToSourceBuffer(audioChunk);

                bytesRead += chunkSize;
                offset += chunkSize;

                if (bytesRead % this.icyMetaInt === 0) {

                    // Read metadata length
                    if (offset >= value!.length) {

                        // Need to read the next chunk to get metadata length
                        ({done, value} = await this.reader.read());
                        if (done) break;
                        offset = 0;

                    }

                    const metaLength = value![offset] * 16; // Metadata length in bytes
                    offset += 1;

                    if (metaLength > 0) {

                        let remainingMetaData: Uint8Array;
                        if (offset + metaLength > value!.length) {

                            // Need to read the next chunk to get full metadata
                            remainingMetaData = new Uint8Array(metaLength);
                            let copied = value!.length - offset;
                            remainingMetaData.set(value!.slice(offset), 0);

                            ({done, value} = await this.reader.read());
                            if (done) break;

                            remainingMetaData.set(value!.slice(0, metaLength - copied), copied);
                            offset = metaLength - copied;

                        } else {

                            remainingMetaData = value!.slice(offset, offset + metaLength);
                            offset += metaLength;

                        }

                        const metaString = new TextDecoder('utf-8').decode(remainingMetaData);
                        this.parseMetadata(metaString);
                    }

                }

            }
            // END WHILE
        }
        // END OUTER WHILE

    }


}