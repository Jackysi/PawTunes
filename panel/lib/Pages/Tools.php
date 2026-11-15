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

namespace Pages;

use lib\Helpers;
use lib\Image\Image;
use lib\PawTunes;
use Panel;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;
use Services\Request;
use Services\Uploads;

/**
 * Tools page: the color scheme compiler, the artwork manager and the debugger. The lists, the artwork
 * import and the lookups on this page run through the panel API (Pages\Api); this class handles the two
 * forms that post to the page itself.
 */
final class Tools extends Page
{

    private PawTunes $pawtunes;

    private Panel $panel;

    private Uploads $uploads;

    private Request $request;

    /**
     * Alert HTML with the outcome of the color scheme compiler
     */
    private ?string $themeMessage = null;


    public function __construct(PawTunes $pawtunes, Panel $panel, Uploads $uploads, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->uploads  = $uploads;
        $this->request  = $request;
    }


    protected function index(): void
    {
        $this->panel->view(
            'tools',
            [
                'theme_message' => $this->themeMessage,
                'templates'     => $this->pawtunes->getTemplates(),
                'channels'      => $this->pawtunes->getChannels(),
                'extensions'    => $this->pawtunes->artworkExtensions,
            ]
        );

    }


    /**
     * Compile a color scheme: one of a template's SCSS schemes with the picked accent and background color,
     * written to templates/<template>/custom/<name>.css
     */
    protected function postCompile(): void
    {
        // Every field ends up in a file name or in SCSS source, so each one is checked against a strict
        // shape before it is used anywhere
        $filename    = strtolower(trim($this->request->post('filename')));
        $template    = $this->request->post('template');
        $baseTheme   = $this->request->post('base-theme');
        $accentColor = trim($this->request->post('base-color'));
        $bgColor     = trim($this->request->post('bg-color'));

        $templates = $this->pawtunes->getTemplates();
        $path      = $templates[$template]['path'] ?? '';

        // The base theme must be one of the chosen template's compilable schemes (manifest "compile" key)
        $scssFile = '';
        foreach ($templates[$template]['schemes'] ?? [] as $scheme) {
            if (($scheme['name'] ?? null) === $baseTheme && ! empty($scheme['compile'])) {
                $scssFile = $scheme['compile'];
                break;
            }
        }

        $problem = null;
        if ( ! isset($templates[$template])) {
            $problem = 'Please select one of the installed templates!';
        } elseif ( ! preg_match('/^[a-z0-9_-]{1,64}$/', $filename)) {
            $problem = 'The theme name may only contain lowercase letters, numbers, "-" and "_" (up to 64 characters)!';
        } elseif ($scssFile === '' || ! is_file("{$path}/{$scssFile}")) {
            $problem = 'Unable to compile a new theme since the <b>base theme</b> is not a compilable scheme of the selected template!';
        } elseif ( ! $this->isCssColor($accentColor) || ! $this->isCssColor($bgColor)) {
            $problem = 'Colors must be CSS colors such as <b>#3498db</b>, <b>rgb(52, 152, 219)</b> or <b>hsl(204, 70%, 53%)</b>!';
        } elseif ( ! is_dir("{$path}/custom/") && ! mkdir("{$path}/custom/", 0755) && ! is_dir("{$path}/custom/")) {
            $problem = 'Directory "custom" under the template directory does not exist because something went wrong while creating it!';
        }

        if ($problem !== null) {
            $this->themeMessage = $this->panel->alert($problem, 'error');

            return;
        }

        try {

            $compiler = new Compiler();
            $compiler->setImportPaths($path.'/'.dirname($scssFile));
            $compiler->setOutputStyle(OutputStyle::COMPRESSED);

            $css = $compiler->compileString("\$accent-color: {$accentColor}; \$bg-color: {$bgColor}; @import '".basename($scssFile)."';")->getCss();

            // The scheme carries its accent color and base file in a comment, the panel reads them back from the CSS
            $css = preg_replace(
                ['/accent=([^;]*);/i', '/scheme=([^;]*);/i'],
                ["accent={$accentColor};", 'scheme='.basename($scssFile).';'],
                $css
            );

        } catch (SassException $e) {

            $this->themeMessage = $this->panel->alert('SASS Compilation ERROR occurred: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'!', 'error');

            return;
        }

        if ( ! file_put_contents("{$path}/custom/{$filename}.css", $css)) {
            $this->themeMessage = $this->panel->alert('Unable to save new theme! Please make sure the template directory is writable (chmod 755)!', 'error');

            return;
        }

        // The channel editor and the player list schemes from the cached template list
        $this->pawtunes->cache->delete('templates');
        $this->themeMessage = $this->panel->alert('Successfully compiled new player theme!', 'success');

    }


    /**
     * Store an uploaded artwork image for a track or artist, cropped to the configured square size. Called
     * by the page's script: answers with plain text, an error with HTTP 400.
     */
    protected function postUploadArtwork(): void
    {
        header('Content-Type: text/plain');

        $extensions = $this->pawtunes->artworkExtensions;
        $fileName   = $this->request->uploadName('image');

        if ($fileName === '') {
            $this->panel->sendError('No image was uploaded!');
        }

        if ( ! in_array(Helpers::extGet($fileName), $extensions, true)) {
            $this->panel->sendError('You have uploaded invalid image file!');
        }

        if ($this->request->post('track') === '') {
            $this->panel->sendError('You need to enter track name!');
        }

        // upload() verifies the file is really one of the allowed image formats
        $track  = $this->pawtunes->parseTrack($this->request->post('track'));
        $upload = $this->uploads->upload('image', 'data/images/', $track, $extensions);
        if ($upload['error'] !== null) {
            $this->panel->sendError("Uploading failed! ERROR: {$upload['error']}");
        }

        if ( ! extension_loaded('gd')) {
            PawTunes::deleteFile($upload['path']);
            $this->panel->sendError('Cropping image failed because GD extension is not available!');
        }

        $size = (int) $this->pawtunes->config('images_size');
        if ($size < 100) {
            $size = 280;
        }

        // The crop offset was picked on a 140px preview, scale it to the stored size
        $scale = $size / 140;

        // "Artwork Format": stored in that one format, otherwise in the uploaded one
        $format = $this->pawtunes->artworkFormat();
        $stored = ($format !== null) ? "data/images/{$track}.{$format}" : $upload['path'];

        // An SVG cannot be opened (nor cropped or converted) and stays as uploaded
        $image   = Image::open($upload['path']);
        $cropped = $image !== null && $image
                ->crop($size, $size, (int) round((int) $this->request->post('cropX') * $scale), (int) round((int) $this->request->post('cropY') * $scale))
                ->save($stored);

        if ( ! $cropped) {
            $stored = $upload['path'];
        }

        // Drop the other copies of this track: older uploads in other formats, and the upload itself once converted
        foreach ($extensions as $extension) {
            if ("data/images/{$track}.{$extension}" !== $stored && is_file("data/images/{$track}.{$extension}")) {
                PawTunes::deleteFile("data/images/{$track}.{$extension}");
            }
        }

        die('Artwork image was uploaded and handled successfully!');

    }


    /**
     * Accept only real CSS color literals: #rgb, #rgba, #rrggbb, #rrggbbaa and rgb()/rgba()/hsl()/hsla()
     * with numeric (optionally percent) arguments. The value is interpolated into SCSS source, so nothing
     * else may pass.
     *
     * @param  string  $color
     *
     * @return bool
     */
    private function isCssColor(string $color): bool
    {
        $hex      = '#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})';
        $number   = '-?\d+(?:\.\d+)?%?';
        $function = '(?:rgb|hsl)a?\(\s*'.$number.'\s*,\s*'.$number.'\s*,\s*'.$number.'(?:\s*,\s*'.$number.')?\s*\)';

        return (bool) preg_match('/^(?:'.$hex.'|'.$function.')$/i', $color);

    }

}
