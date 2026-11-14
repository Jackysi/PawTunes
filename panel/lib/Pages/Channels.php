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
use Services\ConfigStore;
use Services\Request;

/**
 * Channels page: the channel list with delete, sorting and the cache flush. One channel is added or edited
 * on its own page (Pages\Channel). Every action changes the stored channels or the cache and ends with a
 * redirect, so a page refresh never repeats it.
 */
final class Channels extends Page
{

    private const URL = 'index.php?page=channels';

    private PawTunes $pawtunes;

    private Panel $panel;

    private ConfigStore $config;

    private Request $request;

    /**
     * The stored channels, keyed by their index
     */
    private array $channels;


    public function __construct(PawTunes $pawtunes, Panel $panel, ConfigStore $config, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->config   = $config;
        $this->request  = $request;
        $this->channels = $pawtunes->getChannels();
    }


    protected function index(): void
    {
        $this->panel->view(
            'channels',
            [
                'channels' => $this->channels,
                'mode'     => $this->request->query('sort') === 'custom' ? 'custom' : null, // "?sort=custom" opens drag & drop sorting
                'message'  => null,
            ]
        );

    }


    /**
     * Delete the channel named by "?channel=<index>", with its logo
     */
    protected function postDelete(): void
    {
        $id = $this->request->query('channel');
        if ( ! isset($this->channels[$id]) || ! is_array($this->channels[$id])) {
            $this->panel->flash($this->panel->alert('Sorry but selected channel does not exist, so it was not removed.', 'error'), self::URL);
        }

        $logo = $this->channels[$id]['logo'] ?? null;
        if ($logo !== null && is_file($logo)) {
            PawTunes::deleteFile($logo);
        }

        unset($this->channels[$id]);

        $this->save('Channel was successfully deleted.', 'Unable to delete channel, you may not have sufficient permissions!');

    }


    /**
     * Sort the channels by name, "order" is asc or desc
     */
    protected function postSort(): void
    {
        $order = $this->request->post('order');
        if ($order !== 'asc' && $order !== 'desc') {
            return;
        }

        $names = array_column($this->channels, 'name');
        array_multisort($names, $order === 'asc' ? SORT_ASC : SORT_DESC, $this->channels);

        $this->saveOrder();

    }


    /**
     * Store the drag & drop order: "ids" lists the current channel indexes in their new order
     */
    protected function postReorder(): void
    {
        $ids = $this->request->postArray('ids');

        // Every channel exactly once. Anything else (a stale page, a missing row) would drop or duplicate channels.
        if (count($ids) !== count($this->channels) || count(array_unique($ids)) !== count($ids)) {
            $this->panel->flash($this->panel->alert('The channel list has changed in the meantime, please sort it again.', 'error'), self::URL);
        }

        $sorted = [];
        foreach ($ids as $id) {

            if ( ! is_string($id) || ! isset($this->channels[$id])) {
                $this->panel->flash($this->panel->alert('The channel list has changed in the meantime, please sort it again.', 'error'), self::URL);
            }

            $sorted[] = $this->channels[$id];

        }

        $this->channels = $sorted;
        $this->saveOrder();

    }


    /**
     * Empty the cache, downloaded artwork included
     */
    protected function postFlushCache(): void
    {
        $this->pawtunes->cache->clear();

        // The cache folder also holds what the cache store does not know about: downloaded artwork
        $cachePath = realpath($this->pawtunes->config('cache')['path']);
        if ( ! $cachePath) {
            $this->panel->flash($this->panel->alert('Unable to clean cache because specified cache path does not exist or it can not be resolved.', 'error'), self::URL);
        }

        foreach ($this->pawtunes->browse($cachePath) as $file) {
            PawTunes::deleteFile($cachePath.'/'.$file);
        }

        $this->panel->flash($this->panel->alert('Successfully cleaned whole cache including artist images!', 'success'), self::URL);

    }


    private function saveOrder(): void
    {
        $this->save('New channels sorting has been successfully stored!', 'Failed to save the new channels order, you may not have sufficient permissions!');

    }


    /**
     * Write the channels and go back to the list with the outcome
     *
     * @param  string  $success  Message when the file was written
     * @param  string  $failure  Message when it was not
     */
    private function save(string $success, string $failure): void
    {
        $message = $this->config->save('config/channels', $this->channels)
            ? $this->panel->alert($success, 'success')
            : $this->panel->alert($failure, 'error', true);

        $this->panel->flash($message, self::URL);

    }

}
