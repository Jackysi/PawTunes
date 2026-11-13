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
 * A text model that can search the web on its own (provider-side search, nothing runs on this server).
 * Optional: AI Artwork searches for a real picture only when AI Assistant's model implements this.
 */
interface WebSearchModel
{

    /**
     * Search the web, then answer by filling in one form (a tool the model calls with the answer)
     *
     * @param  string  $instructions  System prompt
     * @param  string  $input  User message
     * @param  array  $form  ['name' => ..., 'description' => ..., 'input_schema' => JSON schema]
     * @param  string[]  $domains  Search results come only from these sites (subdomains included)
     * @param  int  $maxSearches  Hard cap on searches, each one is billed by the provider
     *
     * @return array ['answer' => the filled-in form, null when the model finished without it,
     *                'urls' => every result URL the searches returned (to check the answer against)]
     * @throws PawException
     */
    public function searchAndAnswer(string $instructions, string $input, array $form, array $domains, int $maxSearches): array;

}
