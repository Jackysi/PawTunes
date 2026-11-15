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

final class Jpeg implements Format
{

    public function extension(): string
    {
        return 'jpg';
    }


    public function type(): int
    {
        return IMAGETYPE_JPEG;
    }


    public function supported(): bool
    {
        return function_exists('imagejpeg') && (imagetypes() & IMG_JPG) !== 0;
    }


    public function quality(): int
    {
        return 85;
    }


    public function read(string $file)
    {
        return @imagecreatefromjpeg($file);
    }


    public function write(GdImage $image, string $file, int $quality): bool
    {
        // JPEG has no transparency. GD would turn transparent pixels black, so the image is laid over white first
        // (a PNG logo or cover with a transparent edge converted to JPEG).
        $width  = imagesx($image);
        $height = imagesy($image);
        $flat   = imagecreatetruecolor($width, $height);

        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);

        // Progressive: usually a little smaller, and it shows a full preview while loading
        imageinterlace($flat, true);

        return imagejpeg($flat, $file, $quality);
    }

}
