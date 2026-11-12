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

use lib\Helpers;
use lib\ImageResize;
use lib\PawTunes;

class Panel
{

    /**
     * PHP file name parts that the bundled stack may execute, in any position (x.php.png included). This is
     * the same rule data/.htaccess applies to the PHP family. data/.htaccess denies the other script types
     * (.cgi, .pl, .sh, ...) only as the FINAL extension, and a stored image always ends in its detected image
     * extension, so they need no check here: a real artist name such as "Mr. Sh" (mr.sh.jpg) stays allowed.
     */
    private const SCRIPT_EXTENSION_PATTERN = '/\.(php[3-8]?|phtml|phar|pht|phps)(\.|$)/i';

    /**
     * Raster image types accepted by upload(), keyed by the getimagesize() type constant
     */
    private const RASTER_TYPES
        = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

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
     */
    public function __construct(PawTunes $pawtunes, string $prefix, array $vars = [])
    {
        $this->pawtunes = $pawtunes;
        $this->prefix   = $prefix;
        $this->globals  = $vars;

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

        return hash_equals($this->csrfToken(), $token);

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
     * @param $file     string Path & File name without extension
     * @param $contents array to store
     *
     * @return bool
     */
    public function storeConfig(string $file, array $contents): bool
    {

        // Remove CSRF token from stored data
        unset($contents['_token']);

        // Convert array to boolean
        $contents = $this->convertArrayToBool($contents);

        // Attempt storing new config
        $store = file_put_contents("inc/{$file}.php", "<?php \nreturn ".var_export($contents, true).";");

        // Clear various file caches
        if ($store) {

            clearstatcache(true);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate("inc/{$file}.php", true);
            }

        }

        return (bool) $store;

    }


    /**
     * Loop through contents and check for string 'true' or string 'false' and replace with boolean
     *
     * @param  array  $array
     *
     * @return array
     */
    private function convertArrayToBool(array $array): array
    {
        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $array[$key] = $this->convertArrayToBool($value);
                continue;
            }

            if ($value === 'true' || $value === 'false') {
                $array[$key] = ($value === 'true');
            }

        }

        return $array;

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
     * @return void
     */
    public function flashMessages(): void
    {
        if (isset($_SESSION[$this->prefix]['flash'])) {

            if ( ! is_array($_SESSION[$this->prefix]['flash'])) {

                unset($_SESSION[$this->prefix]['flash']);
                return;

            }

            foreach ($_SESSION[$this->prefix]['flash'] as $message) {
                echo $message;
            }

            // Empty
            unset($_SESSION[$this->prefix]['flash']);

        }

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
     * Parsed into config
     *
     * @param $post
     *
     * @return array
     */
    public function mapArtworkSourcesPost($post): array
    {
        $finalData = [];
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $this->artworkSources)) {

                unset($_POST[$key]);
                $finalData[$key]          = $value;
                $finalData[$key]['index'] = array_search($key, array_keys($post), true);

            }
        }

        return $finalData;

    }


    /**
     * Image upload handler. Returns the stored file details, with 'error' set when nothing was stored.
     *
     * Only real images are accepted. Raster files must decode with getimagesize() and the stored
     * extension comes from the detected type, never from the client file name. SVG files (only when
     * the caller lists 'svg') must parse as XML with an <svg> root and must not carry scripts, see
     * isSafeSVG(). The target is always "<filename>.<ext>" with a single, lowercase extension, so a
     * client name such as "x.php.png" can never end up as something a web server would execute.
     *
     * @param  string  $form_name  $_FILES field name
     * @param  string  $path  Target directory, created when missing
     * @param  string  $filename  Target name without extension; '' = sanitized client name, '.' = random
     * @param  string[]  $allowed  Extensions this caller accepts: jpg, jpeg, png, gif, webp, svg
     *
     * @return array{filename: string, path: string, extension: string, mimetype: string, size: int, error: string|null}
     */
    public function upload($form_name, string $path = 'data/uploads/', string $filename = '', array $allowed = ['jpg', 'jpeg', 'png', 'webp']): array
    {
        $file = $_FILES[$form_name] ?? [];

        // ERR Handler, keys must be integer constants matching $_FILES[...]['error']
        $errors = [
            UPLOAD_ERR_INI_SIZE   => "Larger than upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE  => "Your upload is too big!",
            UPLOAD_ERR_PARTIAL    => "Upload partially completed!",
            UPLOAD_ERR_NO_FILE    => "No file specified!",
            UPLOAD_ERR_NO_TMP_DIR => "Server error: missing temp directory.",
            UPLOAD_ERR_CANT_WRITE => "Server error: failed to write to disk.",
            UPLOAD_ERR_EXTENSION  => "Server error: upload blocked by extension.",
        ];

        $result = [
            'filename'  => '',
            'path'      => '',
            'extension' => '',
            'mimetype'  => '',
            'size'      => (int) ($file['size'] ?? 0),
            'error'     => null,
        ];

        $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $result['error'] = $errors[$uploadError] ?? "Unknown upload error.";

            return $result;
        }

        if ( ! is_uploaded_file($file['tmp_name'])) {
            $result['error'] = "No file specified!";

            return $result;
        }

        $image = $this->detectImage($file['tmp_name'], $file['name'], $allowed);
        if ($image === null) {
            $result['error'] = 'Invalid image file! Accepted formats: '.strtoupper(implode(', ', $allowed)).'.';

            return $result;
        }

        // Target file name, always "<name>.<detected extension>"
        if ($filename === '') {

            // Client file name reduced to a safe character set, dots included (no second extension)
            $filename = trim(preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($file['name'], PATHINFO_FILENAME)), '-');
            $filename = ($filename === '') ? uniqid('', true) : $filename;

        } elseif ($filename === '.') {

            $filename = uniqid('', true);

        }

        $target = strtolower($filename.'.'.$image['extension']);

        // Caller-provided names (artist names, for instance) may contain dots: never store "name.php.jpg"
        if (preg_match(self::SCRIPT_EXTENSION_PATTERN, $target)) {
            $result['error'] = 'The file name must not contain a script extension such as ".php".';

            return $result;
        }

        // Check if path for upload exists, if not create it
        if ( ! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        if ( ! move_uploaded_file($file['tmp_name'], $path.$target)) {
            $result['error'] = "Error while saving file!";

            return $result;
        }

        $result['filename']  = $target;
        $result['path']      = $path.$target;
        $result['extension'] = $image['extension'];
        $result['mimetype']  = $image['mimetype'];

        return $result;

    }


    /**
     * Stores the uploaded app icon (home screen / web app manifest) as the three PNG sizes the player
     * links: data/images/app-icon-512.png, -192 and -180. Each is a fresh center crop of the uploaded
     * source (never a resample of a resample), the source is removed once the sizes exist, and a failure
     * half way leaves no partial set behind (PawTunes::getAppIcons() only uses a complete set).
     *
     * @param  string  $form_name  $_FILES field name
     *
     * @return string|null  Error message for the admin, null on success
     */
    public function storeAppIcon(string $form_name): ?string
    {
        $upload = $this->upload($form_name, 'data/images/', 'app-icon-source', ['jpg', 'jpeg', 'png', 'webp']);
        if ($upload['error'] !== null) {
            return $upload['error'];
        }

        $source = $upload['path'];
        $info   = @getimagesize($source);
        if ($info === false || $info[0] < 512 || $info[1] < 512) {
            PawTunes::deleteFile($source);

            return 'The app icon must be at least 512 x 512 pixels.';
        }

        $sizes = [512, 192, 180];
        foreach ($sizes as $size) {

            if (ImageResize::handle($source, "{$size}x{$size}", 'crop', "data/images/app-icon-{$size}.png") === false) {

                PawTunes::deleteFile($source);
                foreach ($sizes as $cleanup) {
                    PawTunes::deleteFile("data/images/app-icon-{$cleanup}.png");
                }

                return 'Unable to process the app icon, the image could not be read by GD.';

            }

        }

        PawTunes::deleteFile($source);

        return null;

    }


    /**
     * Removes the generated app icon sizes, the bundled PawTunes icons are used again
     */
    public function removeAppIcon(): void
    {
        foreach ([512, 192, 180] as $size) {
            PawTunes::deleteFile("data/images/app-icon-{$size}.png");
        }

    }


    /**
     * Identify an uploaded image by its content and return the extension to store it under.
     *
     * @param  string  $tmpFile  Uploaded temporary file
     * @param  string  $clientName  File name sent by the browser (only its extension is used, for SVG)
     * @param  string[]  $allowed  Extensions the caller accepts
     *
     * @return array{extension: string, mimetype: string}|null  null when the file is not an accepted image
     */
    private function detectImage(string $tmpFile, string $clientName, array $allowed): ?array
    {
        $allowed = array_map('strtolower', $allowed);

        // Raster image: the decoded type decides, jpg and jpeg are the same format
        $info = @getimagesize($tmpFile);
        if ($info !== false && isset(self::RASTER_TYPES[$info[2]])) {

            $extension = self::RASTER_TYPES[$info[2]];
            $accepted  = in_array($extension, $allowed, true) || ($extension === 'jpg' && in_array('jpeg', $allowed, true));

            return $accepted ? ['extension' => $extension, 'mimetype' => image_type_to_mime_type($info[2])] : null;

        }

        // SVG has no raster signature, so it must be named .svg and pass the content checks
        if (in_array('svg', $allowed, true) && Helpers::extGet($clientName) === 'svg' && $this->isSafeSVG($tmpFile)) {
            return ['extension' => 'svg', 'mimetype' => 'image/svg+xml'];
        }

        return null;

    }


    /**
     * Accept an SVG only when it parses as XML with an <svg> root and carries no scripting.
     *
     * An SVG embedded through <img> never runs scripts, but uploaded files are also reachable directly
     * from data/images/ on the panel's origin, where an SVG with a <script> element, an on* handler or a
     * javascript:/data: URL would run with the admin's session. A raw-text regex misses namespace-prefixed
     * elements (<svg:script>, <s:script>) and entity-encoded URLs (javascript&#58;), so the document is
     * parsed and walked instead: every element and attribute is judged by its local name, and attribute
     * values are judged after the parser has decoded their entities.
     *
     * @param  string  $file
     *
     * @return bool
     */
    private function isSafeSVG(string $file): bool
    {
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $dom      = new \DOMDocument();
        // LIBXML_NONET blocks network access; no LIBXML_NOENT/LIBXML_DTDLOAD, so entities are never expanded
        // or fetched. A load failure (malformed XML, an undeclared namespace prefix) is rejected below.
        $loaded = $dom->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ( ! $loaded) {
            return false;
        }

        // A DOCTYPE can declare entities or pull in a DTD; a plain SVG image needs none
        if ($dom->doctype !== null) {
            return false;
        }

        $root = $dom->documentElement;
        if ($root === null || strtolower($root->localName) !== 'svg') {
            return false;
        }

        // Judged by local name, so a namespace prefix (<svg:script>, <s:script>) cannot hide a node
        $blockedElements = ['script', 'foreignobject', 'handler'];

        foreach ($dom->getElementsByTagName('*') as $node) {

            if (in_array(strtolower($node->localName), $blockedElements, true)) {
                return false;
            }

            foreach ($node->attributes as $attr) {

                // Event handlers (onload, onclick, ...) in any namespace
                if (strpos(strtolower($attr->localName), 'on') === 0) {
                    return false;
                }

                // The parser has already decoded entities in the value; strip whitespace and control
                // characters so "java\nscript:" or "javascript&#58;" cannot hide the scheme. data: is
                // rejected outright (no legitimate need for it in an uploaded station image).
                $value = strtolower(preg_replace('/[\x00-\x20]+/', '', $attr->value));
                if (strpos($value, 'javascript:') === 0 || strpos($value, 'data:') === 0) {
                    return false;
                }

            }

        }

        return true;

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