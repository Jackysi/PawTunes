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

    use lib\Helpers;
    use lib\PawTunes;

    abstract class Artwork {

        /**
         * @var string
         */
        public $path = './data/images';

        /**
         * Path to store cropped and optimized images
         *
         * @var string
         */
        public $cachePath = './data/cache';

        /**
         * List of allowed extensions
         *
         * @var string[]
         */
        public $extensions = [ 'jpg', 'jpeg', 'png', 'svg', 'webp' ];

        /**
         * @var integer
         */
        protected $imageWidth;

        /**
         * @var integer
         */
        protected $imageHeight;

        /**
         * @var boolean
         */
        protected $cacheImage = true;

        /**
         * @var string
         */
        protected $defaultArtist;

        /**
         * @var string
         */
        protected $defaultTrack;

        /**
         * @var string
         */
        protected $override;

        /**
         * @var
         */
        protected $pawtunes;


        /**
         * Set PHP timeout to 0 and ignore user abort
         *
         * @return void
         */
        protected function ignoreAbort() {

            ignore_user_abort( true );
            set_time_limit( 0 );

        }


        /**
         * @param PawTunes $pawtunes
         */
        public function __construct( PawTunes $pawtunes, $override = null ) {

            $this->imageWidth = $pawtunes->config( 'images_size' ) ?? 360;
            $this->imageHeight = $pawtunes->config( 'images_size' ) ?? 360;

            $this->cacheImage = $pawtunes->config( 'cache_images' ) ?? false;
            $this->cachePath = ( !empty( $pawtunes->config( 'cache' )[ 'path' ] ) ) ? $pawtunes->config( 'cache' )[ 'path' ] : $this->cachePath;

            $this->defaultArtist = $pawtunes->config( 'artist_default' ) ?? 'Various Artists';
            $this->defaultTrack = $pawtunes->config( 'title_default' ) ?? 'Unknown Track';

            $this->pawtunes = $pawtunes;

            // Check if we have an override provided
            $this->override = ( !empty( $override ) ) ? $override : null;

        }


        /**
         * Get image
         *
         * @param string $artist
         * @param string $title
         * @param bool   $skipCache
         *
         * @return string
         */
        public function getExistingImage( $artist, $title = '', bool $skipCache = false ): ?string {

            // Figure out what we are actually searching for
            $trackArtist = $this->pawtunes->parseTrack( $artist );
            $trackName = $this->pawtunes->parseTrack( "{$artist} - {$title}" );

            // Empty Artist, Too sort or default artist-title returned
            if ( empty( $trackArtist ) || strlen( $trackArtist ) < 3 || $this->isDefaultTrack( $artist, $title ) ) {
                return false;
            }

            // Check if we have a cached image (artist - title)
            if ( $title !== '' && $trackImage = $this->getExistingArtwork( $trackName ) ) {
                return $trackImage;
            }

            // Check if we have an artist image
            if ( $artistImage = $this->getExistingArtwork( $trackArtist ) ) {
                return $artistImage;
            }

            // Check if we have a cached artist image
            if ( $skipCache === false && ( $cachedArtist = $this->getCachedArtwork( ( $title === '' ) ? $trackArtist : $trackName ) ) ) {
                return $cachedArtist;
            }

            // Last check if there is an override
            if ( $this->override && filter_var( $this->override, FILTER_VALIDATE_URL ) ) {
                return $this->override;
            }

            return null;

        }


        /**
         * @param $artist
         * @param $title
         *
         * @return bool
         */
        protected function isDefaultTrack( $artist, $title ): bool {

            return ( $artist === $this->defaultArtist && $title === $this->defaultTrack );

        }


        /**
         * @param $name
         *
         * @return string|null
         */
        protected function getExistingArtwork( $name ): ?string {

            foreach ( $this->extensions as $ext ) {

                if ( is_file( "{$this->path}/{$name}.{$ext}" ) ) {
                    return "{$this->path}/{$name}.{$ext}";
                }

            }

            return null;

        }


        /**
         * @param $name
         *
         * @return string|null
         */
        protected function getCachedArtwork( $name ): ?string {

            foreach ( $this->extensions as $ext ) { ## Low priority cached images from various sources

                if ( is_file( "{$this->cachePath}/{$name}.{$ext}" ) ) {
                    return "{$this->cachePath}/{$name}.{$ext}";
                }

            }

            return null;

        }


        /**
         * Default class Artwork means Artwork functionality is "Disabled"
         *
         * @param $artist
         * @param $title
         *
         * @return null
         */
        protected function getArtworkURL( string $artist, ?string $title = '' ) {

            return null;

        }


        /**
         * @param $url
         * @param $imageName
         *
         * @return string|null
         */
        protected function downloadArtwork( $url, $imageName ) {

            $this->ignoreAbort();

            // Headers list
            $imageType = null;

            // URL Is validated, now download image and check it's headers
            $img = $this->pawtunes->get(
                $url,
                null,
                null,
                false,
                0,
                $err,
                [
                    CURLOPT_HEADERFUNCTION => function( $ch, $hLine ) use ( &$imageType ) {

                        // Handle content type header
                        if ( ( stripos( $hLine, 'content-type' ) !== false ) && stripos( $hLine, 'image/' ) !== false ) {
                            $imageType = trim( str_ireplace( 'content-type: image/', '', $hLine ) );
                        }

                        return strlen( $hLine );

                    },
                ]
            );

            // Download failed, invalid extension or too small file
            if ( strlen( $img ) < 1024 || $imageType === null || !in_array( $imageType, $this->extensions ) ) {
                return null;
            }

            // Where to store image
            $path = "{$this->cachePath}/{$imageName}." . $imageType;
            file_put_contents( $path, $img );

            // Resize/Crop image
            \lib\ImageResize::handle( $path, "{$this->imageWidth}x{$this->imageHeight}", 'crop' );

            return $path;

        }


        /**
         * @param      $artist
         * @param      $title
         * @param bool $skipCache
         *
         * @return string|null
         */
        public function __invoke( $artist, $title = '', string $override = '', bool $skipCache = false ): ?string {

            // If we already have an image, stop here.
            $existing = $this->getExistingImage( $artist, $title, $skipCache );
            if ( $existing || empty( $artist ) ) {
                return $existing;
            }

            // Now let's do magic.
            $artworkURL = ( !empty( $override ) ) ? $override : $this->getArtworkURL( $artist, $title );
            if ( !$artworkURL || !filter_var( $artworkURL, FILTER_VALIDATE_URL ) ) {
                return false;
            }

            // Not downloading & caching
            if ( $skipCache || !$this->cacheImage ) {
                return $artworkURL;
            }

            // Download and cache, return new URL
            $fileName = $this->pawtunes->parseTrack( "{$artist} - {$title}" );
            if ( !$newImage = $this->downloadArtwork( $artworkURL, $fileName ) ) {
                return false;
            }

            return $newImage;

        }

    }