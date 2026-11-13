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

/**
 * AI page: AI Assistant settings and tools. Everything here is off until enabled, and every action is a
 * CSRF-checked POST (the panel's index.php verifies the token for all authenticated POSTs).
 *
 * @var lib\PawTunes $pawtunes
 * @var Panel $panel
 */

use lib\AI\ArtworkGenerator;
use lib\AI\Assistant;
use lib\AI\Providers;

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

$languages = include 'panel/inc/language-list.php';

// Stored settings with defaults for installs that never saved this page
$ai         = $pawtunes->config('ai') ?: [];
$ai['text'] = ($ai['text'] ?? []) + [
        'enabled'        => false,
        'provider'       => 'anthropic',
        'api_key'        => '',
        'model'          => '',
        'base_url'       => '',
        'clean_metadata' => true,
    ];

$ai['images'] = ($ai['images'] ?? []) + [
        'enabled'     => false,
        'provider'    => 'openai',
        'api_key'     => '',
        'model'       => '',
        'base_url'    => '',
        'daily_limit' => ArtworkGenerator::DEFAULT_DAILY_LIMIT,
        'verify'      => true,
    ];

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
$mergePosted = static function (array $stored, array $posted, array $registry): array {

    $previous = $stored;
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
};

/**
 * A Track RegEx must work exactly the way handleTrack() runs it: preg_match('/' . pattern . '/', text)
 *
 * @param  string  $pattern
 *
 * @return string|null Problem description, null when the pattern is usable
 */
$patternProblem = static function (string $pattern): ?string {

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
};

$saveSettings = static function () use ($pawtunes, $panel, &$ai): bool {
    $pawtunes->setConfig('ai', $ai);

    return $panel->storeConfig('config/general', $pawtunes->getConfigAll());
};

$action    = $_POST['action'] ?? '';
$message   = null;
$test      = null;
$imageTest = null;
$regex     = null;

try {

    switch ($action) {

        // Save (and optionally test) AI Assistant settings
        case 'save-text':
        case 'test-text':

            $ai['text']                   = $mergePosted($ai['text'], $_POST['text'] ?? [], Providers::TEXT);
            $ai['text']['clean_metadata'] = ($_POST['text']['clean_metadata'] ?? '') === 'true';

            if ( ! $saveSettings()) {
                $message = $panel->alert('Unable to save configuration changes, you may not have sufficient permissions!', 'error', true);
                break;
            }

            $message = $panel->alert('AI Assistant settings saved.', 'success');

            if ($action === 'test-text') {
                set_time_limit(Providers::TEXT_TIMEOUT + 30); // a local model may use the whole request timeout
                $sample = 'DAFT PUNK - ONE MORE TIME (RADIO EDIT) | www.my-station.fm';
                $track  = (new Assistant(Providers::buildText($pawtunes, $ai['text'])))->splitTrack($sample);
                $test   = ['input' => $sample, 'track' => $track];
            }
            break;

        // Save (and optionally test) AI Artwork settings
        case 'save-images':
        case 'test-images':

            $ai['images']                = $mergePosted($ai['images'], $_POST['images'] ?? [], Providers::IMAGE);
            $ai['images']['verify']      = ($_POST['images']['verify'] ?? '') === 'true';
            $ai['images']['daily_limit'] = max(0, min(500, (int) ($_POST['images']['daily_limit'] ?? ArtworkGenerator::DEFAULT_DAILY_LIMIT)));

            if ( ! $saveSettings()) {
                $message = $panel->alert('Unable to save configuration changes, you may not have sufficient permissions!', 'error', true);
                break;
            }

            $message = $panel->alert('AI Artwork settings saved.', 'success');

            if ($action === 'test-images') {

                // The test is a real, paid generation: it goes through the same daily budget and check. Image request,
                // download, mood lookup and vision check can each take up to their timeout.
                set_time_limit(360);
                $generator = new ArtworkGenerator($pawtunes);
                $model     = $generator->model(); // a misconfiguration fails here, before budget is spent

                if ( ! $generator->reserveBudget()) {
                    $message = $panel->alert('Today\'s AI Artwork limit is used up (or <b>data/ai-artwork</b> is not writable), no test image was generated.', 'warning');
                    break;
                }

                [$bytes, $prompt] = $generator->draw($model, 'Daft Punk', 'test.preview');
                $imageTest = [
                    'image'  => 'data:image/jpeg;base64,'.base64_encode(lib\AI\ImageCheck::sideBySide($bytes)),
                    'prompt' => $prompt,
                    'reject' => $generator->rejectReason($bytes),
                ];

            }
            break;

        // Remove one stored AI image (the artist can get a new one a day after its last attempt)
        case 'delete-ai-image':

            $file = ArtworkGenerator::existing(basename((string) ($_POST['name'] ?? '')));
            if ($file !== null && unlink($file)) {
                $panel->flash($panel->alert('AI artwork deleted. The artist gets a new one at most once a day, the next time it plays without other artwork.', 'success'), 'index.php?page=ai');
            }

            $message = $panel->alert('Unable to delete that image.', 'error');
            break;

        // Remove every stored AI image
        case 'delete-ai-artwork':

            foreach (glob(ArtworkGenerator::DIRECTORY.'/*.jpg') ?: [] as $file) {
                @unlink($file);
            }

            $panel->flash($panel->alert('All AI artwork deleted.', 'success'), 'index.php?page=ai');
            break;

        // Suggest a Track RegEx from sample titles
        case 'suggest-regex':

            $samples = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['samples'] ?? '')))));
            if (count($samples) < 1) {
                $message = $panel->alert('Paste a few stream titles first, one per line.', 'warning');
                break;
            }

            $model = Providers::textModel($pawtunes);
            if ($model === null) {
                $message = $panel->alert('Enable and save AI Assistant first.', 'warning');
                break;
            }

            set_time_limit(Providers::TEXT_TIMEOUT + 30); // a local model may use the whole request timeout

            $regex            = (new Assistant($model))->suggestRegex($samples);
            $regex['samples'] = $samples;
            $regex['problem'] = $patternProblem($regex['pattern']);
            $regex['preview'] = [];

            if ($regex['problem'] === null) {
                foreach (array_slice($samples, 0, 20) as $sample) {
                    preg_match("/{$regex['pattern']}/", $sample, $match);
                    $regex['preview'][] = ['sample' => $sample, 'artist' => $match['artist'] ?? null, 'title' => $match['title'] ?? null];
                }
            }
            break;

        // Save a suggested pattern as the Track RegEx (validated again, the form could have been edited)
        case 'use-regex':

            $pattern = (string) ($_POST['pattern'] ?? '');
            if (($problem = $patternProblem($pattern)) !== null) {
                $message = $panel->alert(htmlspecialchars($problem), 'error');
                break;
            }

            $pawtunes->setConfig('track_regex', $pattern);
            if ($panel->storeConfig('config/general', $pawtunes->getConfigAll())) {
                $panel->flash($panel->alert('Track RegEx updated. New stream titles are split with it from now on.', 'success'), 'index.php?page=ai');
            }

            $message = $panel->alert('Unable to save configuration changes, you may not have sufficient permissions!', 'error', true);
            break;

        // Translate the English locale into a new language, then open it in the language editor for review
        case 'translate':

            $iso = preg_replace('/[^a-z]/', '', (string) ($_POST['language'] ?? ''));
            if ( ! isset($languages[$iso]) || is_file("inc/locale/{$iso}.php")) {
                $message = $panel->alert('Pick a language that does not have a translation yet.', 'warning');
                break;
            }

            $model = Providers::textModel($pawtunes);
            if ($model === null) {
                $message = $panel->alert('Enable and save AI Assistant first.', 'warning');
                break;
            }

            // About 30 strings in one answer, allow more than the usual web request time
            set_time_limit(120);

            $english    = include 'inc/locale/en.php';
            $translated = (new Assistant($model))->translate($english, $languages[$iso]['name']);

            if ($panel->storeConfig("locale/{$iso}", $translated)) {
                $panel->flash(
                    $panel->alert("<b>{$languages[$iso]['name']}</b> translation created. Please review it below, machine translations can miss the tone.", 'success'),
                    "index.php?page=language&edit={$iso}"
                );
            }

            $message = $panel->alert('Unable to save the translation, you may not have sufficient permissions!', 'error', true);
            break;

    }

} catch (Throwable $e) {

    // Provider error texts are foreign content, escape them before they go into the alert HTML
    $message = $panel->alert('<b>AI request failed:</b> '.htmlspecialchars($e->getMessage()), 'error');

}

// Languages that can still be added (no locale file yet)
$missingLanguages = [];
foreach ($languages as $iso => $language) {
    if ( ! is_file("inc/locale/{$iso}.php")) {
        $missingLanguages[$iso] = $language;
    }
}

// Stored AI artwork, newest first
$aiImages = glob(ArtworkGenerator::DIRECTORY.'/*.jpg') ?: [];
usort($aiImages, static function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

$panel->view(
    'ai',
    [
        'ai'               => $ai,
        'textProviders'    => Providers::TEXT,
        'imageProviders'   => Providers::IMAGE,
        'usage'            => ArtworkGenerator::usage(),
        'aiImages'         => $aiImages,
        'message'          => $message,
        'test'             => $test,
        'imageTest'        => $imageTest,
        'regex'            => $regex,
        'missingLanguages' => $missingLanguages,
    ]
);
