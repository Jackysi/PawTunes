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

namespace lib\PawTunes\Artwork;

use lib\PawException;
use lib\PawTunes;

/**
 * Apple Music API artwork source.
 *
 * Same catalog and same image CDN (mzstatic.com) as the public iTunes Search API,
 * but requests are authenticated with a developer token (Apple Developer Program),
 * so rate limits apply per token instead of per IP.
 *
 * Config (artwork_sources.applemusic):
 *   api_key     => "TEAM_ID:KEY_ID"
 *   private_key => PEM contents of the .p8 key, or "file:///absolute/path/AuthKey_XXXX.p8"
 *   storefront  => two-letter store code, default "us"
 */
class AppleMusic extends Artwork
{

    private const SEARCH_URL = 'https://api.music.apple.com/v1/catalog/%s/search?types=songs&limit=1&term=%s';

    /**
     * Apple allows up to 6 months. 30 days keeps a leaked token short-lived.
     */
    private const TOKEN_LIFETIME = 30 * 86400;

    private const TOKEN_CACHE_KEY = 'applemusic_token';

    private string $teamId;

    private string $keyId;

    private string $privateKey;

    private string $storefront;


    public function __construct(PawTunes $pawtunes, $override = null)
    {
        parent::__construct($pawtunes, $override);

        $config = $this->pawtunes->config('artwork_sources')['applemusic'] ?? [];
        [$teamId, $keyId] = array_pad(explode(':', $config['api_key'] ?? '', 2), 2, '');

        if ($teamId === '' || $keyId === '' || empty($config['private_key'])) {
            throw new PawException('Missing Team ID, Key ID or private key for the Apple Music API!');
        }

        $this->teamId     = $teamId;
        $this->keyId      = $keyId;
        $this->privateKey = $config['private_key'];
        $this->storefront = $config['storefront'] ?? 'us';
    }


    /**
     * @param  string  $artist
     * @param  string  $title
     *
     * @return string|null
     */
    protected function getArtworkURL($artist, $title = '')
    {
        // Apple expects words joined with "+", urlencode() does exactly that (and encodes a literal "+" as %2B)
        $url = sprintf(
            self::SEARCH_URL,
            rawurlencode($this->storefront),
            urlencode(trim("{$artist} {$title}"))
        );

        $response = $this->pawtunes->get(
            $url,
            null,
            null,
            false,
            10,
            $error,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.$this->getDeveloperToken(),
                ],
            ]
        );

        $data    = json_decode((string) $response, true);
        $artwork = $data['results']['songs']['data'][0]['attributes']['artwork']['url'] ?? null;
        if ($artwork === null) {
            return null;
        }

        // Artwork URL is a template, e.g. https://is1-ssl.mzstatic.com/image/thumb/.../{w}x{h}bb.jpg
        return str_replace(['{w}', '{h}'], [$this->imageWidth, $this->imageHeight], $artwork);
    }


    /**
     * Build (or reuse from cache) the ES256-signed JWT developer token.
     *
     * @return string
     */
    private function getDeveloperToken(): string
    {
        // Key the token by its credentials, so editing them in the panel signs a new token right away
        // instead of reusing one signed with the old (possibly wrong) Team ID or Key ID
        $cacheKey = self::TOKEN_CACHE_KEY.'.'.md5("{$this->teamId}:{$this->keyId}:{$this->privateKey}");

        if ($token = $this->pawtunes->cache->get($cacheKey)) {
            return $token;
        }

        $now     = time();
        $header  = $this->base64Url(json_encode(['alg' => 'ES256', 'kid' => $this->keyId]));
        $payload = $this->base64Url(json_encode(['iss' => $this->teamId, 'iat' => $now, 'exp' => $now + self::TOKEN_LIFETIME]));

        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false || ! openssl_sign("{$header}.{$payload}", $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new PawException('Unable to sign the Apple Music developer token, check the private key!');
        }

        $token = "{$header}.{$payload}.".$this->base64Url($this->derToRaw($derSignature));

        // Expire one day early so we never send a token that is about to expire
        $this->pawtunes->cache->set($cacheKey, $token, self::TOKEN_LIFETIME - 86400);

        return $token;
    }


    /**
     * openssl_sign() returns an ASN.1 DER ECDSA signature: SEQUENCE { INTEGER r, INTEGER s }.
     * JWT ES256 requires raw r||s, each exactly 32 bytes (RFC 7518, section 3.4).
     * DER integers may carry a leading 0x00 (sign bit) or be shorter than 32 bytes,
     * so strip leading zeros and left-pad back to 32.
     *
     * @param  string  $der
     *
     * @return string
     */
    private function derToRaw(string $der): string
    {
        $offset = 2; // Skip SEQUENCE tag + length (P-256 signatures are max 72 bytes, so short-form length)
        $raw    = '';

        for ($i = 0; $i < 2; $i++) {

            $length  = ord($der[$offset + 1]);            // INTEGER length byte
            $integer = substr($der, $offset + 2, $length); // INTEGER value
            $raw     .= str_pad(ltrim($integer, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset  += 2 + $length;
            
        }

        return $raw;
    }


    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

}