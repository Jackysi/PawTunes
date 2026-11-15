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

namespace lib;

use lib\AI\ArtworkGenerator;
use RuntimeException;
use Throwable;

class PawTunes
{

    /**
     * @var \lib\Cache
     */
    public Cache $cache;
    /**
     * @var \lib\HttpClient
     */
    public HttpClient $http;
    /**
     * List of allowed extensions
     *
     * @var string[]
     */
    public array $artworkExtensions = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
    /**
     * @var string
     */
    public string $prefix;
    /**
     * @var array
     */
    protected array $channels = [];
    /**
     * @var array
     */
    protected array $settings;
    /**
     * @var string
     */
    protected string $artworkPath = './data/images';
    /**
     * Reflects available artwork methods
     *
     * @var array|string[]
     */
    protected array $artworkMethods
        = [
            'itunes'     => 'iTunes',
            'applemusic' => 'AppleMusic',
            'fanarttv'   => 'FanArtTV',
            'lastfm'     => 'LastFM',
            'spotify'    => 'Spotify',
            'custom'     => 'Custom',
        ];
    /**
     * @var string|null
     */
    protected ?string $foundDefaultArtwork = null;
    /**
     * @var string
     */
    protected string $currentDir;


    /**
     * @param  string  $settingsFile
     * @param  string  $channelsFile
     *
     * @return void
     */
    public function __construct(
        string $settingsFile = 'inc/config/general.php',
        string $channelsFile = 'inc/config/channels.php'
    ) {

        $this->currentDir = __DIR__;
        $this->prefix     = substr(base64_encode($this->currentDir), 0, 8).'_';
        $this->settings   = require($settingsFile);

        // May not exist.
        if (is_file($channelsFile)) {
            $this->channels = require($channelsFile);
        }

        $cacheOptions           = $this->settings['cache'] ?? [];
        $cacheOptions['prefix'] = $cacheOptions['prefix'] ?? $this->prefix;
        $cacheOptions['logger'] = function (string $message): void {
            $this->writeLog('player_errors', $message);
        };

        $this->cache = new Cache($cacheOptions);

        // HTTP client with error logging callback
        $this->http = new HttpClient(
            $this->currentDir.DIRECTORY_SEPARATOR.'bundle.crt',
            function (string $message) {
                $this->writeLog('player_errors', $message);
            }
        );

    }


    public function getChannels()
    {
        return $this->channels;
    }


    public function getConfigAll()
    {
        return $this->settings;
    }


    public function setConfigAll($settings)
    {
        return $this->settings = $settings;
    }


    public function getCache(): Cache
    {
        return $this->cache;
    }


    public function setCache(Cache $cache): Cache
    {
        return $this->cache = $cache;
    }


    public function setConfig($key, $value)
    {
        return $this->settings[$key] = $value;

    }


    public function config($key)
    {
        return $this->settings[$key] ?? null;

    }


    /* ============================================================================
     * HTTP — delegates to HttpClient
     * ============================================================================ */

    /**
     * HTTP GET/POST request (delegates to HttpClient)
     *
     * @param  string  $url
     * @param  array|null  $post
     * @param  string|null  $auth
     * @param  bool|callable  $progress
     * @param  int  $timeout
     * @param  string|int  $error
     * @param  array  $options
     * @param  boolean  $log
     *
     * @return bool|string
     */
    public function get(
        string $url,
        ?array $post = null,
        ?string $auth = null,
        $progress = false,
        int $timeout = 5,
        &$error = false,
        array $options = [],
        bool $log = true
    ) {
        return $this->http->get($url, $post, $auth, $progress, $timeout, $error, $options, $log);
    }


    /* ============================================================================
     * Logging & Error Handling
     * ============================================================================ */

    /**
     * Write to log file
     *
     * @param        $file
     * @param        $text
     * @param  string  $path
     *
     * @return bool|int
     */
    public function writeLog($file, $text, string $path = 'data/logs/')
    {

        ## Logging is disabled!
        if ($this->settings['debugging'] === 'disabled') {
            return false;
        }

        // Logged text often comes from the outside (track names, upstream responses, request
        // parameters). Strip the control characters, then fold CR/LF into a newline plus an indent:
        // a multi-line value (a stack trace) stays readable, but a continuation line always starts
        // with spaces, so it can never begin with a "[date]" stamp and forge a new log entry.
        $text = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]+/', '', (string) $text);
        $text = preg_replace('/[\r\n]+/', "\n    ", $text);

        ## Check if path is writable
        if (is_writable($path)) {
            return file_put_contents($path.$file.".log", "[".date("j.n.Y-G:i")."] {$text}\n", FILE_APPEND);
        }

        return false;

    }


    /**
     * @param  \Throwable  $e
     *
     * @return void
     */
    protected function handleException(Throwable $e): void
    {

        if ($this->config('debugging') !== 'disabled') {

            $this->writeLog('player_errors', "FATAL ERROR: {$e->getMessage()}");

            if ($this->config('debugging') === 'enabled') {
                $this->writeLog('player_errors', $e->getTraceAsString());
            }

        }

    }


    /* ============================================================================
     * Template Engine
     * ============================================================================ */

    /**
     * Replace {{$VARIABLE}} placeholders with values from array.
     * Supports dot notation: {{$VAR.DEEPER.DEEPER}}.
     *
     * @param  string  $content
     * @param  array  $array
     * @param  bool  $uppercase
     *
     * @return string
     * @throws \Exception
     */
    public function template(string $content, array $array, bool $uppercase = false): string
    {

        if ($uppercase) {
            $array = $this->arrayKeysToUppercase($array);
        }

        $replace_count = preg_match_all("/{{\\$(.*?)}}/", $content, $matches);

        for ($i = 0; $i < $replace_count; $i++) {

            $full_match    = $matches[0][$i];
            $variable_path = $matches[1][$i];

            $variable = $this->getNestedValue($array, $variable_path, $uppercase);

            if (isset($variable)) {
                $content = str_replace($full_match, $variable, $content);
                continue;
            }

            if ($this->settings['debugging'] === 'enabled') {
                throw new RuntimeException("Variable not found: {$variable_path}");
            }

        }

        return $content;

    }


    /**
     * @param  array  $array
     *
     * @return array
     */
    private function arrayKeysToUppercase(array $array): array
    {

        $result = [];
        foreach ($array as $key => $value) {

            $key = strtoupper($key);
            if (is_array($value)) {
                $value = $this->arrayKeysToUppercase($value);
            }

            $result[$key] = $value;

        }

        return $result;

    }


    /**
     * @param  array  $array
     * @param  string  $path
     * @param  bool  $uppercase
     *
     * @return mixed|null
     */
    private function getNestedValue(array $array, string $path, bool $uppercase = false)
    {

        $keys  = explode('.', $path);
        $value = $array;
        foreach ($keys as $key) {

            $key = ($uppercase) ? strtoupper($key) : $key;
            if (isset($value[$key])) {
                $value = $value[$key];
                continue;
            }

            return null;

        }

        return $value;

    }


    /* ============================================================================
     * Artwork
     * ============================================================================ */

    /**
     * Get artwork for artist/title, iterating through configured sources
     *
     * @param        $artist
     * @param  string  $title
     * @param  string  $override
     * @param  bool  $skipCache
     * @param  bool  $fallback  False skips the art PawTunes makes itself (Generated Artwork URL, AI Artwork and its
     *                          queueing): for tools that ask "does a source know this artist", like the panel lookup
     *
     * @return string|null
     */
    public function getArtwork($artist, string $title = "", string $override = "", bool $skipCache = false, bool $fallback = true): ?string
    {

        $sources = $this->getSortedArtworkSourcesList();

        $found = null;
        foreach ($sources as $source) {
            try {

                if ( ! isset($this->artworkMethods[$source['method']])) {
                    continue;
                }

                $seeker = "lib\PawTunes\Artwork\\{$this->artworkMethods[ $source['method']]}";

                $art             = new $seeker($this);
                $art->path       = $this->artworkPath;
                $art->cachePath  = $this->settings['cache']['path'] ?? './data/cache';
                $art->extensions = $this->artworkExtensions;
                $artwork         = $art($artist, $title, $override, $skipCache);

                if ($artwork) {
                    $found = $artwork;
                    break;
                }

            } catch (Throwable $e) {

                $this->handleException($e);

            }
        }

        if ($found) {
            return $found;
        }

        // Nothing uploaded, downloaded or found by a provider. Art we make ourselves is checked only now,
        // so a real cover always wins, also one that shows up later.
        $made = $fallback ? $this->fallbackArtwork((string) $artist) : null;

        return $made ?? $this->defaultArtwork();

    }


    /**
     * Art PawTunes makes itself for artists no source knows, null to show the default artwork
     *
     * @param  string  $artist
     *
     * @return string|null
     */
    protected function fallbackArtwork(string $artist): ?string
    {
        // The station's own placeholder artist keeps the branded default image
        if (trim($artist) === '' || $artist === $this->config('artist_default')) {
            return null;
        }

        $name = $this->parseTrack($artist);
        if ($name === '') {
            return null;
        }

        // AI Artwork: a stored image, or one drawn after this response (all safeguards in ArtworkGenerator)
        if (ArtworkGenerator::enabled($this)) {

            if ($stored = ArtworkGenerator::existing($name)) {
                return $stored;
            }

            ArtworkGenerator::queue($this, (string) $artist);

        }

        if ($this->config('generated_artwork')) {
            return GeneratedArtwork::url($name);
        }

        return null;
    }


    /**
     * Run work after the response reached the client, so slow work (AI Artwork) never keeps a listener waiting.
     * When this request's response cannot be ended early, the work is skipped (and logged) instead of blocking it.
     *
     * @param  callable  $task
     *
     * @return void
     */
    public function afterResponse(callable $task): void
    {
        // Every path in PawTunes is relative to the install root, but shutdown functions may run in another working
        // directory (Apache with CGI/FastCGI switches to the PHP binary's), which silently put AI Artwork, its
        // ledger and the logs next to php.exe. Return to the directory the request ran in first.
        $cwd = getcwd();

        register_shutdown_function(function () use ($task, $cwd) {

            if ($cwd !== false) {
                chdir($cwd);
            }

            if ( ! $this->finishResponse()) {
                $this->writeLog('player_errors', 'Background work skipped: this server cannot end the response early (headers already sent, or an output buffer that cannot be removed). PHP-FPM or LiteSpeed always can.');

                return;
            }

            set_time_limit(360);

            try {
                $task();
            } catch (Throwable $e) {
                $this->handleException($e);
            }

        });
    }


    /**
     * End the HTTP response so the client is done with it, while this process keeps running. Runs at shutdown:
     * output buffers are still open then (PHP flushes them after the shutdown functions).
     *
     * @return bool False when the response cannot be ended early, nothing slow may run in this request then
     */
    private function finishResponse(): bool
    {
        // The client disconnects as soon as it has the whole response, which must not abort the work after it
        ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) { // PHP-FPM
            return fastcgi_finish_request();
        }

        if (function_exists('litespeed_finish_request')) { // LiteSpeed LSAPI
            return litespeed_finish_request();
        }

        if (PHP_SAPI === 'cli') { // no client to wait
            return true;
        }

        // Other SAPIs (Apache mod_php, CGI): end the response by framing it ourselves. Content-Length tells the
        // client where the body ends and Connection: close that nothing follows. That needs the headers unsent
        // (the sendfile branch of handle-artwork.php flushes them) and every output buffer removable.
        if (headers_sent()) {
            return false;
        }

        foreach (ob_get_status(true) as $buffer) {
            if ( ! (($buffer['flags'] ?? 0) & PHP_OUTPUT_HANDLER_REMOVABLE)) {
                return false;
            }
        }

        // Collect the raw body from the innermost buffer outwards. Raw on purpose: a compressing handler
        // (zlib.output_compression) would make Content-Length wrong, so it is dropped together with its buffer.
        $body = '';
        while (ob_get_level() > 0) {
            $body = ob_get_clean().$body;
        }

        header('Content-Length: '.strlen($body));
        header('Connection: close');

        echo $body;
        flush();

        // Note: an Apache output filter that buffers (mod_deflate) can still hold the end of the body. Redirects,
        // the usual artwork answer, are not affected: browsers follow them as soon as the headers arrive.
        return true;
    }


    /**
     * URL of the lazy artwork endpoint, e.g. "./index.php?artwork&artist=AC%2FDC&title=Back%20in%20Black".
     * Values are encoded so names like "AC/DC" or "Rock & Roll" (and quotes) survive the query string.
     *
     * @param  string|null  $artist
     * @param  string|null  $title
     * @param  string|null  $override  Remote artwork URL from the stats provider (sent base64 encoded)
     *
     * @return string
     */
    public function lazyArtworkURL($artist, $title, $override = ''): string
    {
        // Untyped on purpose: provider history items may carry null or numeric values
        $url = './index.php?artwork&artist='.rawurlencode((string) $artist).'&title='.rawurlencode((string) $title);

        // Base64 contains "+" and "/", unencoded the "+" arrives as a space and the strict decode drops the override
        if ( ! empty($override)) {
            $url .= '&override='.rawurlencode(base64_encode((string) $override));
        }

        return $url;
    }


    /**
     * First data/images/default.<ext>, or null
     *
     * @return string|null
     */
    public function defaultArtwork(): ?string
    {

        if ($this->foundDefaultArtwork !== null) {
            return $this->foundDefaultArtwork;
        }

        foreach ($this->artworkExtensions as $ext) {
            if (is_file("{$this->artworkPath}/default.{$ext}")) {
                return $this->foundDefaultArtwork = "{$this->artworkPath}/default.{$ext}";
            }
        }

        return null;

    }


    /**
     * Returns sorted array of artwork sources
     *
     * @return array
     */
    protected function getSortedArtworkSourcesList(): array
    {
        $list = [];
        foreach ($this->config('artwork_sources') as $key => $value) {
            if (isset($value['state']) && $value['state'] === 'enabled') {
                $list[] = ['method' => $key] + $value;
            }
        }

        uasort($list, static function ($a, $b) {
            if (isset($a['index'], $b['index'])) {
                return $a['index'] <=> $b['index'];
            }

            return isset($a['index']) ? -1 : 1;
        });

        return $list;

    }


    /**
     * Transforms track name to a simplified string (Used for Artworks)
     *
     * @param $string
     *
     * @return string
     */
    public function parseTrack($string): string
    {
        $string = str_replace(
            ['&', 'ft.'],
            ['and', 'feat'],
            empty($string) ? '' : $string
        );

        $rep_arr = [
            '/[^a-z0-9\p{L}\.]+/iu' => '.',
            '/[\.]{1,}/'            => '.',
        ];

        $string = preg_replace(array_keys($rep_arr), $rep_arr, trim($string));

        return strtolower(rtrim($string, '.'));

    }


    /* ============================================================================
     * Output & Rendering
     * ============================================================================ */

    /**
     * @throws \Exception
     */
    public function outputBufferHandler($buffer)
    {
        $regex = [
            "/<!--.*?-->|\t/s" => "",
        ];

        $html_out = preg_replace(array_keys($regex), $regex, $buffer);

        $html_out = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', static function ($m) {

            $css = $m[2];
            $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
            $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '     '], '', $css);
            $css = preg_replace(['(( )+{)', '({( )+)'], '{', $css);
            $css = preg_replace(['(( )+})', '(}( )+)', '(;( )*})'], '}', $css);
            $css = preg_replace(['(;( )+)', '(( )+;)'], ';', $css);

            return '<style>'.$css.'</style>';

        }, $html_out);

        return preg_replace_callback('#<script(.*?)>(.*?)</script>#is', static function ($m) {

            $js = $m[2];
            $js = preg_replace('/\/\*(?:[^*]|\*+[^*\/])*\*+\/|(?<!:|\|\')\/\/.*/', '', $js);
            $js = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '     '], '', $js);

            return "<script{$m[1]}>".$js."</script>";

        }, $html_out);

    }


    /**
     * @throws \JsonException
     */
    public function getTemplateEngineOpts(): array
    {
        $host                   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->settings['host'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://').$host;
        $this->settings['url']  = "{$this->settings['host']}{$_SERVER['REQUEST_URI']}";

        if (empty($this->settings['share_image_override'])) {
            $facebookShare = $this->settings['host'].dirname($_SERVER['PHP_SELF']).'/'.$this->defaultArtwork();
        }

        $pass       = [];
        $configKeys = ['autoplay', 'site_title', 'title', 'description', 'google_analytics', 'template', 'artist_default', 'title_default'];
        foreach ($configKeys as $key) {
            $pass[$key] = $this->config($key);
        }

        $json = $this->generateConfigJSON();
        $tpl  = ['tpl' => Helpers::arrayKeysCaseToSnakeCase($json['tpl'])];

        $opts = [
            'url'             => $this->config('url'),
            'indexing'        => ($this->config('disable_index') ? 'NOINDEX, NOFOLLOW' : 'INDEX, FOLLOW'),
            'default_artwork' => $json['trackInfo']['default']['artwork'],
            'og_image'        => $facebookShare ?? $this->settings['share_image_override'],
            'og_site_title'   => (( ! empty($this->config('site_title'))) ? '<meta property="og:site_name" content="'.$this->config('site_title').'">' : ' '),
            'timestamp'       => time(),
            'json_settings'   => json_encode($json, JSON_THROW_ON_ERROR),
        ];

        return array_merge($opts, $pass, $this->getLanguage(), $tpl);

    }


    /**
     * @throws \JsonException
     */
    public function exitJSON(): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        echo json_encode([], JSON_THROW_ON_ERROR);
        exit;

    }


    /**
     * @throws \JsonException
     */
    private function generateConfigJSON(): array
    {
        $channels = [];
        if (count($this->channels) >= 1) {
            foreach ($this->channels as $channel) {

                $chn = [
                    'name'    => $channel['name'],
                    'logo'    => $channel['logo'] ?? null,
                    'skin'    => isset($channel['skin']) && is_file("templates/{$this->settings['template']}/{$channel['skin']}") ? $channel['skin'] : null,
                    'streams' => $channel['streams'],
                ];

                if (
                    ! empty($channel['stats']['url'])
                    && in_array($channel['stats']['method'], ['azuracast', 'custom'])
                ) {

                    $statsURL = parse_url($channel['stats']['url']);

                    $chn['ws']['method'] = $channel['stats']['method'];
                    $chn['ws']['url']    = ($statsURL['scheme'] === 'wss') ? $channel['stats']['url'] : false;

                    if ($channel['stats']['method'] === 'azuracast') {

                        $chn['ws']['station']         = $channel['stats']['station'];
                        $chn['ws']['history']         = $channel['stats']['azura-history'];
                        $chn['ws']['useRemoteCovers'] = $channel['stats']['use-cover'];

                    }

                }

                $channels[] = $chn;

            }
        }

        return [
            'debug'         => ($this->config('debugging') && $this->config('debugging') === 'enabled'),
            'channels'      => $channels,
            'analytics'     => (! empty($this->config('google_analytics')) ? $this->config('google_analytics') : false),
            // Casting is on unless the panel switched it off (configs from before the option have no key)
            'cast'          => filter_var($this->settings['cast'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'defaults'      => [
                'channel'        => Helpers::strToUTF8($this->config('default_channel')),
                'default_volume' => (($this->config('default_volume') >= 1 && $this->config('default_volume') <= 100) ? (int) $this->config('default_volume') : 50),
                'autoplay'       => (isset($_GET['autoplay']) && $_GET['autoplay'] === 'false') ? false : $this->config('autoplay'),
            ],
            'dynamicTitle'  => $this->config('dynamic_title') ?? false,
            'prefix'        => $this->prefix,
            'history'       => $this->config('history'),
            'historyMaxLen' => $this->config('historyLength') ?? 20,
            'language'      => $this->getLanguage(),
            'refreshRate'   => (is_numeric($this->config('stats_refresh')) && $this->config('stats_refresh') >= 3) ? (int) $this->config('stats_refresh') : 15,
            'template'      => $this->config('template'),
            'tpl'           => $this->getAdvancedTemplateOptions($this->config('template')),
            'title'         => Helpers::strToUTF8($this->config('title')),
            'trackInfo'     => [
                'artistMaxLen'     => $this->config('artist_maxlength'),
                'titleMaxLen'      => $this->config('title_maxlength'),
                'lazyLoadArtworks' => $this->config('artwork_lazy_loading'),
                'default'          => [
                    'artist'  => Helpers::strToUTF8($this->config('artist_default')),
                    'title'   => Helpers::strToUTF8($this->config('title_default')),
                    'artwork' => $this->defaultArtwork(),
                ],
            ],
        ];

    }


    private function getLanguage()
    {
        $lang = empty($_GET['language'])
            ? strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2))
            : $_GET['language'];

        $lang = preg_replace('/[^a-z0-9_-]/i', '', $lang);

        $localeDir = realpath("{$this->currentDir}/../locale");
        $langFile  = realpath("{$this->currentDir}/../locale/{$lang}.php");

        if ($langFile !== false && strpos($langFile, $localeDir) === 0 && is_file($langFile)) {
            return require($langFile);
        }

        return require("{$this->currentDir}/../locale/{$this->config('default_lang')}");

    }


    /**
     * @param $template
     *
     * @return array
     * @throws \JsonException
     */
    public function getAdvancedTemplateOptions($template): array
    {
        $templates = $this->getTemplates();

        if ( ! empty($templates[$template]) && ! empty($templates[$template]['extra'])) {

            $extras = [];
            foreach ($templates[$template]['extra'] as $index => $extra) {

                // Use saved value if available, otherwise use manifest default
                if (isset($this->settings['tplOptions'][$template][$index])) {
                    $extras[$index] = $this->settings['tplOptions'][$template][$index];
                } else {
                    $extras[$index] = ($extra['type'] !== 'checkbox') ? $extra['default'] : (bool) ($extra['default']);
                }

            }

            return $extras;

        }

        return [];

    }


    /**
     * @return array|mixed
     * @throws \JsonException
     */
    public function getTemplates()
    {
        if (($templates = $this->cache->get('templates')) === false) {

            $templates = [];

            $list = Helpers::browse("templates/", false, true, false);

            foreach ($list as $dir) {

                if (is_file("templates/{$dir}/manifest.json")) {

                    $loadedFile = json_decode(file_get_contents("templates/{$dir}/manifest.json"), true, 512, JSON_THROW_ON_ERROR);

                    if ( ! empty($loadedFile['name']) && is_file("templates/{$dir}/{$loadedFile['template']}")) {

                        $templates[$dir]         = $loadedFile;
                        $templates[$dir]['path'] = "templates/{$dir}";

                    }

                }

            }

            asort($templates);
            $this->cache->set('templates', $templates, 0);

        }

        return $templates;

    }


    /* ============================================================================
     * Backward-compatible forwarding to Helpers static methods
     *
     * Existing callers use $pawtunes->method() — these delegate to Helpers::method().
     * New code should call Helpers::method() directly.
     * ============================================================================ */

    public function currentURL(): string
    {
        return Helpers::currentURL();
    }

    public function xml2array(string $xml, bool $lower = false): array
    {
        return Helpers::xml2array($xml, $lower);
    }

    public function strToUTF8($string): string
    {
        return Helpers::strToUTF8($string);
    }

    public function shorten($text, $length)
    {
        return Helpers::shorten($text, $length);
    }

    public function parseURL($url): ?string
    {
        return Helpers::parseURL($url);
    }

    public function extGet($filename): string
    {
        return Helpers::extGet($filename);
    }

    public function extDel($filename)
    {
        return Helpers::extDel($filename);
    }

    public function formatBytes($b, $p = null): string
    {
        return Helpers::formatBytes($b, $p);
    }

    public function browse($path, bool $show_files = true, bool $show_directories = false, bool $directory_append = true): array
    {
        return Helpers::browse($path, $show_files, $show_directories, $directory_append);
    }

    public static function deleteFile($path): bool
    {
        return Helpers::deleteFile($path);
    }

    public function camelCaseToSnakeCase(string $input): string
    {
        return Helpers::camelCaseToSnakeCase($input);
    }

    public function arrayKeysCaseToSnakeCase($input): array
    {
        return Helpers::arrayKeysCaseToSnakeCase($input);
    }

}