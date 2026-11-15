![PawTunes Logo](https://cdn.prahec.com/projects/pawtunes/pawtunes-dark.png)

# PawTunes - Open Source Web Radio Player

[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](#requirements)
[![License](https://img.shields.io/github/license/Jackysi/pawtunes)](LICENSE)
[![Docker Pulls](https://img.shields.io/docker/pulls/jackyprahec/pawtunes)](https://hub.docker.com/r/jackyprahec/pawtunes)
[![GitHub Stars](https://img.shields.io/github/stars/Jackysi/pawtunes?style=social)](https://github.com/Jackysi/pawtunes)
[![Latest Release](https://img.shields.io/github/v/release/Jackysi/pawtunes)](https://github.com/Jackysi/pawtunes/releases)

**PawTunes** is a free, open-source web radio player with real-time track info, artwork fetching, and multi-channel streaming.
Supports Shoutcast, Icecast, AzuraCast, direct ICY metadata, and more. Self-hosted PHP backend with Docker support.

Built from the ground up with over 15 years of experience, PawTunes combines modern web technologies, elegant design,
and high performance to deliver a complete radio player platform.
It's not just a JavaScript audio library - it's a full-stack app that fetches live track information, manages album artwork from multiple APIs,
and includes a control panel for managing every aspect of your radio player.

---

## Table of Contents

- [Quick Start](#quick-start)
- [Demo & Screenshots](#demo--screenshots)
- [Why PawTunes?](#why-pawtunes)
- [Features](#features)
- [Installation](#installation)
- [Docker](#docker)
- [Track Info APIs](#track-info-apis)
- [Artwork APIs](#artwork-apis)
- [Developer API & Customization](#developer-api--customization)
- [Scalability & Caching](#scalability--caching)
- [Requirements](#requirements)
- [Documentation](#documentation)
- [Links](#links)
- [Credits](#credits)
- [Support the Project](#support-the-project)

---

## Quick Start

### Docker (recommended)
```bash
docker run -d -p 80:80 jackyprahec/pawtunes:latest
```
Then visit `http://localhost/panel/` - login with `admin` / `password`.

### Manual Upload
Upload the release ZIP to your web server, then visit:
```
http://your-host.com/pawtunes/panel/
```
Login: `admin` / `password`

See [Installation](#-installation) for detailed setup instructions.

---

## Demo & Screenshots

Explore the live demo:
- **Control Panel**: [https://prahec.com/projects/pawtunes/demo/panel/](https://prahec.com/projects/pawtunes/demo/panel/)  
  **Username**: `admin`  
  **Password**: `password`

##### Aurora (default)
The music rises behind the player as northern lights, in the colours of the current artwork. One layout for every size: desktop, phone, popup and a strip for your website.

[![PawTunes Aurora template, Night scheme: a blue aurora and play button taken from the artwork, with the played songs in a side rail](https://cdn.prahec.com/projects/pawtunes/screens/aurora-night-desktop.webp)](https://cdn.prahec.com/projects/pawtunes/screens/aurora-night-desktop.webp)

<p>
  <img src="https://cdn.prahec.com/projects/pawtunes/screens/aurora-night-phone.webp" width="32%" alt="Aurora on a phone, Night scheme">
  <img src="https://cdn.prahec.com/projects/pawtunes/screens/aurora-day-phone.webp" width="32%" alt="Aurora on a phone, Day scheme">
  <img src="https://cdn.prahec.com/projects/pawtunes/screens/aurora-polar-phone.webp" width="32%" alt="Aurora on a phone, Night scheme with artwork colours off">
</p>

##### Aurora: Day
[![PawTunes Aurora template, Day scheme: a light sky with a gold aurora taken from the artwork](https://cdn.prahec.com/projects/pawtunes/screens/aurora-day-desktop.webp)](https://cdn.prahec.com/projects/pawtunes/screens/aurora-day-desktop.webp)

##### Aurora: Night with artwork colours off
[![PawTunes Aurora template with its own teal and violet aurora instead of the artwork colours](https://cdn.prahec.com/projects/pawtunes/screens/aurora-polar-desktop.webp)](https://cdn.prahec.com/projects/pawtunes/screens/aurora-polar-desktop.webp)

##### Aurora: popup and website strip
<p>
  <img src="https://cdn.prahec.com/projects/pawtunes/screens/aurora-day-popup.webp" width="58%" alt="Aurora in a 960 x 600 popup, Day scheme">
</p>
<p>
  <img src="https://cdn.prahec.com/projects/pawtunes/screens/aurora-night-strip.webp" width="58%" alt="Aurora as a 728 x 120 strip for a website">
</p>

##### PawTunes
[![PawTunes template with circular artwork and spectrum visualizer](https://cdn.prahec.com/projects/pawtunes/screens/20241129175123.png)](https://cdn.prahec.com/projects/pawtunes/screens/20241129175123.png)

##### Modern Light
[![PawTunes Modern Light theme with ring visualizer and mobile-first design](https://cdn.prahec.com/projects/pawtunes/screens/modern-light.png)](https://cdn.prahec.com/projects/pawtunes/screens/modern-light.png)

##### Modern Dark
[![PawTunes Modern Dark theme with glassmorphism UI and animated gradient background](https://cdn.prahec.com/projects/pawtunes/screens/modern-dark.png)](https://cdn.prahec.com/projects/pawtunes/screens/modern-dark.png)

---

## Why PawTunes?

| Feature | PawTunes | Typical Players |
|---|---|---|
| Multi-channel streaming | Unlimited channels | 1-3 channels |
| Track info APIs | 7+ (Shoutcast, Icecast, AzuraCast, CentovaCast...) | 1-2 |
| Artwork sources | Spotify, iTunes, LastFM, FanArt TV, Custom | iTunes only |
| Caching | Redis, Memcached, APCu, Disk | None |
| Docker support | Yes, with persistent volumes | Rare |
| Self-hosted | Full control, no SaaS dependency | Often SaaS |
| Templates | 3 included with 6 color schemes, fully customizable | 1 fixed layout |
| WebSocket support | Built-in for live updates | Not available |
| Artwork management | Auto-crop, optimize, cache | Pass-through only |
| PHP backend | Full control panel with API integrations | JavaScript only |

---

## Features

### Frontend
- **Responsive Design**: Optimized for seamless performance on desktops, tablets, and mobiles.
- **HTML5 Audio API**: Fully compatible with all major browsers, ensuring a consistent experience.
- **Unlimited Multi-Channels**: Configure an unlimited number of channels per player.
- **Multi-bitrate Streaming**: Users can select their preferred stream quality (optional).
- **Multi-language Support**: Automatically adjusts based on browser locale.
- **Dynamic Playlist Generation**: Create streaming playlists on the fly with PHP.
- **Built-in WebSocket Support**: Ensures low-latency communication.
- **Artwork Caching and Management**: Fetch and store images from APIs like Spotify, iTunes, and FanArtTV.
- **Customizable Themes**: Includes multiple templates with the ability to create custom colour schemes.
- **Dynamic Window Title**: Updates the browser window title dynamically based on the current track info.
- **MediaInfoAPI**: Similar to YouTube, **PawTunes** displays artwork/track info on any Bluetooth device.
- **Easy Customization**: With minimal development skills, you can tailor the front-end to your preferences.
- **Stream History**: Track stream history for each channel using API data or generate it dynamically.
- **Auto-Reconnect**: Automatically reconnects streams in case of browser disconnection or network errors.
- **Casting**: Chromecast (Chromium browsers) and AirPlay (Safari, iOS) buttons in every template, see [Casting](#casting-chromecast-and-airplay).
- **Installable**: a web app manifest with your station's name and icon, so "Add to Home Screen" installs a branded app.
- **Now Playing Widget**: embed the current track on any website or in OBS as a browser source.
- **and much more...**

### Backend
- **PHP Backend**: Powers advanced features like track info, artwork management, API integrations, and more
- **Caching Options**: Supports APCu, Redis, Memcached, and disk-based caching for high performance.
- **Control Panel**: Manage all settings, templates, and tracks via an intuitive dashboard.
- **AI Assistant** (optional): reads stream titles your regex cannot split, suggests a regex from samples and translates the player. Works with OpenAI, Anthropic or a local Ollama.
- **API Support**: Integrates seamlessly with APIs like Spotify and FanArtTV for enhanced metadata (details below).
- **Templates**: Use variables in HTML templates, with future support planned for the Blade templating engine.
- **Advanced Template Options**: Add custom templates with metadata-driven options (e.g. enable/disable spectrum, song search URL and much more) see `metadata.json` in the template folders.
- **and more...**

### Track Info APIs
- **Any stream** with embedded ICY-METADATA (used by most streams today).
- **Shoutcast** (Public & Admin access).
- **Icecast** (Admin access required).
- **AzuraCast** (Web Sockets and API integration).
- **Sam Broadcaster** (via database integration).
- **CentovaCast** (Public widget API).
- **Custom** (Use external APIs of your choice).

### Artwork APIs
- **iTunes**: Public API, no API key required.
- **Deezer**: Public API, no API key required. Good coverage of independent and electronic releases.
- **Spotify**: Public API, API key required.
- **LastFM**: Public API, API key required.
- **FanArt TV**: Public API, API key required.
- **Apple Music**: Apple Music API, developer key required.
- **AI Artwork** (optional): abstract art from OpenAI, Gemini or fal.ai for artists no other source knows, with a daily cost limit.
- **Custom**: Use your own sources, e.g., point to a folder or a URL like `https://page.com/{{$artist}}%20-%20{{$title}}.jpg`. You can also integrate with any other artwork service.

---

## Installation
Installation is straightforward in most cases, download the shared "ZIP" file and upload its contents to your web host.
Since **PawTunes** is a standalone PHP script/app, you only need to upload the files to a folder of your choice and then access that folder using the following URL format:  
`http://your-host.com/folder-name/panel/index.php`

> [!NOTE]
> Default Login Information  
> Username: **admin**  
> Password: **password**

If you encounter any issues during installation, please refer to the [Installation Guide](https://doc.prahec.com/pawtunes#installation) for detailed instructions.

I also offer installation and customization services. For more details, feel free to reach out via the [Prahec - Contact Me](https://prahec.com/contact) page.

### Docker
The easiest way to run **PawTunes** is with Docker.  
You can get started in seconds:
```bash
docker run -d -p 80:80 jackyprahec/pawtunes:latest
```

#### Persisting Data
To keep your configuration and data across container updates, you should mount the following paths:

- **/var/www/html/inc/config** - holds `general.php` and other config
- **/var/www/html/inc/locale** - translations/locales
- **/var/www/html/data** - holds artworks, cache, images

Example:
```bash
docker run \
  --name=pawtunes \
  -p 80:80 \
  -v /local/folder/config:/var/www/html/inc/config \
  -v /local/folder/locale:/var/www/html/inc/locale \
  -v /local/folder/data:/var/www/html/data \
  jackyprahec/pawtunes:latest
```

Since **PawTunes 1.0.7**, the `entrypoint.sh` script will automatically copy default config/locale/data files into empty volumes on first run.

PawTunes includes an Nginx server by default, but for TLS/HTTPS you'll usually want to put it behind a reverse proxy (e.g. Traefik, NGINX Proxy Manager, Caddy, etc...).  
You _can_ modify the bundled Nginx config for TLS directly, but that's out of scope here.

### Deployment and security
Everything PawTunes writes lives in `data/`. Only the images are meant to be public: the player links to
`data/images/` (artwork, logos, default artwork) and to the downloaded artwork copies in `data/cache/`.
Logs, cache entries (they can hold API tokens) and update archives are private, and nothing under `data/`
may ever run as a script.

**Apache**: the shipped `data/.htaccess`, `data/logs/.htaccess` and `data/updates/.htaccess` enforce this on
Apache 2.2 and 2.4. Grant the PawTunes directory `AllowOverride All`, or at least these override classes:
`AuthConfig` (the `Require` deny rules), `Limit` (the Apache 2.2 `Order`/`Deny` fallbacks) and `FileInfo`
(the `Content-Security-Policy` header set on served SVGs). The files intentionally do **not** use
`php_flag engine off`: that is an `Options`-class directive and would make Apache return 500 for every
`data/` URL on a mod_php host that does not also grant `Options`. Blocking the script requests by name is
enough.

**nginx** ignores `.htaccess`, so add the equivalent rules to your `server` block. Regex locations are matched
in order, so place these **before** your `location ~ \.php$` block (adjust the `/data/` prefix when PawTunes
is installed in a sub-folder):

```nginx
# Logs and update files are never served
location ~ ^/data/(logs|updates)/ { deny all; }

# Cache entries, logs and archives anywhere under data/
location ~ ^/data/.*\.(cache|log|zip)$ { deny all; }

# Nothing under data/ is ever executed: PHP in any position of the name (x.php.png included), other script
# types as the final extension only (artwork named after a track such as x.sh.y.jpeg stays servable)
location ~* ^/data/.*\.(php[3-8]?|phtml|phar|pht|phps)(\.|$) { deny all; }
location ~* ^/data/.*\.(cgi|pl|py|sh|asp|aspx|jsp|shtml)$ { deny all; }
```

The Docker image bundles its own nginx configuration; apply the same rules there if you replace it.

---

### Casting (Chromecast and AirPlay)
Every template has a cast button that appears once a device is available: Chromecast in Chromium browsers (Chrome, Edge, Opera, Android) through Google's Cast SDK, AirPlay in Safari and every iOS browser through the system picker. Firefox has neither, so it shows no button. Casting can be switched off in the control panel under Settings.

- **HTTPS** is required for Chromecast (the Cast SDK only works in a secure context); `localhost` works for development.
- The Chromecast fetches the stream itself, so the **stream URL must be reachable from the device**: a public host name, not a LAN address or a URL behind a login. HTTPS streams are preferred.
- **MP3 and AAC** streams play on every Cast device. FLAC, Ogg and WebM are supported by Cast audio devices only; the player prefers MP3, then AAC, then the rest of the stream group. A stream the device cannot play ends the cast session with a message and keeps playing in the browser.
- While casting, the play, stop and volume controls drive the device, track info keeps updating in the page, and the sleep timer stops the device. The device screen shows the channel name, the station title and the channel logo (or the default artwork); it cannot show track names, because the Cast receiver would have to reload the stream for every change.
- **Privacy**: Chromecast support loads `cast_sender.js` from `gstatic.com` in Chromium browsers; mention it in your privacy policy or turn casting off. AirPlay loads nothing.

### Home screen icon (web app manifest)
The player serves a web app manifest at `index.php?manifest` with the player title, description, template colours and an icon, so listeners who add the player to their home screen get your station, not a generic icon. Upload a square image of at least 512 x 512 px under Settings > App Icon; without one the PawTunes icon is used.

### Aurora and Modern template extras (listeners, up next, live DJ, sleep timer)
The Aurora and Modern templates have a few features of their own, switched in the control panel under Settings > Template Options:

- **Aurora visualizer** (Aurora): the music's spectrum rises behind the player as northern lights in the current artwork's color; the page, the buttons and the aurora cross-fade on every track. The layout follows the size of the player itself, not the window: a side rail on wide screens, tabs and a bottom sheet on phones, artwork beside the text in landscape, and a one-row strip when it is embedded short (from 120 px high). Keyboard: Space plays and stops, M mutes, the arrow keys change the volume.
- **Listeners page** (off by default): one more tab that shows who is listening right now: a masked address (`93.103.x.x`, `2a01:260:x:x`), the device ("Chrome on Android", "VLC", "Sonos") and the listening time. It works for channels whose track info method is **AzuraCast** (add an API key with the "View Station Reports" permission in the channel editor; the key stays on the server), **Icecast (Admin)** or **Shoutcast (Admin)**. The list is fetched from the admin API at most once every 30 seconds, only while the page is open, and raw addresses are masked before anything is cached or sent. **Privacy**: the page is visible to every visitor, which is why it is off unless you switch it on; the endpoint `index.php?listeners&channel=...` refuses to answer while the option is off.
- **Up next**: the queued track below the current one, for AzuraCast and SAM Broadcaster channels with their own "Up Next" switch on in the channel editor.
- **Live DJ badge**: a LIVE pill with the streamer name while an AzuraCast channel has a live DJ.
- **Sleep timer**: the moon in the header offers Off, 15, 30, 60 and 90 minutes; playback fades out over the last 30 seconds and stops. (The timer itself lives in the player core, so other templates can offer it too.)

## Developer API & Customization
**PawTunes** is designed to be easily extended, upgraded, and customized.
The codebase is straightforward, well-commented, and structured for simplicity.
Full documentation for all player classes and functions is coming soon.
In the meantime, you can find practical code examples for integrating the player into your website using the **External API (JSONP)** here:
[Developer Documentation](https://doc.prahec.com/pawtunes#developers).

#### Experimental feature: ICY Metadata
For years, reading ICY metadata directly in the browser seemed impossible without Web Assembly.
Now, we're pushing the boundaries with an experimental implementation.
It's still a work in progress, currently in beta, and may not function on some mobile browsers or older environments.
Check out the demo and feel free to contribute or provide feedback: [Live ICY Metadata Demo](https://prahec.com/pawdemo/)

---

## Scalability & Caching
- **High Performance**: Optimized to handle thousands of simultaneous listeners.
- **Caching** using APC, APCu, Redis, Memcached, and Disk cache (can be on shared storage).
- **Artwork Optimization**: Images are cropped, optimized, and stored on PawTunes for fast access. This feature can be disabled in the settings, using only direct API requests, which are also cached.
- **One upstream request per refresh interval**: when the cached track info expires, a single request refreshes it while every other listener keeps getting the previous copy, so Icecast, Shoutcast, AzuraCast and friends see one request per interval per channel, however many listeners are polling.

### High traffic
Track info responses (`index.php?channel=<name>`) carry HTTP caching headers:
`Cache-Control: public, max-age=<seconds until the cached copy expires>`
plus a weak `ETag`, and conditional requests (`If-None-Match`) are answered with `304 Not Modified`.
Browsers reuse a poll result within the refresh interval on their own, and any shared cache in front of PHP
(a CDN such as Cloudflare, Varnish, or an nginx microcache) serves the same copy to every listener, so PHP
runs about once per interval per channel instead of once per listener.

With nginx and PHP-FPM, a microcache keyed on the request URI is all it takes:

```nginx
# http { } context: 10 MB of keys, entries unused for 10 minutes are dropped
fastcgi_cache_path /var/cache/nginx/pawtunes levels=1:2 keys_zone=pawtunes:10m inactive=10m max_size=100m;

# server { } context, inside your location ~ \.php$ block
fastcgi_cache pawtunes;
fastcgi_cache_key "$scheme$host$request_uri";
fastcgi_cache_valid 200 1s;            # floor; the Cache-Control max-age sent by PawTunes extends it
fastcgi_cache_use_stale updating error timeout;
fastcgi_cache_lock on;                 # one request refreshes a stale entry, the others get the old copy
fastcgi_ignore_headers Set-Cookie;
add_header X-Cache $upstream_cache_status;
```

`fastcgi_cache_use_stale updating` together with `fastcgi_cache_lock on` is the safe, up-to-date combination:
while an entry is being refreshed, the one request that holds the lock reaches PHP and every other listener is
served the previous copy, so the upstream sees one request per interval. There is deliberately no
`fastcgi_cache_background_update` (and PawTunes sends no `stale-while-revalidate`): background revalidation
hands a listener the stale copy and refreshes behind it, which shows each track change one poll late.

Only cache the player endpoints: add `fastcgi_cache_bypass $no_cache; fastcgi_no_cache $no_cache;` with a
`map $request_uri $no_cache { default 1; ~^/index\.php\?channel= 0; }` so the control panel (`/panel/`)
is never cached. The channel list (`?channel=all`) is cacheable for 60 seconds.

---

## Requirements
PawTunes has minimal requirements to ensure smooth operation:
- **PHP 8.1+** with the cURL, JSON, SimpleXML, GD and mbstring extensions.
- **PHP ZipArchive Extension** (optional, for updates).
- **Access to API Ports**: Ensure proper configuration for ports (e.g., Shoutcast uses port 8000 by default).

---

## Documentation
Comprehensive documentation is available at:
- [PawTunes Official Docs](https://doc.prahec.com/pawtunes)

---

## Links

- [Live Demo](https://prahec.com/projects/pawtunes/demo/panel/)
- [Documentation](https://doc.prahec.com/pawtunes)
- [Docker Hub](https://hub.docker.com/r/jackyprahec/pawtunes)
- [Report Issues](https://github.com/Jackysi/pawtunes/issues)
- [Contact](https://prahec.com/contact)

---

## Credits
**PawTunes** would have taken even more time to develop without the incredible open-source projects that made it possible.
Here's the list of libraries and projects used in the **Control Panel**:

- **FontAwesome 6 Free**: [https://fontawesome.com/](https://fontawesome.com/)
- **Bootstrap Modals**: [https://getbootstrap.com/docs/5.3/components/modal/](https://getbootstrap.com/docs/5.3/components/modal/)
- **SCSS PHP Compiler**: For custom colour schemes [https://scssphp.github.io/scssphp/](https://scssphp.github.io/scssphp/)
- **Spectrum**: For colour picking of templates [https://bgrins.github.io/spectrum/](https://bgrins.github.io/spectrum/)
- **jQuery**: [https://jquery.com/](https://jquery.com/)

The player itself uses only one lightweight library:

- **AudioMotion Analyser**: [https://www.npmjs.com/package/audiomotion-analyzer](https://www.npmjs.com/package/audiomotion-analyzer)

If I've missed crediting any library, please let me know, and I will update this list promptly.

---

## Support the Project
**Donate & Support**

This project took an immense amount of effort to build. While it may not look like much,
it represents over 5 months of initial (first release) full-time work and over a decade of experience developing similar apps and scripts.
My initial plan was to sell this as a product on Envato Market - CodeCanyon for 35$ (about 31€), but the review team permanently declined the project,
so I decided to share this awesome project for free.

As this project is close to my heart, I will continue working on it for free. However,
if you find it useful and want to support its ongoing development, any financial support would be greatly appreciated.
It would help cover costs (hosting) my effort and, of course, fund a coffee or two to keep me coding!

[Donate via PayPal](https://www.paypal.com/donate/?hosted_button_id=VN3SBVYNHC2SE)

I will also be extremely grateful for any help solving issues and/or improving my code for others.