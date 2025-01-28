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
 * @var PawTunes $pawtunes
 */

use lib\PawTunes;

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

// View passed variables
$error = null;

// Already logged in?
if ($panel->isAuthorized() === true) {

    header("Location: index.php?page=home");
    exit;

}

// Handle login post
if ( ! empty($_POST)) {

    $auth_list = ($pawtunes->cache->get('auth') !== false) ? $pawtunes->cache->get('auth') : [];
    $attempts  = $auth_list[$_SERVER['REMOTE_ADDR']] ?? 0;

    // Anti-spam or brute force
    if ($attempts >= 5) {

        $pawtunes->writeLog('auth.bans', "User with IP \"{$_SERVER['REMOTE_ADDR']}\" has failed to authorize for more than 3 times!", './../data/logs/');
        $error = '<div class="text-red">Too many invalid login attempts, please try again in approximately 30 minutes!</div><div class="divider"></div>';

    } elseif (
        ! isset($_POST['username'])
        || $_POST['username'] !== $pawtunes->config('admin_username')
        ||
        ! password_verify($_POST['password'], $pawtunes->config('admin_password'))
    ) {

        $error = '<div class="text-red">Invalid username or password, login failed!</div><div class="divider"></div>';

        // Set attempts and store them (save this ip)
        $auth_list[$_SERVER['REMOTE_ADDR']] = $attempts + 1;
        $pawtunes->cache->set('auth', $auth_list, 1800);

    } else { // Login

        $panel->setOption('auth', $panel->authToken());

        // Set attempts and store them (clear this IP)
        $auth_list[$_SERVER['REMOTE_ADDR']] = 0;
        $pawtunes->cache->set('auth', $auth_list, 1800);

        // Redirect
        header("Location: index.php?page=home");
        exit;

    }

}

$panel->view(
    "login",
    [
        'error' => $error,
    ]
);