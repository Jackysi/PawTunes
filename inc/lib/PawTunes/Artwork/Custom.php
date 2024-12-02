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

    use lib\PawTunes;

    class Custom extends Artwork {

        /**
         * @var string
         */
        private $url;


        /**
         * @param       $apiKey
         * @param array $settings
         */
        public function __construct( PawTunes $pawtunes, $override = null ) {

            parent::__construct( $pawtunes, $override );
            $this->url = $this->pawtunes->config( 'artwork_sources' )[ 'custom' ][ 'api_url' ] ?? null;

        }


        /**
         * @param string $artist
         * @param string $title
         *
         * @return mixed
         */
        protected function getArtworkURL( $artist, $title = '' ) {

            // Create full URL
            $url = $this->pawtunes->template(
                $this->url,
                [
                    'artist' => rawurlencode( $artist ),
                    'title'  => rawurlencode( $title ),
                ]
            );

            // Check if URL exists
            $fh = @get_headers( $url );
            $code = false;

            // URL exists or is it 404?
            if ( $fh && is_array( $fh ) ) {
                foreach ( $fh as $line ) {

                    // Try to find 200 header
                    if ( stripos( $line, '200 OK' ) !== false ) {
                        $code = true;
                    }

                }
            }

            // Now return
            return ( $code ) ? $url : null;

        }

    }