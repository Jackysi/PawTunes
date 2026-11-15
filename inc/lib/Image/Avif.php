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

final class Avif implements Format
{

    public function extension(): string
    {
        return 'avif';
    }


    public function type(): int
    {
        return IMAGETYPE_AVIF;
    }


    public function supported(): bool
    {
        // GD is often built without AVIF (it needs libavif), so both the function and the type bit are checked
        return function_exists('imageavif') && (imagetypes() & IMG_AVIF) !== 0;
    }


    public function quality(): int
    {
        // AVIF's scale is not JPEG's: 60 looks about like JPEG 85 at roughly half the size
        return 60;
    }


    public function read(string $file)
    {
        return @imagecreatefromavif($file);
    }


    public function write(GdImage $image, string $file, int $quality): bool
    {
        imagesavealpha($image, true);

        return imageavif($image, $file, $quality);
    }

}
