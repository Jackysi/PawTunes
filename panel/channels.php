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

// Load channels file (used for all actions on this page)
$channels = $pawtunes->getChannels();
if (isset($_GET['action']) && ($_GET['action'] === 'add' || $_GET['action'] === 'edit')) {
    require 'channels.edit.php';
    exit;
}

// Every action below changes the stored channels or the cache, so each one is POST only (CSRF-checked in
// panel/index.php) and ends with a redirect, so a page refresh never re-submits it. The one GET exception is
// ?sort=custom without posted ids: it only opens the drag & drop mode.
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$sort   = $isPost ? ($_POST['sort'] ?? $_GET['sort'] ?? '') : (($_GET['sort'] ?? '') === 'custom' ? 'custom' : '');

// Delete channel, by key
if ($isPost && isset($_POST['delete'])) {

    $deleteId = $_POST['delete'];

    // Check if the channel with specified ID exists
    if ( ! isset($channels[$deleteId]) || ! is_array($channels[$deleteId])) {

        $message = $panel->alert('Sorry but selected channel does not exist, so it was not removed.', 'error');

    } else {

        // Attempt to delete Logo of channel
        if (isset($channels[$deleteId]['logo']) && is_file($channels[$deleteId]['logo'])) {
            $pawtunes::deleteFile($channels[$deleteId]['logo']);
        }

        // Delete channel
        unset($channels[$deleteId]);

        // Delete channel and save changes
        if ( ! $panel->storeConfig('config/channels', $channels)) { // Attempt to save

            $message = $panel->alert('Unable to delete channel, you may not have sufficient permissions!', 'error', true);

        } else {

            $message = $panel->alert('Channel was successfully deleted.', 'success');

        }

    }

    $panel->flash($message, 'index.php?page=channels');

} elseif ($sort !== '') { // Sort actions (asc, desc and custom)

    // Switch sorting mode
    switch ($sort) {

        case 'asc':
            $mode = SORT_ASC;
            break;

        case 'desc':
            $mode = SORT_DESC;
            break;

        default:
            $mode = 'custom';
            break;

    }

    // Only work when not custom (allow asc/desc)
    if ( ! isset($error)) {

        $save = false;

        // When we are sorting ASC or DESC
        if (($sort === 'desc' || $sort === 'asc') && $mode !== 'custom') {

            foreach ($channels as $key => $row): $ss_by[$key] = $row['name']; endforeach; ## Find common key
            array_multisort($ss_by, $mode, $channels);
            $save = true;

        }

        // When sorting using DRAG & DROP
        if ($isPost && ! empty($_POST['ids']) && $mode === 'custom') {

            // Loop through old list of channels and create new one based on sorting picks
            $new_list = [];
            foreach ($_POST['ids'] as $new_key => $old_key) {

                if ( ! isset($channels[$old_key])) {
                    $error = 'Unable to find a channel with id <b>#'.$old_key.'</b>!';
                }

                $new_list[$new_key] = $channels[(int) $old_key];

            }

            // If no error, clear mode/channels vars and create new channels var
            if ( ! isset($error)) {

                unset($mode, $channels);
                $channels = $new_list;

            }

            $save = true;

        }

        // Attempt to save new list of channels into the file and show what happen
        if ($save) {
            if ( ! $panel->storeConfig('config/channels', $channels)) { // Attempt to save

                $message = $panel->alert('Failed to save the new channels order, you may not have sufficient permissions!', 'error', true);

            } else {

                $message = $panel->alert('New channels sorting has been successfully stored!', 'success');

            }

            $panel->flash($message, 'index.php?page=channels');
        }

    }

} elseif ($isPost && ($_POST['cache'] ?? '') === 'flush') {

    // Use cache delete first
    $pawtunes->cache->clear();

    // Cache directory
    $cachePath = realpath($pawtunes->config('cache')['path']);

    // Make sure cache exists/resolves
    if ($cachePath) {

        // Get list of caches (remove cache)
        $files = $pawtunes->browse($cachePath);
        foreach ($files as $file): $pawtunes::deleteFile($cachePath.'/'.$file); endforeach;
        $message = $panel->alert('Successfully cleaned whole cache including artist images!', 'success');

    } else {

        $message = $panel->alert("Unable to clean cache because specified cache path does not exist or it can not be resolved.", "error");

    }

    $panel->flash($message, 'index.php?page=channels');

}

$panel->view(
    'channels',
    [
        'channels' => $channels,
        'mode'     => $mode ?? null,
        'message'  => $message ?? null,
    ]
);