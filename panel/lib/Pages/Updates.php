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
 * Updates page: the update itself runs through the panel API (API\Updates), this only renders the page
 */
final class Updates extends Page
{

    private Panel $panel;


    public function __construct(Panel $panel)
    {
        $this->panel = $panel;
    }


    protected function index(): void
    {
        $message = null;
        if ( ! is_writable('data/updates')) {

            $message = $this->panel->alert('Directory <b>/data/updates/</b> is not writable! This means that player will not be able to download update files!
		<br>You can fix this issue by setting <b>chmod</b> of folder <b>/data/updates/</b> to <b>755</b>.');

        }

        $this->panel->view('updates', ['message' => $message]);

    }

}
