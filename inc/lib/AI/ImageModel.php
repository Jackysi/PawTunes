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

use lib\PawException;

/**
 * An image generation provider for AI Artwork (see Providers::IMAGE).
 *
 * Providers only make the HTTP call. Prompt, safeguards, checks and storage live in ArtworkGenerator,
 * so no provider can skip one of them.
 */
interface ImageModel
{

    /**
     * @param  string  $prompt
     *
     * @return string Raw image bytes (format is detected from the bytes, not trusted from the provider)
     * @throws PawException
     */
    public function generate(string $prompt): string;

}
