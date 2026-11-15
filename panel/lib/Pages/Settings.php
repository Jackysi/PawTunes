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

use lib\Cache;
use lib\Helpers;
use lib\PawTunes;
use lib\PawTunes\History;
use Panel;
use Services\ConfigStore;
use Services\Request;
use Services\Uploads;

/**
 * Settings page: everything in inc/config/general.php except the AI settings (Pages\Ai).
 *
 * The main form is stored as a whole: saving REPLACES general.php with the posted fields, plus the few
 * keys the form does not show (see postSave()). The smaller actions each change one thing and leave the
 * rest of the configuration alone.
 */
final class Settings extends Page
{

    private const URL = 'index.php?page=settings';

    /**
     * Checkbox fields of views/settings.blade.php, with the value the player assumes while the key is missing.
     * Browsers do not post unchecked boxes, so each one is written explicitly: otherwise unchecking would drop
     * the key from general.php, and an option that defaults to on (clean_artwork_search) could never be
     * turned off.
     */
    private const CHECKBOXES
        = [
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

    private PawTunes $pawtunes;

    private Panel $panel;

    private ConfigStore $config;

    private Uploads $uploads;

    private Request $request;

    /**
     * Alert HTML shown at the top of the page
     */
    private ?string $message = null;

    /**
     * The submitted form, shown again after a save (successful or not). null = show the stored settings.
     */
    private ?array $submitted = null;

    /**
     * A changed cache configuration was saved by this request
     */
    private bool $cacheApplied = false;


    public function __construct(PawTunes $pawtunes, Panel $panel, ConfigStore $config, Uploads $uploads, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->config   = $config;
        $this->uploads  = $uploads;
        $this->request  = $request;
    }


    protected function index(): void
    {
        $values = $this->submitted ?? $this->storedValues();

        // Passwords are never sent back to the browser
        unset($values['admin_password'], $values['admin_pass2']);

        // Cache section fields, from the "cache" array (stored, or as submitted). An ACL [user, password]
        // pair as Redis auth cannot be shown in one field, it stays empty.
        $cache                  = is_array($values['cache'] ?? null) ? $values['cache'] : [];
        $cacheAuth              = $cache['extra']['auth'] ?? '';
        $values['cache_mode']   = Cache::normalizeMode((string) ($cache['mode'] ?? ''));
        $values['cache_path']   = (string) ($cache['path'] ?? '');
        $values['cache_host']   = (string) ($cache['host'] ?? '');
        $values['cache_auth']   = is_string($cacheAuth) ? $cacheAuth : '';
        $values['cache_prefix'] = (string) ($cache['prefix'] ?? '');

        $channels = ['0' => 'Default'];
        foreach ((array) $this->pawtunes->getChannels() as $channel) {
            $channels[$channel['name']] = $channel['name'];
        }

        $templates = $this->templates();

        // Mode select, a missing extension is named in the label (saving such a mode is refused in postSave())
        $cacheModeOptions = [];
        foreach ($this->cacheModes() as $mode => $info) {
            $cacheModeOptions[$mode] = $info['label'].($info['available'] ? '' : ' (extension not installed)');
        }

        $this->panel->view(
            'settings',
            [
                'values'           => $values,
                'artworkAPIs'      => $this->panel->mapArtworkSourcesView($this->pawtunes->getConfigAll()['artwork_sources'] ?? []),
                'message'          => $this->message,
                'languages'        => $this->installedLanguages(),
                'templates'        => $templates,
                // Fall back to the first template when the configured one no longer exists, same as the player does
                'selectedTemplate' => isset($templates[$values['template'] ?? '']) ? $values['template'] : array_key_first($templates),
                'channels'         => $channels,
                'cacheModeOptions' => $cacheModeOptions,
                'cacheStatus'      => $this->cacheStatus($values['cache_mode']),
            ]
        );

    }


    /**
     * Save the main settings form
     */
    protected function postSave(): void
    {
        $posted = $this->request->postFields();

        foreach (self::CHECKBOXES as $key => $default) {
            $posted[$key] = ($posted[$key] ?? '') === 'true';
        }

        // Cache section. Starts from the stored array, so keys the form does not show (ext, the Redis and
        // Memcached OPT_* options in extra) survive the save.
        $previousCache = $this->pawtunes->config('cache') ?: [];
        $cachePath     = trim($this->request->post('cache_path'));
        $cache         = array_merge($previousCache, [
            'mode'   => Cache::normalizeMode($this->request->post('cache_mode')),
            'path'   => $cachePath !== '' ? $cachePath : './data/cache',
            'host'   => trim($this->request->post('cache_host')),
            'prefix' => trim($this->request->post('cache_prefix')),
        ]);

        // Redis password. An empty field removes it, except an ACL [user, password] pair, which only
        // general.php can set and the field cannot show. Memcached ignores the key, so it survives mode switches.
        $extra = is_array($cache['extra'] ?? null) ? $cache['extra'] : [];
        if ($this->request->post('cache_auth') !== '') {
            $extra['auth'] = $this->request->post('cache_auth');
        } elseif ( ! is_array($extra['auth'] ?? null)) {
            unset($extra['auth']);
        }
        $cache['extra'] = $extra;

        $cacheChanged = $this->cacheSignature($cache) !== $this->cacheSignature($previousCache);

        // The cache_* fields never reach general.php, they are stored as the "cache" array
        $posted['cache'] = $cache;
        unset($posted['cache_mode'], $posted['cache_path'], $posted['cache_host'], $posted['cache_auth'], $posted['cache_prefix']);

        // A new cache folder is created like the panel's requirement check creates the default one
        if ($cacheChanged && ! is_dir($cache['path'])) {
            @mkdir($cache['path'], 0755, true);
        }

        // Each artwork source posts its fields as one array, they are stored together as "artwork_sources"
        $artworkSources = $this->panel->mapArtworkSourcesPost($posted);
        $posted         = array_diff_key($posted, $artworkSources);

        // Uploads come first: a stored share image becomes the value of its text field
        $problem = null;
        if ($this->request->uploadName('override_image') !== '') {

            $upload = $this->uploads->upload('override_image', 'data/images/', 'override-image', $this->pawtunes->artworkExtensions);
            if ($upload['error'] !== null) {
                $problem = $upload['error'];
            } else {
                $posted['share_image_override'] = "./{$upload['path']}";
            }

        }

        // App icon (home screen / web app manifest): stored as files, not in the config
        if ($this->request->uploadName('app_icon') !== '') {
            $problem = $this->uploads->storeAppIcon('app_icon') ?? $problem;
        }

        $this->submitted = $posted;

        $problem = $problem ?? $this->problem($posted, $cache, $cacheChanged);
        if ($problem !== null) {
            $this->message = $this->panel->alert($problem, 'error');

            return;
        }

        // An empty password field keeps the current password
        $password                 = (string) ($posted['admin_password'] ?? '');
        $posted['admin_password'] = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $this->pawtunes->config('admin_password');
        $posted['historyLength']  = (int) $posted['historyLength'];
        unset($posted['admin_pass2']);

        // Keys without a form field. Everything else that is not posted is dropped from general.php.
        if ( ! empty($this->pawtunes->config('development'))) {
            $posted['development'] = $this->pawtunes->config('development');
        }

        $kept = [
            'cache'           => $cache,
            'tplOptions'      => $this->pawtunes->config('tplOptions') ?: [],
            'ai'              => $this->pawtunes->config('ai') ?: [], // edited on the AI page
            'artwork_sources' => $artworkSources,
        ];

        $this->pawtunes->setConfigAll($kept + $posted);
        if ( ! $this->config->save('config/general', $this->pawtunes->getConfigAll())) {
            $this->message = $this->panel->alert('Unable to save configuration changes, you may not have sufficient permissions!', 'error', true);

            return;
        }

        $this->message      = $this->panel->alert('Settings successfully updated!', 'success');
        $this->cacheApplied = $cacheChanged;

    }


    /**
     * Forget the cached template list, so the templates folder is scanned again (e.g. after adding a template)
     */
    protected function postResetTemplates(): void
    {
        $this->pawtunes->cache->delete('templates');
        $this->panel->flash($this->panel->alert('Templates cache flushed successfully!', 'success'), self::URL);

    }


    /**
     * Remove the uploaded app icon
     */
    protected function postRemoveAppIcon(): void
    {
        $this->uploads->removeAppIcon();
        $this->panel->flash($this->panel->alert('App icon removed, the bundled PawTunes icon is used again.', 'success'), self::URL);

    }


    /**
     * Remove the share image. Called by the page's script, answers with JSON.
     */
    protected function postDeleteShareImage(): void
    {
        // The field is free text (a URL or a path), so only the panel's own upload is ever deleted; anything else is
        // just cleared. Otherwise saving "inc/config/general.php" there and pressing remove would delete the config.
        $shareImage = (string) $this->pawtunes->config('share_image_override');
        if (preg_match('#^\./data/images/override-image\.[a-z]+$#', $shareImage)) {
            PawTunes::deleteFile($shareImage);
        }

        $this->pawtunes->setConfig('share_image_override', '');

        $this->json(['success' => $this->config->save('config/general', $this->pawtunes->getConfigAll())]);

    }


    /**
     * Save the options of one template (the per-template options dialog saves on every change). Called by
     * the page's script, answers with JSON.
     */
    protected function postSaveTemplateOptions(): void
    {
        $template = $this->request->post('template');
        $options  = $this->pawtunes->getAdvancedTemplateOptions($template);
        if (count($options) < 1) {
            $this->json(['error' => 'Unable to set template settings, Template not found!'], 404);
        }

        // Only options the template's manifest declares are stored. A missing field is an unchecked box.
        $posted = $this->request->postFields();
        foreach ($options as $option => $value) {
            $options[$option] = $posted[$option] ?? false;
        }

        $this->pawtunes->setConfig('tplOptions', array_merge($this->pawtunes->config('tplOptions') ?: [], [$template => $options]));

        if ($this->config->save('config/general', $this->pawtunes->getConfigAll())) {
            $this->json(['success' => true]);
        }

        $this->json(['error' => 'Unable to save settings!'], 500);

    }


    /**
     * Check the submitted main form
     *
     * @param  array  $posted  The submitted fields
     * @param  array  $cache  The cache configuration built from them
     * @param  bool  $cacheChanged
     *
     * @return string|null Problem description (HTML), null when the form can be saved
     */
    private function problem(array $posted, array $cache, bool $cacheChanged): ?string
    {
        $password   = (string) ($posted['admin_password'] ?? '');
        $trackRegex = is_string($posted['track_regex'] ?? null) ? $posted['track_regex'] : '';
        $refresh    = $posted['stats_refresh'] ?? null;
        $history    = $posted['historyLength'] ?? null;
        $cacheModes = $this->cacheModes();

        if ($password !== '' && strlen($password) < 5) {
            return 'Panel password must have at least 5 characters!';
        }

        if ($password !== (string) ($posted['admin_pass2'] ?? '')) {
            return 'new passwords do not match, please try again!';
        }

        if (empty($posted['admin_username'])) {
            return 'You must enter admin username or else you won\'t be able to login to the control panel!';
        }

        if ( ! is_numeric($posted['artist_maxlength'] ?? null) || ! is_numeric($posted['title_maxlength'] ?? null) || empty($posted['title']) || $trackRegex === '') {
            return 'You did not define artist and/or title max length!';
        }

        if (@preg_match("/{$trackRegex}/i", '') === false) {
            return 'Track RegEx is invalid! Please fix it or use default value.';
        }

        if ( ! is_numeric($refresh) || $refresh < 3 || $refresh > 120) {
            return 'Invalid range for Stats Refresh Speed. The value must not be lower than <b>3</b> and higher than <b>120</b>!';
        }

        if ( ! is_numeric($history) || $history < 1 || $history > History::MAX_LENGTH) {
            return 'Invalid range for Track History Length. The value must not be lower than <b>1</b> and higher than <b>'.History::MAX_LENGTH.'</b>!';
        }

        if ( ! isset($cacheModes[$cache['mode']])) {
            return 'Unknown cache mode, please pick one from the list.';
        }

        if ( ! $cacheModes[$cache['mode']]['available']) {
            return "Cache mode <b>{$cacheModes[$cache['mode']]['label']}</b> needs a PHP extension that is not installed on this server. Settings were not saved.";
        }

        // Last check on purpose: on success prepare() has already emptied the new cache
        if ($cacheChanged && ($cacheError = Cache::prepare($this->pawtunes->cacheOptions($cache))) !== null) {
            return 'Settings were not saved, the new cache settings do not work: '.htmlspecialchars($cacheError, ENT_QUOTES, 'UTF-8');
        }

        return null;

    }


    /**
     * The stored settings as form field values
     *
     * @return array
     */
    private function storedValues(): array
    {
        $values = $this->pawtunes->getConfigAll();
        unset($values['artwork_sources']);

        // Show what the player actually does for keys that are not in general.php yet
        foreach (self::CHECKBOXES as $key => $default) {
            $values[$key] = $values[$key] ?? $default;
        }

        $values['historyLength'] = History::length($this->pawtunes);

        return $values;

    }


    /**
     * Cache modes lib\Cache supports, with whether this server's PHP can use them
     *
     * @return array ['<mode>' => ['label' => string, 'available' => bool]]
     */
    private function cacheModes(): array
    {
        return [
            'disk'      => ['label' => 'Disk (files)', 'available' => true],
            'apcu'      => ['label' => 'APCu', 'available' => function_exists('apcu_enabled') && apcu_enabled()],
            'redis'     => ['label' => 'Redis', 'available' => class_exists('Redis')],
            'memcached' => ['label' => 'Memcached', 'available' => class_exists('Memcached')],
            'memcache'  => ['label' => 'Memcache (legacy extension)', 'available' => class_exists('Memcache')],
        ];

    }


    /**
     * What identifies a cache configuration: when any of it changes on save, the new one is tested first
     *
     * @param  array  $cache
     *
     * @return array
     */
    private function cacheSignature(array $cache): array
    {
        return [
            Cache::normalizeMode((string) ($cache['mode'] ?? '')),
            (string) ($cache['path'] ?? ''),
            (string) ($cache['host'] ?? ''),
            (string) ($cache['prefix'] ?? ''),
            $cache['extra'] ?? [],
        ];

    }


    /**
     * Status line of the Cache section: what this request's cache is doing. After a cache change was saved,
     * that is still the old configuration, so the status reports the new one instead (prepare() just
     * proved it works).
     *
     * @param  string  $shownMode  The mode the form shows
     *
     * @return array ['ok' => bool, 'text' => string]
     */
    private function cacheStatus(string $shownMode): array
    {
        $modes = $this->cacheModes();
        $debug = $this->pawtunes->cache->getDebugInfo();
        $label = static function (?string $mode) use ($modes): string {
            return $modes[$mode]['label'] ?? (string) $mode;
        };

        if ($this->cacheApplied) {
            return ['ok' => true, 'text' => $label($shownMode).' tested and emptied, the player uses it from now on.'];
        }

        if ( ! $debug['startup']) {
            return ['ok' => false, 'text' => 'No cache is working: '.($debug['last_error'] ?? 'unknown error')];
        }

        if ($debug['fallback_active']) {
            return [
                'ok'   => false,
                'text' => $label($debug['requested_mode']).' is unavailable, using the '.$label($debug['active_mode']).' fallback: '.$debug['last_error'],
            ];
        }

        return ['ok' => true, 'text' => 'Working: '.$label($debug['active_mode'])];

    }


    /**
     * The installed templates with their screenshot URL
     *
     * @return array
     */
    private function templates(): array
    {
        // Re-read manifests on every visit (also refreshes the player's cached copy), so manifest
        // edits such as a new screenshot show up here without a manual cache flush
        $this->pawtunes->cache->delete('templates');
        $templates = $this->pawtunes->getTemplates();

        // Screenshot URL relative to the panel, versioned by mtime so a replaced image is not served stale.
        // Templates without a screenshot get null and the view renders a placeholder instead.
        foreach ($templates as $key => $template) {

            $screenshot = "{$template['path']}/".($template['screenshot'] ?? '');

            $templates[$key]['screenshotURL'] = ( ! empty($template['screenshot']) && is_file($screenshot))
                ? "./../{$screenshot}?v=".filemtime($screenshot)
                : null;

        }

        return $templates;

    }


    /**
     * Languages that have a locale file, keyed by that file name (e.g. "en.php")
     *
     * @return array
     */
    private function installedLanguages(): array
    {
        $known     = include 'panel/inc/language-list.php';
        $installed = [];

        foreach (Helpers::browse('inc/locale/') as $file) {
            $installed[$file] = $known[Helpers::extDel($file)];
        }

        return $installed;

    }


    /**
     * Answer a script call with JSON and end the request
     *
     * @param  array  $data
     * @param  int  $status  HTTP status code
     */
    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        die(json_encode($data));

    }

}
