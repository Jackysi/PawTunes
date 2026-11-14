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

use Panel;

/**
 * Logs page: the view loads and deletes the log files through the panel API
 */
final class Logs extends Page
{

    private Panel $panel;


    public function __construct(Panel $panel)
    {
        $this->panel = $panel;
    }


    protected function index(): void
    {
        $this->panel->view('logs');

    }

}
