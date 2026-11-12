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

/**
 * Merge one feature's posted fields into its stored block. A blank API key keeps the saved one, so keys never
 * have to be sent back to the browser.
 *
 * @param  array  $stored
 * @param  array  $posted
 * @param  array  $registry  Providers::TEXT or Providers::IMAGE
 *
 * @return array
 */
$mergePosted = static function (array $stored, array $posted, array $registry): array {

    $stored['enabled']  = ($posted['enabled'] ?? '') === 'true';
    $stored['provider'] = isset($registry[$posted['provider'] ?? '']) ? $posted['provider'] : $stored['provider'];

    // Model ids look like "claude-haiku-5-5", "llama3.2:3b" or "fal-ai/flux/schnell"
    $model           = trim((string) ($posted['model'] ?? ''));
    $stored['model'] = preg_match('~^[\w.:/@-]{0,100}$~', $model) ? $model : $stored['model'];

    $baseURL            = rtrim(trim((string) ($posted['base_url'] ?? '')), '/');
    $validURL           = filter_var($baseURL, FILTER_VALIDATE_URL) && in_array(parse_url($baseURL, PHP_URL_SCHEME), ['http', 'https'], true);
    $stored['base_url'] = ($baseURL === '' || $validURL) ? $baseURL : $stored['base_url'];

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

$action  = $_POST['action'] ?? '';
$message = null;
$test    = null;
$regex   = null;

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
                $sample = 'DAFT PUNK - ONE MORE TIME (RADIO EDIT) | www.my-station.fm';
                $track  = (new Assistant(Providers::buildText($pawtunes, $ai['text'])))->splitTrack($sample);
                $test   = ['input' => $sample, 'track' => $track];
            }
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

$panel->view(
    'ai',
    [
        'ai'               => $ai,
        'textProviders'    => Providers::TEXT,
        'message'          => $message,
        'test'             => $test,
        'regex'            => $regex,
        'missingLanguages' => $missingLanguages,
    ]
);
