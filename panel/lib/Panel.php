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

use lib\PawTunes;
use Services\ConfigStore;
use Services\Uploads;

class Panel
{

    /**
     * Update server used to check for updates
     *
     * @var string
     */
    public string $updateCheckURL = 'https://prahec.com/api/releases/pawtunes';
    /**
     * Update server used download updates
     *
     * @var string
     */
    public string $updateDownloadURL = 'https://prahec.com/api/release/pawtunes/{version}';
    /**
     * @var PawTunes
     */
    public PawTunes $pawtunes;
    private ConfigStore $config;
    private Uploads $uploads;
    private string $prefix;
    /**
     * A collection of variables for the template class
     *
     * @var array
     */
    private array $globals;
    /**
     * Rendered using "Forms" class
     *
     * @var array
     */
    private array $artworkSources
        = [
            'itunes'   => [
                'name' => 'iTunes (no API keys)',
            ],
            'deezer'   => [
                'name' => 'Deezer (no API keys)',
            ],
            'applemusic' => [
                'name'   => 'Apple Music (API key required)',
                'fields' => [
                    [
                        'name'        => 'api_key',
                        'placeholder' => 'TEAM_ID:KEY_ID',
                        'helpText'    => 'Requires an Apple Developer account. Click to create a MusicKit key.',
                        'helpURL'     => 'https://developer.apple.com/account/resources/authkeys/list',
                    ],
                    [
                        'name'        => 'private_key',
                        'type'        => 'password',
                        'placeholder' => 'file:///path/to/AuthKey_XXXX.p8',
                        'helpText'    => 'Absolute path to AuthKey_XXXX.p8 (downloaded when creating the MusicKit key), prefixed with file://. Pasting the key contents here does not work, as this field drops the line breaks the key needs.',
                    ],
                    [
                        'name'        => 'storefront',
                        'size'        => 'narrow',
                        'placeholder' => 'Store (us)',
                        'helpText'    => 'Two-letter storefront code, e.g. us, de, si. Default: us',
                    ],
                ],
            ],
            'fanarttv' => [
                'name'  => 'FanArtTV (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'Your API key',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://fanart.tv/get-an-api-key/',
                ],
            ],
            'spotify'  => [
                'name'  => 'Spotify (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'client-id:client-secret format',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://developer.spotify.com/dashboard/',
                ],
            ],
            'lastfm'   => [
                'name'  => 'LastFM (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'Your API key',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://www.last.fm/api/account/create',
                ],
            ],
            'custom'   => [
                'name'  => 'Custom',
                'field' => [
                    'name'        => 'api_url',
                    'placeholder' => 'Enter URL to the API, {{$artist}} and {{$title}} will be replaced with the artist and title respectively',
                ],
            ],
        ];


    /**
     * @param  PawTunes  $pawtunes
     * @param  string  $prefix
     * @param  array  $vars
     * @param  ConfigStore  $config
     * @param  Uploads  $uploads
     */
    public function __construct(PawTunes $pawtunes, string $prefix, array $vars, ConfigStore $config, Uploads $uploads)
    {
        $this->pawtunes = $pawtunes;
        $this->prefix   = $prefix;
        $this->globals  = $vars;
        $this->config   = $config;
        $this->uploads  = $uploads;

    }


    /**
     * Generate or retrieve the CSRF token for this session
     *
     * @return string
     * @throws \Random\RandomException
     */
    public function csrfToken(): string
    {
        if (empty($_SESSION[$this->prefix]['csrf_token'])) {
            $_SESSION[$this->prefix]['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$this->prefix]['csrf_token'];

    }


    /**
     * Generate a hidden input field containing the CSRF token
     *
     * @return string
     * @throws \Random\RandomException
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->csrfToken() . '">';

    }


    /**
     * Verify that the submitted CSRF token matches the session token
     *
     * @return bool
     * @throws \Random\RandomException
     */
    public function verifyCsrfToken(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        return is_string($token) && hash_equals($this->csrfToken(), $token);

    }


    /**
     * Verify the CSRF token sent as a "_token" query parameter. Only for the streaming (SSE) API actions:
     * EventSource can only issue GET requests, so they can not carry the token in a POST body or a header.
     *
     * @return bool
     * @throws \Random\RandomException
     */
    public function verifyCsrfQueryToken(): bool
    {
        $token = $_GET['_token'] ?? '';

        return is_string($token) && hash_equals($this->csrfToken(), $token);

    }


    /**
     * Short function to speed up deployment of alerts
     *
     * @param        $text
     * @param  string  $mode
     * @param  bool  $php_message
     *
     * @return string
     */
    public function alert($text, string $mode = 'warning', bool $php_message = false): string
    {
        // Optional feature which allows replacing $text with actual PHP error message
        if ($php_message === true) {

            $err = error_get_last();
            if ( ! empty($err['message'])) {
                $text .= '<pre>'.htmlspecialchars($err['message'], ENT_QUOTES, 'UTF-8').'</pre>';
            }

        }

        // Different modes with icons (looks nice <3)
        switch ($mode) {

            case 'warning':
                $mode = 'alert-icon alert-warning';
                break;

            case 'error':
                $mode = 'alert-icon alert-danger';
                break;

            case 'success':
                $mode = 'alert-icon alert-success';
                break;

            default:
                $mode = 'alert-icon alert-info';
                break;

        }

        return '<div class="alert '.$mode.'"><div class="content">'.$text.'</div></div>';

    }


    /**
     * Forwards to ConfigStore::save(). This method must keep its name and signature for good: the
     * post-update.php of a future release runs inside the updater of the release being replaced and
     * calls $this->panel->storeConfig().
     *
     * @param $file     string Path & File name without extension
     * @param $contents array to store
     *
     * @return bool
     */
    public function storeConfig(string $file, array $contents): bool
    {
        return $this->config->save($file, $contents);

    }


    /**
     * @param  string  $key
     *
     * @return mixed|null
     */
    public function get(string $key = '')
    {
        return $this->globals[$key];

    }


    /**
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function setOption($key, $value)
    {
        return $this->globals[$key] = $value;

    }


    /**
     * Simple way to verify authorization
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return ! empty($this->globals['auth']);

    }


    /**
     * Generate a cryptographically secure random auth token
     *
     * @return string
     */
    public function createAuthToken(): string
    {
        return bin2hex(random_bytes(32));

    }


    /**
     * Destroy the current auth session
     *
     * @return void
     */
    public function destroyAuth(): void
    {
        $this->globals['auth'] = null;

    }


    /**
     * Get flash messages as structured array for toast rendering
     *
     * @return array<array{message: string, type: string}>
     */
    public function getFlashMessages(): array
    {
        if ( ! isset($_SESSION[$this->prefix]['flash']) || ! is_array($_SESSION[$this->prefix]['flash'])) {
            unset($_SESSION[$this->prefix]['flash']);
            return [];
        }

        $result = [];
        foreach ($_SESSION[$this->prefix]['flash'] as $html) {

            // Determine type from alert class
            $type = 'info';
            if (strpos($html, 'alert-success') !== false) {
                $type = 'success';
            } elseif (strpos($html, 'alert-danger') !== false || strpos($html, 'alert-error') !== false) {
                $type = 'error';
            } elseif (strpos($html, 'alert-warning') !== false) {
                $type = 'warning';
            }

            // Strip HTML tags to get plain text
            $text = strip_tags($html);
            $text = trim($text);

            $result[] = ['message' => $text, 'type' => $type];
        }

        unset($_SESSION[$this->prefix]['flash']);
        return $result;
    }


    /**
     * Simple function to generate HTML view
     *
     * @throws Exception
     */
    public function view($view, $vars = []): void
    {
        $blade = new BladeOne(
            './panel/views',
            './panel/views/cache',
            ($this->pawtunes->config('development') ? BladeOne::MODE_DEBUG : BladeOne::MODE_AUTO)
        );

        $blade->pipeEnable = true;
        $blade->setCompiledExtension('.compiled.php');
        $blade->share($this->globals);
        $blade->share($vars);
        echo $blade->run(
            $view,
            [
                'panel'    => $this,
                'pawtunes' => $this->pawtunes,
            ]
        );

    }


    /**
     * @param        $message
     * @param  string  $redirect
     *
     * @return void
     */
    public function flash($message, string $redirect = ""): void
    {
        $_SESSION[$this->prefix]['flash'][] = $message;

        // When redirecting, quit
        if ($redirect !== "") {

            header("Location: $redirect");
            exit;

        }

    }


    /**
     * Short function to delete all extensions for artist
     *
     * @param $name
     *
     * @return bool
     */
    public function deleteArtwork($name): bool
    {
        // Define extensions
        $allow_ext = ['jpeg', 'jpg', 'png', 'svg', 'webp'];

        // Set variables
        $files = $this->pawtunes->browse('data/images/');
        $name  = $this->pawtunes->parseTrack($name);

        // If is array, loop through files and match what we're deleting
        foreach ($files as $file) { // Loop files

            if ($this->pawtunes->extDel($file) === $name) { // File matches, delete all extensions of this artist
                foreach ($allow_ext as $ext) {

                    // Delete file
                    if (is_file("data/images/".$this->pawtunes->extDel($file).".{$ext}")) {
                        $this->pawtunes::deleteFile("data/images/".$this->pawtunes->extDel($file).".{$ext}");
                    }

                }

                return true; // stop loop

            }
        }

        return false;

    }


    /**
     * This will be passed to the view
     *
     * @param $data
     *
     * @return array
     */
    public function mapArtworkSourcesView($data): array
    {
        $finalData = [];
        foreach ($this->artworkSources as $key => $value) {
            if (array_key_exists($key, $data)) {

                $finalData[$key] = array_merge(
                    is_array($value) ? $value : [],
                    is_array($data[$key]) ? $data[$key] : [] // Ensure $post[$key] is an array
                );

            } else {

                $finalData[$key] = $value;
            }
        }

        // Include keys from $post that aren't in artworkSources
        foreach ($data as $key => $value) {
            if ( ! array_key_exists($key, $this->artworkSources)) {
                $finalData[$key] = $value;
            }
        }

        // Neat multi-sort
        uasort($finalData, static function ($a, $b) {
            if (isset($a['index'], $b['index'])) {
                return $a['index'] <=> $b['index'];
            }

            return isset($a['index']) ? -1 : 1;
        });

        return $finalData;

    }


    /**
     * Pick the artwork source fields out of the posted settings form: each source posts its fields as one
     * array under its key. The list is drag & drop sortable, so the order the sources arrive in is the
     * order they are tried in: it is stored as "index" (0 = first).
     *
     * @param  array  $post  The posted settings form
     *
     * @return array The "artwork_sources" configuration
     */
    public function mapArtworkSourcesPost(array $post): array
    {
        $finalData = [];
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $this->artworkSources) && is_array($value)) {

                $finalData[$key]          = $value;
                $finalData[$key]['index'] = count($finalData) - 1;

            }
        }

        return $finalData;

    }


    /**
     * Forwards to Uploads::importImage() for API\Artwork: the API classes receive only PawTunes and Panel
     *
     * @see Uploads::importImage()
     */
    public function importImage(string $source, string $sourceName, string $name, string $path = 'data/images/', bool $overwrite = true, array $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg']): array
    {
        return $this->uploads->importImage($source, $sourceName, $name, $path, $overwrite, $allowed);

    }


    /**
     * @param $message
     *
     * @return void
     */
    public function sendError($message): void
    {
        http_response_code(400);
        echo $message;
        exit;
    }

}