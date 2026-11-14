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
require 'vendor/autoload.php';

use lib\PawTunes;
use Pages\Home;
use Pages\Login;
use Pages\Page;
use Services\ConfigStore;
use Services\Request;
use Services\Uploads;

/**
 * Build a page and run it. The page's constructor lists what it needs by type, and each parameter is
 * filled from $services: there is no container behind this, only that fixed list.
 *
 * @param  string  $class  A subclass of Pages\Page
 * @param  array  $services  [class name => instance]
 *
 * @return void
 */
function showPage(string $class, array $services): void
{
    $arguments   = [];
    $constructor = (new ReflectionClass($class))->getConstructor();

    foreach ($constructor ? $constructor->getParameters() : [] as $parameter) {

        $type = (string) $parameter->getType();
        if ( ! isset($services[$type])) {
            throw new LogicException("{$class} asks for \"{$type}\", which panel/index.php does not provide.");
        }

        $arguments[] = $services[$type];

    }

    (new $class(...$arguments))->dispatch($services[Request::class]);
}

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

// The panel must never render inside a frame on another site (clickjacking). The home page iframes the player
// preview from this same origin, which SAMEORIGIN / 'self' still allow. The public player and the widget do not
// pass through here, so they stay embeddable.
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");

// Session cookie: HttpOnly keeps it away from scripts, SameSite=Lax keeps it off cross-site POSTs (a second line
// of defense next to the CSRF token), Secure when the panel is served over HTTPS (directly or behind a proxy).
$isHttps = ( ! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);

// Output buffer & PHP SESSION
ob_start();
session_start();

// Required control panel files
$config  = new ConfigStore();
$uploads = new Uploads();
$panel   = new Panel(
    $pawtunes,
    $prefix,
    [
        'auth'     => &$_SESSION[$prefix]['pawtunes-auth'],
        'item'     => $item,
        'version'  => (is_file('panel/version.txt')) ? file_get_contents('panel/version.txt') : 'development',
        'settings' => $pawtunes->getConfigAll(),
    ],
    $config,
    $uploads
);

// Everything a page may ask for in its constructor. A page lists what it needs by type and gets exactly that.
$services = [
    PawTunes::class    => $pawtunes,
    Panel::class       => $panel,
    ConfigStore::class => $config,
    Uploads::class     => $uploads,
    Request::class     => Request::fromGlobals(),
];

// If password is missing, generate one
if (empty($pawtunes->config('admin_password'))) {

    $pawtunes->setConfig('admin_password', password_hash('password', PASSWORD_DEFAULT));
    $panel->storeConfig('config/general', $pawtunes->getConfigAll());

}

// Create header and attempt login
if ( ! $panel->isAuthorized()) {

    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        echo json_encode(['error' => 'You must be logged in to access this page.'], JSON_THROW_ON_ERROR);
        exit();
    }

    // Whatever page was asked for, a visitor without a session gets the login page
    showPage(Login::class, $services);
    exit();

}


// CSRF protection: verify the token on all authenticated POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $panel->verifyCsrfToken()) {
    http_response_code(403);

    // For AJAX/API requests, return a plain error message instead of a redirect
    $page    = preg_replace('/[^0-9a-z_]/i', '', $_GET['page'] ?? '');
    $isAjax  = $page === 'api'
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        echo 'Invalid or expired security token. Please try again.';
        exit;
    }

    $panel->flash($panel->alert('Invalid or expired security token. Please try again.', 'error'));
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Logout user. POST only, so it runs after the CSRF gate: a plain link on another site can not log the admin out.
if (isset($_GET['logout']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $panel->destroyAuth();
    header("Location: index.php?page=login");
    exit;

}

// Safety feature, replaces anything but numbers or letters
$_GET['page'] = preg_replace('/[^0-9a-z_]/i', '', (( ! empty($_GET['page'])) ? $_GET['page'] : 'home'));

// A page is a class in panel/lib/Pages ("?page=ai" -> Pages\Ai), anything unknown shows the home page
$class = 'Pages\\'.ucfirst(strtolower($_GET['page']));

showPage(is_subclass_of($class, Page::class) ? $class : Home::class, $services);