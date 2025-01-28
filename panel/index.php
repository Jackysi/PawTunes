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

// Use player directory as chroot
chdir('./../');
require 'inc/autoload.php';
require 'panel/lib/autoload.php';

use lib\PawTunes;

// Variables & functions
$item   = 'pawhtn3S';
$prefix = base64_encode(getcwd());

// Required general player settings
$pawtunes = new PawTunes();

// ERROR Reporting & Settings
error_reporting(($pawtunes->config('debugging') !== 'enabled') ? E_ALL & ~E_NOTICE : E_ALL);
ini_set('display_errors', $pawtunes->config('debugging') === 'enabled');
ini_set('error_reporting', ($pawtunes->config('debugging') !== 'enabled') ? E_ALL & ~E_NOTICE : E_ALL);
ini_set("log_errors", $pawtunes->config('debugging') !== 'disabled');
ini_set("error_log", getcwd()."/data/logs/panel_errors.log");

// Output buffer & PHP SESSION
ob_start();
session_start();

// Required control panel files
$panel = new Panel(
    $pawtunes,
    $prefix,
    [
        'auth'     => &$_SESSION[$prefix]['pawtunes-auth'],
        'item'     => $item,
        'version'  => (is_file('panel/version.txt')) ? file_get_contents('panel/version.txt') : 'development',
        'settings' => $pawtunes->getConfigAll(),
    ]
);

// If password is missing, generate one
if (empty($pawtunes->config('admin_password'))) {

    $pawtunes->setConfig('admin_password', password_hash('password', PASSWORD_DEFAULT));
    $panel->storeConfig('config/general', $pawtunes->getConfigAll());

}

// Logout user
if (isset($_GET['logout'])) {

    unset($_SESSION[$prefix]['pawtunes-auth']);
    header("Location: index.php?page=login");
    exit;

}

// Create header and attempt login
if ( ! $panel->isAuthorized()) {

    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        echo json_encode(['error' => 'You must be logged in to access this page.'], JSON_THROW_ON_ERROR);
        exit();
    }

    require 'login.php';
    exit();

}


// Safety feature, replaces anything but numbers or letters
$_GET['page'] = preg_replace('/[^0-9a-z_]/i', '', (( ! empty($_GET['page'])) ? $_GET['page'] : 'home'));

// Now rest
if ( ! empty($_GET['page']) && is_file("panel/{$_GET['page']}.php")) {

    require "panel/{$_GET['page']}.php";

} else {

    require 'panel/home.php';

}