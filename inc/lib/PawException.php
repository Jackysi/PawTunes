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

namespace lib;

use Exception;
use RuntimeException;

final class PawException extends RuntimeException
{
    public function __construct($message, $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

    }

}