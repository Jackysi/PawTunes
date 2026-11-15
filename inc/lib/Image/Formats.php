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

/**
 * The formats the image library knows. Adding one: a class implementing Format, plus a line in all().
 */
final class Formats
{

    /**
     * @var Format[]|null
     */
    private static ?array $all = null;


    /**
     * @return Format[]
     */
    public static function all(): array
    {
        return self::$all ??= [new Jpeg(), new Png(), new Webp(), new Avif()];
    }


    /**
     * Format for a file extension, with or without a path in front ("cover.JPEG", "webp")
     *
     * @param  string  $extension
     *
     * @return Format|null  null for a format the library does not handle (svg, gif, ...)
     */
    public static function byExtension(string $extension): ?Format
    {
        $dot       = strrchr($extension, '.');
        $extension = strtolower($dot !== false ? substr($dot, 1) : $extension);

        // The one format with two spellings
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        foreach (self::all() as $format) {
            if ($format->extension() === $extension) {
                return $format;
            }
        }

        return null;
    }


    /**
     * Format of an existing file, decided by its content: a file name can lie (a provider sending WebP as
     * "image/jpeg"), the first bytes cannot.
     *
     * @param  string  $file
     *
     * @return Format|null
     */
    public static function byContent(string $file): ?Format
    {
        $info = @getimagesize($file);
        if ($info === false) {
            return null;
        }

        foreach (self::all() as $format) {
            if ($format->type() === $info[2]) {
                return $format;
            }
        }

        return null;
    }


    /**
     * Extensions of the formats this server's GD can write, e.g. ['jpg', 'png', 'webp']
     *
     * @return string[]
     */
    public static function supported(): array
    {
        $extensions = [];
        foreach (self::all() as $format) {
            if ($format->supported()) {
                $extensions[] = $format->extension();
            }
        }

        return $extensions;
    }

}
