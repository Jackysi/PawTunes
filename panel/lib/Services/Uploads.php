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

namespace Services;

use lib\Helpers;
use lib\ImageResize;
use lib\PawTunes;
use RuntimeException;

/**
 * Stores images that come from outside: HTTP uploads, artwork imports and the app icon. Every file is
 * identified by its content and written under the detected type's extension, so nothing a web server
 * would execute can end up in data/.
 */
final class Uploads
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
     * Store an image that did not arrive as an HTTP upload (artwork import from a local folder or FTP) with the
     * same checks upload() applies: the content must be an accepted image (raster types by their decoded type,
     * SVG through isSafeSVG()), the stored extension is the detected one, and the name must not contain a
     * script extension. Without these checks an import folder could place x.php or a scripted SVG into
     * data/images/, which is served from the panel's origin.
     *
     * @param  string  $source  Readable local file (a temporary copy for remote sources)
     * @param  string  $sourceName  Original file name, only its extension is used (SVG detection)
     * @param  string  $name  Target name without extension, e.g. parseTrack() of the artist
     * @param  string  $path  Target directory, must exist
     * @param  bool  $overwrite  false = keep an existing file and report it as skipped
     * @param  string[]  $allowed  Extensions this caller accepts
     *
     * @return array{filename: string, path: string, skipped: bool, error: string|null}
     */
    public function importImage(string $source, string $sourceName, string $name, string $path = 'data/images/', bool $overwrite = true, array $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg']): array
    {
        $result = ['filename' => '', 'path' => '', 'skipped' => false, 'error' => null];

        $image = $this->detectImage($source, $sourceName, $allowed);
        if ($image === null) {
            $result['error'] = 'not an accepted image (allowed: '.strtoupper(implode(', ', $allowed)).')';

            return $result;
        }

        $target = strtolower($name.'.'.$image['extension']);
        if ($name === '' || preg_match(self::SCRIPT_EXTENSION_PATTERN, $target)) {
            $result['error'] = 'the file name is empty or contains a script extension such as ".php"';

            return $result;
        }

        $result['filename'] = $target;
        $result['path']     = $path.$target;

        if ( ! $overwrite && is_file($result['path'])) {
            $result['skipped'] = true;

            return $result;
        }

        if ( ! copy($source, $result['path'])) {
            $result['error'] = 'unable to write the file into '.$path;
        }

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

}
