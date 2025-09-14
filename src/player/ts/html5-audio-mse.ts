import {PawMediaSource} from "./types";
import HTML5Audio from "./html5-audio";

/**
 * HTML5AudioMSE
 * - Streams ICY MP3 (and other supported) via Media Source Extensions.
 * - Exact-byte ICY parsing (no drift), generation-guarded to prevent mixing
 *   old/new buffers during channel switches.
 */
export default class HTML5AudioMSE extends HTML5Audio {

    private reader: ReadableStreamDefaultReader<Uint8Array> | null | undefined;
    private mediaSource?: MediaSource;
    private sourceBuffer?: SourceBuffer;
    private abortCtrl?: AbortController;
    private icyMetaInt?: number;
    private objectUrl?: string;
    private trimTimer?: number | null;

    /** Bumped on every setMedia() to invalidate stale loops. */
    private gen = 0;

    constructor(container: string) {
        super(container);
    }

    /**
     * Replace current media with new sources. Resolves only after MSE is ready.
     */
    async setMedia(sources: PawMediaSource[]): Promise<void> {

        if (!this.audio) throw new Error("No audio element");
        if (!("MediaSource" in window)) throw new Error("MSE not supported");

        const wasPlaying = !!this.audio && !this.audio.paused;

        // Hard-stop previous stream and invalidate in-flight readers.
        this.gen++;
        await this.teardown();

        // Select first supported source.
        this.media = [];
        const picked = sources.find((s) =>
            MediaSource.isTypeSupported(this.formats[s.type].codec)
        );

        if (!picked) throw new Error("No supported media source");
        this.media.push(picked);

        // Create & attach fresh MediaSource, wait for it to open.
        const myGen = this.gen;
        this.mediaSource = new MediaSource();
        this.objectUrl = URL.createObjectURL(this.mediaSource);
        this.audio.src = this.objectUrl;

        await new Promise<void>((resolve) => {
            this.mediaSource!.addEventListener(
                "sourceopen",
                async () => {
                    if (this.gen !== myGen || !this.mediaSource) return resolve();
                    try {
                        const mime = this.formats[this.media[0].type].codec;
                        this.sourceBuffer = this.mediaSource.addSourceBuffer(mime);
                        this.sourceBuffer.mode = "sequence";
                        this.startTrimTimer();
                        await this.fetchAndStream(myGen);
                    } catch (err) {
                        console.error("sourceopen/start", err);
                    } finally {
                        resolve(); // MSE ready (SB created, fetch kicked off)
                    }
                },
                {once: true}
            );
        });

        // Hint autoplay/resume (PawTunes also calls play()—this is harmless).
        this.audio.autoplay = true;
        if (wasPlaying || this.autoplay) this.audio.play().catch(() => {
        });

        this.ready = true;
        this.trigger("ready");

    }

    async stop(): Promise<void> {

        await this.teardown();
        await super.stop();

    }

    destroy(): void {

        void this.teardown();
        super.destroy();

    }

    // --- Core streaming --------------------------------------------------------
    private async fetchAndStream(myGen: number): Promise<void> {

        if (this.gen !== myGen) return;

        const url = this.media[0].src;
        this.abortCtrl = new AbortController();

        const resp = await fetch(url, {
            method: "GET",
            headers: {"Icy-MetaData": "1"},
            signal: this.abortCtrl.signal,
        });

        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

        this.icyMetaInt = parseInt(resp.headers.get("icy-metaint") || "0", 10);
        this.reader = resp.body!.getReader();

        if (this.icyMetaInt > 0) {
            await this.readIcy(myGen);
        } else {
            await this.readPlain(myGen);
        }

    }

    /** Pass-through for non-ICY streams. */
    private async readPlain(myGen: number): Promise<void> {

        if (!this.reader) return;

        while (true) {

            if (this.gen !== myGen) return;
            const {done, value} = await this.reader.read();
            if (done) break;
            if (this.gen !== myGen) return;
            await this.append(value!);

        }

        this.endOfStream();

    }

    /** Exact-byte ICY reader: never mixes meta bytes into audio. */
    private async readIcy(myGen: number): Promise<void> {

        if (!this.reader || !this.sourceBuffer || !this.icyMetaInt) return;

        const decoder = new TextDecoder("utf-8");
        let stash = new Uint8Array(0);

        const readExact = async (n: number): Promise<Uint8Array> => {

            while (stash.length < n) {

                if (this.gen !== myGen) throw new Error("switched");
                const r = await this.reader!.read();
                if (r.done) throw new Error("ended");
                const next = r.value!;
                const buf = new Uint8Array(stash.length + next.length);
                buf.set(stash, 0);
                buf.set(next, stash.length);
                stash = buf;

            }

            const out = stash.subarray(0, n);
            stash = stash.subarray(n);
            return out;

        };

        try {
            while (true) {

                if (this.gen !== myGen) return;

                // 1) Exactly icyMetaInt bytes of AUDIO
                const audioChunk = await readExact(this.icyMetaInt);
                await this.append(audioChunk);

                // 2) 1 byte length (units of 16 bytes), then metadata bytes
                const metaUnits = (await readExact(1))[0];
                const metaLen = metaUnits * 16;
                if (metaLen > 0) {
                    const metaRaw = await readExact(metaLen);
                    const metaStr = decoder.decode(metaRaw).replace(/\0+$/g, "");
                    const m = /StreamTitle='([^']*)';/i.exec(metaStr);
                    if (m && m[1] != null) this.trigger("metadata", m[1]);
                }

            }
        } catch (err: any) {

            if (err?.message !== "ended" && err?.message !== "switched") {
                console.error("ICY", err);
            }

            this.endOfStream();

        }
    }

    private async append(chunk: Uint8Array): Promise<void> {

        if (
            !chunk?.length ||
            !this.sourceBuffer ||
            !this.mediaSource ||
            this.mediaSource.readyState !== "open"
        ) {
            return;
        }

        if (this.sourceBuffer.updating) {
            await new Promise<void>((res) =>
                this.sourceBuffer!.addEventListener("updateend", () => res(), {
                    once: true,
                })
            );
        }

        try {
            this.sourceBuffer.appendBuffer(chunk);
        } catch (err) {
            console.error("appendBuffer", err);
        }

    }

    private endOfStream(): void {

        if (this.mediaSource?.readyState === "open") {
            try {
                this.mediaSource.endOfStream();
            } catch {
                /* noop */
            }
        }

    }

    /** Periodically trims old buffered data to keep memory in check. */
    private startTrimTimer(): void {

        this.stopTrimTimer();
        const KEEP_SECONDS = 30;
        this.trimTimer = window.setInterval(() => {
            if (!this.sourceBuffer || !this.audio || this.sourceBuffer.updating) return;
            const cut = this.audio.currentTime - KEEP_SECONDS;
            if (cut > 0) {
                try {
                    this.sourceBuffer.remove(0, cut);
                } catch (err) {
                    console.error("remove", err);
                }
            }
        }, 5000);

    }

    private stopTrimTimer(): void {

        if (this.trimTimer) {
            clearInterval(this.trimTimer);
            this.trimTimer = null;
        }

    }

    /** Closes network + MSE resources. Safe to call repeatedly. */
    private async teardown(): Promise<void> {

        this.stopTrimTimer();

        // Network
        this.abortCtrl?.abort();
        this.abortCtrl = undefined;
        if (this.reader) {
            try {
                await this.reader.cancel();
            } catch {
                /* noop */
            }
            this.reader = null;
        }

        // MSE
        const sb = this.sourceBuffer;
        const ms = this.mediaSource;
        this.sourceBuffer = undefined;
        this.mediaSource = undefined;
        this.icyMetaInt = undefined;

        if (sb) {
            try {
                if (sb.updating) {
                    await new Promise<void>((res) =>
                        sb.addEventListener("updateend", () => res(), {once: true})
                    );
                }
                sb.abort();
                ms?.removeSourceBuffer(sb);
            } catch {
                /* noop */
            }
        }

        if (ms && ms.readyState === "open") {
            try {
                ms.endOfStream();
            } catch {
                /* noop */
            }
        }

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = undefined;
        }

        if (this.audio) {
            this.audio.pause();
            this.audio.removeAttribute("src");
            this.audio.load();
        }

    }

}