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

namespace lib\Image;

/**
 * Recompresses a stored image with an external command line tool, when the server has one installed. Nothing
 * is configured: the first tool of the format's list found in PATH is used, and a server without any simply
 * keeps the files GD wrote.
 *
 * Two kinds of tools exist:
 *  - Re-encoders (guetzli, cwebp, avifenc) encode the pixels again with a better, slower encoder than GD's.
 *    They are lossy, so Image::save() first writes a near-lossless master for them to work from.
 *  - Repackers (jpegoptim, jpegtran, oxipng, optipng) store the same pixels in fewer bytes. Lossless.
 *
 * A tool never touches the stored file itself. It writes a temporary file, and that replaces the original only
 * when the tool succeeded, the result is a readable image and it is smaller. Whatever goes wrong, the image
 * that was there stays.
 */
final class Optimizer
{

    /**
     * Tools per format extension, best first. "input" says what the tool is given:
     *  - png:  {in} is a lossless PNG copy of the image, the tool encodes it into {out} (a re-encoder)
     *  - file: {in} is the stored file, the tool writes its result to {out}
     *  - copy: {out} is a copy of the stored file, which the tool rewrites in place
     *
     * "slow" marks a tool that takes seconds per image, see find().
     *
     * Arguments are passed as a list and never through a shell, so a file name cannot inject a command.
     */
    private const TOOLS
        = [
            'jpg'  => [
                // Slow (seconds per cover, a few hundred MB of memory) but smaller than GD at the same look
                ['bin' => 'guetzli', 'input' => 'png', 'slow' => true, 'timeout' => 300, 'args' => ['--quality', '85', '{in}', '{out}']],
                ['bin' => 'jpegoptim', 'input' => 'copy', 'args' => ['--strip-all', '--all-progressive', '--quiet', '{out}']],
                ['bin' => 'jpegtran', 'input' => 'file', 'args' => ['-copy', 'none', '-optimize', '-progressive', '-outfile', '{out}', '{in}']],
            ],
            'png'  => [
                ['bin' => 'oxipng', 'input' => 'copy', 'args' => ['-o', '2', '--strip', 'safe', '--quiet', '{out}']],
                ['bin' => 'optipng', 'input' => 'copy', 'args' => ['-o2', '-strip', 'all', '-quiet', '{out}']],
            ],
            'webp' => [
                // -m 6: the slowest and best compression method (GD uses the default, 4)
                ['bin' => 'cwebp', 'input' => 'png', 'args' => ['-quiet', '-q', '82', '-m', '6', '{in}', '-o', '{out}']],
            ],
            'avif' => [
                ['bin' => 'avifenc', 'input' => 'png', 'timeout' => 120, 'args' => ['-q', '60', '-s', '4', '{in}', '{out}']],
            ],
        ];

    /**
     * Seconds a tool may run unless it names its own limit
     */
    private const TIMEOUT = 30;

    /**
     * Found binaries by tool name, null = not installed. PATH is searched once per tool and request.
     *
     * @var array<string, string|null>
     */
    private static array $binaries = [];

    /**
     * Full path of the tool's executable
     */
    private string $binary;

    /**
     * The tool's entry in TOOLS
     */
    private array $tool;

    /**
     * Extension the result is stored under
     */
    private string $extension;


    private function __construct(string $binary, array $tool, string $extension)
    {
        $this->binary    = $binary;
        $this->tool      = $tool;
        $this->extension = $extension;
    }


    /**
     * The best installed tool for a format
     *
     * @param  string  $extension  Format::extension()
     * @param  bool  $slowTools  false skips the tools marked "slow": for a caller whose visitor may end up
     *                           waiting for the tool
     *
     * @return self|null  null: no tool installed, or this PHP may not start processes
     */
    public static function find(string $extension, bool $slowTools = false): ?self
    {
        // disable_functions removes the function, shared hosts often list proc_open there
        if ( ! function_exists('proc_open')) {
            return null;
        }

        foreach (self::TOOLS[$extension] ?? [] as $tool) {

            if ( ! $slowTools && ! empty($tool['slow'])) {
                continue;
            }

            $binary = self::locate($tool['bin']);
            if ($binary !== null) {
                return new self($binary, $tool, $extension);
            }

        }

        return null;
    }


    /**
     * true = the tool encodes the pixels again (lossy) and wants a high quality file to start from
     */
    public function reencodes(): bool
    {
        return $this->tool['input'] === 'png';
    }


    /**
     * Recompress a stored image
     *
     * @param  string  $file  Absolute path
     *
     * @return bool  true when the file was replaced by a smaller one
     */
    public function run(string $file): bool
    {
        if ( ! is_file($file)) {
            return false;
        }

        // Temporary names keep a real image extension at the end: several tools pick the format by it
        $unique = uniqid('', true);
        $input  = $file;
        $output = "{$file}.{$unique}.{$this->extension}";
        $png    = "{$file}.{$unique}.png";

        try {

            if ($this->tool['input'] === 'png') {

                if ( ! $this->writePng($file, $png)) {
                    return false;
                }

                $input = $png;

            } elseif ($this->tool['input'] === 'copy' && ! @copy($file, $output)) {
                return false;
            }

            $command = [$this->binary];
            foreach ($this->tool['args'] as $argument) {
                $command[] = str_replace(['{in}', '{out}'], [$input, $output], $argument);
            }

            if ( ! $this->execute($command, $this->tool['timeout'] ?? self::TIMEOUT)) {
                return false;
            }

            // Only a readable image that is really smaller may replace the original
            clearstatcache();
            if ( ! is_file($output) || @getimagesize($output) === false || filesize($output) >= filesize($file)) {
                return false;
            }

            return @rename($output, $file);

        } finally {

            // After a successful rename $output is gone already
            @unlink($output);
            @unlink($png);

        }
    }


    /**
     * Decode the stored image into a lossless PNG for a re-encoder
     */
    private function writePng(string $file, string $png): bool
    {
        $format = Formats::byContent($file);
        $image  = ($format !== null && $format->supported()) ? $format->read($file) : false;
        if ($image === false) {
            return false;
        }

        imagesavealpha($image, true);

        // Level 1: the file lives for seconds, a fast write matters more than its size
        return imagepng($image, $png, 1);
    }


    /**
     * Run a command and wait for it, at most $timeout seconds
     *
     * @param  string[]  $command  Executable and arguments
     * @param  int  $timeout
     *
     * @return bool  true when it exited with status 0 in time
     */
    private function execute(array $command, int $timeout): bool
    {
        // Output goes nowhere: a pipe nobody reads fills up and would block the tool forever
        $null    = (PHP_OS_FAMILY === 'Windows') ? 'NUL' : '/dev/null';
        $streams = [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']];

        $process = @proc_open($command, $streams, $pipes);
        if ( ! is_resource($process)) {
            return false;
        }

        $deadline = microtime(true) + $timeout;

        while (true) {

            // The exit code is only valid in the first status read after the process ended, hence one loop
            $status = proc_get_status($process);
            if ( ! $status['running']) {
                proc_close($process);

                return $status['exitcode'] === 0;
            }

            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                proc_close($process);

                return false;
            }

            usleep(50000);

        }
    }


    /**
     * Find an executable in PATH, without asking a shell
     *
     * @param  string  $name  e.g. "jpegoptim"
     *
     * @return string|null  Full path, null when it is not installed
     */
    private static function locate(string $name): ?string
    {
        if (array_key_exists($name, self::$binaries)) {
            return self::$binaries[$name];
        }

        $fileName = (PHP_OS_FAMILY === 'Windows') ? "{$name}.exe" : $name;

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {

            if ($directory === '') {
                continue;
            }

            // @: open_basedir answers a path outside its list with a warning
            $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$fileName;
            if (@is_file($path) && @is_executable($path)) {
                return self::$binaries[$name] = $path;
            }

        }

        return self::$binaries[$name] = null;
    }

}
