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
use lib\AI\WebSearchModel;
use lib\PawException;

/**
 * Anthropic Claude through the Messages API with structured outputs, and web search (server tool) for AI Artwork.
 * Raw HTTP on purpose: the official PHP SDK requires PHP 8.1, PawTunes supports 7.4.
 */
final class Anthropic implements TextModel, WebSearchModel
{

    private const URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2026-10-21';

    /**
     * Answers are a few short fields, the headroom is for the model's thinking
     */
    private const MAX_TOKENS = 4096;

    /**
     * Seconds for a request with web search: Anthropic runs the searches inside the request. Only used after the
     * response was sent (AI Artwork), never while a listener waits.
     */
    private const SEARCH_TIMEOUT = 120;

    /**
     * A long search can pause the turn ("pause_turn"), each continuation is one more request
     */
    private const MAX_CONTINUATIONS = 2;

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

    /**
     * @inheritDoc
     */
    public function searchAndAnswer(string $instructions, string $input, array $form, array $domains, int $maxSearches): array
    {
        $tools = [
            [
                'type'            => $this->searchToolType(),
                'name'            => 'web_search',
                'max_uses'        => $maxSearches,
                'allowed_domains' => $domains,
            ],
            $form + ['strict' => true],
        ];

        $messages = [['role' => 'user', 'content' => $input]];
        $urls     = [];

        for ($request = 0; $request <= self::MAX_CONTINUATIONS; $request++) {

            $answer = $this->http->post(
                self::URL,
                [
                    'model'       => $this->model,
                    'max_tokens'  => self::MAX_TOKENS,
                    'system'      => $instructions,
                    'messages'    => $messages,
                    'tools'       => $tools,
                    // "auto": recent models refuse forced tool use, the instructions ask for the form instead
                    'tool_choice' => ['type' => 'auto'],
                ],
                [
                    'x-api-key: '.$this->apiKey,
                    'anthropic-version: '.self::API_VERSION,
                ],
                self::SEARCH_TIMEOUT
            );

            $filled = null;
            foreach ($answer['content'] ?? [] as $block) {

                // A failed search has an error object as content instead of a list of results
                if (($block['type'] ?? '') === 'web_search_tool_result' && isset($block['content'][0])) {
                    foreach ($block['content'] as $result) {
                        $urls[] = (string) ($result['url'] ?? '');
                    }
                }

                if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $form['name'] && is_array($block['input'] ?? null)) {
                    $filled = $block['input'];
                }

            }

            if ($filled !== null) {
                return ['answer' => $filled, 'urls' => $urls];
            }

            $stop = $answer['stop_reason'] ?? 'unknown';
            if ($stop !== 'pause_turn') {

                if ($stop === 'refusal') {
                    throw new PawException('Claude declined the search');
                }

                return ['answer' => null, 'urls' => $urls];

            }

            // The server-side search loop paused: send its turn back unchanged and it resumes where it stopped
            $messages = [$messages[0], ['role' => 'assistant', 'content' => $answer['content'] ?? []]];

        }

        return ['answer' => null, 'urls' => $urls];
    }


    /**
     * Search tool version: the dynamic filtering one where the model supports it, the basic one for Haiku
     *
     * @return string
     */
    private function searchToolType(): string
    {
        return (strpos($this->model, 'haiku') !== false) ? 'web_search_20250305' : 'web_search_20260209';
    }

}
