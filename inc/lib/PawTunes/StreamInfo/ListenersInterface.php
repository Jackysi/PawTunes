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

namespace lib\PawTunes\StreamInfo;

/**
 * Optional capability of a track info provider: listing who is connected to the stream.
 * Implemented by the providers that talk to an admin API (AzuraCast, Icecast, Shoutcast).
 * Addresses must be masked by the provider (lib\PawTunes\Listeners does it), raw IPs never leave it.
 */
interface ListenersInterface
{

    /**
     * Does this channel carry everything the listener list needs (credentials, mount, station...)?
     * A configuration check only, no network access: it decides whether the player offers the page.
     *
     * @return bool
     */
    public function supportsListeners(): bool;


    /**
     * Current listeners, newest fetch, never cached by the provider (the handler caches).
     *
     * @return array  ['total' => int, 'listeners' => [['ip' => masked, 'device' => label, 'connected' => seconds], ...]]
     * @throws \lib\PawException
     */
    public function getListeners(): array;

}
