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

namespace lib\AI;

/**
 * Hard daily cap on paid AI calls, kept in a small JSON file under an exclusive lock.
 *
 * A file and not the cache on purpose: Flush Cache, an update or a dead cache backend cannot reset or bypass it.
 * Every failure (folder not writable, lock not available) answers "no budget": the cap fails closed.
 */
final class DailyBudget
{

    private string $file;


    /**
     * @param  string  $file  Ledger path, e.g. ArtworkGenerator::DIRECTORY.'/.ledger.json'
     */
    public function __construct(string $file)
    {
        $this->file = $file;
    }


    /**
     * Take one call from today's budget
     *
     * @param  int  $limit  Calls per day
     *
     * @return bool False when the budget is used up or the ledger cannot be read and written
     */
    public function take(int $limit): bool
    {
        $directory = dirname($this->file);
        if ($limit < 1 || ( ! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory))) {
            return false;
        }

        $handle = @fopen($this->file, 'c+');
        if ($handle === false) {
            return false;
        }

        try {

            if ( ! flock($handle, LOCK_EX)) {
                return false;
            }

            $ledger = self::today(json_decode((string) stream_get_contents($handle), true));
            if ($ledger['count'] >= $limit) {
                return false;
            }

            $ledger['count']++;
            $ledger['total']++;

            return ftruncate($handle, 0) && rewind($handle) && fwrite($handle, json_encode($ledger)) !== false;

        } finally {

            flock($handle, LOCK_UN);
            fclose($handle);

        }
    }


    /**
     * @return array ['date' => 'Y-m-d', 'count' => int (today), 'total' => int (all time)]
     */
    public function usage(): array
    {
        return self::today(json_decode((string) @file_get_contents($this->file), true));
    }


    /**
     * The ledger as of today: a new day starts at zero and keeps the all-time total
     *
     * @param  mixed  $ledger
     *
     * @return array
     */
    private static function today($ledger): array
    {
        if ( ! is_array($ledger) || ($ledger['date'] ?? '') !== date('Y-m-d')) {
            return ['date' => date('Y-m-d'), 'count' => 0, 'total' => (int) ($ledger['total'] ?? 0)];
        }

        return ['date' => $ledger['date'], 'count' => (int) $ledger['count'], 'total' => (int) $ledger['total']];
    }

}
