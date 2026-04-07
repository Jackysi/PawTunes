import {PawMediaSource} from "./types";
import HTML5Audio from "./html5-audio";

const RECONNECT_DELAY = 2000;
const TRIM_INTERVAL = 10_000;
const TRIM_KEEP_SEC = 30;

/**
 * HTML5AudioMSE
 *
 * Streams audio via Media Source Extensions with ICY metadata parsing.
 * Generation-guarded to prevent mixing old/new buffers during channel switches.
 * Auto-reconnects on stream end and rebuilds the MSE pipeline on decode errors.
 */
export default class HTML5AudioMSE extends HTML5Audio {

    private reader: ReadableStreamDefaultReader<Uint8Array> | null = null;
    private mediaSource?: MediaSource;
    private sourceBuffer?: SourceBuffer;
    private abortCtrl?: AbortController;
    private objectUrl?: string;
    private icyMetaInt = 0;
    private trimTimer: number | null = null;
    private gen = 0;
    private icyUnsupported = false;


    // ── Public API ────────────────────────────────────────────────────────────
    async setMedia(sources: PawMediaSource[]): Promise<void> {

        if (!this.audio) throw new Error("No audio element");
        if (!("MediaSource" in window)) throw new Error("MSE not supported");

        const wasPlaying = !this.audio.paused;

        this.gen++;
        this.icyUnsupported = false;
        await this.teardown();

        // Select first supported source
        this.media = [];
        const picked = sources.find(s => MediaSource.isTypeSupported(this.formats[s.type].codec));
        if (!picked) throw new Error("No supported media source");
        this.media.push(picked);

        // Wire up MSE and start streaming in the background
        const myGen = this.gen;
        await this.initPipeline(myGen);
        this.streamLoop(myGen);

        this.audio.autoplay = true;
        if (wasPlaying || this.autoplay) this.audio.play().catch(() => {
        });

        this.ready = true;
        this.trigger("ready");

    }

    /**
     * Full stop — tears down MSE without restarting.
     * Does NOT call super.stop() because the parent re-calls setMedia().
     */
    async stop(): Promise<void> {

        this.gen++;
        await this.teardown();
        this.trigger('stop');
        this.ready = false;

    }

    destroy(): void {

        this.gen++;
        void this.teardown();
        super.destroy();

    }

    /**
     * Intercepts events for MSE-specific handling:
     * - Suppresses transient decode errors (pipeline rebuilds automatically).
     * - Emits metadata directly (parent replaces falsy "" with status()).
     */
    protected trigger(event: string, data: any = null) {

        if (event === 'error' && data?.code === MediaError.MEDIA_ERR_DECODE) {
            console.warn("MSE: decode error, pipeline will rebuild");
            return;
        }

        if (event === 'metadata') {
            this.emit(event, data);
            return;
        }

        super.trigger(event, data);

    }


    // ── Pipeline lifecycle ────────────────────────────────────────────────────
    /**
     * Creates a fresh MediaSource + SourceBuffer and wires it to the audio
     * element. Returns once the SourceBuffer is ready to accept data.
     */
    private async initPipeline(myGen: number): Promise<void> {

        if (!this.audio || this.gen !== myGen) return;

        this.mediaSource = new MediaSource();
        this.objectUrl = URL.createObjectURL(this.mediaSource);
        this.audio.src = this.objectUrl;

        await new Promise<void>((resolve) => {
            this.mediaSource!.addEventListener("sourceopen", () => {

                if (this.gen !== myGen || !this.mediaSource) {
                    resolve();
                    return;
                }

                try {
                    const mime = this.formats[this.media[0].type].codec;
                    this.sourceBuffer = this.mediaSource!.addSourceBuffer(mime);
                    this.sourceBuffer.mode = "sequence";
                    this.startTrimTimer();
                } catch (err) {
                    console.error("initPipeline", err);
                }

                resolve();

            }, {once: true});
        });

    }

    /**
     * Recovers from a broken MSE pipeline (e.g. post-decode-error).
     * Tears everything down, then builds a fresh pipeline without changing gen
     * so the calling streamLoop can continue.
     */
    private async rebuildPipeline(myGen: number): Promise<void> {

        if (!this.audio || this.gen !== myGen) return;

        await this.teardown();
        if (this.gen !== myGen) return;

        await this.initPipeline(myGen);

        if (this.audio) {
            this.audio.autoplay = true;
            this.audio.play().catch(() => {
            });
        }

    }

    /**
     * Releases all network + MSE + audio resources. Safe to call repeatedly.
     * Pauses audio first to prevent stalled/waiting events during cleanup.
     */
    private async teardown(): Promise<void> {

        this.stopTrimTimer();

        if (this.audio) {
            this.audio.pause();
            this.audio.autoplay = false;
        }

        // Network
        this.abortCtrl?.abort();
        this.abortCtrl = undefined;
        if (this.reader) {
            try {
                await this.reader.cancel();
            } catch { /* noop */
            }
            this.reader = null;
        }

        // MSE — clean up while still connected to the audio element
        const sb = this.sourceBuffer;
        const ms = this.mediaSource;
        this.sourceBuffer = undefined;
        this.mediaSource = undefined;
        this.icyMetaInt = 0;

        if (sb) {
            try {

                if (sb.updating) {
                    await new Promise<void>(r => sb.addEventListener("updateend", r, {once: true}));
                }

                sb.abort();
                ms?.removeSourceBuffer(sb);

            } catch { /* noop */
            }
        }

        if (ms?.readyState === "open") {
            try {
                ms.endOfStream();
            } catch { /* noop */
            }
        }

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = undefined;
        }

        if (this.audio) {
            this.audio.removeAttribute("src");
            this.audio.load();
        }

    }


    // ── Streaming ─────────────────────────────────────────────────────────────
    /**
     * Keeps the stream alive across disconnects and decode errors.
     * Exits when gen changes (stop or channel switch).
     */
    private async streamLoop(myGen: number): Promise<void> {

        while (this.gen === myGen) {

            try {
                await this.fetchAndStream(myGen);
            } catch (err: any) {
                if (this.gen !== myGen) return;
                console.warn("Stream interrupted, reconnecting…", err?.message);
            }

            if (this.gen !== myGen) return;

            // Decode error leaves audio.error set and SourceBuffer unusable
            if (this.audio?.error) {

                await this.rebuildPipeline(myGen);
                if (this.gen !== myGen) return;
                continue;

            }

            await new Promise(r => setTimeout(r, RECONNECT_DELAY));

        }

    }

    private async fetchAndStream(myGen: number): Promise<void> {

        if (this.gen !== myGen) return;

        const url = this.media[0].src;
        this.abortCtrl = new AbortController();
        const {signal} = this.abortCtrl;

        // Build headers — skip ICY if a previous attempt proved it unsupported
        const headers: Record<string, string> = {};
        if (!this.icyUnsupported) headers["Icy-MetaData"] = "1";

        let resp: Response;
        try {

            resp = await fetch(url, {method: "GET", headers, signal});

        } catch (err) {

            // Already know ICY is unsupported, or gen changed — don't retry
            if (this.gen !== myGen || this.icyUnsupported) throw err;

            // ICY header may have caused a CORS preflight failure — retry without it.
            // Only mark icyUnsupported if the retry actually succeeds (otherwise
            // it was a server issue, not an ICY issue, and we'd lose metadata permanently).
            try {
                resp = await fetch(url, {method: "GET", signal});
                this.icyUnsupported = true;
            } catch {
                throw err;
            }

        }

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
            if (done) return;
            if (this.gen !== myGen) return;
            await this.append(value!);

        }

    }

    /** Exact-byte ICY reader — separates audio data from inline metadata. */
    private async readIcy(myGen: number): Promise<void> {

        if (!this.reader || !this.sourceBuffer || !this.icyMetaInt) return;

        const decoder = new TextDecoder("utf-8");
        let stash = new Uint8Array(0);

        const readExact = async (n: number): Promise<Uint8Array> => {

            while (stash.length < n) {

                if (this.gen !== myGen) throw new Error("switched");

                const r = await this.reader!.read();
                if (r.done) throw new Error("ended");
                const buf = new Uint8Array(stash.length + r.value!.length);

                buf.set(stash, 0);
                buf.set(r.value!, stash.length);
                stash = buf;

            }

            const out = stash.subarray(0, n);
            stash = stash.subarray(n);
            return out;

        };

        try {
            while (true) {

                if (this.gen !== myGen) return;

                // Audio block — exactly icyMetaInt bytes
                await this.append(await readExact(this.icyMetaInt));

                // Metadata block — 1 length byte + N*16 payload bytes
                const metaLen = (await readExact(1))[0] * 16;
                if (metaLen > 0) {

                    const text = decoder.decode(await readExact(metaLen)).replace(/\0+$/g, "");
                    const m = /StreamTitle='([^']*)';/i.exec(text);
                    if (m && m[1] != null) this.trigger("metadata", m[1]);

                }

            }
        } catch (err: any) {

            if (err?.message === "switched") return;
            if (err?.message !== "ended") console.error("ICY read error:", err);

        }
    }


    // ── SourceBuffer helpers ──────────────────────────────────────────────────

    /**
     * Appends audio data to the SourceBuffer.
     * Throws "mse-error" if the pipeline is broken, signaling streamLoop to rebuild.
     * Uses a while-loop to wait because the trim timer can call remove()
     * between an updateend and our appendBuffer — a single check races.
     */
    private async append(chunk: Uint8Array): Promise<void> {

        if (!chunk?.length || !this.sourceBuffer || !this.mediaSource || this.mediaSource.readyState !== "open") {
            return;
        }

        if (this.audio?.error) throw new Error("mse-error");

        while (this.sourceBuffer && this.sourceBuffer.updating) {
            await new Promise<void>(r => this.sourceBuffer!.addEventListener("updateend", r, {once: true}));
        }

        if (!this.sourceBuffer || !this.mediaSource || this.mediaSource.readyState !== "open" || this.sourceBuffer.updating) {
            return;
        }

        try {
            this.sourceBuffer.appendBuffer(chunk);
        } catch (err) {
            console.error("appendBuffer", err);
        }

    }

    private startTrimTimer(): void {

        this.stopTrimTimer();
        this.trimTimer = window.setInterval(() => {

            if (!this.sourceBuffer || !this.audio || this.sourceBuffer.updating) {
                return;
            }

            const cut = this.audio.currentTime - TRIM_KEEP_SEC;
            if (cut > 0) {
                try {
                    this.sourceBuffer.remove(0, cut);
                } catch { /* noop */
                }
            }

        }, TRIM_INTERVAL);

    }

    private stopTrimTimer(): void {

        if (this.trimTimer) {

            clearInterval(this.trimTimer);
            this.trimTimer = null;

        }

    }

}