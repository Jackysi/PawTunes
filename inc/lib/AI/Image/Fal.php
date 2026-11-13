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
 * fal.ai FLUX models through the synchronous endpoint, e.g. fal-ai/flux/schnell (cheapest) or
 * fal-ai/flux-2/klein/4b. Images come back as hosted URLs and are downloaded right away.
 */
final class Fal implements ImageModel
{

    private const URL = 'https://fal.run/';

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
            throw new PawException('Missing API key for fal.ai!');
        }

        // The model id becomes the URL path
        if ( ! preg_match('~^[a-z0-9][a-z0-9._-]*(/[a-z0-9._-]+)*$~i', $config['model'])) {
            throw new PawException('Invalid fal.ai model id');
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
            self::URL.$this->model,
            [
                'prompt'                => $prompt,
                'image_size'            => 'square', // 512x512, billed as one megapixel
                'num_images'            => 1,
                'num_inference_steps'   => 4,
                'enable_safety_checker' => true,
                'output_format'         => 'jpeg',
            ],
            ['Authorization: Key '.$this->apiKey],
            $this->timeout
        );

        // The safety checker answers with a black image instead of an error
        if ( ! empty($answer['has_nsfw_concepts'][0])) {
            throw new PawException('fal.ai flagged the generated image as unsafe');
        }

        // A hosted URL: inline data URIs only come back with sync_mode, which this request does not set
        $url = (string) ($answer['images'][0]['url'] ?? '');

        if ($url === '') {
            throw new PawException('fal.ai answered without an image');
        }

        return $this->http->download($url, 60);
    }

}
