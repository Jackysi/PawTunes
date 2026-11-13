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

namespace lib\AI\Text;

use lib\AI\JsonClient;
use lib\AI\TextModel;
use lib\PawException;

/**
 * OpenAI Chat Completions with structured outputs. The same request shape is spoken by self-hosted servers
 * (Ollama, LM Studio, vLLM, LocalAI), so a custom base URL runs PawTunes on a local model with no cloud at all.
 */
final class OpenAI implements TextModel
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

        // Local servers usually run without a key, OpenAI itself always needs one
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
    public function json(string $instructions, string $input, array $schema, ?array $image = null): array
    {
        $user = [['type' => 'text', 'text' => $input]];
        if ($image !== null) {
            // Full detail: the image check must read small, faint text that "low" would downscale away
            $user[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$image['mime']};base64,{$image['data']}", 'detail' => 'high']];
        }

        $answer = $this->http->post(
            $this->baseURL.'/chat/completions',
            [
                'model'           => $this->model,
                'messages'        => [
                    ['role' => 'system', 'content' => $instructions],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => [
                    'type'        => 'json_schema',
                    'json_schema' => ['name' => 'pawtunes', 'strict' => true, 'schema' => $schema],
                ],
            ],
            ($this->apiKey !== '') ? ['Authorization: Bearer '.$this->apiKey] : [],
            $this->timeout
        );

        $choice = $answer['choices'][0] ?? [];

        // A refusal or a cut-off answer may not match the schema, those are never parsed
        if ( ! empty($choice['message']['refusal'])) {
            throw new PawException('The model refused: '.mb_substr((string) $choice['message']['refusal'], 0, 200));
        }

        $finish = $choice['finish_reason'] ?? 'unknown';
        if ($finish !== 'stop') {
            throw new PawException("The model did not finish the answer (finish reason: {$finish})");
        }

        return JsonClient::decodeObject((string) ($choice['message']['content'] ?? ''));
    }

}
