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
 * JSON over HTTP for the AI providers, sent through PawTunes' HttpClient (CA bundle, error log).
 * API keys always travel in headers: HttpClient logs the full URL of failed requests.
 */
final class JsonClient
{

    private PawTunes $pawtunes;


    public function __construct(PawTunes $pawtunes)
    {
        $this->pawtunes = $pawtunes;
    }


    /**
     * POST a JSON body and return the decoded JSON answer
     *
     * @param  string  $url
     * @param  array  $body
     * @param  string[]  $headers  e.g. ['Authorization: Bearer ...']
     * @param  int  $timeout  Seconds
     *
     * @return array
     * @throws PawException Transport errors, non-2xx answers (with the provider's own message) and invalid JSON
     */
    public function post(string $url, array $body, array $headers, int $timeout): array
    {
        $http = $this->pawtunes->http;
        $http->resetLastStatus();

        $response = $this->pawtunes->get(
            $url,
            null,
            null,
            false,
            $timeout,
            $error,
            [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            ]
        );

        if ($response === false) {
            throw new PawException("AI request failed: {$error}");
        }

        $status = (int) $http->getLastStatus();
        $data   = json_decode($response, true);

        if ($status < 200 || $status >= 300) {
            throw new PawException("AI request failed (HTTP {$status}): ".self::errorMessage($data, $response));
        }

        if ( ! is_array($data)) {
            throw new PawException('AI answer was not valid JSON');
        }

        return $data;
    }


    /**
     * Download a generated image that a provider returned as a URL
     *
     * @param  string  $url
     * @param  int  $timeout  Seconds
     *
     * @return string Raw bytes
     * @throws PawException
     */
    public function download(string $url, int $timeout): string
    {
        if ( ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new PawException('The image API returned an invalid image URL');
        }

        $http = $this->pawtunes->http;
        $http->resetLastStatus();

        $bytes  = $this->pawtunes->get($url, null, null, false, $timeout, $error);
        $status = (int) $http->getLastStatus();

        if ($bytes === false || $status < 200 || $status >= 300) {
            throw new PawException("Downloading the generated image failed (HTTP {$status}) {$error}");
        }

        return $bytes;
    }


    /**
     * Decode the JSON text a model wrote for a structured output request
     *
     * @param  string  $text
     *
     * @return array
     * @throws PawException
     */
    public static function decodeObject(string $text): array
    {
        $data = json_decode($text, true);
        if ( ! is_array($data)) {
            throw new PawException('The model answered with invalid JSON');
        }

        return $data;
    }


    /**
     * The provider's own error text: OpenAI, Anthropic and Gemini use error.message, fal uses detail
     *
     * @param  mixed  $data  Decoded answer
     * @param  string  $raw
     *
     * @return string
     */
    private static function errorMessage($data, string $raw): string
    {
        $message = $data['error']['message']
                   ?? (is_string($data['error'] ?? null) ? $data['error'] : null)
                      ?? (is_string($data['detail'] ?? null) ? $data['detail'] : null)
                         ?? $data['detail'][0]['msg']
                            ?? strip_tags($raw);

        return mb_substr(trim((string) $message), 0, 300);
    }

}
