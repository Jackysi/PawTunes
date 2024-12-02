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

    class PawTunes extends Helpers {

        /**
         * @var array
         */
        protected array $channels = [];

        /**
         * @var array
         */
        protected array $settings;

        /**
         * @var \lib\Cache
         */
        public Cache $cache;

        /**
         * List of allowed extensions
         *
         * @var string[]
         */
        public array $artworkExtensions = [ 'jpg', 'jpeg', 'png', 'svg', 'webp' ];

        /**
         * @var string
         */
        protected string $artworkPath = './data/images';

        /**
         * Reflects available artwork methods
         *
         * @var array|string[]
         */
        protected array $artworkMethods = [
            'itunes'   => 'iTunes',
            'fanarttv' => 'FanArtTV',
            'lastfm'   => 'LastFM',
            'spotify'  => 'Spotify',
            'custom'   => 'Custom',
        ];

        /**
         * @var string|null
         */
        protected ?string $foundDefaultArtwork = null;

        /**
         * @var string
         */
        protected string $currentDir;

        /**
         * @var string
         */
        public string $prefix;


        /**
         * @param string $settingsFile
         * @param string $channelsFile
         *
         * @return void
         */
        public function __construct(
            string $settingsFile = 'inc/config/general.php',
            string $channelsFile = 'inc/config/channels.php'
        ) {

            $this->currentDir = __DIR__;
            $this->prefix = substr( base64_encode( $this->currentDir ), 0, 8 ) . '_';
            $this->settings = require( $settingsFile );

            // May not exist.
            if ( is_file( $channelsFile ) ) {
                $this->channels = require( $channelsFile );
            }

            // If DISK cache and path is set, do realpath as we need full path for cache to work
            if ( empty( $this->settings[ 'cache' ][ 'mode' ] ) || $this->settings[ 'cache' ][ 'mode' ] === 'disk' ) {
                if ( !empty( $this->settings[ 'cache' ][ 'path' ] ) ) {
                    $cachePath = realpath( $this->settings[ 'cache' ][ 'path' ] );
                }
            }

            $this->cache = new Cache( [ 'prefix' => $this->prefix, 'path' => $cachePath ?? null ] + $this->settings[ 'cache' ] );

        }


        public function getChannels() {

            return $this->channels;

        }


        public function getConfigAll() {

            return $this->settings;

        }


        public function setConfigAll( $settings ) {

            return $this->settings = $settings;

        }


        public function config( $key ) {

            return $this->settings[ $key ] ?? null;

        }


        public function getCache(): Cache {
            return $this->cache;
        }


        public function setCache( Cache $cache ): Cache {

            return $this->cache = $cache;

        }


        public function setConfig( $key, $value ) {

            return $this->settings[ $key ] = $value;

        }


        /**
         * @throws \Exception
         */
        public function outputBufferHandler( $buffer ) {

            // Array with replacement matching (regex)
            $regex = [
                ## REGEX					  ## REPLACE WITH
                "/<!--.*?-->|\t/s" => "",
                //"/\>([\s\t]+)?([ ]{2,}+)?\</s" => "><",
            ];

            // Replace tabs, empty spaces etc etc...
            $html_out = preg_replace( array_keys( $regex ), $regex, $buffer );

            // Optimize <style> tags
            $html_out = preg_replace_callback( '#<style(.*?)>(.*?)</style>#is', static function( $m ) {

                // Minify the css
                $css = $m[ 2 ];
                $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );

                $css = str_replace( [ "\r\n", "\r", "\n", "\t", '  ', '    ', '     ' ], '', $css );

                $css = preg_replace( [ '(( )+{)', '({( )+)' ], '{', $css );
                $css = preg_replace( [ '(( )+})', '(}( )+)', '(;( )*})' ], '}', $css );
                $css = preg_replace( [ '(;( )+)', '(( )+;)' ], ';', $css );

                return '<style>' . $css . '</style>';

            },                                 $html_out );


            // Optimize <script> tags
            return preg_replace_callback( '#<script(.*?)>(.*?)</script>#is', static function( $m ) {

                // Minify the js
                $js = $m[ 2 ];
                $js = preg_replace( '/\/\*(?:[^*]|\*+[^*\/])*\*+\/|(?<!:|\|\')\/\/.*/', '', $js );
                $js = str_replace( [ "\r\n", "\r", "\n", "\t", '  ', '    ', '     ' ], '', $js );
                return "<script{$m[1]}>" . $js . "</script>";

            },                            $html_out );

        }


        private function getLanguage() {

            $lang = ( ( empty( $_GET[ 'language' ] ) ) ? strtolower( substr( $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ] ?? '', 0, 2 ) ) : $_GET[ 'language' ] );
            if ( file_exists( "{$this->currentDir}/.././locale/{$lang}.php" ) ) { // Load if language is found
                return require( "{$this->currentDir}/.././locale/{$lang}.php" );
            }

            if ( $this->config( 'multi_lang' ) || $this->config( 'multi_lang' ) !== true ) {
                return require( "{$this->currentDir}/.././locale/{$this->config(  'default_lang' )}" );
            }

            return require( "{$this->currentDir}/.././locale/{$this->config(  'default_lang' )}" );

        }


        /**
         * @throws \JsonException
         */
        private function generateConfigJSON(): array {

            $channels = [];
            if ( count( $this->channels ) >= 1 ) {
                foreach ( $this->channels as $channel ) {

                    $chn = [
                        'name'    => $channel[ 'name' ],
                        'logo'    => $channel[ 'logo' ] ?? null,
                        'skin'    => isset( $channel[ 'skin' ] ) && is_file( "templates/{$this->settings['template']}/{$channel['skin']}" ) ? $channel[ 'skin' ] : null,
                        'streams' => $channel[ 'streams' ],
                    ];

                    // Check if we need to use websocket
                    if ( $channel[ 'stats' ][ 'method' ] === 'azuracast' && !empty( $channel[ 'stats' ][ 'url' ] ) ) {
                        $statsURL = parse_url( $channel[ 'stats' ][ 'url' ] );
                        $chn[ 'ws' ][ 'method' ] = 'azuracast';
                        $chn[ 'ws' ][ 'station' ] = $channel[ 'stats' ][ 'station' ];
                        $chn[ 'ws' ][ 'url' ] = ( $statsURL[ 'scheme' ] === 'wss' ) ? $channel[ 'stats' ][ 'url' ] : false;
                        $chn[ 'ws' ][ 'history' ] = $channel[ 'stats' ][ 'azura-history' ];
                        $chn[ 'ws' ][ 'useRemoteCovers' ] = $channel[ 'stats' ][ 'use-cover' ];
                    }

                    $channels[] = $chn;
                }
            }

            return [
                'channels'      => $channels,
                'analytics'     => ( !empty( $this->config( 'google_analytics' ) ) ? $this->config( 'google_analytics' ) : false ),
                'defaults'      => [
                    'channel'        => $this->strToUTF8( $this->config( 'default_channel' ) ),
                    'default_volume' => ( ( $this->config( 'default_volume' ) >= 1 && $this->config( 'default_volume' ) <= 100 ) ? (int) $this->config( 'default_volume' ) : 50 ),
                    'autoplay'       => ( isset( $_GET[ 'autoplay' ] ) && $_GET[ 'autoplay' ] === 'false' ) ? false : $this->config( 'autoplay' ),
                ],
                'dynamicTitle'  => $this->config( 'dynamic_title' ) ?? false,
                'prefix'        => $this->prefix,
                'history'       => $this->config( 'history' ),
                'historyMaxLen' => $this->config( 'historyLength' ) ?? 20,
                'language'      => $this->getLanguage(),
                'refreshRate'   => ( is_numeric( $this->config( 'stats_refresh' ) ) && $this->config( 'stats_refresh' ) >= 3 ) ? (int) $this->config( 'stats_refresh' ) : 15,
                'template'      => $this->config( 'template' ),
                'tpl'           => $this->getAdvancedTemplateOptions( $this->config( 'template' ) ),
                'title'         => $this->strToUTF8( $this->config( 'title' ) ),
                'trackInfo'     => [
                    'artistMaxLen'     => $this->config( 'artist_maxlength' ),
                    'titleMaxLen'      => $this->config( 'title_maxlength' ),
                    'lazyLoadArtworks' => $this->config( 'artwork_lazy_loading' ),
                    'default'          => [
                        'artist'  => $this->strToUTF8( $this->config( 'artist_default' ) ),
                        'title'   => $this->strToUTF8( $this->config( 'title_default' ) ),
                        'artwork' => $this->defaultArtwork(),
                    ],
                ],
            ];

        }


        /**
         * @throws \JsonException
         */
        public function getTemplateEngineOpts(): array {

            ## Handle URL to the player generation
            $this->settings[ 'host' ] = ( ( isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] === 'on' ) ? 'https://' : 'http://' ) . $_SERVER[ 'HTTP_HOST' ] ?? $_SERVER[ 'SERVER_NAME' ];
            $this->settings[ 'url' ] = "{$this->settings['host']}{$_SERVER['REQUEST_URI']}";

            ## Facebook share image
            if ( empty( $this->settings[ 'share_image_override' ] ) ) {
                $facebookShare = $this->settings[ 'host' ] . dirname( $_SERVER[ 'PHP_SELF' ] ) . '/' . $this->defaultArtwork();
            }

            // Expose config keys:
            $pass = [];
            $configKeys = [ 'autoplay', 'site_title', 'title', 'description', 'google_analytics', 'template', 'artist_default', 'title_default' ];
            foreach ( $configKeys as $key ) {
                $pass[ $key ] = $this->config( $key );
            }

            $json = $this->generateConfigJSON();
            $tpl = [ 'tpl' => $this->arrayKeysCaseToSnakeCase( $json[ 'tpl' ] ) ];

            // Others
            $opts = [
                'url'             => $this->config( 'url' ),
                'indexing'        => ( $this->config( 'disable_index' ) ? 'NOINDEX, NOFOLLOW' : 'INDEX, FOLLOW' ),
                'default_artwork' => $json[ 'trackInfo' ][ 'default' ][ 'artwork' ],
                'og_image'        => $facebookShare ?? $this->settings[ 'share_image_override' ],
                'og_site_title'   => ( ( !empty( $this->config( 'site_title' ) ) ) ? '<meta property="og:site_name" content="' . $this->config( 'site_title' ) . '">' : ' ' ),
                'timestamp'       => time(),
                'json_settings'   => json_encode( $json, JSON_THROW_ON_ERROR ),
            ];

            return array_merge( $opts, $pass, $this->getLanguage(), $tpl );

        }


        /**
         * Returns sorted array of artwork sources
         *
         * @return array
         */
        protected function getSortedArtworkSourcesList(): array {

            $list = [];
            foreach ( $this->config( 'artwork_sources' ) as $key => $value ) {
                if ( isset( $value[ 'state' ] ) && $value[ 'state' ] === 'enabled' ) {
                    $list[] = [ 'method' => $key ] + $value;
                }
            }

            uasort( $list, static function( $a, $b ) {
                if ( isset( $a[ 'index' ], $b[ 'index' ] ) ) {
                    return $a[ 'index' ] <=> $b[ 'index' ];
                }
                return isset( $a[ 'index' ] ) ? -1 : 1;
            } );

            return $list;

        }


        /**
         * Transforms track name to a simplified string (Used for Artworks)
         *
         * @param $string
         *
         * @return string
         */
        public function parseTrack( $string ): string {

            // Replace some known characters/strings with text
            $string = str_replace(
                [ '&', 'ft.' ],
                [ 'and', 'feat' ],
                empty( $string ) ? '' : $string
            );

            // Rep
            $rep_arr = [
                '/[^a-z0-9\p{L}\.]+/iu' => '.',    // Replace all non-standard strings with dot
                '/[\.]{1,}/'            => '.',    // Replace multiple dots in same string
            ];

            // Replace bad characters
            $string = preg_replace( array_keys( $rep_arr ), $rep_arr, trim( $string ) );
            return strtolower( rtrim( $string, '.' ) );

        }


        /**
         * Simple and good function to handle templates (we read jsons)
         *
         * @return array|mixed
         * @throws \JsonException
         */
        public function getTemplates() {

            // Use cache
            if ( ( $templates = $this->cache->get( 'templates' ) ) === false ) {

                // New list
                $templates = [];

                // Handle themes here
                $list = $this->browse( "templates/", false, true, false );

                // Loop
                foreach ( $list as $dir ) {

                    // Definitions?
                    if ( is_file( "templates/{$dir}/manifest.json" ) ) {

                        // Get json
                        $loadedFile = json_decode( file_get_contents( "templates/{$dir}/manifest.json" ), true, 512, JSON_THROW_ON_ERROR );

                        // Verify List - Do not append unless manifest is correct
                        if ( !empty( $loadedFile[ 'name' ] ) && is_file( "templates/{$dir}/{$loadedFile['template']}" ) ) {

                            // This is JSON from the template
                            $templates[ $dir ] = $loadedFile;

                            // Add full path to the variable
                            $templates[ $dir ][ 'path' ] = "templates/{$dir}";

                        }

                    }

                }

                // Sort them ascending
                asort( $templates );

                // Store cache
                $this->cache->set( 'templates', $templates, 0 );

            }

            return $templates;

        }


        /**
         * @param $template
         *
         * @return array
         * @throws \JsonException
         */
        public function getAdvancedTemplateOptions( $template ): array {

            // Get templates
            $templates = $this->getTemplates();

            // Check if template exists
            if ( !empty( $templates[ $template ] ) && !empty( $templates[ $template ][ 'extra' ] ) ) {

                $extras = [];
                foreach ( $templates[ $template ][ 'extra' ] as $index => $extra ) {

                    // If template isset exists and is not checkbox set to default value
                    if ( !isset( $this->settings[ 'tplOptions' ][ $template ][ $index ] ) ) {

                        $extras[ $index ] = ( $extra[ 'type' ] !== 'checkbox' ) ? $extra[ 'default' ] : (bool) ( $extra[ 'default' ] );
                        continue;

                    }

                    $extras[ $index ] = $this->settings[ 'tplOptions' ][ $template ][ $index ] ?? null;

                }

                return $extras;

            }

            return [];

        }


        /**
         * Very small function to exit JSON with grace
         *
         * @throws \JsonException
         */
        public function exitJSON(): void {

            // Clean buffer and every thing above
            if ( ob_get_level() ) {
                ob_end_clean();
            }

            // Empty array
            echo json_encode( [], JSON_THROW_ON_ERROR );
            exit;

        }

    }