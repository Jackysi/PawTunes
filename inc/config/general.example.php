<?php
return array(
    'tplOptions'           => array(),
    'artwork_sources'      =>
        array(
            'itunes'   =>
                array(
                    'state' => 'enabled',
                    'index' => 25,
                ),
            // Keyless like iTunes, finds many independent and electronic releases iTunes does not have
            'deezer'   =>
                array(
                    'state' => 'enabled',
                    'index' => 26,
                ),
            'spotify'  =>
                array(
                    'index'   => 26,
                    'api_key' => '',
                ),
            'fanarttv' =>
                array(
                    'index'   => 27,
                    'api_key' => '',
                ),
            'lastfm'   =>
                array(
                    'index'   => 28,
                    'api_key' => '',
                ),
            'custom'   =>
                array(
                    'index'   => 29,
                    'api_url' => '',
                ),
            'applemusic' =>
                array(
                    'index'       => 30,
                    'api_key'     => '',
                    'private_key' => '',
                    'storefront'  => 'us',
                ),
        ),
    'title'                => 'PawTunes',
    'description'          => 'Discover PawTunes, the ultimate internet radio player with purrfect visuals, customizable templates, and clean code. Built for pros, loved by cats!',
    'site_title'           => 'PawTunes Radio Player',
    'google_analytics'     => '',
    'share_image_override' => '',
    'disable_index'        => false,
    'default_lang'         => 'en.php',
    'multi_lang'           => true,            // Pick the locale from the browser's language (?language= always applies)
    'autoplay'             => true,
    'cast'                 => true,            // Chromecast (loads Google's Cast SDK in Chromium browsers) and AirPlay buttons
    'debugging'            => 'log-only',
    'default_channel'      => '0',
    'default_volume'       => '75',
    'template'             => 'aurora',
    'artist_default'       => 'Various Artists',
    'title_default'        => 'Unknown Track',
    'dynamic_title'        => true,
    'artist_maxlength'     => '24',
    'title_maxlength'      => '28',
    'stats_refresh'        => '10',
    'api'                  => false,
    'track_regex'          => '(?P<artist>[^-]*)[ ]?-[ ]?(?P<title>.*)',
    'history'              => true,
    'cache_images'         => true,
    'cache_api_requests'   => true,
    'clean_artwork_search' => true,
    'serve_via_web'        => false,
    'artist_images_only'   => true,
    'artwork_lazy_loading' => true,
    'images_size'          => '280',
    'admin_username'       => 'admin',
    'admin_password'       => '$2y$10$TI7WVV8ZiIktYvjYWzmCseW1/GqQ4oUq/YiRv7yXapHRmXNwz57bm',
    'development'          => false,
    'ai'                   =>
        array(
            // AI Assistant (panel > AI): track title cleanup and panel tools. Off by default.
            'text' =>
                array(
                    'enabled'        => false,
                    'provider'       => 'anthropic', // anthropic, openai (also OpenAI-compatible servers via base_url)
                    'api_key'        => '',
                    'model'          => '',          // blank = provider default
                    'base_url'       => '',          // e.g. http://localhost:11434/v1 for a local Ollama
                    'clean_metadata' => true,
                ),
            // AI Artwork (panel > AI): a picture found by web search, or drawn art, for artists no source has artwork for.
            // Off by default, paid per attempt.
            'images' =>
                array(
                    'enabled'     => false,
                    'provider'    => 'openai', // openai (also OpenAI-compatible servers via base_url), gemini, fal
                    'api_key'     => '',
                    'model'       => '',       // blank = provider default
                    'base_url'    => '',
                    'daily_limit' => 20,       // artists per day (one search and one image each), data/ai-artwork/.ledger.json
                    'search'      => true,     // web search for a real picture first (AI Assistant on Anthropic Claude)
                    'verify'      => false,    // vision check of drawn images through AI Assistant (one more paid request)
                ),
        ),
    'cache'                =>
        array(
            'path'    => './data/cache',    // Disk cache directory (also used as the fallback when another mode fails)
            'host'    => '127.0.0.1:11211', // Redis/Memcached "host:port" or a unix socket path (port defaults: redis 6379, memcached 11211)
            'ext'     => '.cache',          // Disk cache file extension
            'mode'    => 'disk',            // Modes: disk, apcu, redis, memcache, memcached ("apc" is read as apcu)
            'extra'   => [],                // Redis: 'auth' => 'password' plus Redis::OPT_* => value; memcached: Memcached::OPT_* => value
            'prefix'  => '',                // Key prefix, empty = derived from the install path (keeps installs on a shared server apart)
        ),
);