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

use Forms;
use lib\Helpers;
use lib\Image\Image;
use lib\PawTunes;
use Panel;
use Services\ConfigStore;
use Services\Request;
use Services\Uploads;

/**
 * Channel editor: "index.php?page=channel" adds a channel, "index.php?page=channel&id=<index>" edits one.
 *
 * The track info settings are driven by panel/inc/track-info-methods.php: the form fields are drawn from
 * that list and the stored "stats" array is built from it, see statsFromPost().
 */
final class Channel extends Page
{

    private const LIST_URL = 'index.php?page=channels';

    /**
     * Stream formats the player can play: file extension key => label
     */
    private const CODECS = ['mp3' => 'MP3', 'oga' => 'OGG', 'm4a' => 'AAC', 'flac' => 'FLAC'];

    private const LOGO_EXTENSIONS = ['jpeg', 'png', 'webp', 'jpg', 'svg'];

    /**
     * A stream URL ending in one of these is a playlist file, which the player cannot play
     */
    private const PLAYLIST_EXTENSIONS = ['pls', 'm3u', 'xspf'];

    private PawTunes $pawtunes;

    private Panel $panel;

    private ConfigStore $config;

    private Uploads $uploads;

    private Request $request;

    /**
     * The stored channels, keyed by their index
     */
    private array $channels;

    /**
     * Track info methods, see panel/inc/track-info-methods.php
     */
    private array $methods;

    /**
     * Index of the channel being edited, null while adding a new one
     */
    private ?string $id;

    /**
     * Error HTML shown above the form
     */
    private ?string $error = null;

    /**
     * The submitted fields, shown again when saving failed. null = show the stored channel.
     */
    private ?array $submitted = null;


    public function __construct(PawTunes $pawtunes, Panel $panel, ConfigStore $config, Uploads $uploads, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->config   = $config;
        $this->uploads  = $uploads;
        $this->request  = $request;
        $this->channels = $pawtunes->getChannels();
        $this->methods  = include 'panel/inc/track-info-methods.php';
        $this->id       = $request->query('id') !== '' ? $request->query('id') : null;

        if ($this->id !== null && ( ! is_numeric($this->id) || empty($this->channels[$this->id]))) {
            $panel->flash($panel->alert('Unable to edit specified channel because it was not found!', 'error'), self::LIST_URL);
        }

    }


    protected function index(): void
    {
        $values = $this->submitted ?? $this->storedValues();

        // Secrets are never sent back to the browser, not even the ones just submitted with a rejected form
        foreach ($this->methods as $method) {
            foreach ($method['fields'] as $field) {
                if (isset($field['sent_to'])) {
                    unset($values[$field['name']]);
                }
            }
        }

        $this->panel->view(
            'channel-edit',
            [
                'form'             => new Forms($values),
                'values'           => $values,
                'codecs'           => self::CODECS,
                'channels'         => $this->channels,
                'cid'              => $this->id,
                'error'            => $this->error,
                'schemesList'      => $this->colorSchemes(),
                'trackInfoMethods' => $this->methodsForView(),
            ]
        );

    }


    /**
     * Add the channel, or replace the one being edited
     */
    protected function postSave(): void
    {
        $post            = $this->request->postFields();
        $this->submitted = $post;

        // Each step runs only while everything before it was fine, and reports its problem in $this->error.
        // The logo comes last, so a rejected form never replaces the logo file of an existing channel.
        $name        = trim($this->request->post('name'));
        $this->error = $this->nameProblem($name);
        $streams     = $this->error === null ? $this->streamsFromPost($post) : null;
        $stats       = $this->error === null ? $this->statsFromPost($post) : null;
        $logo        = $this->error === null ? $this->storeLogo() : null;

        if ($this->error !== null) {
            $this->error = $this->panel->alert($this->error, 'error');

            return;
        }

        $channel = [
            'name'    => Helpers::strToUTF8($name),
            'logo'    => $logo,
            'skin'    => $this->request->post('skin') !== '' ? $this->request->post('skin') : null,
            'streams' => $streams,
            'stats'   => $stats,
        ];

        // A new channel goes to the end of the list
        $channels = $this->channels;
        if ($this->id !== null) {
            $channels[$this->id] = $channel;
        } else {
            $channels = array_merge($channels, [$channel]);
        }

        if ($this->config->save('config/channels', $channels)) {
            $this->panel->flash($this->panel->alert('Successfully '.($this->id === null ? 'added' : 'updated').' channel.', 'success'), self::LIST_URL);
        }

        $this->error = $this->panel->alert('Unable to store channel settings, you may not have sufficient permissions!', 'error', true);

    }


    /**
     * Delete the logo file of the channel being edited. Called by the editor's script, which only needs
     * the request to succeed.
     */
    protected function postDeleteLogo(): void
    {
        if ($this->id !== null && ! empty($this->channels[$this->id]['logo'])) {
            PawTunes::deleteFile($this->channels[$this->id]['logo']);
        }

        die('Deleted logo');

    }


    /**
     * @param  string  $name
     *
     * @return string|null Problem description, null when the name is usable
     */
    private function nameProblem(string $name): ?string
    {
        if ($name === '') {
            return 'You need to specify name of the channel you are creating or editing.';
        }

        // The name is the channel's key in URLs, the location hash and playlist files
        if (preg_match('/[^a-zA-Z0-9\s-]/', $name)) {
            return 'The channel name is invalid, it should not contain any special characters.';
        }

        return null;

    }


    /**
     * Build the "streams" array from the stream groups of the form. Group number N posts its label as
     * quality[N] and its streams as the parallel lists url_N[] and codec_N[].
     *
     * @param  array  $post
     *
     * @return array|null ['<quality label>' => ['<codec>' => '<url>']], null with $this->error set
     */
    private function streamsFromPost(array $post): ?array
    {
        $qualities = is_array($post['quality'] ?? null) ? array_values($post['quality']) : [];
        if (count($qualities) < 1 || empty($post['url_0'][0])) {
            $this->error = 'You have to configure streams! Player does not work without them.';

            return null;
        }

        $groups = [];
        foreach ($qualities as $group => $quality) {

            $urls    = is_array($post["url_{$group}"] ?? null) ? array_values($post["url_{$group}"]) : [];
            $codecs  = is_array($post["codec_{$group}"] ?? null) ? array_values($post["codec_{$group}"]) : [];
            $streams = [];

            foreach ($urls as $row => $url) {

                $url      = is_string($url) ? $url : '';
                $codec    = is_string($codecs[$row] ?? null) ? $codecs[$row] : '';
                $shownURL = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                if ( ! filter_var($url, FILTER_VALIDATE_URL)) {
                    $this->error = 'Stream URL <b>"'.$shownURL.'"</b> is not valid url! Please read section <b>"How to configure streams?"</b> below.';

                    return null;
                }

                if ( ! isset(self::CODECS[$codec])) {
                    $this->error = 'Stream URL <b>"'.$shownURL.'"</b> has no valid format, please pick one from the list.';

                    return null;
                }

                if (in_array(Helpers::extGet($url), self::PLAYLIST_EXTENSIONS, true)) {
                    $this->error = 'Stream URL <b>"'.$shownURL.'"</b> is a playlist file, not an actual stream! Please read section <b>"How to configure streams?"</b> below.';

                    return null;
                }

                $streams[$codec] = $url;

            }

            $groups[is_string($quality) ? $quality : ''] = $streams;

        }

        return $groups;

    }


    /**
     * Build the "stats" array of the picked track info method from the method's field list: each field
     * is read from its POST "name", checked by its "validate" rule and stored under its "map" key.
     *
     * @param  array  $post
     *
     * @return array|null ['method' => '<method>', ...], null with $this->error set
     */
    private function statsFromPost(array $post): ?array
    {
        $method = $this->request->post('stats');
        if ( ! isset($this->methods[$method])) {
            $this->error = 'Invalid stats configuration! Can not continue!';

            return null;
        }

        $stats = ['method' => $method];
        foreach ($this->methods[$method]['fields'] as $field) {

            $value = $post[$field['name']] ?? null;

            if (($field['type'] ?? '') === 'checkbox') {
                $stats[$field['map']] = ! empty($value);
                continue;
            }

            $value = is_string($value) ? $value : null;
            if ( ! empty($field['trim'])) {
                $value = trim((string) $value) !== '' ? trim($value) : null;
            }

            if (isset($field['sent_to'])) {
                $value = $this->secretFromPost($field, $value, $stats, $post);
            }

            $this->error = $this->error ?? $this->fieldProblem($field, $value);
            if ($this->error !== null) {
                return null;
            }

            $stats[$field['map']] = $value;

        }

        return $stats;

    }


    /**
     * Value to store for a secret field (one with "sent_to"). The saved secret is never sent to the browser,
     * so an empty field means "keep the saved one" and removing it takes the "forget-<name>" checkbox.
     *
     * @param  array  $field  Field definition from panel/inc/track-info-methods.php
     * @param  string|null  $value  The posted value
     * @param  array  $stats  The stats array built so far, it already holds the "sent_to" field
     * @param  array  $post
     *
     * @return string|null The value to store. A saved secret that cannot be kept sets $this->error.
     */
    private function secretFromPost(array $field, ?string $value, array $stats, array $post): ?string
    {
        if ( ! empty($post["forget-{$field['name']}"])) {
            return null;
        }

        $saved = $this->savedStats();
        $typed = $value !== null && $value !== '';
        if ($typed || ($saved['method'] ?? null) !== $stats['method'] || empty($saved[$field['map']])) {
            return $value;
        }

        // A saved secret belongs to the server it was entered for. Keeping it after the server changed would
        // send it to the new address, which is how someone in the panel could read it without ever seeing it.
        if (($saved[$field['sent_to']] ?? null) !== ($stats[$field['sent_to']] ?? null)) {
            $this->error = "The saved <b>{$field['label']}</b> belongs to the server this channel used before. Enter it again to use it with the new one.";

            return null;
        }

        return $saved[$field['map']];

    }


    /**
     * @return array The stored "stats" array of the channel being edited, empty while adding a new one
     */
    private function savedStats(): array
    {
        return $this->id !== null ? (array) ($this->channels[$this->id]['stats'] ?? []) : [];

    }


    /**
     * The track info methods as the form draws them. A secret that is saved shows that in its placeholder,
     * is no longer required (empty keeps it) and, when the method works without it, gets a checkbox to remove it.
     *
     * @return array
     */
    private function methodsForView(): array
    {
        $saved   = $this->savedStats();
        $methods = $this->methods;

        foreach ($methods as $method => $definition) {

            $fields = [];
            foreach ($definition['fields'] as $field) {

                $isSaved  = isset($field['sent_to']) && ($saved['method'] ?? null) === $method && ! empty($saved[$field['map']]);
                $optional = empty($field['required']);

                if ($isSaved) {
                    $field['placeholder'] = 'Saved, leave blank to keep it';
                    unset($field['required']);
                }

                $fields[] = $field;

                if ($isSaved && $optional) {
                    $fields[] = [
                        'name'        => "forget-{$field['name']}",
                        'type'        => 'checkbox',
                        'value'       => 'true',
                        'class'       => 'col-sm-9 col-sm-offset-2',
                        'description' => "Remove the saved {$field['label']}",
                    ];
                }

            }

            $methods[$method]['fields'] = $fields;

        }

        return $methods;

    }


    /**
     * Check one track info field against its "validate" rule
     *
     * @param  array  $field  Field definition from panel/inc/track-info-methods.php
     * @param  string|null  $value
     *
     * @return string|null Problem description, null when the value is fine
     */
    private function fieldProblem(array $field, ?string $value): ?string
    {
        $rule  = $field['validate'] ?? '';
        $label = "<b>{$field['label']}</b>";
        $empty = $value === null || $value === '';

        if ($rule === 'required' && $empty) {
            return "{$label} is not set. Without it, PawTunes cannot read the track info.";
        }

        // An optional URL may stay empty
        if ($rule === 'url' && ( ! $empty || ! empty($field['required'])) && ! filter_var($value, FILTER_VALIDATE_URL)) {
            return "{$label} is not a valid URL.";
        }

        if ($rule === 'server' && Helpers::parseURL($value) === null) {
            return "{$label} could not be detected. Please use the <b>http://url-to-server:port</b> format.";
        }

        return null;

    }


    /**
     * Store an uploaded logo, resized to 80px height (SVG logos are used as they are)
     *
     * @return string|null Path of the channel's logo: the new upload, else the one it already had.
     *                     A failed upload sets $this->error.
     */
    private function storeLogo(): ?string
    {
        $current = $this->id !== null ? ($this->channels[$this->id]['logo'] ?? null) : null;
        if ($this->request->uploadName('logo') === '') {
            return $current;
        }

        // The time in the name gives every upload a new URL, so browsers never show a cached old logo
        $upload = $this->uploads->upload('logo', 'data/images/', 'logo.'.time(), self::LOGO_EXTENSIONS);
        if ($upload['error'] !== null) {
            $this->error = "Uploading logo failed! ERROR: {$upload['error']}";

            return $current;
        }

        if ( ! empty($current)) {
            PawTunes::deleteFile($current);
        }

        // 80px high, proportions kept. An SVG cannot be opened and is used as it is.
        $image = Image::open($upload['path']);
        if ($image !== null) {
            $image->scaleToHeight(80)->save($upload['path']);
        }

        return $upload['path'];

    }


    /**
     * The stored channel as form field values, the reverse of streamsFromPost() and statsFromPost()
     *
     * @return array
     */
    private function storedValues(): array
    {
        // A new channel starts without track info
        if ($this->id === null) {
            return ['stats' => 'disabled'];
        }

        $channel = $this->channels[$this->id];
        $values  = ['name' => $channel['name'] ?? ''];

        $group = 0;
        foreach ((array) ($channel['streams'] ?? []) as $quality => $streams) {

            $values['quality'][$group] = $quality;
            foreach ($streams as $codec => $url) {
                $values["url_{$group}"][]   = $url;
                $values["codec_{$group}"][] = $codec;
            }

            $group++;

        }

        $stats           = (array) ($channel['stats'] ?? []);
        $values['stats'] = $stats['method'] ?? 'disabled';

        foreach ($this->methods[$values['stats']]['fields'] ?? [] as $field) {
            $values[$field['name']] = $stats[$field['map']] ?? null;
        }

        return $values;

    }


    /**
     * Color schemes a channel can use: the active template's own ones and the ones compiled in the panel
     *
     * @return array ['<label>' => '<stylesheet path relative to the template>']
     */
    private function colorSchemes(): array
    {
        $template = $this->pawtunes->getTemplates()[$this->pawtunes->config('template')] ?? [];
        $schemes  = [];

        foreach ((array) ($template['schemes'] ?? []) as $scheme) {
            $schemes[$scheme['name']] = $scheme['style'];
        }

        foreach (Helpers::browse(($template['path'] ?? '').'/custom/', true, false) as $file) {
            $schemes[ucfirst(Helpers::extDel($file))] = "custom/{$file}";
        }

        return $schemes;

    }

}
