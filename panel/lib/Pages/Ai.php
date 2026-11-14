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

namespace Pages;

use lib\AI\ArtworkGenerator;
use lib\AI\Assistant;
use lib\AI\ImageCheck;
use lib\AI\Log;
use lib\AI\Providers;
use lib\PawTunes;
use Panel;
use Services\ConfigStore;
use Services\Request;
use Throwable;

/**
 * AI page: AI Assistant and AI Artwork settings, and the tools built on them. Everything here is off
 * until enabled. An action leaves its outcome in the properties below and index() shows them.
 */
final class Ai extends Page
{

    private const SAVE_FAILED = 'Unable to save configuration changes, you may not have sufficient permissions!';

    private PawTunes $pawtunes;

    private Panel $panel;

    private ConfigStore $config;

    private Request $request;

    /**
     * The stored "ai" configuration: ['text' => [...], 'images' => [...]]
     */
    private array $settings;

    /**
     * Every language PawTunes knows, keyed by ISO code (panel/inc/language-list.php)
     */
    private array $languages;

    /**
     * Alert HTML shown at the top of the page
     */
    private ?string $message = null;

    /**
     * Result of "Save & Test": ['input' => string, 'track' => array|null]
     */
    private ?array $textTest = null;

    /**
     * Result of "Save & Generate Test Image": ['image' => data URL, 'prompt' => string, 'reject' => string|null]
     */
    private ?array $imageTest = null;

    /**
     * Suggested Track RegEx with its preview: ['pattern', 'explanation', 'samples', 'problem', 'preview']
     */
    private ?array $regex = null;


    public function __construct(PawTunes $pawtunes, Panel $panel, ConfigStore $config, Request $request)
    {
        $this->pawtunes  = $pawtunes;
        $this->panel     = $panel;
        $this->config    = $config;
        $this->request   = $request;
        $this->languages = include 'panel/inc/language-list.php';

        // Stored settings with defaults for installs that never saved this page
        $settings       = $pawtunes->config('ai') ?: [];
        $this->settings = [
            'text'   => ($settings['text'] ?? []) + [
                    'enabled'        => false,
                    'provider'       => 'anthropic',
                    'api_key'        => '',
                    'model'          => '',
                    'base_url'       => '',
                    'clean_metadata' => true,
                ],
            'images' => ($settings['images'] ?? []) + [
                    'enabled'     => false,
                    'provider'    => 'openai',
                    'api_key'     => '',
                    'model'       => '',
                    'base_url'    => '',
                    'daily_limit' => ArtworkGenerator::DEFAULT_DAILY_LIMIT,
                    'search'      => true,
                    'verify'      => false,
                ],
        ];

    }


    protected function index(): void
    {
        // Languages that can still be added (no locale file yet)
        $missingLanguages = [];
        foreach ($this->languages as $iso => $language) {
            if ( ! is_file("inc/locale/{$iso}.php")) {
                $missingLanguages[$iso] = $language;
            }
        }

        // Stored AI artwork, newest first
        $aiImages = glob(ArtworkGenerator::DIRECTORY.'/*.jpg') ?: [];
        usort($aiImages, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $this->panel->view(
            'ai',
            [
                'ai'               => $this->settings,
                'textProviders'    => Providers::TEXT,
                'imageProviders'   => Providers::IMAGE,
                'usage'            => ArtworkGenerator::usage(),
                'aiImages'         => $aiImages,
                'message'          => $this->message,
                'test'             => $this->textTest,
                'imageTest'        => $this->imageTest,
                'regex'            => $this->regex,
                'missingLanguages' => $missingLanguages,
            ]
        );

    }


    /**
     * Save the AI Assistant settings
     */
    protected function postSaveText(): void
    {
        $this->saveText();

    }


    /**
     * Save the AI Assistant settings, then split a sample title with them
     */
    protected function postTestText(): void
    {
        if ( ! $this->saveText()) {
            return;
        }

        set_time_limit(Providers::TEXT_TIMEOUT + 30); // a local model may use the whole request timeout

        $sample         = 'DAFT PUNK - ONE MORE TIME (RADIO EDIT) | www.my-station.fm';
        $assistant      = new Assistant(Providers::buildText($this->pawtunes, $this->settings['text']));
        $this->textTest = ['input' => $sample, 'track' => $assistant->splitTrack($sample)];

    }


    /**
     * Save the AI Artwork settings
     */
    protected function postSaveImages(): void
    {
        $this->saveImages();

    }


    /**
     * Save the AI Artwork settings, then generate one test image with them
     */
    protected function postTestImages(): void
    {
        if ( ! $this->saveImages()) {
            return;
        }

        // The test is a real, paid generation: it goes through the same daily budget and check. Image request,
        // download, mood lookup and vision check can each take up to their timeout.
        set_time_limit(360);
        $generator = new ArtworkGenerator($this->pawtunes);
        $model     = $generator->model(); // a misconfiguration fails here, before budget is spent

        if ( ! $generator->reserveBudget()) {
            $this->message = $this->panel->alert('Today\'s AI Artwork limit is used up (or <b>data/ai-artwork</b> is not writable), no test image was generated.', 'warning');

            return;
        }

        [$bytes, $prompt] = $generator->draw($model, 'Daft Punk', 'test.preview');
        $this->imageTest = [
            'image'  => 'data:image/jpeg;base64,'.base64_encode(ImageCheck::sideBySide($bytes)),
            'prompt' => $prompt,
            'reject' => $generator->rejectReason($bytes),
        ];

    }


    /**
     * Remove one stored AI image (the artist can get a new one a day after its last attempt)
     */
    protected function postDeleteAiImage(): void
    {
        $file = ArtworkGenerator::existing(basename($this->request->post('name')));
        if ($file !== null && unlink($file)) {
            $this->panel->flash($this->panel->alert('AI artwork deleted. The artist gets a new one at most once a day, the next time it plays without other artwork.', 'success'), 'index.php?page=ai');
        }

        $this->message = $this->panel->alert('Unable to delete that image.', 'error');

    }


    /**
     * Remove every stored AI image
     */
    protected function postDeleteAiArtwork(): void
    {
        foreach (glob(ArtworkGenerator::DIRECTORY.'/*.jpg') ?: [] as $file) {
            @unlink($file);
        }

        $this->panel->flash($this->panel->alert('All AI artwork deleted.', 'success'), 'index.php?page=ai');

    }


    /**
     * Suggest a Track RegEx from sample titles and preview it on them
     */
    protected function postSuggestRegex(): void
    {
        $samples = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->request->post('samples')))));
        if (count($samples) < 1) {
            $this->message = $this->panel->alert('Paste a few stream titles first, one per line.', 'warning');

            return;
        }

        $model = Providers::textModel($this->pawtunes);
        if ($model === null) {
            $this->message = $this->panel->alert('Enable and save AI Assistant first.', 'warning');

            return;
        }

        set_time_limit(Providers::TEXT_TIMEOUT + 30); // a local model may use the whole request timeout

        $regex            = (new Assistant($model))->suggestRegex($samples);
        $regex['samples'] = $samples;
        $regex['problem'] = $this->patternProblem($regex['pattern']);
        $regex['preview'] = [];

        if ($regex['problem'] === null) {
            foreach (array_slice($samples, 0, 20) as $sample) {
                preg_match("/{$regex['pattern']}/", $sample, $match);
                $regex['preview'][] = ['sample' => $sample, 'artist' => $match['artist'] ?? null, 'title' => $match['title'] ?? null];
            }
        }

        $this->regex = $regex;

    }


    /**
     * Save a suggested pattern as the Track RegEx (validated again, the form could have been edited)
     */
    protected function postUseRegex(): void
    {
        $pattern = $this->request->post('pattern');
        $problem = $this->patternProblem($pattern);
        if ($problem !== null) {
            $this->message = $this->panel->alert(htmlspecialchars($problem), 'error');

            return;
        }

        $this->pawtunes->setConfig('track_regex', $pattern);
        if ($this->config->save('config/general', $this->pawtunes->getConfigAll())) {
            $this->panel->flash($this->panel->alert('Track RegEx updated. New stream titles are split with it from now on.', 'success'), 'index.php?page=ai');
        }

        $this->message = $this->panel->alert(self::SAVE_FAILED, 'error', true);

    }


    /**
     * Translate the English locale into a new language, then open it in the language editor for review
     */
    protected function postTranslate(): void
    {
        $iso = preg_replace('/[^a-z]/', '', $this->request->post('language'));
        if ( ! isset($this->languages[$iso]) || is_file("inc/locale/{$iso}.php")) {
            $this->message = $this->panel->alert('Pick a language that does not have a translation yet.', 'warning');

            return;
        }

        $model = Providers::textModel($this->pawtunes);
        if ($model === null) {
            $this->message = $this->panel->alert('Enable and save AI Assistant first.', 'warning');

            return;
        }

        // About 30 strings in one answer, allow more than the usual web request time
        set_time_limit(120);

        $name       = $this->languages[$iso]['name'];
        $english    = include 'inc/locale/en.php';
        $translated = (new Assistant($model))->translate($english, $name);

        if ($this->config->save("locale/{$iso}", $translated)) {
            $this->panel->flash(
                $this->panel->alert("<b>{$name}</b> translation created. Please review it below, machine translations can miss the tone.", 'success'),
                "index.php?page=language&edit={$iso}"
            );
        }

        $this->message = $this->panel->alert('Unable to save the translation, you may not have sufficient permissions!', 'error', true);

    }


    /**
     * A failed AI request (provider down, bad key, ...) is shown on the page instead of ending the request
     */
    protected function actionFailed(string $action, Throwable $e): void
    {
        // Also into the log: the alert is gone with the next page load
        $feature = in_array($action, ['save-images', 'test-images', 'delete-ai-image', 'delete-ai-artwork'], true) ? 'AI Artwork' : 'AI Assistant';
        Log::error($this->pawtunes, $feature, "panel action \"{$action}\" failed: {$e->getMessage()}", $e);

        // Provider error texts are foreign content, escape them before they go into the alert HTML
        $this->message = $this->panel->alert('<b>AI request failed:</b> '.htmlspecialchars($e->getMessage()), 'error');

    }


    /**
     * @return bool false when the configuration file could not be written
     */
    private function saveText(): bool
    {
        $posted = $this->request->postArray('text');

        $this->settings['text']                   = $this->mergePosted($this->settings['text'], $posted, Providers::TEXT);
        $this->settings['text']['clean_metadata'] = ($posted['clean_metadata'] ?? '') === 'true';

        return $this->saveSettings('AI Assistant settings saved.');

    }


    /**
     * @return bool false when the configuration file could not be written
     */
    private function saveImages(): bool
    {
        $posted = $this->request->postArray('images');

        $this->settings['images']                = $this->mergePosted($this->settings['images'], $posted, Providers::IMAGE);
        $this->settings['images']['search']      = ($posted['search'] ?? '') === 'true';
        $this->settings['images']['verify']      = ($posted['verify'] ?? '') === 'true';
        $this->settings['images']['daily_limit'] = max(0, min(500, (int) ($posted['daily_limit'] ?? ArtworkGenerator::DEFAULT_DAILY_LIMIT)));

        return $this->saveSettings('AI Artwork settings saved.');

    }


    /**
     * Write $this->settings into general.php and leave the outcome in $this->message
     *
     * @param  string  $success  Text of the success alert
     *
     * @return bool
     */
    private function saveSettings(string $success): bool
    {
        $this->pawtunes->setConfig('ai', $this->settings);

        if ( ! $this->config->save('config/general', $this->pawtunes->getConfigAll())) {
            $this->message = $this->panel->alert(self::SAVE_FAILED, 'error', true);

            return false;
        }

        $this->message = $this->panel->alert($success, 'success');

        return true;

    }


    /**
     * Merge one feature's posted fields into its stored block. A blank API key keeps the saved one (as long as the
     * provider and base URL stay the same), so keys never have to be sent back to the browser.
     *
     * @param  array  $stored
     * @param  array  $posted
     * @param  array  $registry  Providers::TEXT or Providers::IMAGE
     *
     * @return array
     */
    private function mergePosted(array $stored, array $posted, array $registry): array
    {
        $previous           = $stored;
        $stored['enabled']  = ($posted['enabled'] ?? '') === 'true';
        $stored['provider'] = isset($registry[$posted['provider'] ?? '']) ? $posted['provider'] : $stored['provider'];

        // Model ids look like "claude-haiku-5-5", "llama3.2:3b" or "fal-ai/flux/schnell"
        $model           = trim((string) ($posted['model'] ?? ''));
        $stored['model'] = preg_match('~^[\w.:/@-]{0,100}$~', $model) ? $model : $stored['model'];

        $baseURL            = rtrim(trim((string) ($posted['base_url'] ?? '')), '/');
        $validURL           = filter_var($baseURL, FILTER_VALIDATE_URL) && in_array(parse_url($baseURL, PHP_URL_SCHEME), ['http', 'https'], true);
        $stored['base_url'] = ($baseURL === '' || $validURL) ? $baseURL : $stored['base_url'];

        // A saved key belongs to the provider and server it was entered for: never send it to another one. Switching
        // the provider or the base URL without entering a new key forgets the old key.
        if ($stored['provider'] !== $previous['provider'] || $stored['base_url'] !== $previous['base_url']) {
            $stored['api_key'] = '';
        }

        if (trim((string) ($posted['api_key'] ?? '')) !== '') {
            $stored['api_key'] = trim($posted['api_key']);
        }

        if ( ! empty($posted['forget_key'])) {
            $stored['api_key'] = '';
        }

        return $stored;

    }


    /**
     * A Track RegEx must work exactly the way handleTrack() runs it: preg_match('/' . pattern . '/', text)
     *
     * @param  string  $pattern
     *
     * @return string|null Problem description, null when the pattern is usable
     */
    private function patternProblem(string $pattern): ?string
    {
        if ($pattern === '' || strlen($pattern) > 500) {
            return 'The pattern is empty or too long.';
        }

        if (preg_match('~(?<!\\\\)/~', $pattern)) {
            return 'The pattern contains an unescaped "/", which would end the pattern early.';
        }

        if (@preg_match("/{$pattern}/", '') === false) {
            return 'The pattern does not compile.';
        }

        if ( ! preg_match('/\(\?P?<artist>/', $pattern) || ! preg_match('/\(\?P?<title>/', $pattern)) {
            return 'The pattern needs the named groups (?P<artist>...) and (?P<title>...).';
        }

        return null;

    }

}
