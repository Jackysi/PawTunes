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

use lib\PawTunes;
use Panel;

/**
 * Home page: the embed code generator and the now playing widget card
 */
final class Home extends Page
{

    private PawTunes $pawtunes;

    private Panel $panel;


    public function __construct(PawTunes $pawtunes, Panel $panel)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
    }


    protected function index(): void
    {
        $templates = $this->pawtunes->getTemplates();

        // "None" is the empty value: a numeric key would collide with a channel or template named "0"
        $templatesList = ['' => 'None'];
        foreach ($templates as $key => $template) {
            $templatesList[$key] = $template['name'];
        }

        $channels = is_file('inc/config/channels.php') ? include 'inc/config/channels.php' : [];
        $channels = is_array($channels) ? $channels : [];

        // The widget needs a real channel, so it gets the plain list without the "None" entry
        $channelNames   = array_map('strval', array_column($channels, 'name'));
        $defaultChannel = ['' => 'None'] + array_combine($channelNames, $channelNames);

        // Popup size of the active template, "WxH" in its manifest
        $size = $templates[$this->pawtunes->config('template')]['size'] ?? '';
        if (strpos($size, 'x') !== false) {
            [$width, $height] = explode('x', $size);
        }

        $this->panel->view(
            'home',
            [
                'templatesList' => $templatesList,
                'def_channel'   => $defaultChannel,
                'channelNames'  => $channelNames,
                'languages'     => $this->installedLanguages(),
                'w'             => $width ?? null,
                'h'             => $height ?? null,
                'apiEnabled'    => $this->pawtunes->config('api') === true, // the now playing widget is part of the public API
            ]
        );

    }


    /**
     * Languages that have a locale file, keyed by that file name (e.g. "en.php")
     *
     * @return array
     */
    private function installedLanguages(): array
    {
        if ( ! is_dir('inc/locale')) {
            return [];
        }

        $known     = include 'panel/inc/language-list.php';
        $installed = [];

        foreach ($this->pawtunes->browse('inc/locale/') as $file) {
            $installed[$file] = $known[$this->pawtunes->extDel($file)];
        }

        return $installed;

    }

}
