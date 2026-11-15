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
 * Hint IDE already defined variables from parent (this file is included)
 * Returns default image if not found
 *
 * @var lib\PawTunes $pawtunes
 */

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

// Delete templates cache (manual re-scan of the templates folder, e.g. after adding a new template).
// Its own small POST form (CSRF-checked like every panel POST), it must run before the settings save below.
if ( ! empty($_POST['reset_templates'])) {

    $pawtunes->cache->delete('templates');
    $panel->flash($panel->alert('Templates cache flushed successfully!', 'success'), 'index.php?page=settings');

}

// Template settings (saved instantly via AJAX from the per-template options modal)
if (isset($_GET['advanced-options']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $template = $_POST['template'] ?? '';
    unset($_POST['template']);

    $tpl = $pawtunes->getAdvancedTemplateOptions($template);
    if (count($tpl) < 1) {
        http_response_code(404);
        die(json_encode(['error' => 'Unable to set template settings, Template not found!']));
    }

    // Make sure we fill only values in manifest, also don't store empty values
    foreach ($tpl as $option => $value) {

        if ( ! isset($_POST[$option])) {
            $tpl[$option] = false;
            continue;
        }

        if ($_POST[$option] === 'true') {
            $tpl[$option] = $_POST[$option] === 'true';
        }

        $tpl[$option] = $_POST[$option];

    }


    $pawtunes->setConfig(
        'tplOptions',
        array_merge(($pawtunes->config('tplOptions') ?: []), [$template => $tpl])
    );

    if ($panel->storeConfig('config/general', $pawtunes->getConfigAll())) {
        die(json_encode(['success' => true]));
    }

    http_response_code(500);
    die(json_encode(['error' => 'Unable to save settings!']));

}

// Share image removal, POSTed by the view's AJAX call. It exits here: the settings save below treats any POST
// as a full form submission and would wipe every field the AJAX call did not send.
if ( ! empty($_POST['delete_share_image'])) {

    // The field is free text (a URL or a path), so only the panel's own upload is ever deleted; anything else is
    // just cleared. Otherwise saving "inc/config/general.php" there and pressing remove would delete the config.
    $shareImage = (string) $pawtunes->config('share_image_override');
    if (preg_match('#^\./data/images/override-image\.[a-z]+$#', $shareImage)) {
        $pawtunes::deleteFile($shareImage);
    }

    $pawtunes->setConfig('share_image_override', '');
    $stored = $panel->storeConfig('config/general', $pawtunes->getConfigAll());

    header('Content-Type: application/json');
    die(json_encode(['success' => $stored]));

}

// App icon removal: its own small POST form (CSRF-checked like every panel POST), nothing else is saved
if ( ! empty($_POST['remove_app_icon'])) {

    $panel->removeAppIcon();
    $panel->flash($panel->alert('App icon removed, the bundled PawTunes icon is used again.', 'success'), 'index.php?page=settings');

}

// Cache modes lib\Cache supports, with whether this server's PHP can use them
$cacheModes = [
    'disk'      => ['label' => 'Disk (files)', 'available' => true],
    'apcu'      => ['label' => 'APCu', 'available' => function_exists('apcu_enabled') && apcu_enabled()],
    'redis'     => ['label' => 'Redis', 'available' => class_exists('Redis')],
    'memcached' => ['label' => 'Memcached', 'available' => class_exists('Memcached')],
    'memcache'  => ['label' => 'Memcache (legacy extension)', 'available' => class_exists('Memcache')],
];

// What identifies a cache configuration: when any of it changes on save, the new one is tested first
$cacheSignature = function (array $cache): array {
    return [
        lib\Cache::normalizeMode((string) ($cache['mode'] ?? '')),
        (string) ($cache['path'] ?? ''),
        (string) ($cache['host'] ?? ''),
        (string) ($cache['prefix'] ?? ''),
        $cache['extra'] ?? [],
    ];
};

// Checkbox fields of views/settings.blade.php, with the value the player assumes while the key is missing.
// Browsers do not post unchecked boxes, so each one is written explicitly: otherwise unchecking would drop the
// key from general.php, and an option that defaults to on (clean_artwork_search) could never be turned off.
$checkboxes = [
    'disable_index'        => false,
    'multi_lang'           => true,
    'autoplay'             => false,
    'cast'                 => true,
    'dynamic_title'        => false,
    'api'                  => false,
    'history'              => false,
    'cache_images'         => false,
    'cache_api_requests'   => false,
    'clean_artwork_search' => true,
    'serve_via_web'        => false,
    'artist_images_only'   => false,
    'artwork_lazy_loading' => false,
];

// Check if method is post
if ( ! empty($_POST)) {

    foreach ($checkboxes as $key => $default) {
        $_POST[$key] = ($_POST[$key] ?? '') === 'true';
    }

    // Cache section. Starts from the stored array, so keys the form does not show (ext, the Redis and
    // Memcached OPT_* options in extra) survive the save.
    $previousCache = $pawtunes->config('cache') ?: [];
    $cache         = array_merge($previousCache, [
        'mode'   => lib\Cache::normalizeMode((string) ($_POST['cache_mode'] ?? '')),
        'path'   => (trim($_POST['cache_path'] ?? '') !== '') ? trim($_POST['cache_path']) : './data/cache',
        'host'   => trim($_POST['cache_host'] ?? ''),
        'prefix' => trim($_POST['cache_prefix'] ?? ''),
    ]);

    // Redis password. An empty field removes it, except an ACL [user, password] pair, which only
    // general.php can set and the field cannot show. Memcached ignores the key, so it survives mode switches.
    $extra = is_array($cache['extra'] ?? null) ? $cache['extra'] : [];
    if (($_POST['cache_auth'] ?? '') !== '') {
        $extra['auth'] = $_POST['cache_auth'];
    } elseif ( ! is_array($extra['auth'] ?? null)) {
        unset($extra['auth']);
    }
    $cache['extra'] = $extra;

    $cacheChanged = $cacheSignature($cache) !== $cacheSignature($previousCache);

    // The form shows what was submitted again, saved or not; the cache_* fields never reach general.php
    $_POST['cache'] = $cache;
    unset($_POST['cache_mode'], $_POST['cache_path'], $_POST['cache_host'], $_POST['cache_auth'], $_POST['cache_prefix']);

    // A new cache folder is created like the panel's requirement check creates the default one
    if ($cacheChanged && ! is_dir($cache['path'])) {
        @mkdir($cache['path'], 0755, true);
    }

    // Map a few fields which we will store
    $store = [
        'cache'           => $cache,
        'tplOptions'      => ($pawtunes->config('tplOptions') ?: []),
        'ai'              => ($pawtunes->config('ai') ?: []), // edited on the AI page, not part of this form
        'artwork_sources' => $panel->mapArtworkSourcesPost($_POST),
    ];

    // Upload, but also works with input field
    if ( ! empty($_FILES['override_image']['name'])) {

        $uploadOverride = $panel->upload('override_image', 'data/images/', 'override-image', $pawtunes->artworkExtensions);
        if ($uploadOverride['error'] !== null) {

            $message = $panel->alert($uploadOverride['error'], 'error');

        } else {

            $_POST['share_image_override'] = "./{$uploadOverride[ 'path' ]}";

        }
    }

    // App icon (home screen / web app manifest): stored as files, not in the config
    if ( ! empty($_FILES['app_icon']['name'])) {

        $iconError = $panel->storeAppIcon('app_icon');
        if ($iconError !== null) {
            $message = $panel->alert($iconError, 'error');
        }

    }


    if (isset($message)) {

        echo "";

    } elseif ( ! empty($_POST['admin_password']) && strlen($_POST['admin_password']) < 5) {

        $message = $panel->alert('Panel password must have at least 5 characters!', 'error');

    } elseif ($_POST['admin_password'] !== $_POST['admin_pass2']) {

        $message = $panel->alert('new passwords do not match, please try again!', 'error');

    } elseif (empty($_POST['admin_username'])) {

        $message = $panel->alert('You must enter admin username or else you won\'t be able to login to the control panel!', 'error');

    } elseif ( ! is_numeric($_POST['artist_maxlength']) || ! is_numeric($_POST['title_maxlength']) || empty($_POST['title']) || empty($_POST['track_regex'])) {

        $message = $panel->alert('You did not define artist and/or title max length!', 'error');

    } elseif (@preg_match("/{$_POST['track_regex']}/i", null) === false) {

        $message = $panel->alert('Track RegEx is invalid! Please fix it or use default value.', 'error');

    } elseif ( ! is_numeric($_POST['stats_refresh']) || $_POST['stats_refresh'] < 3 || $_POST['stats_refresh'] > 120) {

        $message = $panel->alert('Invalid range for Stats Refresh Speed. The value must not be lower than <b>3</b> and higher than <b>120</b>!', 'error');

    } elseif ( ! is_numeric($_POST['historyLength'] ?? null) || $_POST['historyLength'] < 1 || $_POST['historyLength'] > lib\PawTunes\History::MAX_LENGTH) {

        $message = $panel->alert('Invalid range for Track History Length. The value must not be lower than <b>1</b> and higher than <b>'.lib\PawTunes\History::MAX_LENGTH.'</b>!', 'error');

    } elseif ( ! isset($cacheModes[$cache['mode']])) {

        $message = $panel->alert('Unknown cache mode, please pick one from the list.', 'error');

    } elseif ( ! $cacheModes[$cache['mode']]['available']) {

        $message = $panel->alert("Cache mode <b>{$cacheModes[ $cache[ 'mode' ] ][ 'label' ]}</b> needs a PHP extension that is not installed on this server. Settings were not saved.", 'error');

    } elseif ($cacheChanged && ($cacheError = lib\Cache::prepare($pawtunes->cacheOptions($cache))) !== null) {

        // Last check on purpose: on success prepare() has already emptied the new cache
        $message = $panel->alert('Settings were not saved, the new cache settings do not work: '.htmlspecialchars($cacheError, ENT_QUOTES, 'UTF-8'), 'error');

    } else {

        // Delete submit key
        unset($_POST['admin_pass2']);

        // Password handle
        if ( ! empty($_POST['admin_password'])) { // Hash password, safety

            $_POST['admin_password'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);

        } else { // No password provided

            $_POST['admin_password'] = $pawtunes->config('admin_password');

        }

        $_POST['historyLength'] = (int) $_POST['historyLength'];

        // Keep development key
        if ( ! empty($pawtunes->config('development'))) {
            $_POST['development'] = $pawtunes->config('development');
        }

        $pawtunes->setConfigAll($store + $_POST);
        if ($panel->storeConfig('config/general', $pawtunes->getConfigAll())) {

            $message      = $panel->alert('Settings successfully updated!', 'success');
            $cacheApplied = $cacheChanged;

        } else {

            $message = $panel->alert('Unable to save configuration changes, you may not have sufficient permissions!', 'error', true);

        }

    }


} else {

    $_POST = $pawtunes->getConfigAll();
    unset($_POST['artwork_sources']);

    // Show what the player actually does for keys that are not in general.php yet
    foreach ($checkboxes as $key => $default) {
        $_POST[$key] = $_POST[$key] ?? $default;
    }

    $_POST['historyLength'] = lib\PawTunes\History::length($pawtunes);

}

// Never show password
if (isset($_POST['admin_password'])) {
    unset($_POST['admin_password']);
}

if (isset($_POST['admin_pass2'])) {
    unset($_POST['admin_pass2']);
}

// Create list of available languages
$language  = include('panel/inc/language-list.php');
$languages = [];
if (is_dir('inc/locale')) {

    $ff    = $pawtunes->browse('inc/locale/');
    $files = [];

    // Only available
    foreach ($ff as $file) {
        $filename         = $pawtunes->extDel($file);
        $languages[$file] = $language[$filename];
    }

}

// Get all available channels
$channels = $pawtunes->getChannels();

// Now create options array
$channelsList = [0 => 'Default'];
if (is_array($channels)) {
    foreach ($channels as $c): $channelsList[$c['name']] = $c['name']; endforeach;
}

// Re-read manifests on every visit (also refreshes the player's cached copy), so manifest
// edits such as a new screenshot show up here without a manual cache flush
$pawtunes->cache->delete('templates');
$templates = $pawtunes->getTemplates();

// Screenshot URL relative to the panel, versioned by mtime so a replaced image is not served stale.
// Templates without a screenshot get null and the view renders a placeholder instead.
foreach ($templates as $key => $template) {
    $screenshot = "{$template['path']}/".($template['screenshot'] ?? '');

    $templates[$key]['screenshotURL'] = ( ! empty($template['screenshot']) && is_file($screenshot))
        ? "./../{$screenshot}?v=".filemtime($screenshot)
        : null;
}

// Fall back to the first template when the configured one no longer exists, same as the player does
$selectedTemplate = isset($templates[$_POST['template'] ?? '']) ? $_POST['template'] : array_key_first($templates);

// Cache section fields, from the "cache" array (stored, or as submitted). Forms does not escape values.
$cacheForm             = $_POST['cache'] ?? [];
$cacheAuth             = $cacheForm['extra']['auth'] ?? '';
$_POST['cache_mode']   = lib\Cache::normalizeMode((string) ($cacheForm['mode'] ?? ''));
$_POST['cache_path']   = htmlspecialchars((string) ($cacheForm['path'] ?? ''), ENT_QUOTES, 'UTF-8');
$_POST['cache_host']   = htmlspecialchars((string) ($cacheForm['host'] ?? ''), ENT_QUOTES, 'UTF-8');
$_POST['cache_auth']   = is_string($cacheAuth) ? htmlspecialchars($cacheAuth, ENT_QUOTES, 'UTF-8') : '';
$_POST['cache_prefix'] = htmlspecialchars((string) ($cacheForm['prefix'] ?? ''), ENT_QUOTES, 'UTF-8');

// Status line: what this request's cache is doing. After a cache change was saved, that is still the old
// configuration, so the status reports the new one instead (prepare() just proved it works).
$cacheDebug  = $pawtunes->cache->getDebugInfo();
$cacheLabel  = function (?string $mode) use ($cacheModes): string {
    return $cacheModes[$mode]['label'] ?? (string) $mode;
};
$cacheStatus = ['ok' => true, 'text' => 'Working: '.$cacheLabel($cacheDebug['active_mode'])];

if ( ! empty($cacheApplied)) {
    $cacheStatus['text'] = $cacheLabel($_POST['cache_mode']).' tested and emptied, the player uses it from now on.';
} elseif ( ! $cacheDebug['startup']) {
    $cacheStatus = ['ok' => false, 'text' => 'No cache is working: '.($cacheDebug['last_error'] ?? 'unknown error')];
} elseif ($cacheDebug['fallback_active']) {
    $cacheStatus = [
        'ok'   => false,
        'text' => $cacheLabel($cacheDebug['requested_mode']).' is unavailable, using the '.$cacheLabel($cacheDebug['active_mode']).' fallback: '.$cacheDebug['last_error'],
    ];
}

// Mode select, a missing extension is named in the label (saving such a mode is refused above)
$cacheModeOptions = [];
foreach ($cacheModes as $mode => $info) {
    $cacheModeOptions[$mode] = $info['label'].($info['available'] ? '' : ' (extension not installed)');
}

$panel->view(
    'settings',
    [
        'artworkAPIs'   => $panel->mapArtworkSourcesView($pawtunes->getConfigAll()['artwork_sources'] ?? []),
        'pawtunes'      => $pawtunes,
        'message'       => $message ?? null,
        'languages'     => $languages,
        'templates'        => $templates,
        'selectedTemplate' => $selectedTemplate,
        'channels'         => $channelsList,
        'cacheModeOptions' => $cacheModeOptions,
        'cacheStatus'      => $cacheStatus,
    ]
);