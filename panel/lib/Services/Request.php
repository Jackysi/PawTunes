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

namespace Services;

/**
 * The current HTTP request. Pages read input through it instead of $_GET and $_POST, so what a page
 * consumes is visible in its code, and a field posted as the wrong type (name[]=x where a string is
 * expected) becomes an empty value instead of a TypeError further down.
 */
final class Request
{

    private array $query;

    private array $post;

    private array $files;

    private array $server;


    /**
     * @param  array  $query  $_GET
     * @param  array  $post  $_POST
     * @param  array  $files  $_FILES
     * @param  array  $server  $_SERVER
     */
    public function __construct(array $query, array $post, array $files = [], array $server = [])
    {
        $this->query  = $query;
        $this->post   = $post;
        $this->files  = $files;
        $this->server = $server;
    }


    /**
     * @return self The request PHP is handling right now
     */
    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }


    /**
     * @return bool
     */
    public function isPost(): bool
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }


    /**
     * @return string Address the request came from (the proxy's address behind a reverse proxy)
     */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }


    /**
     * Query string value as text
     *
     * @param  string  $key
     *
     * @return string '' when missing or not a string
     */
    public function query(string $key): string
    {
        return is_string($this->query[$key] ?? null) ? $this->query[$key] : '';
    }


    /**
     * Whether a query string parameter is present at all, for flags without a value such as "?add"
     *
     * @param  string  $key
     *
     * @return bool
     */
    public function hasQuery(string $key): bool
    {
        return isset($this->query[$key]);
    }


    /**
     * Posted field as text
     *
     * @param  string  $key
     *
     * @return string '' when missing or not a string
     */
    public function post(string $key): string
    {
        return is_string($this->post[$key] ?? null) ? $this->post[$key] : '';
    }


    /**
     * Posted field group, such as the text[...] inputs of one form
     *
     * @param  string  $key
     *
     * @return array [] when missing or not an array
     */
    public function postArray(string $key): array
    {
        return is_array($this->post[$key] ?? null) ? $this->post[$key] : [];
    }


    /**
     * Every posted field without the two that belong to the panel itself (the action name and the CSRF
     * token). For forms that are stored or shown again as a whole, such as the settings.
     *
     * @return array
     */
    public function postFields(): array
    {
        return array_diff_key($this->post, ['action' => true, '_token' => true]);
    }


    /**
     * Client file name of an uploaded file. A non-empty name means the browser sent a file; whether it
     * arrived intact is checked by Uploads::upload(), which reports upload errors (too large, ...).
     *
     * @param  string  $key  $_FILES field name
     *
     * @return string '' when no file was picked
     */
    public function uploadName(string $key): string
    {
        return is_string($this->files[$key]['name'] ?? null) ? $this->files[$key]['name'] : '';
    }

}
