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
 * One image file format: how GD reads and writes it. Image does the pixel work and never knows which format it
 * is handling, so a new format is one class here plus one line in Formats.
 */
interface Format
{

    /**
     * File extension files of this format are stored under, e.g. "jpg"
     */
    public function extension(): string;


    /**
     * The IMAGETYPE_* constant getimagesize() reports for this format
     */
    public function type(): int;


    /**
     * Can this server's GD read and write the format?
     */
    public function supported(): bool;


    /**
     * Quality (0-100) used when the caller names none. Lossless formats ignore quality altogether.
     */
    public function quality(): int;


    /**
     * @param  string  $file
     *
     * @return GdImage|false
     */
    public function read(string $file);


    /**
     * @param  GdImage  $image  Always true color
     * @param  string  $file
     * @param  int  $quality  0-100
     *
     * @return bool
     */
    public function write(GdImage $image, string $file, int $quality): bool;

}
