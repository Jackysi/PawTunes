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
 * Image prompts for AI Artwork.
 *
 * The artist and title never reach the image model. AI Assistant may read them and describe what the music
 * evokes (a "concept": "Fractured Truth" -> a motif of "shattered glass shards"), but only as fixed words from the
 * lists below plus a short motif that normalizeConcept() filters down to a few plain lowercase words. Without a
 * concept the choices are seeded by the artist name, so an artist keeps the same style either way.
 */
final class ArtworkPrompt
{

    public const MOODS = ['calm', 'dreamy', 'energetic', 'dark', 'warm', 'melancholic', 'euphoric', 'raw'];

    public const STYLES = [
        'flowing liquid gradients',
        'soft geometric shapes',
        'layered paper cut-outs',
        'watercolor washes',
        'blurred light bokeh',
        'sweeping ink brush strokes',
        'smooth abstract 3D forms',
        'grainy risograph texture',
        'shattered glass and refracted light',
        'macro photography of natural textures',
    ];

    public const PALETTES = [
        'deep blue and violet',
        'warm orange and magenta',
        'teal and emerald',
        'crimson and gold',
        'pastel pink and lavender',
        'midnight black and neon cyan',
        'sand and terracotta',
        'silver and ice blue',
    ];

    /**
     * Motif limits: enough for "shattered glass shards", too short to smuggle in instructions
     */
    public const MOTIF_MAX_WORDS = 4;

    public const MOTIF_MAX_LENGTH = 40;

    private const COMPOSITIONS = ['centered', 'diagonal', 'layered', 'radial'];

    /**
     * Everything that would make the image pass for a real cover, or show a real person, is excluded
     */
    private const RULES = 'No text, no letters, no numbers, no words, no logos, no symbols, no signatures, '.
                          'no people, no faces, no hands, no silhouettes, no animals, no instruments, '.
                          'no album cover layout, no frames, no borders, no watermark.';


    /**
     * @param  string  $seed  Normalized artist name (PawTunes::parseTrack)
     * @param  array  $concept  From normalizeConcept(), empty for a purely seeded prompt
     *
     * @return string
     */
    public static function build(string $seed, array $concept = []): string
    {
        $bytes = array_values(unpack('C*', md5($seed, true)));

        $style       = $concept['style'] ?? self::STYLES[$bytes[0] % count(self::STYLES)];
        $palette     = $concept['palette'] ?? self::PALETTES[$bytes[1] % count(self::PALETTES)];
        $composition = self::COMPOSITIONS[$bytes[2] % count(self::COMPOSITIONS)];
        $mood        = $concept['mood'] ?? self::MOODS[$bytes[3] % count(self::MOODS)];
        $motif       = isset($concept['motif']) ? " evoking {$concept['motif']}" : '';

        return "Abstract {$mood} artwork{$motif}, made of {$style}, {$palette} color palette, {$composition} composition, square. ".self::RULES;
    }


    /**
     * Keep only what a model answer may contribute: list words, and a motif of plain lowercase words that does not
     * contain the artist's name (so the image model is never asked to show it). Anything else is dropped and the
     * seeded choice is used for that part.
     *
     * @param  array  $answer  mood, style, palette, motif as the model wrote them
     * @param  string  $artist
     *
     * @return array Only the valid keys
     */
    public static function normalizeConcept(array $answer, string $artist): array
    {
        $concept = [];

        foreach (['mood' => self::MOODS, 'style' => self::STYLES, 'palette' => self::PALETTES] as $key => $allowed) {
            $value = strtolower(trim((string) ($answer[$key] ?? '')));
            if (in_array($value, $allowed, true)) {
                $concept[$key] = $value;
            }
        }

        // Letters and spaces only: no digits, punctuation or quotes, so no URLs, no prompt syntax, no "1999"
        $motif = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($answer['motif'] ?? ''))));
        $words = ($motif === '') ? [] : explode(' ', $motif);

        $name = strtolower(trim(preg_replace('/[^\p{L}]+/u', ' ', $artist)));

        if (
            $motif !== ''
            && strlen($motif) <= self::MOTIF_MAX_LENGTH
            && count($words) <= self::MOTIF_MAX_WORDS
            && preg_match('/^[a-z ]+$/', $motif)
            && ($name === '' || strpos(" {$motif} ", " {$name} ") === false)
        ) {
            $concept['motif'] = $motif;
        }

        return $concept;
    }

}
