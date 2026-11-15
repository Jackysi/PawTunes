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

final class Png implements Format
{

    public function extension(): string
    {
        return 'png';
    }


    public function type(): int
    {
        return IMAGETYPE_PNG;
    }


    public function supported(): bool
    {
        return function_exists('imagepng') && (imagetypes() & IMG_PNG) !== 0;
    }


    public function quality(): int
    {
        return 100;
    }


    public function read(string $file)
    {
        return @imagecreatefrompng($file);
    }


    public function write(GdImage $image, string $file, int $quality): bool
    {
        // PNG is lossless: there is no quality to pick, only how hard zlib works. 9 = smallest file.
        imagesavealpha($image, true);

        return imagepng($image, $file, 9);
    }

}
