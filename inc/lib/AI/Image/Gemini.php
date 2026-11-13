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
 * Google Gemini image models through generateContent (the key goes in a header, never in the URL)
 */
final class Gemini implements ImageModel
{

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private JsonClient $http;

    private string $apiKey;

    private string $model;

    private int $timeout;


    /**
     * @param  JsonClient  $http
     * @param  array  $config  api_key, model, timeout
     *
     * @throws PawException
     */
    public function __construct(JsonClient $http, array $config)
    {
        if (empty($config['api_key'])) {
            throw new PawException('Missing API key for Google Gemini!');
        }

        // The model id becomes part of the URL path
        if ( ! preg_match('/^[a-z0-9][a-z0-9.-]*$/i', $config['model'])) {
            throw new PawException('Invalid Gemini model id');
        }

        $this->http    = $http;
        $this->apiKey  = $config['api_key'];
        $this->model   = $config['model'];
        $this->timeout = $config['timeout'];
    }


    /**
     * @inheritDoc
     */
    public function generate(string $prompt): string
    {
        $answer = $this->http->post(
            sprintf(self::URL, $this->model),
            [
                'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig'        => ['aspectRatio' => '1:1', 'imageSize' => '1K'],
                ],
            ],
            ['x-goog-api-key: '.$this->apiKey],
            $this->timeout
        );

        foreach ($answer['candidates'][0]['content']['parts'] ?? [] as $part) {

            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if ($data !== null && ($bytes = base64_decode($data, true)) !== false) {
                return $bytes;
            }

        }

        // Safety blocks come back as a finish or block reason instead of an error status
        $reason = $answer['candidates'][0]['finishReason'] ?? $answer['promptFeedback']['blockReason'] ?? 'no image';

        throw new PawException("Gemini returned no image ({$reason})");
    }

}
