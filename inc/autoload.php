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
 * Autoload Functions and libraries on-demand so we only load what we actually need
 */
spl_autoload_register(static function (string $className) {

    # 1 with namespaces
    # 2 without
    $fileName = [
        __DIR__.'/'.str_replace('\\', '/', $className.'.php'),
        __DIR__.'/'.$className.'.php',
    ];

    foreach ($fileName as $file) {
        if (is_file($file)) {

            require $file;
            break;

        }
    }

});