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
use lib\PawTunes;
use Panel;
use Services\ConfigStore;
use Services\Request;

/**
 * Language page: the list of translations (inc/locale/<iso>.php), and the editor for one of them.
 * "?page=language&add" opens the editor for a new language, "?page=language&edit=<iso>" for an existing one.
 */
final class Language extends Page
{

    private const URL = 'index.php?page=language';

    private PawTunes $pawtunes;

    private Panel $panel;

    private ConfigStore $config;

    private Request $request;

    /**
     * Every language PawTunes knows, keyed by ISO code (panel/inc/language-list.php)
     */
    private array $languages;

    /**
     * Alert HTML shown in the editor
     */
    private ?string $message = null;

    /**
     * The submitted texts, shown again when saving failed. null = show the stored translation.
     */
    private ?array $submitted = null;


    public function __construct(PawTunes $pawtunes, Panel $panel, ConfigStore $config, Request $request)
    {
        $this->pawtunes  = $pawtunes;
        $this->panel     = $panel;
        $this->config    = $config;
        $this->request   = $request;
        $this->languages = include 'panel/inc/language-list.php';
    }


    protected function index(): void
    {
        $adding  = $this->request->hasQuery('add');
        $editing = $this->isoCode($this->request->query('edit'));

        if ( ! $adding && $editing === '') {

            $this->panel->view(
                'language-list',
                [
                    'languages'    => $this->languages,
                    'translations' => Helpers::browse('inc/locale/'),
                    'message'      => null,
                ]
            );

            return;
        }

        $values = $this->submitted ?? ($adding ? [] : $this->storedTexts($editing));

        $this->panel->view(
            'language-edit',
            [
                'form'      => new Forms($values),
                'languages' => $this->languages,
                'iso'       => $adding ? null : $editing,
                'message'   => $this->message,
            ]
        );

    }


    /**
     * Save the texts of the editor as inc/locale/<iso>.php
     */
    protected function postSave(): void
    {
        // Every posted field is one text of the translation, except the language picker of a new one
        $texts = array_map('trim', array_filter($this->request->postFields(), 'is_string'));
        $iso   = $this->isoCode($texts['isocode'] ?? $this->request->query('edit'));
        unset($texts['isocode']);

        $this->submitted = $texts;

        if ( ! isset($this->languages[$iso])) {
            $this->message = $this->panel->alert('Invalid ISO code or file name, please cancel and try again.', 'error');

            return;
        }

        if ($this->config->save("locale/{$iso}", $texts)) {

            $done = $this->request->hasQuery('add') ? 'added' : 'updated';
            $this->panel->flash($this->panel->alert("<b>{$this->languages[$iso]['name']}</b> translation has been {$done} successfully!", 'success'), self::URL);

        }

        $this->message = $this->panel->alert('Failed saving translation because you may not have sufficient permissions!', 'error', true);

    }


    /**
     * Delete the translation named by "?iso=<iso>"
     */
    protected function postDelete(): void
    {
        $iso  = $this->isoCode($this->request->query('iso'));
        $file = "inc/locale/{$iso}.php";

        // The player requires the default language file, without it every page load is a fatal error
        if ("{$iso}.php" === $this->pawtunes->config('default_lang')) {
            $this->panel->flash($this->panel->alert('The default language can not be deleted, choose another default language in Settings first.', 'error'), self::URL);
        }

        if ($iso !== '' && is_file($file) && unlink($file)) {
            $this->panel->flash($this->panel->alert('Successfully deleted the <b>'.($this->languages[$iso]['name'] ?? 'Unknown').'</b> translation!', 'success'), self::URL);
        }

        $this->panel->flash($this->panel->alert('Failed to delete specified language file, you may not have sufficient permissions!', 'error'), self::URL);

    }


    /**
     * The code becomes a file name, so it is reduced to letters and digits
     *
     * @param  string  $code
     *
     * @return string
     */
    private function isoCode(string $code): string
    {
        return preg_replace('![^a-z0-9]!i', '', $code);
    }


    /**
     * @param  string  $iso
     *
     * @return array The texts of a stored translation
     */
    private function storedTexts(string $iso): array
    {
        if ( ! is_file("inc/locale/{$iso}.php")) {
            $this->panel->flash($this->panel->alert('Invalid ISO code or file name, please select another language and try again.', 'error'), self::URL);
        }

        return include "inc/locale/{$iso}.php";

    }

}
