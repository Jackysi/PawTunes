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

namespace lib\Image;

use GdImage;

/**
 * A raster image that can be resized, cropped and saved as JPEG, PNG, WebP or AVIF (GD only).
 *
 *     Image::open($file)?->crop(360, 360)->save('cover.webp');
 *
 * The format is read from the file's content and written by the target's extension, so saving under another
 * extension converts. Every method that changes the image returns $this.
 */
final class Image
{

    /**
     * Quality of a file an external re-encoder will replace: close to lossless on purpose, so the re-encoder
     * works from clean pixels instead of compressing the artifacts of a first lossy pass.
     */
    private const MASTER_QUALITY = 95;

    /**
     * The post-save hook: "run this work later", see postProcess()
     *
     * @var callable|null
     */
    private static $defer = null;

    /**
     * May the hook use tools that take seconds per image (guetzli)?
     */
    private static bool $slowTools = false;

    private GdImage $gd;


    private function __construct(GdImage $gd)
    {
        $this->gd = $gd;
    }


    /**
     * Turn on the post-save hook: after every save() the matching external tool (see Optimizer) recompresses
     * the stored file. The callable receives that work as a closure and decides WHEN it runs: PawTunes passes
     * afterResponse(), so no visitor ever waits for an optimizer.
     *
     * @param  callable|null  $defer  function (callable $work): void, null turns the hook off
     * @param  bool  $slowTools  true only when the deferred work really runs with nobody waiting for it
     *
     * @return void
     */
    public static function postProcess(?callable $defer, bool $slowTools = false): void
    {
        self::$defer     = $defer;
        self::$slowTools = $slowTools;
    }


    /**
     * @param  string  $file
     *
     * @return self|null  null when the file is not an image this server's GD can read (SVG included)
     */
    public static function open(string $file): ?self
    {
        $format = Formats::byContent($file);
        if ($format === null || ! $format->supported()) {
            return null;
        }

        $gd = $format->read($file);
        if ($gd === false) {
            return null;
        }

        // 256-color PNGs: WebP and AVIF cannot be written from a palette image
        if ( ! imageistruecolor($gd)) {
            imagepalettetotruecolor($gd);
        }

        return new self($gd);
    }


    public function width(): int
    {
        return imagesx($this->gd);
    }


    public function height(): int
    {
        return imagesy($this->gd);
    }


    /**
     * Stretch to exactly this size, proportions are NOT kept
     */
    public function resize(int $width, int $height): self
    {
        return $this->resample($width, $height, 0, 0, $this->width(), $this->height());
    }


    /**
     * Scale to this width, the height follows the proportions
     */
    public function scaleToWidth(int $width): self
    {
        return $this->resize($width, (int) round($width * $this->height() / $this->width()));
    }


    /**
     * Scale to this height, the width follows the proportions
     */
    public function scaleToHeight(int $height): self
    {
        return $this->resize((int) round($height * $this->width() / $this->height()), $height);
    }


    /**
     * Scale to the largest size that fits inside the box, proportions kept, nothing cut off
     */
    public function fit(int $width, int $height): self
    {
        $scale = min($width / $this->width(), $height / $this->height());

        return $this->resize((int) round($this->width() * $scale), (int) round($this->height() * $scale));
    }


    /**
     * Fill the box completely: scale until both sides cover it, then cut off what sticks out.
     *
     * @param  int  $width
     * @param  int  $height
     * @param  int|null  $x  Left edge of the cut, in pixels of the SCALED image. null = centered.
     * @param  int|null  $y  Top edge of the cut, same unit. null = centered.
     *
     * @return $this
     */
    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): self
    {
        // The factor at which the image just covers the box
        $scale = max($width / $this->width(), $height / $this->height());

        // How far the scaled image sticks out of the box on each axis (0 on at least one of them)
        $spareX = $this->width() * $scale - $width;
        $spareY = $this->height() * $scale - $height;

        // An offset outside the image is pulled back in, so the cut never contains empty pixels
        $x = ($x === null) ? $spareX / 2 : max(0, min($x, $spareX));
        $y = ($y === null) ? $spareY / 2 : max(0, min($y, $spareY));

        // Scaling and cutting in one pass: the cut is expressed in pixels of the original
        return $this->resample(
            $width,
            $height,
            (int) round($x / $scale),
            (int) round($y / $scale),
            (int) round($width / $scale),
            (int) round($height / $scale)
        );
    }


    /**
     * Write the image. The extension of $file picks the format.
     *
     * @param  string  $file
     * @param  int|null  $quality  0-100, null = the format's default
     *
     * @return bool  false: unknown extension, a format GD cannot write here, or the file could not be written
     */
    public function save(string $file, ?int $quality = null): bool
    {
        $format = Formats::byExtension($file);
        if ($format === null || ! $format->supported()) {
            return false;
        }

        $optimizer = (self::$defer !== null) ? Optimizer::find($format->extension(), self::$slowTools) : null;

        if ($quality === null) {
            $quality = ($optimizer !== null && $optimizer->reencodes()) ? self::MASTER_QUALITY : $format->quality();
        }

        // Written next to the target and renamed over it: a request that reads the file meanwhile gets the
        // old or the new image, never half of one
        $temp = $file.'.'.uniqid('', true).'.tmp';
        if ( ! $format->write($this->gd, $temp, $quality) || ! @rename($temp, $file)) {
            @unlink($temp);

            return false;
        }

        if ($optimizer !== null && ($path = realpath($file)) !== false) {

            // Absolute path: the work may run at shutdown, where the working directory can be another one
            (self::$defer)(static function () use ($optimizer, $path): void {
                $optimizer->run($path);
            });

        }

        return true;
    }


    /**
     * Copy a rectangle of the image into a new canvas of the target size
     */
    private function resample(int $width, int $height, int $srcX, int $srcY, int $srcWidth, int $srcHeight): self
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

        // Copy the alpha channel as it is instead of blending it into the (black) new canvas
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled($canvas, $this->gd, 0, 0, $srcX, $srcY, max(1, $width), max(1, $height), max(1, $srcWidth), max(1, $srcHeight));

        $this->gd = $canvas;

        return $this;
    }

}
