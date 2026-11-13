<?php

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
 * Now playing widget: index.php?widget[&channel=<name>][&theme=light|dark|transparent]
 * Required scope: $pawtunes (PawTunes)
 *
 * A small self-contained page (artwork, artist, title, channel name) meant for iframes and OBS browser
 * sources. It polls the track info endpoint of the same install, so it needs no CORS itself, but it is
 * part of the public API and therefore only served when the "api" setting is on.
 *
 * Every upstream string reaches the page through textContent or an attribute set from script, never
 * as markup. Server-rendered values are escaped (HTML) or encoded with the JSON_HEX_* flags (script).
 * Channels that publish track info over a WebSocket only get what the PHP endpoint returns (defaults).
 */

while (ob_get_level()) {
    ob_end_clean();
}

// Part of the public API: closed unless the admin enabled it
if ($pawtunes->config('api') !== true) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The now playing widget is disabled. Enable "Public API" in the PawTunes settings.');
}

// Channel: exact name match, otherwise index 0, the same fallback as the track info endpoint
$channels = $pawtunes->getChannels();
$channel  = reset($channels) ?: null; // index 0 may be gone: deletes do not reindex
foreach ($channels as $search) {
    if (isset($_GET['channel']) && $search['name'] === $_GET['channel']) {
        $channel = $search;
        break;
    }
}

if ( ! is_array($channel)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No channels are configured.');
}

// Theme whitelist, anything else is light
$theme = (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark', 'transparent'], true)) ? $_GET['theme'] : 'light';

// Same polling interval as the player (json_settings.refreshRate)
$refresh = (is_numeric($pawtunes->config('stats_refresh')) && $pawtunes->config('stats_refresh') >= 3) ? (int) $pawtunes->config('stats_refresh') : 15;

$settings = [
    'channel' => (string) $channel['name'],
    'refresh' => $refresh,
    'artist'  => (string) $pawtunes->config('artist_default'),
    'title'   => (string) $pawtunes->config('title_default'),
    'artwork' => $pawtunes->defaultArtwork(),
];

// JSON_HEX_* keeps "</script>", quotes and ampersands inert inside the inline script. JSON_INVALID_UTF8_SUBSTITUTE
// replaces bad bytes (a non-UTF-8 channel name or default text) instead of failing, which would print "var cfg = ;"
$json = json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

// Framing is the point of the widget, so no frame-ancestors. Inline style/script only, images from anywhere
// (provider covers are remote), requests only to this install.
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'self' https: http: data:; connect-src 'self'; base-uri 'none'; form-action 'none'");

?><!DOCTYPE html>
<html lang="en" class="theme-<?= $e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($channel['name']) ?> - <?= $e($pawtunes->config('title')) ?></title>
    <style>
        :root { --bg: #ffffff; --fg: #1d2129; --muted: #6b7280; --accent: #2563eb; --art-bg: #e5e7eb; --shadow: 0 1px 3px rgba(0, 0, 0, .12); }
        .theme-dark { --bg: #16181d; --fg: #f3f4f6; --muted: #9ca3af; --accent: #60a5fa; --art-bg: #2a2e36; }
        .theme-transparent { --bg: transparent; --fg: #ffffff; --muted: rgba(255, 255, 255, .8); --accent: #ffffff; --art-bg: rgba(0, 0, 0, .25); --shadow: none; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: var(--bg); color: var(--fg); }
        body { font: 14px/1.35 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; overflow: hidden; }
        .theme-transparent body { text-shadow: 0 1px 3px rgba(0, 0, 0, .75); }
        .widget { display: flex; align-items: center; gap: 12px; height: 100%; padding: 10px; }
        .artwork { flex: none; width: 64px; height: 64px; border-radius: 8px; background: var(--art-bg); object-fit: cover; box-shadow: var(--shadow); transition: opacity .3s ease; }
        .artwork.is-empty { visibility: hidden; }
        .meta { min-width: 0; flex: 1; }
        .channel { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--accent); }
        .title { font-size: 16px; font-weight: 700; }
        .artist { color: var(--muted); }
        .channel, .title, .artist { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fade { animation: fade .4s ease; }
        @keyframes fade { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .fade { animation: none; } .artwork { transition: none; } }
    </style>
</head>
<body>
<div class="widget" aria-live="polite">
    <img class="artwork is-empty" alt="">
    <div class="meta">
        <div class="channel"><?= $e($channel['name']) ?></div>
        <div class="title"></div>
        <div class="artist"></div>
    </div>
</div>
<script>
    (function () {

        var cfg = <?= $json ?>;
        var art = document.querySelector('.artwork');
        var title = document.querySelector('.title');
        var artist = document.querySelector('.artist');
        var meta = document.querySelector('.meta');
        var last = '';

        // Upstream strings only ever go through textContent and the src property
        function render(info) {
            var a = (typeof info.artist === 'string' && info.artist !== '') ? info.artist : cfg.artist;
            var t = (typeof info.title === 'string' && info.title !== '') ? info.title : cfg.title;
            var img = info.artwork || info.image || cfg.artwork; // "image" is the disabled-method shape

            var key = a + '\n' + t + '\n' + (img || '');
            if (key === last) {
                return;
            }
            last = key;

            artist.textContent = a;
            title.textContent = t;
            title.title = t;
            artist.title = a;

            if (typeof img === 'string' && img !== '') {
                art.src = img;
                art.classList.remove('is-empty');
            } else {
                art.removeAttribute('src');
                art.classList.add('is-empty');
            }

            meta.classList.remove('fade');
            void meta.offsetWidth; // restart the animation
            meta.classList.add('fade');
        }

        // A broken cover falls back to the default artwork once, then hides
        art.addEventListener('error', function () {
            if (cfg.artwork && art.getAttribute('src') !== cfg.artwork) {
                art.src = cfg.artwork;
            } else {
                art.classList.add('is-empty');
            }
        });

        function poll() {
            fetch('index.php?channel=' + encodeURIComponent(cfg.channel), {credentials: 'same-origin'})
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (info) {
                    // The error shape ({cache} only) keeps whatever is shown
                    if (info && (info.artist || info.title)) {
                        render(info);
                    }
                })
                .catch(function () { /* keep the last track, retry on the next tick */ });
        }

        render({});
        poll();
        setInterval(poll, cfg.refresh * 1000);

    })();
</script>
</body>
</html>
