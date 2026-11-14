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
 * Writes the PHP array files under inc/: config/general, config/channels and the locale/<iso> files.
 */
final class ConfigStore
{

    /**
     * Write an array as "inc/<file>.php". Not atomic: a crash mid-write leaves a broken file.
     *
     * @param  string  $file  Path and file name below inc/, without the extension (e.g. "config/general")
     * @param  array  $contents
     *
     * @return bool
     */
    public function save(string $file, array $contents): bool
    {
        // Whole forms are stored as they were posted, the CSRF token is not part of the configuration
        unset($contents['_token']);

        $contents = $this->stringsToBool($contents);
        $stored   = file_put_contents("inc/{$file}.php", "<?php \nreturn ".var_export($contents, true).";");

        // The next request must read the new file, not a cached copy of the old one
        if ($stored) {

            clearstatcache(true);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate("inc/{$file}.php", true);
            }

        }

        return (bool) $stored;

    }


    /**
     * Checkboxes and selects post the strings 'true' and 'false', the configuration holds real booleans
     *
     * @param  array  $array
     *
     * @return array
     */
    private function stringsToBool(array $array): array
    {
        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $array[$key] = $this->stringsToBool($value);
                continue;
            }

            if ($value === 'true' || $value === 'false') {
                $array[$key] = ($value === 'true');
            }

        }

        return $array;

    }

}
