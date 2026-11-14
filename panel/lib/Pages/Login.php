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
use Services\Request;

/**
 * Login page. panel/index.php shows it for every request without a session, whatever page was asked for.
 * It is the only page whose POST runs without a CSRF check (there is no session to hold a token yet).
 */
final class Login extends Page
{

    /**
     * Failed logins per address before the address is locked out, and for how long (seconds)
     */
    private const MAX_ATTEMPTS = 5;
    private const LOCK_TIME    = 1800;

    private PawTunes $pawtunes;

    private Panel $panel;

    private Request $request;

    /**
     * Error HTML shown above the form
     */
    private ?string $error = null;


    public function __construct(PawTunes $pawtunes, Panel $panel, Request $request)
    {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
        $this->request  = $request;
    }


    protected function index(): void
    {
        if ($this->panel->isAuthorized()) {
            header('Location: index.php?page=home');
            exit;
        }

        $this->panel->view('login', ['error' => $this->error]);

    }


    /**
     * Check the credentials and start the session
     */
    protected function postLogin(): void
    {
        if ($this->panel->isAuthorized()) {
            return;
        }

        // Failed attempts of every address live in one cache entry
        $ip       = $this->request->ip();
        $attempts = $this->pawtunes->cache->get('auth') ?: [];
        $failed   = $attempts[$ip] ?? 0;

        if ($failed >= self::MAX_ATTEMPTS) {

            $this->pawtunes->writeLog('auth.bans', "User with IP \"{$ip}\" has failed to authorize for more than 3 times!", './../data/logs/');
            $this->error = '<div class="text-red">Too many invalid login attempts, please try again in approximately 30 minutes!</div><div class="divider"></div>';

            return;
        }

        $validUser     = $this->request->post('username') === $this->pawtunes->config('admin_username');
        $validPassword = password_verify($this->request->post('password'), (string) $this->pawtunes->config('admin_password'));

        if ( ! $validUser || ! $validPassword) {

            $attempts[$ip] = $failed + 1;
            $this->pawtunes->cache->set('auth', $attempts, self::LOCK_TIME);
            $this->error = '<div class="text-red">Invalid username or password, login failed!</div><div class="divider"></div>';

            return;
        }

        // A new session ID, so an ID an attacker planted before the login is worthless (session fixation)
        session_regenerate_id(true);
        $this->panel->setOption('auth', $this->panel->createAuthToken());

        $attempts[$ip] = 0;
        $this->pawtunes->cache->set('auth', $attempts, self::LOCK_TIME);

        header('Location: index.php?page=home');
        exit;

    }

}
