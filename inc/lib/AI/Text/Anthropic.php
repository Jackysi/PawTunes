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
 * Anthropic Claude through the Messages API with structured outputs.
 * Raw HTTP on purpose: the official PHP SDK requires PHP 8.1, PawTunes supports 7.4.
 */
final class Anthropic implements TextModel
{

    private const URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2026-10-21';

    /**
     * Answers are a few short fields, the headroom is for the model's thinking
     */
    private const MAX_TOKENS = 4096;

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
            throw new PawException('Missing API key for Anthropic Claude!');
        }

        $this->http    = $http;
        $this->apiKey  = $config['api_key'];
        $this->model   = $config['model'];
        $this->timeout = $config['timeout'];
    }


    /**
     * @inheritDoc
     */
    public function json(string $instructions, string $input, array $schema, ?array $image = null): array
    {
        $content = [];
        if ($image !== null) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']]];
        }

        $content[] = ['type' => 'text', 'text' => $input];

        $answer = $this->http->post(
            self::URL,
            [
                'model'         => $this->model,
                'max_tokens'    => self::MAX_TOKENS,
                'system'        => $instructions,
                'messages'      => [['role' => 'user', 'content' => $content]],
                'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
            ],
            [
                'x-api-key: '.$this->apiKey,
                'anthropic-version: '.self::API_VERSION,
            ],
            $this->timeout
        );

        // A refusal or a cut-off answer may not match the schema, those are never parsed
        $stop = $answer['stop_reason'] ?? 'unknown';
        if ($stop !== 'end_turn') {
            throw new PawException("Claude did not finish the answer (stop reason: {$stop})");
        }

        foreach ($answer['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return JsonClient::decodeObject($block['text']);
            }
        }

        throw new PawException('Claude answered without text');
    }

}
