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

namespace API;

use JsonException;
use lib\PawTunes;
use Panel;

abstract class Base
{

    protected Pawtunes $pawtunes;

    protected Panel $panel;

    protected string $sseName = "";


    public function __construct(
        $pawtunes,
        $panel
    ) {
        $this->pawtunes = $pawtunes;
        $this->panel    = $panel;
    }


    /**
     * Escape text for the SSE/HTML output. The panel appends SSE data as HTML, so anything that
     * comes from outside (upstream responses, file names, error messages) must go through here.
     *
     * @param  mixed  $text
     *
     * @return string
     */
    protected function escape($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }


    /**
     * @param      $content
     * @param  null  $name
     *
     * @return void
     */
    public function sendSSE($content, $name = null): void
    {
        $content = base64_encode($content);

        // Named events
        if ( ! empty($this->sseName) || $name !== null) {
            $name = $name ?? $this->sseName;
            echo "event: {$name}\n";
        }

        echo "data: {$content}\n\n";
        flush();
    }


    /**
     * @param $name
     *
     * @return void
     */
    public function closeSSE($name = null): void
    {
        if ( ! empty($this->sseName) || $name !== null) {
            $name = $name ?? $this->sseName;
            echo "event: {$name}\n";
        }

        echo "data: close\n\n";
        flush();
        exit;
    }


    /**
     * @param $data
     *
     * @return void
     * @throws JsonException
     */
    public function sendJSON($data): void
    {
        header("Content-Type: application/json");
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

}