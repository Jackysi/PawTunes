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
 * A language model provider for AI Assistant (see Providers::TEXT)
 */
interface TextModel
{

    /**
     * One structured answer: a JSON object matching $schema. Schemas list every property as required and
     * set additionalProperties to false, the strict form every supported provider accepts.
     *
     * The output can only take the shape of the schema, so text inside the input or image (prompt injection)
     * can at most change the values, never make PawTunes do anything else.
     *
     * @param  string  $instructions  Trusted instructions (system prompt)
     * @param  string  $input  Untrusted content to work on
     * @param  array  $schema  JSON Schema of the answer
     * @param  array|null  $image  ['mime' => 'image/jpeg', 'data' => base64] for image checks
     *
     * @return array
     * @throws PawException
     */
    public function json(string $instructions, string $input, array $schema, ?array $image = null): array;

}
