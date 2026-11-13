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

namespace lib\AI;

/**
 * Image prompts built only from fixed word lists.
 *
 * The artist and title never reach the image model: no stream metadata can inject prompt text, and the model
 * cannot draw a lookalike of a real cover, a band photo or a name. The choices are seeded by the artist name,
 * so an artist keeps the same style. With AI Assistant on, the mood may come from the language model, but only
 * as one word of MOODS (enforced by the answer schema and checked again here).
 */
final class ArtworkPrompt
{

    public const MOODS = ['calm', 'dreamy', 'energetic', 'dark', 'warm', 'melancholic', 'euphoric', 'raw'];

    private const STYLES = [
        'flowing liquid gradients',
        'soft geometric shapes',
        'layered paper cut-outs',
        'watercolor washes',
        'blurred light bokeh',
        'sweeping ink brush strokes',
        'smooth abstract 3D forms',
        'grainy risograph texture',
    ];

    private const PALETTES = [
        'deep blue and violet',
        'warm orange and magenta',
        'teal and emerald',
        'crimson and gold',
        'pastel pink and lavender',
        'midnight black and neon cyan',
        'sand and terracotta',
        'silver and ice blue',
    ];

    private const COMPOSITIONS = ['centered', 'diagonal', 'layered', 'radial'];

    /**
     * Everything that would make the image pass for a real cover, or show a real person, is excluded
     */
    private const RULES = 'Purely abstract: no text, no letters, no numbers, no words, no logos, no symbols, no signatures, '.
                          'no people, no faces, no hands, no silhouettes, no animals, no instruments, '.
                          'no album cover layout, no frames, no borders, no watermark.';


    /**
     * @param  string  $seed  Normalized artist name (PawTunes::parseTrack)
     * @param  string|null  $mood  One of MOODS, anything else is replaced by a seeded pick
     *
     * @return string
     */
    public static function build(string $seed, ?string $mood = null): string
    {
        $bytes = array_values(unpack('C*', md5($seed, true)));

        $style       = self::STYLES[$bytes[0] % count(self::STYLES)];
        $palette     = self::PALETTES[$bytes[1] % count(self::PALETTES)];
        $composition = self::COMPOSITIONS[$bytes[2] % count(self::COMPOSITIONS)];
        $mood        = in_array($mood, self::MOODS, true) ? $mood : self::MOODS[$bytes[3] % count(self::MOODS)];

        return "Abstract {$mood} artwork made of {$style}, {$palette} color palette, {$composition} composition, square. ".self::RULES;
    }

}
