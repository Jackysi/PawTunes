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

namespace lib;

/**
 * Abstract gradient artwork for artists no artwork source knows ("Generated Artwork" setting).
 *
 * The image is a pure function of the artist name: the same artist always gets the same colors and shapes,
 * there is no API and no cost. No text is rendered, so it can never pass for a real cover.
 *
 * Nothing is stored: the artwork endpoint draws it on every request (microseconds), so random names in public
 * "?artwork&artist=..." URLs cannot fill the disk, and browsers may cache it for good.
 */
final class GeneratedArtwork
{

    private const SIZE = 512;


    /**
     * URL that draws the artist's artwork, e.g. "./index.php?artwork&generated=daft.punk"
     *
     * @param  string  $name  Artist name normalized by PawTunes::parseTrack()
     *
     * @return string
     */
    public static function url(string $name): string
    {
        return './index.php?artwork&generated='.rawurlencode($name);
    }


    /**
     * Only names PawTunes::parseTrack() can produce: letters, digits and dots
     *
     * @param  string  $name
     *
     * @return bool
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[\p{L}\p{N}.]{1,200}$/u', $name) === 1;
    }


    /**
     * A diagonal two-color gradient with three soft glows, all derived from md5($seed)
     *
     * @param  string  $seed
     *
     * @return string SVG document
     */
    public static function svg(string $seed): string
    {
        // 16 bytes of stable "randomness", one byte per decision
        $bytes = array_values(unpack('C*', md5($seed, true)));

        // Base hue anywhere, the second one 25-70 degrees away: analogous colors always look intended,
        // wider gaps produce pairs like green and red
        $hue1 = $bytes[0] / 255 * 360;
        $hue2 = $hue1 + 25 + $bytes[1] / 255 * 45;

        $from  = self::hsl($hue1, 0.55 + $bytes[2] / 255 * 0.2, 0.42 + $bytes[3] / 255 * 0.12);
        $to    = self::hsl($hue2, 0.55 + $bytes[4] / 255 * 0.2, 0.30 + $bytes[5] / 255 * 0.12);
        $angle = round($bytes[6] / 255 * 360);

        $size  = self::SIZE;
        $glows = '';
        $stops = '';

        // Glows stay inside the palette (both hues and the one between them), unrelated hues mix into mud
        $glowHues = [$hue1, $hue2, ($hue1 + $hue2) / 2];

        for ($i = 0; $i < 3; $i++) {

            $b     = 7 + $i * 3;
            $cx    = round($bytes[$b] / 255 * $size);
            $cy    = round($bytes[$b + 1] / 255 * $size);
            $r     = round($size * (0.3 + $bytes[$b + 2] / 255 * 0.35));
            $color = self::hsl($glowHues[$i], 0.8, 0.66);

            $stops .= "<radialGradient id=\"g{$i}\"><stop offset=\"0\" stop-color=\"{$color}\" stop-opacity=\".55\"/>".
                      "<stop offset=\"1\" stop-color=\"{$color}\" stop-opacity=\"0\"/></radialGradient>";
            $glows .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"url(#g{$i})\"/>";

        }

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 {$size} {$size}\">".
               "<defs><linearGradient id=\"bg\" gradientTransform=\"rotate({$angle} .5 .5)\">".
               "<stop offset=\"0\" stop-color=\"{$from}\"/><stop offset=\"1\" stop-color=\"{$to}\"/></linearGradient>{$stops}</defs>".
               "<rect width=\"{$size}\" height=\"{$size}\" fill=\"url(#bg)\"/>{$glows}</svg>";
    }


    /**
     * HSL to a hex color (hex keeps the SVG readable by every renderer, including image libraries)
     *
     * @param  float  $hue  Degrees, any value (wrapped)
     * @param  float  $saturation  0..1
     * @param  float  $lightness  0..1
     *
     * @return string e.g. "#3f7ac2"
     */
    private static function hsl(float $hue, float $saturation, float $lightness): string
    {
        $hue    = fmod(fmod($hue, 360) + 360, 360) / 60;
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x      = $chroma * (1 - abs(fmod($hue, 2) - 1));
        $match  = $lightness - $chroma / 2;

        $rgb = [
            [$chroma, $x, 0], [$x, $chroma, 0], [0, $chroma, $x],
            [0, $x, $chroma], [$x, 0, $chroma], [$chroma, 0, $x],
        ][(int) floor($hue) % 6];

        return sprintf('#%02x%02x%02x', ...array_map(static function ($channel) use ($match) {
            return (int) round(($channel + $match) * 255);
        }, $rgb));
    }

}
