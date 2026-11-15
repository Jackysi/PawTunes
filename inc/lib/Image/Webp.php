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

final class Webp implements Format
{

    public function extension(): string
    {
        return 'webp';
    }


    public function type(): int
    {
        return IMAGETYPE_WEBP;
    }


    public function supported(): bool
    {
        return function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
    }


    public function quality(): int
    {
        return 82;
    }


    public function read(string $file)
    {
        return @imagecreatefromwebp($file);
    }


    public function write(GdImage $image, string $file, int $quality): bool
    {
        imagesavealpha($image, true);

        return imagewebp($image, $file, $quality);
    }

}
