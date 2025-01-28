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
 * @var \lib\PawTunes $pawtunes
 */

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

// Writable or not?
if ( ! is_writable('data/updates')) {

    $message = $panel->alert('Directory <b>/data/updates/</b> is not writable! This means that player will not be able to download update files!
		<br>You can fix this issue by setting <b>chmod</b> of folder <b>/data/updates/</b> to <b>755</b>.');

}

$panel->view(
    "updates",
    [
        'message' => $message ?? null,
    ]
);