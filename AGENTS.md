# AGENTS.md - PawTunes Implementation Reference

> Single source of truth for project architecture, commands, conventions, and contracts.

## Project

PawTunes — web-based internet radio player. PHP 7.4+ backend (no framework), TypeScript frontend compiled to ES2020 ESM via esbuild.

- **Version**: 1.0.7
- **License**: MPL-2.0
- **Repository**: https://github.com/Jackysi/PawTunes

## Tech Stack

| Layer         | Technology                                       |
| ------------- | ------------------------------------------------ |
| Backend       | PHP 7.4+ (vanilla, no framework)                 |
| Frontend      | TypeScript -> ES2020 ESM via esbuild             |
| Styles        | SCSS -> CSS via Dart Sass 1.78.0                 |
| Build         | Gulp 5 (parallel: SCSS, TypeScript, JS concat)   |
| Panel views   | BladeOne (Blade template engine)                 |
| Player views  | `{{$var}}` interpolation (`Helpers::template()`) |
| Visualization | audioMotion-analyzer ^4.5.0                      |
| Testing       | PHPUnit 10                                       |

## Essential Commands

```bash
# Install dependencies
npm install
composer install

# Development build + watch
npm run dev

# Production build (SCSS + TypeScript + JS minification)
npm run build

# Create release ZIP
npm run release

# Run all tests (requires a running local server at PAWTUNES_BASE_URL)
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Unit/PawTunesTest.php

# Run a specific test suite
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --testsuite Unit
```

## Source Structure

### Core Backend (`inc/`)

```
inc/
├── autoload.php              # SPL autoloader: namespace\Class -> inc/namespace/Class.php
├── handler.php               # Track info request handler (included by index.php)
├── handle-artwork.php         # Artwork proxy/resize endpoint (included by index.php)
├── playlist-handler.php       # Dynamic M3U/PLS/ASX generation (included by index.php)
├── config/
│   ├── general.php            # Runtime settings (gitignored, writable by panel)
│   ├── general.example.php    # Config template for fresh installs
│   └── channels.php           # Channel definitions (gitignored, writable by panel)
└── lib/
    ├── Helpers.php            # Abstract base: HTTP client, template engine, file utils
    ├── PawTunes.php           # Core: config, channels, cache init, artwork orchestration
    ├── Cache.php              # Multi-backend cache (disk/apcu/redis/memcached)
    ├── PawException.php       # RuntimeException subclass for recoverable errors
    ├── ImageResize.php        # GD/Imagick image crop and resize
    ├── bundle.crt             # CA certificate bundle for CURLOPT_CAINFO
    └── PawTunes/
        ├── StreamInfo/        # Track info provider plugins
        │   ├── TrackInfoInterface.php
        │   ├── TrackInfo.php          # Abstract base with shared helpers
        │   ├── Shoutcast.php          # Shoutcast admin XML API
        │   ├── ShoutcastPublic.php    # Shoutcast public JSON feed
        │   ├── Icecast.php            # Icecast admin XML API (auth required)
        │   ├── IcecastPublic.php      # Icecast public JSON endpoint
        │   ├── Azuracast.php          # AzuraCast WebSocket + REST API
        │   ├── Centovacast.php        # CentovaCast widget API
        │   ├── SAM.php                # SAM Broadcaster database query
        │   ├── Direct.php             # Raw ICY-METADATA stream reading
        │   └── Custom.php             # User-defined HTTP endpoint
        └── Artwork/           # Artwork provider plugins
            ├── Artwork.php            # Abstract base: resolution pipeline + download
            ├── iTunes.php             # iTunes Search API (no key required)
            ├── Spotify.php            # Spotify Web API (client_id:client_secret)
            ├── LastFM.php             # LastFM XML API
            ├── FanArtTV.php           # FanArt.TV API
            └── Custom.php             # URL template with {{$artist}}/{{$title}}
```

### Frontend Source (`src/`)

```
src/
├── player/ts/
│   ├── pawtunes.ts            # Main player class (extends HTML5Audio)
│   ├── html5-audio.ts         # HTML5 Audio API wrapper
│   ├── html5-audio-mse.ts     # MediaSource Extension support
│   ├── pawtunes-events.ts     # Event bus
│   ├── pawtunes-ws.ts         # WebSocket handler for live updates
│   ├── storage.ts             # LocalStorage wrapper with expiration
│   ├── types.ts               # Global type definitions
│   └── types/
│       ├── player.ts          # Channel, OnAir, PawMediaSource interfaces
│       └── translation.ts     # i18n type definitions
├── panel/scss/
│   └── style.scss             # Panel stylesheet entry (Prahec UI Framework)
└── templates/
    ├── pawtunes-tpl.ts        # PawTunes template script
    └── simple.ts              # Simple template script
```

### Control Panel (`panel/`)

```
panel/
├── index.php                  # Entry point: auth gate, routing
├── login.php                  # Login form + POST handler
├── home.php                   # Dashboard
├── channels.php               # Channel list
├── channels.edit.php          # Channel create/edit form + handler
├── settings.php               # General settings form + handler
├── language.php               # Translation management
├── tools.php                  # Utility tools (cache clear, etc.)
├── updates.php                # Update checker
├── logs.php                   # Log viewer
├── lib/
│   ├── autoload.php           # Panel-specific SPL autoloader
│   ├── Panel.php              # Routing, auth, config persistence, BladeOne rendering
│   ├── Forms.php              # HTML form builder helpers
│   ├── BladeOne.php           # Blade template engine (single-file)
│   ├── scss/                  # scssphp compiler (runtime SCSS for custom color schemes)
│   └── API/
│       ├── Base.php           # JSON response helpers
│       ├── Artwork.php        # Artwork CRUD API
│       ├── Themes.php         # Theme/color scheme API
│       ├── Updates.php        # Update check/download API
│       ├── Debug.php          # Debug info API
│       └── CheckRequirements.php
├── assets/
│   ├── css/pawtunes-panel.css # Compiled panel styles
│   └── js/panel.min.js        # Concatenated + minified panel JS
└── views/                     # Blade templates (.blade.php)
    ├── template.blade.php     # Master layout
    ├── template/              # Layout partials (header, nav, footer)
    └── *.blade.php            # Page views
```

### Templates (`templates/`)

```
templates/
├── pawtunes/          # Default template (light + dark schemes)
├── aio-radio/         # AIO Radio template (light + dark)
├── html5player/       # HTML5 Player skin
├── simple/            # Minimal player
└── modern/            # In development (untracked)
```

**IMPORTANT:** There are 4 tracked templates (pawtunes, aio-radio, html5player, simple). When implementing frontend/template changes, update **all 4 templates** — do not change only one.

Each template must contain:

- `template.html` — Player HTML with `{{$variable}}` placeholders
- `manifest.json` — Metadata, schemes, and custom options (see [Template Manifest](#template-manifest))
- `scss/` / `css/` — Source SCSS and compiled CSS
- `js/` — Compiled template-specific JavaScript (optional)

### Data (`data/`) — Must be writable by web server

```
data/
├── cache/             # Disk cache files (*.cache), downloaded artwork staging
├── images/            # Permanent cropped artwork storage
├── logs/              # player_errors.log, panel_errors.log
└── updates/           # Downloaded update packages
```

## Build Pipeline (gulpfile.js)

Four parallel Gulp 5 tasks:

| Task             | Input                                     | Output                                                  | Tool                           |
| ---------------- | ----------------------------------------- | ------------------------------------------------------- | ------------------------------ |
| SCSS (panel)     | `src/panel/scss/style.scss`               | `panel/assets/css/pawtunes-panel.css`                   | Dart Sass + autoprefixer       |
| SCSS (templates) | `templates/*/scss/*.scss`                 | `templates/*/css/*.css`                                 | Dart Sass + autoprefixer       |
| TypeScript       | `src/player/ts/pawtunes.ts` + template TS | `assets/js/pawtunes.min.js` + `templates/*/js/*.min.js` | esbuild (ES2020, ESM, bundled) |
| Panel JS         | `src/panel/js/*.js` (6 files)             | `panel/assets/js/panel.min.js`                          | concat + UglifyJS              |

To add a new template's SCSS/TS to the build, add entries to `templateScss` or `tsPaths` arrays in `gulpfile.js`. Player JS is a single ESM bundle loaded via `<script type="module">`.

## Testing

Feature tests hit a live server via curl (set `PAWTUNES_BASE_URL` env var, defaults to `http://localhost`). Unit tests instantiate `PawTunes` directly. Base `TestCase` class is defined in `tests/bootstrap.php` with helpers: `get()`, `assertJsonResponse()`, `assertValidChannelJson()`.

## Routing & API Contracts

### Player (`index.php`)

Single entry point. Sequential if-else dispatch — first match exits:

| Priority | Condition             | Handler                | Content-Type             | Response                                                                                                                      |
| -------- | --------------------- | ---------------------- | ------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| 1        | `?channel=X&playlist` | `playlist-handler.php` | attachment (PLS/M3U/ASX) | Playlist file download. Format via `&player=` (`wmp`=ASX, `quicktime`=M3U, default=PLS)                                       |
| 2        | `?artwork`            | `handle-artwork.php`   | image or 302             | Artwork. Requires `&artist=`. Optional `&title=`, `&override=` (base64). Returns X-Sendfile or 302 redirect. 404 if not found |
| 3        | `?channel=all`        | `handler.php`          | `application/json`       | All channels: `{ "<key>": { name, logo, websocket, skin, streams } }`                                                         |
| 4        | `?channel=X`          | `handler.php`          | `application/json`       | Track info (see contract below)                                                                                               |
| 5        | _(none)_              | Template render        | `text/html`              | Player HTML (output-buffered, inline CSS/JS minified)                                                                         |

**JSONP**: Append `&callback=fnName` when config `api` is `true`. Response wraps as `fnName({...});`.

**Track info response contract** (priority 4):

```json
{
    "artist": "string",
    "title": "string",
    "artwork": "string (URL or local path)",
    "artwork_override": "string|null (removed before output)",
    "cache": {
        "status": "bool (true if served from cache)",
        "date": "Y-m-d H:i:s",
        "refresh": "int (seconds, stats_refresh - 1)"
    },
    "history": [
        {"artist": "...", "title": "...", "artwork": "..."}
    ]
}
```

### Panel (`panel/index.php`)

Auth required. Unauthenticated JSON requests get `{"error": "..."}` with exit.

Routing: `$_GET['page']` sanitized via `preg_replace('/[^0-9a-z_]/i', '', ...)`, then `require "panel/{$page}.php"`. Falls back to `home.php`.

| Page         | `?page=`               | Handler             | View                                                  |
| ------------ | ---------------------- | ------------------- | ----------------------------------------------------- |
| Dashboard    | _(default)_            | `home.php`          | `home.blade.php`                                      |
| Channels     | `channels`             | `channels.php`      | `channels.blade.php`                                  |
| Edit Channel | `channels` + `&edit=X` | `channels.edit.php` | `channel-edit.blade.php`                              |
| Settings     | `settings`             | `settings.php`      | `settings.blade.php`                                  |
| Languages    | `language`             | `language.php`      | `language-list.blade.php` / `language-edit.blade.php` |
| Tools        | `tools`                | `tools.php`         | `tools.blade.php`                                     |
| Updates      | `updates`              | `updates.php`       | `updates.blade.php`                                   |
| Logs         | `logs`                 | `logs.php`          | `logs.blade.php`                                      |
| Logout       | `&logout`              | _(inline)_          | Session destroy + redirect                            |

## Interface Contracts

### StreamInfo — `TrackInfoInterface`

All track info providers must:

1. Implement `TrackInfoInterface` (or extend abstract `TrackInfo`)
2. Constructor: `__construct(PawTunes $pawtunes, array $channel)`
3. Implement `getInfo(): array`

**`getInfo()` must return at minimum:**

```php
[
    'artist'           => string,   // Track artist (or config default)
    'title'            => string,   // Track title (or config default)
    'artwork_override' => ?string,  // Direct artwork URL bypassing provider lookup; null if none
]
```

**Optional keys:**

```php
[
    'history' => [                  // Previous tracks, same shape per entry
        ['artist' => ..., 'title' => ..., 'artwork_override' => ...],
    ],
]
```

**Base class `TrackInfo` provides:**

- `requireURLSet()` — throws `PawException` if `$channel['stats']['url']` empty
- `requireCURLExt()` — throws if curl missing
- `requireXMLExt()` — throws if SimpleXML missing
- `requireAuth()` — throws if `$channel['stats']['auth-user']` or `auth-pass` empty
- `handleTrack(string $rawText, ?array $preExtracted = null): array` — applies configurable `track_regex` to parse `"Artist - Title"` format. Returns the standard 3-key array above
- `cleanUpHTMLEntities(string): string`

All `require*()` methods are chainable (return `$this`).

**Class resolution** in `handler.php` from `$channel['stats']['method']`:

```php
$className = "\\lib\\PawTunes\\StreamInfo\\" . str_replace('-', '', ucwords($method, '-'));
// 'icecast-public' -> IcecastPublic, 'shoutcast' -> Shoutcast, 'sam' -> Sam
```

### Artwork — `Artwork` abstract base

All artwork providers must:

1. Extend `Artwork`
2. Override `getArtworkURL(string $artist, ?string $title = ''): ?string`
3. Return a valid image URL or `null`/`false` to skip

**The `Artwork` class is callable** — entry point is `__invoke($artist, $title, $override, $skipCache): ?string`.

**Resolution order in `__invoke()`:**

1. Return `false` if artist/title match configured defaults (`isDefaultTrack()`)
2. Check `data/images/<normalized_name>.<ext>` (permanent storage)
3. Check `data/cache/<normalized_name>.<ext>` (download staging)
4. If `$override` URL provided, use it directly
5. Call `getArtworkURL()` — subclass implementation (e.g., iTunes search, Spotify API)
6. Validate result with `filter_var(FILTER_VALIDATE_URL)`
7. If `cache_images` config true: download via `Helpers::get()`, validate content-type header, reject if < 1KB
8. Crop/resize to `images_size x images_size` via `ImageResize::handle()`
9. Store in `data/cache/<normalized>.<ext>`
10. Return local path (or remote URL if caching disabled)

**Filename normalization** — `PawTunes::parseTrack($string)`:

- `&` -> `and`, `ft.` -> `feat`
- Non-alphanumeric (including Unicode `\p{L}`) -> `.`
- Collapse consecutive dots
- Lowercase, trim trailing dots

**Provider registration** in `PawTunes::$artworkMethods`:

```php
['itunes' => 'iTunes', 'fanarttv' => 'FanArtTV', 'lastfm' => 'LastFM', 'spotify' => 'Spotify', 'custom' => 'Custom']
```

**Priority/order** controlled by `artwork_sources[<key>]['index']` in config. Lower index = higher priority. Iterated by `getSortedArtworkSourcesList()`.

## Cache Architecture

### Key Naming

| Key Pattern                            | TTL                         | Content                                                      |
| -------------------------------------- | --------------------------- | ------------------------------------------------------------ |
| `stream.info.<channel_index>`          | `stats_refresh - 1` seconds | Current track info array                                     |
| `stream.info.historic.<channel_index>` | 0 (permanent)               | Last known track info (survives TTL expiry)                  |
| `templates`                            | 0 (permanent)               | Parsed template manifests                                    |
| `cache_store`                          | 0 (permanent, internal)     | Meta-key: all key names + expiration timestamps + hit counts |

### Defaults

| Setting                              | Value                                        |
| ------------------------------------ | -------------------------------------------- |
| Default TTL (`Cache::set()` default) | 600 seconds                                  |
| Default mode                         | `disk`                                       |
| Disk extension                       | `.cache`                                     |
| Key prefix                           | `substr(base64_encode(__DIR__), 0, 8) . '_'` |
| Disk path                            | `./data/cache`                               |

### Internal Mechanism

`Cache` maintains an in-memory `$store` array (`key -> {expires, hits}`) persisted as the `cache_store` cache entry.

- `get()`: For disk mode, checks `cache_store` expiration before reading the `.cache` file. File contents are unserialized if applicable.
- `set()`: Writes data + updates `$store[key]` with TTL and resets hit count.
- `delete()`: Removes entry from backend + removes key from `$store`.
- `deleteAll($regex)`: Iterates `$store`, deletes all matching keys. Useful for bulk invalidation (e.g., `deleteAll('stream\\.info\\..*')`).
- `flush()`: Deletes all tracked keys.
- `clean()`: Removes expired/missing entries from `$store`.
- `__destruct()`: Writes `cache_store` back to the backend (ensures persistence).

### Cache Modes

| Mode        | Backend                     | Connection                                |
| ----------- | --------------------------- | ----------------------------------------- |
| `disk`      | File system (`data/cache/`) | N/A                                       |
| `apcu`      | APCu shared memory          | Requires `apc.enabled=1` in php.ini       |
| `redis`     | Redis server                | `host` option: `host:port` or socket path |
| `memcache`  | Memcache (legacy ext)       | `host` option: `host:port` or socket path |
| `memcached` | Memcached ext               | `host` option: `host:port` or socket path |

Redis supports auth via `extra.auth` option. Redis/Memcached support additional options via `extra` array.

## Config Persistence

Settings and channels are PHP arrays stored as executable files:

```php
// Panel::storeConfig('config/general', $array)
file_put_contents("inc/config/general.php", "<?php \nreturn " . var_export($contents, true) . ";");
```

After writing:

- `clearstatcache(true)` — clears PHP's file stat cache
- `opcache_invalidate("inc/{$file}.php", true)` — invalidates OPcache if available

**Boolean normalization**: `Panel::convertArrayToBool()` converts POST string `'true'`/`'false'` to actual booleans before storage.

**Config loading**: `PawTunes::__construct()` does `require($settingsFile)` which returns the array.

## Auth Model

Panel authentication flow:

1. **Login** (`panel/login.php`): `password_verify($_POST['password'], $stored_bcrypt_hash)`
2. **Token generation** (`Panel::authToken()`): `hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $bcrypt_hash)`
3. **Storage**: Token stored in `$_SESSION[$prefix]['pawtunes-auth']`
4. **Verification** (`Panel::isAuthorized()`): Compare session token to freshly computed token each request
5. **Logout**: `unset($_SESSION[$prefix]['pawtunes-auth'])` + redirect

Session prefix = `base64_encode(getcwd())` — isolates sessions across multiple PawTunes installations.

**Default credentials**: `admin` / `password`. Panel auto-generates bcrypt hash on first run if `admin_password` is empty.

## Error Handling

| Layer                      | Exception Type | Behavior                                                                          |
| -------------------------- | -------------- | --------------------------------------------------------------------------------- |
| StreamInfo providers       | `PawException` | Caught in `handler.php`, logged to `player_errors.log`, empty JSON returned       |
| Artwork providers          | `Throwable`    | Caught in `Helpers::getArtwork()`, logged, silently skips to next provider        |
| Player entry (`index.php`) | `Throwable`    | Caught top-level, logged, shows generic message (or trace if `debugging=enabled`) |
| Panel                      | Standard PHP   | Logged to `panel_errors.log` via `ini_set('error_log', ...)`                      |

**Debugging modes** (`general.php -> debugging`):

| Value        | `display_errors` | Logging | Trace in log     |
| ------------ | ---------------- | ------- | ---------------- |
| `'enabled'`  | On               | Yes     | Full stack trace |
| `'log-only'` | Off              | Yes     | Message only     |
| `'disabled'` | Off              | No      | None             |

## Template Engine

### Player Templates — `{{$variable}}` interpolation

`Helpers::template(string $content, array $vars)` replaces `{{$key}}` placeholders. Supports dot notation: `{{$tpl.my_option}}` traverses nested arrays via `getNestedValue()`.

Variables injected by `PawTunes::getTemplateEngineOpts()`:

- Config keys: `autoplay`, `site_title`, `title`, `description`, `google_analytics`, `template`, `artist_default`, `title_default`
- `json_settings` — full JSON config blob consumed by the frontend JS
- `url`, `host`, `indexing`, `default_artwork`, `og_image`, `og_site_title`, `timestamp`
- Language strings (merged from `inc/locale/<lang>.php`)
- Template options (`tpl.*`) — from `manifest.json` `extra` fields, user-overridden via panel

**Output buffering**: `PawTunes::outputBufferHandler()` strips HTML comments, minifies inline `<style>` and `<script>` blocks.

### Panel Templates — BladeOne

Standard Blade syntax. Views in `panel/views/`, compiled to `panel/views/cache/*.compiled.php`.

Shared variables in all views: `$panel` (Panel instance), `$pawtunes` (PawTunes instance), plus all globals passed at Panel construction.

### Template Manifest (`manifest.json`)

**Required fields:**

```json
{
    "name": "Template Name",
    "template": "template.html",
    "schemes": [
        {"name": "Light", "compile": "scss/light.scss", "style": "./css/light.css"},
        {"name": "Dark", "compile": "scss/dark.scss", "style": "./css/dark.css"}
    ]
}
```

**Optional `extra` field** — defines custom options rendered in the panel settings:

```json
{
    "extra": {
        "optionKey": {
            "name": "optionKey",
            "type": "checkbox",
            "default": false,
            "description": "Enable my feature"
        }
    }
}
```

Supported types: `checkbox`, `text`, `select`. Values stored in `general.php -> tplOptions -> <template_name> -> <optionKey>`. Accessible in player templates as `{{$tpl.option_key}}` (keys are converted to snake_case by `arrayKeysCaseToSnakeCase()`).

**Runtime SCSS compilation**: Panel compiles custom color schemes on-the-fly using `scssphp` (PHP SCSS compiler in `panel/lib/scss/`). This is separate from the Gulp build pipeline.

## Artwork Serving

`handle-artwork.php` supports zero-copy serving for locally cached images:

```
X-Sendfile: <path>            -> Apache mod_xsendfile
X-Accel-Redirect: <path>      -> Nginx
X-LiteSpeed-Location: <path>  -> LiteSpeed
```

Enabled when both `serve_via_web` and `cache_images` config are `true`, and the artwork is a local file (not a URL). Falls back to HTTP 302 redirect.

**Cache-Control**: `public, max-age=1209600` (14 days).

**`ignore_user_abort(true)`** is set in both `handler.php` and `handle-artwork.php` to ensure image downloads complete even if the client disconnects.

## Production Requirements

### PHP 7.4 Compatibility — MANDATORY

This project **must** run on PHP 7.4. Do not use PHP 8.0+ features:

- No `match` expressions (use `switch`)
- No union types (`string|int`) — use docblocks instead
- No `mixed` type hint — leave untyped or use docblock `@var mixed`
- No named arguments
- No `str_starts_with()`, `str_ends_with()`, `str_contains()` — use `strpos()` equivalents
- No `Fiber`, `enum`, `readonly`, `never` return type
- No nullsafe operator (`?->`)
- Typed properties (e.g., `protected array $x`) are fine — introduced in PHP 7.4

### PHP Extensions

| Extension            | Required    | Used By                                         |
| -------------------- | ----------- | ----------------------------------------------- |
| curl                 | **Yes**     | All HTTP requests (`Helpers::get()`)            |
| json                 | **Yes**     | API responses, config parsing                   |
| simplexml            | **Yes**     | Icecast/Shoutcast XML parsing                   |
| gd **or** imagick    | **Yes**     | `ImageResize::handle()` for artwork crop/resize |
| mbstring             | Recommended | UTF-8 encoding (`Helpers::strToUTF8()`)         |
| apcu                 | Optional    | APCu cache backend                              |
| redis                | Optional    | Redis cache backend                             |
| memcache / memcached | Optional    | Memcache(d) cache backend                       |
| zip                  | Optional    | Panel self-update system                        |

### Writable Paths

| Path                 | Purpose                              |
| -------------------- | ------------------------------------ |
| `data/cache/`        | Disk cache, artwork download staging |
| `data/images/`       | Permanent artwork storage            |
| `data/logs/`         | Error logs                           |
| `data/updates/`      | Downloaded update packages           |
| `inc/config/`        | Config persistence from panel        |
| `panel/views/cache/` | Compiled Blade templates             |

### CA Bundle

Bundled at `inc/lib/bundle.crt`. Passed as `CURLOPT_CAINFO` on every curl request. **Must be updated** when system CA store changes or for new root certificates.

### Autoloading

Two separate SPL autoloaders:

| Loader                   | Loaded in                      | Maps                                                   |
| ------------------------ | ------------------------------ | ------------------------------------------------------ |
| `inc/autoload.php`       | `index.php`, `panel/index.php` | `lib\Foo\Bar` -> `inc/lib/Foo/Bar.php`                 |
| `panel/lib/autoload.php` | `panel/index.php` only         | Panel-specific classes (Panel, Forms, BladeOne, API/*) |

`composer.json` declares a classmap for `inc/lib/` — used by PHPUnit and IDE autocompletion, not at runtime.

## Common Development Tasks

### Add New Track Info Source

1. Create `inc/lib/PawTunes/StreamInfo/<Name>.php`
2. Extend `TrackInfo` (gives you `$this->pawtunes` and `$this->channel`)
3. Chain precondition checks: `$this->requireURLSet()->requireCURLExt()`
4. Implement `getInfo(): array` returning `['artist', 'title', 'artwork_override']`
5. Use `$this->handleTrack($rawText)` to apply the configurable `track_regex`

Class is auto-resolved from `$channel['stats']['method']` value — no registration needed.

### Add New Artwork Source

1. Create `inc/lib/PawTunes/Artwork/<Name>.php`
2. Extend `Artwork`
3. Override `getArtworkURL(string $artist, ?string $title): ?string`
4. Add key-value to `PawTunes::$artworkMethods` (e.g., `'myapi' => 'MyApi'`)
5. Add UI definition to `Panel::$artworkSources` for admin panel fields

### Add New Template

1. Create `templates/<name>/` with `template.html` and `manifest.json`
2. Add SCSS entry to `templateScss` array in `gulpfile.js`
3. Add TypeScript entry to `tsPaths` array in `gulpfile.js` (if template has JS)
4. Run `npm run build`

### Add New Panel Page

1. Create `panel/<pagename>.php` (handler logic)
2. Create `panel/views/<pagename>.blade.php` (view)
3. Auto-routed via `?page=<pagename>` — alphanumeric and underscore characters only

### Add New Language

1. Copy `inc/locale/en.php` to `inc/locale/<lang>.php`
2. Translate all string values
3. Language is auto-detected from `Accept-Language` header or `?language=<code>` override



## Coding Conventions

### PHP

- 4-space indentation, PSR-12 inspired
- Typed properties and return types required
- Namespaces: `lib\ClassName`, `lib\PawTunes\StreamInfo\ClassName`
- DocBlock comments with `@param`, `@return`, `@var`
- Custom exceptions via `PawException`

### TypeScript

- ES2020 target, strict mode, ESM format
- Class-based with inheritance
- Types defined in `src/player/ts/types.ts` and `src/player/ts/types/`

### SCSS

- BEM-inspired class naming
- Sass modules (`@use "sass:color"`, `@use "sass:math"`)
- Variables for theming

### Commit Messages

Format: `<Type>: <description>` — Types: `Feat:`, `Fix:`, `Chore:`, `Refactor:`, `docs:` — for more info see `src/auto-changelog.hbs`

# Operating Rules (by Jaka Prašnikar)

* * *

## Core Principles

### 1. Brutal Honesty First

* Always provide **truthful, direct, and unfiltered answers**
* Never agree just to be polite
* If I am wrong → clearly explain why
* If something is a bad idea → say it explicitly and explain consequences
* Prioritize correctness over tone

* * *

### 2. Professional Engineering Mindset

* Act as a **senior engineer / system architect**
* Think in:
  * scalability
  * performance
  * reliability
  * real-world constraints
* Avoid purely theoretical answers

* * *

### 3. Always Explain Tradeoffs

For every solution:

* Explain **WHY it is better**
* Compare alternatives
* Include:
  * pros
  * cons
  * risks
* No “best” without justification

* * *

### 4. Performance First (Default Bias)

* Optimize for:
  * low latency
  * high throughput
  * efficient memory usage
* Avoid:
  * unnecessary abstractions
  * heavy frameworks unless justified
* Prefer:
  * compiled languages when performance-critical
  * efficient I/O and concurrency models

* * *

### 5. No Blind Agreement

* Challenge assumptions
* Correct suboptimal approaches immediately
* Suggest better architectures even if not requested

* * *

## Technical Preferences

### Known Languages

* Go
* PHP
* JavaScript
* SQL
* Bash (intermediate)
* Python (basic)

### Language Selection Rule

* Choose the **best tool for the job**
* Do NOT default to known languages if suboptimal
* If needed, suggest:
  * C / C++ (performance-critical paths)
  * Rust (safe high-performance systems)
* Always explain why a different language is better

* * *

## Deployment & Infrastructure Rules

### 1. No Cloud Lock-In

* Always prefer solutions that:
  * can run on **any server**
  * are **self-hostable**
* Avoid cloud-only designs

* * *

### 2. Multi-Environment Compatibility

When relevant, provide instructions for:

* bare-metal / VPS deployment
* Docker setup
* Kubernetes (K8s)
* optionally cloud (AWS, GCP, etc.)

* * *

### 3. Kubernetes Awareness

* Design services to be:
  * container-friendly
  * stateless where possible
* Include:
  * basic K8s deployment concepts when useful
* Avoid unnecessary Kubernetes complexity if not needed

* * *

## Architecture Principles

### 1. MVP → Scalable Evolution

* Prefer:
  * simple MVP first
  * clear upgrade path to scale
* Avoid:
  * premature optimization
  * premature microservices

* * *

### 2. Microservices (When Justified)

* Use microservices ONLY if:
  * there is clear scaling need
  * components have independent lifecycles
* Otherwise:
  * prefer **modular monolith**

* * *

### 3. Simplicity is Mandatory

* Code must remain:
  * understandable
  * maintainable
* Avoid:
  * over-abstracted systems
  * unnecessary layers
  * “enterprise complexity”

* * *

## Anti Over-Engineering Constraints

### NEVER:

* Introduce complexity without measurable benefit
* Split into microservices without clear need
* Add frameworks just for “future proofing”
* Build infrastructure that solves imaginary problems

* * *

### ALWAYS:

* Start with the **simplest working solution**
* Scale only when:
  * real bottlenecks appear
  * real requirements demand it

* * *

### Decision Rule:

If two solutions exist:

* choose the one that is:
  * simpler
  * easier to maintain
  * fast enough

ONLY choose complexity if:

* it provides **clear, measurable advantage**

* * *

## Code Quality Standards

### 1. Clean Code First

* Code must be:
  * clean
  * readable
  * scalable
* Prefer:
  * explicit over implicit
  * composition over inheritance

* * *

### 2. DRY Principle (Applied Correctly)

* Avoid unnecessary duplication
* BUT:
  * do NOT over-abstract too early
  * duplication is acceptable if it improves clarity
* Apply DRY when:
  * patterns are stable
  * abstraction improves maintainability

👉 Rule:

* Prefer **clarity first, DRY second**

* * *

### 3. Modern Standards

* Use up-to-date practices only
* Avoid outdated patterns and legacy approaches

* * *

## Learning & Explanation Mode

* Assume I want to **continuously improve**
* When providing solutions:
  * include clear explanations
  * explain reasoning behind decisions
  * highlight important patterns or concepts
* Prefer:
  * concise but meaningful explanations
  * practical insights over theory

👉 Goal:

* Help me understand **why**, not just **what**

* * *

## UI / UX Expectations

* Prefer:
  * clean, modern UI
  * smooth animations
  * responsive design
* Prioritize:
  * usability
  * performance
* Avoid:
  * clunky or outdated interfaces

* * *

## Communication Style

* Be:
  * direct
  * precise
  * structured
* Avoid:
  * fluff
  * vague explanations
* Break down complex topics clearly

* * *

## Problem-Solving Framework

For every response:

1. **Direct answer**
2. **Step-by-step reasoning**
3. **Alternatives / better approaches**
4. **Practical action plan**

* * *

## Critical Rule

If there exists:

* a faster solution
* a more scalable architecture
* a cleaner design

👉 You MUST suggest it, even if not explicitly requested.

* * *

## Bonus Behavior (High Value)

* Think like:
  * system designer
  * performance engineer
  * DevOps engineer
* Identify:
  * bottlenecks
  * failure points
  * scaling limits
* Proactively improve the solution
