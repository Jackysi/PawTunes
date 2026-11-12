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
use lib\PawTunes;

/**
 * Registry of AI providers, also the source of the panel's provider lists.
 * Adding a provider = one class implementing TextModel or ImageModel + one line here.
 *
 * Config lives in general.php under "ai" => ["text" => [...], "images" => [...]], both disabled by default:
 * enabled, provider, api_key, model (blank = the default below), base_url (OpenAI-compatible servers only).
 */
final class Providers
{

    /**
     * AI Assistant providers: key => class, panel label, default model
     */
    public const TEXT = [
        'anthropic' => ['class' => Text\Anthropic::class, 'name' => 'Anthropic Claude', 'model' => 'claude-haiku-5-5'],
        'openai'    => ['class' => Text\OpenAI::class, 'name' => 'OpenAI or compatible (Ollama, LM Studio, vLLM)', 'model' => 'gpt-6-luna'],
    ];

    /**
     * Seconds per text request: answers are short, and the player waits for track info cleanup
     */
    private const TEXT_TIMEOUT = 20;


    /**
     * The configured AI Assistant model, or null while AI Assistant is disabled
     *
     * @param  PawTunes  $pawtunes
     *
     * @return TextModel|null
     * @throws PawException When enabled but misconfigured (missing key, unknown provider)
     */
    public static function textModel(PawTunes $pawtunes): ?TextModel
    {
        $config = $pawtunes->config('ai')['text'] ?? [];

        return empty($config['enabled']) ? null : self::buildText($pawtunes, $config);
    }


    /**
     * Build a text model from a config block, enabled or not (the panel tests settings before enabling them)
     *
     * @param  PawTunes  $pawtunes
     * @param  array  $config
     *
     * @return TextModel
     * @throws PawException
     */
    public static function buildText(PawTunes $pawtunes, array $config): TextModel
    {
        return self::build(self::TEXT, $pawtunes, $config, self::TEXT_TIMEOUT);
    }


    /**
     * @param  array  $registry
     * @param  PawTunes  $pawtunes
     * @param  array  $config
     * @param  int  $timeout
     *
     * @return TextModel|ImageModel
     * @throws PawException
     */
    private static function build(array $registry, PawTunes $pawtunes, array $config, int $timeout)
    {
        $entry = $registry[$config['provider'] ?? ''] ?? null;
        if ($entry === null) {
            throw new PawException('Unknown AI provider "'.($config['provider'] ?? '').'"');
        }

        return new $entry['class'](new JsonClient($pawtunes), [
            'api_key'  => trim((string) ($config['api_key'] ?? '')),
            'model'    => trim((string) ($config['model'] ?? '')) ?: $entry['model'],
            'base_url' => trim((string) ($config['base_url'] ?? '')),
            'timeout'  => $timeout,
        ]);
    }

}
