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
     * AI Artwork providers: key => class, panel label, default model
     */
    public const IMAGE = [
        'openai' => ['class' => Image\OpenAI::class, 'name' => 'OpenAI GPT Image or compatible (LocalAI)', 'model' => 'gpt-image-2.6-flare'],
        'gemini' => ['class' => Image\Gemini::class, 'name' => 'Google Gemini', 'model' => 'gemini-3.1-flash-lite-image'],
        'fal'    => ['class' => Image\Fal::class, 'name' => 'fal.ai FLUX', 'model' => 'fal-ai/flux/schnell'],
    ];

    /**
     * Default seconds per text request, for the panel tools and work that runs after the response: a local model
     * (Ollama on a small CPU) can take most of a minute. Callers inside a listener's request pass a shorter one.
     */
    public const TEXT_TIMEOUT = 60;

    /**
     * Seconds per image request: GPT Image can take up to two minutes, generation runs after the response anyway
     */
    private const IMAGE_TIMEOUT = 150;


    /**
     * The configured AI Assistant model, or null while AI Assistant is disabled
     *
     * @param  PawTunes  $pawtunes
     * @param  int  $timeout  Seconds per request
     *
     * @return TextModel|null
     * @throws PawException When enabled but misconfigured (missing key, unknown provider)
     */
    public static function textModel(PawTunes $pawtunes, int $timeout = self::TEXT_TIMEOUT): ?TextModel
    {
        $config = $pawtunes->config('ai')['text'] ?? [];

        return empty($config['enabled']) ? null : self::buildText($pawtunes, $config, $timeout);
    }


    /**
     * Build a text model from a config block, enabled or not (the panel tests settings before enabling them)
     *
     * @param  PawTunes  $pawtunes
     * @param  array  $config
     * @param  int  $timeout  Seconds per request
     *
     * @return TextModel
     * @throws PawException
     */
    public static function buildText(PawTunes $pawtunes, array $config, int $timeout = self::TEXT_TIMEOUT): TextModel
    {
        return self::build(self::TEXT, $pawtunes, $config, $timeout);
    }


    /**
     * The configured AI Artwork model, or null while AI Artwork is disabled
     *
     * @param  PawTunes  $pawtunes
     *
     * @return ImageModel|null
     * @throws PawException When enabled but misconfigured (missing key, unknown provider)
     */
    public static function imageModel(PawTunes $pawtunes): ?ImageModel
    {
        $config = $pawtunes->config('ai')['images'] ?? [];

        return empty($config['enabled']) ? null : self::buildImage($pawtunes, $config);
    }


    /**
     * Build an image model from a config block, enabled or not
     *
     * @param  PawTunes  $pawtunes
     * @param  array  $config
     *
     * @return ImageModel
     * @throws PawException
     */
    public static function buildImage(PawTunes $pawtunes, array $config): ImageModel
    {
        return self::build(self::IMAGE, $pawtunes, $config, self::IMAGE_TIMEOUT);
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
