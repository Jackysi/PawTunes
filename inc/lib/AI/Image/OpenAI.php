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

namespace lib\AI\Image;

use lib\AI\ImageModel;
use lib\AI\JsonClient;
use lib\PawException;

/**
 * OpenAI GPT Image through the Images API. The same endpoint is spoken by self-hosted servers such as LocalAI,
 * reachable with a custom base URL.
 */
final class OpenAI implements ImageModel
{

    public const DEFAULT_URL = 'https://api.openai.com/v1';

    private JsonClient $http;

    private string $apiKey;

    private string $model;

    private string $baseURL;

    private int $timeout;


    /**
     * @param  JsonClient  $http
     * @param  array  $config  api_key, model, base_url, timeout
     *
     * @throws PawException
     */
    public function __construct(JsonClient $http, array $config)
    {
        $this->baseURL = rtrim($config['base_url'] ?: self::DEFAULT_URL, '/');

        if (empty($config['api_key']) && $this->baseURL === self::DEFAULT_URL) {
            throw new PawException('Missing API key for OpenAI!');
        }

        $this->http    = $http;
        $this->apiKey  = (string) $config['api_key'];
        $this->model   = $config['model'];
        $this->timeout = $config['timeout'];
    }


    /**
     * @inheritDoc
     */
    public function generate(string $prompt): string
    {
        $body = ['model' => $this->model, 'prompt' => $prompt, 'n' => 1];

        if ($this->baseURL === self::DEFAULT_URL) {
            // Smallest square and cheapest quality: the player shows artwork at a few hundred pixels
            $body += ['size' => '1024x1024', 'quality' => 'low', 'output_format' => 'jpeg', 'output_compression' => 85];
        } else {
            // Compatible servers answer with a URL unless asked for base64, OpenAI rejects this field
            $body += ['size' => '512x512', 'response_format' => 'b64_json'];
        }

        $answer = $this->http->post(
            $this->baseURL.'/images/generations',
            $body,
            ($this->apiKey !== '') ? ['Authorization: Bearer '.$this->apiKey] : [],
            $this->timeout
        );

        $image = $answer['data'][0] ?? [];

        if ( ! empty($image['b64_json']) && ($bytes = base64_decode($image['b64_json'], true)) !== false) {
            return $bytes;
        }

        if ( ! empty($image['url'])) {
            // LocalAI may answer with a path on its own host
            $url = (strpos($image['url'], '/') === 0)
                ? parse_url($this->baseURL, PHP_URL_SCHEME).'://'.parse_url($this->baseURL, PHP_URL_HOST).
                  (parse_url($this->baseURL, PHP_URL_PORT) ? ':'.parse_url($this->baseURL, PHP_URL_PORT) : '').$image['url']
                : $image['url'];

            return $this->http->download($url, 60);
        }

        throw new PawException('The image API answered without an image');
    }

}
