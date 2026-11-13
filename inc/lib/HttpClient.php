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

class HttpClient
{

    /**
     * @var string
     */
    protected string $caBundlePath;

    /**
     * @var callable|null
     */
    protected $logger;

    /**
     * HTTP status code of the last request, null when it failed at the transport level (timeout, DNS, TLS)
     *
     * @var int|null
     */
    protected ?int $lastStatus = null;


    /**
     * @param  string  $caBundlePath  Path to CA certificate bundle
     * @param  callable|null  $logger  Callback accepting (string $message) for error logging
     */
    public function __construct(string $caBundlePath, ?callable $logger = null)
    {
        $this->caBundlePath = $caBundlePath;
        $this->logger       = $logger;

    }


    /**
     * CURL wrap function to make HTTP requests
     *
     * @param  string  $url
     * @param  array|null  $post  POST data as NAME=>VALUE array
     * @param  string|null  $auth  HTTP auth in format (username:password)
     * @param  bool|callable  $progress  Progress callback
     * @param  int  $timeout  Request timeout in seconds
     * @param  string|int  $error  Error message returned by reference
     * @param  array  $options  Custom CURL options (overrides defaults)
     * @param  boolean  $log  Whether to log errors
     *
     * @return bool|string
     */
    public function get(
        string $url,
        ?array $post = null,
        ?string $auth = null,
        $progress = false,
        int $timeout = 5,
        &$error = false,
        array $options = [],
        bool $log = true
    ) {

        // Create CURL Object
        $CURL = curl_init();

        // Defaults
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (PawTunes) AppleWebKit/537.36 (KHTML, like Gecko)',
            CURLOPT_FOLLOWLOCATION => ! ini_get('open_basedir'),
            CURLOPT_CONNECTTIMEOUT => (($timeout < 1 && $timeout !== 0) ? 5 : $timeout),
            CURLOPT_CAINFO         => $this->caBundlePath,
        ];

        // Post data to the URL (expects array)
        if ($post !== null) {
            $opts += [
                CURLOPT_POSTFIELDS    => http_build_query($post, '', '&'),
                CURLOPT_POST          => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE  => true,
            ];
        }

        // Use HTTP Authorization
        if ( ! empty($auth)) {
            $opts += [CURLOPT_USERPWD => $auth];
        }

        // Call anonymous $progress_function function
        if ( ! is_bool($progress)) {
            $opts += [
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_PROGRESSFUNCTION => function ($resource, $downloaded, $download, $upload_total, $uploaded) use ($progress) {

                    // Normalize values for compatibility with older libcurl versions
                    if (is_numeric($resource)) {

                        $normalized_download_total = $resource;
                        $normalized_downloaded     = $downloaded;
                        $normalized_upload_total   = $upload_total;
                        $normalized_uploaded       = $uploaded;

                    } else {

                        $normalized_download_total = $downloaded;
                        $normalized_downloaded     = $download;
                        $normalized_upload_total   = $upload_total;
                        $normalized_uploaded       = $uploaded;

                    }

                    // Call the progress function with normalized values
                    return $progress($normalized_download_total, $normalized_downloaded, $normalized_upload_total, $normalized_uploaded);

                },
            ];
        }

        $opts = array_replace($opts, $options);

        // Before executing CURL pass options array to the session
        curl_setopt_array($CURL, $opts);

        // Finally execute CURL
        $data = curl_exec($CURL);

        // Must be read before curl_close()
        $this->lastStatus = ($data === false) ? null : curl_getinfo($CURL, CURLINFO_RESPONSE_CODE);

        // Parse ERROR
        if (curl_error($CURL)) {

            // This must be referenced in-memory variable
            $error = curl_error($CURL);

            // Log via callback if provided
            if ($log && $this->logger) {
                ($this->logger)("CURL Request \"".self::redactURL($url)."\" failed! LOG: ".curl_error($CURL));
            }

        }

        // PHP < 8.0: resource needs explicit closing
        if (is_resource($CURL)) {
            curl_close($CURL);
        }

        return $data;

    }


    /**
     * HTTP status code of the last request. get() returns the body for ANY status,
     * so callers that must tell "no results" apart from "rate limited" check this.
     *
     * @return int|null
     */
    public function getLastStatus(): ?int
    {
        return $this->lastStatus;
    }


    /**
     * Forget the last status, so a caller can tell whether its own code made a request at all
     *
     * @return void
     */
    public function resetLastStatus(): void
    {
        $this->lastStatus = null;
    }


    /**
     * URL safe for the log: some stream servers take credentials in the URL (Shoutcast's admin.cgi?pass=,
     * http://user:pass@host), and logs are files that end up in bug reports and backups.
     *
     * @param  string  $url
     *
     * @return string
     */
    public static function redactURL(string $url): string
    {
        $url = preg_replace('~^([a-z][a-z0-9+.-]*://)[^/@\s]+@~i', '$1***@', $url);

        return preg_replace('~([?&](?:pass|password|passwd|pwd|auth|key|api_key|apikey|token|access_token|secret|sig)=)[^&#]*~i', '$1***', $url);
    }

}
