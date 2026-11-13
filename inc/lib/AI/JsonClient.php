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

        $started  = microtime(true);
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

        $status = (int) $http->getLastStatus();

        if ($response === false) {
            $this->debugFailure($url, $body, $status, $started, (string) $error);
            throw new PawException("AI request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($status < 200 || $status >= 300) {
            $this->debugFailure($url, $body, $status, $started, $response);
            throw new PawException("AI request failed (HTTP {$status}): ".self::errorMessage($data, $response));
        }

        if ( ! is_array($data)) {
            $this->debugFailure($url, $body, $status, $started, $response);
            throw new PawException('AI answer was not valid JSON');
        }

        return $data;
    }


    /**
     * Debug log of a failed request: the exception only carries the provider's message, this adds where it went,
     * which model, how long it took (a timeout shows as the full timeout) and the start of the raw answer.
     * The URL is safe to log, API keys travel in headers.
     *
     * @param  string  $url
     * @param  array  $body
     * @param  int  $status  0 when no answer came back
     * @param  float  $started  microtime(true) before the request
     * @param  string  $answer  Raw answer, or the transport error
     *
     * @return void
     */
    private function debugFailure(string $url, array $body, int $status, float $started, string $answer): void
    {
        if ( ! Log::verbose($this->pawtunes)) {
            return;
        }

        // Gemini and fal carry the model in the URL, OpenAI and Anthropic in the body
        $model   = isset($body['model']) ? ", model {$body['model']}" : '';
        $seconds = number_format(microtime(true) - $started, 1);
        $answer  = mb_substr(trim(preg_replace('/\s+/', ' ', $answer) ?? ''), 0, 500);

        Log::debug($this->pawtunes, 'AI request', "POST {$url}{$model}, HTTP {$status} after {$seconds} s: {$answer}");
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
