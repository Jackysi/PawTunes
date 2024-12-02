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

    use lib\PawTunes;

    class Panel {

        private string $prefix;

        /**
         * Update server used to check for updates
         *
         * @var string
         */
        public string $updateCheckURL = 'https://prahec.com/api/releases/pawtunes';

        /**
         * Update server used download updates
         *
         * @var string
         */
        public string $updateDownloadURL = 'https://prahec.com/api/release/pawtunes/{version}';

        /**
         * A collection of variables for the template class
         *
         * @var array
         */
        private array $globals;

        /**
         * @var \lib\PawTunes
         */
        public PawTunes $pawtunes;

        /**
         * Rendered using "Forms" class
         *
         * @var array
         */
        private array $artworkSources = [
            'itunes'   => [
                'name' => 'iTunes (no API keys)',
            ],
            'fanarttv' => [
                'name'  => 'FanArtTV (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'Your API key',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://fanart.tv/get-an-api-key/',
                ],
            ],
            'spotify'  => [
                'name'  => 'Spotify (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'client-id:client-secret format',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://developer.spotify.com/dashboard/',
                ],
            ],
            'lastfm'   => [
                'name'  => 'LastFM (API key required)',
                'field' => [
                    'name'        => 'api_key',
                    'placeholder' => 'Your API key',
                    'helpText'    => 'Click to open providers website in a new tab.',
                    'helpURL'     => 'https://www.last.fm/api/account/create',
                ],
            ],
            'custom'   => [
                'name'  => 'Custom',
                'field' => [
                    'name'        => 'api_url',
                    'placeholder' => 'Enter URL to the API, {{$artist}} and {{$title}} will be replaced with the artist and title respectively',
                ],
            ],
        ];


        /**
         * @param \lib\PawTunes $pawtunes
         * @param string        $prefix
         * @param array         $vars
         */
        public function __construct( PawTunes $pawtunes, string $prefix, array $vars = [] ) {

            $this->pawtunes = $pawtunes;
            $this->prefix = $prefix;
            $this->globals = $vars;

        }


        /**
         * Short function to speed up deployment of alerts
         *
         * @param        $text
         * @param string $mode
         * @param bool   $php_message
         *
         * @return string
         */
        public function alert( $text, string $mode = 'warning', bool $php_message = false ): string {

            // *** Optional feature which allows replacing $text with actual PHP error message
            if ( $php_message === true ) {

                $err = error_get_last();
                if ( !empty( $err[ 'message' ] ) ) {
                    $text .= '<pre>' . $err[ 'message' ] . '</pre>';
                }

            }

            // Different modes with icons (looks nice <3)
            switch ( $mode ) {

                case 'warning':
                    $mode = 'alert-icon alert-warning';
                    break;

                case 'error':
                    $mode = 'alert-icon alert-danger';
                    break;

                case 'success':
                    $mode = 'alert-icon alert-success';
                    break;

                default:
                    $mode = 'alert-icon alert-info';
                    break;

            }

            return '<div class="alert ' . $mode . '"><div class="content">' . $text . '</div></div>';

        }


        /**
         * Loop through contents and check for string 'true' or string 'false' and replace with boolean
         *
         * @param array $array
         *
         * @return array
         */
        private function convertArrayToBool( array $array ): array {
            foreach ( $array as $key => $value ) {

                if ( is_array( $value ) ) {
                    $array[ $key ] = $this->convertArrayToBool( $value );
                    continue;
                }

                if ( $value === 'true' || $value === 'false' ) {
                    $array[ $key ] = (bool) $value;
                }

            }

            return $array;

        }


        /**
         * @param $file     string Path & File name without extension
         * @param $contents array to store
         *
         * @return bool
         */
        public function storeConfig( string $file, array $contents ): bool {

            // Convert array to boolean
            $contents = $this->convertArrayToBool( $contents );

            // Attempt storing new config
            $store = file_put_contents( "inc/{$file}.php", "<?php \nreturn " . var_export( $contents, true ) . ";" );

            // Clear various file caches
            if ( $store ) {

                clearstatcache( true );
                if ( function_exists( 'opcache_invalidate' ) ) {
                    opcache_invalidate( "inc/{$file}.php", true );
                }

            }

            return (bool) $store;

        }


        /**
         * @param string $key
         *
         * @return mixed|null
         */
        public function get( string $key = '' ) {

            return $this->globals[ $key ];

        }


        /**
         * @param $key
         * @param $value
         *
         * @return mixed
         */
        public function setOption( $key, $value ) {

            return $this->globals[ $key ] = $value;

        }


        /**
         * Simple way to verify authorization
         *
         * @return bool
         */
        public function isAuthorized(): bool {

            return ( isset( $this->globals[ 'auth' ] ) && $this->globals[ 'auth' ] === $this->authToken() );

        }


        /**
         * @return string
         */
        public function authToken(): string {

            $algorithm = 'sha256';
            if ( function_exists( 'sha512' ) ) {

                $algorithm = 'sha512';

            }

            return hash( $algorithm, $_SERVER[ 'HTTP_USER_AGENT' ] . $this->pawtunes->config( 'admin_password' ) );

        }


        /**
         * @return void
         */
        public function flashMessages(): void {

            if ( isset( $_SESSION[ $this->prefix ][ 'flash' ] ) ) {

                if ( !is_array( $_SESSION[ $this->prefix ][ 'flash' ] ) ) {
                    unset( $_SESSION[ $this->prefix ][ 'flash' ] );
                    return;
                }

                foreach ( $_SESSION[ $this->prefix ][ 'flash' ] as $message ) {
                    echo $message;
                }

                // Empty
                unset( $_SESSION[ $this->prefix ][ 'flash' ] );

            }

        }


        /**
         * Simple function to generate HTML view
         *
         * @throws \Exception
         */
        public function view( $view, $vars = [] ): void {

            $blade = new BladeOne(
                './panel/views',
                './panel/views/cache',
                ( $this->pawtunes->config( 'development' ) ? BladeOne::MODE_DEBUG : BladeOne::MODE_AUTO )
            );

            $blade->pipeEnable = true;
            $blade->setCompiledExtension( '.compiled.php' );
            $blade->share( $this->globals );
            $blade->share( $vars );
            echo $blade->run(
                $view,
                [
                    'panel'    => $this,
                    'pawtunes' => $this->pawtunes,
                ]
            );

        }


        /**
         * @param        $message
         * @param string $redirect
         *
         * @return void
         */
        public function flash( $message, string $redirect = "" ): void {

            if ( !isset( $_SESSION[ $this->prefix ][ 'flash' ] ) ) {
                $_SESSION[ $this->prefix ][ 'flash' ][] = $message;
            }

            // When redirecting, quit
            if ( $redirect !== "" ) {

                header( "Location: $redirect" );
                exit;

            }

        }


        /**
         * Short function to delete all extensions for artist
         *
         * @param $name
         *
         * @return bool
         */
        public function deleteArtwork( $name ): bool {

            // Define extensions
            $allow_ext = [ 'jpeg', 'jpg', 'png', 'svg', 'webp' ];

            // Set variables
            $files = $this->pawtunes->browse( 'data/images/' );
            $name = $this->pawtunes->parseTrack( $name );

            // If is array, loop through files and match what we're deleting
            foreach ( $files as $file ) { // Loop files

                if ( $this->pawtunes->extDel( $file ) === $name ) { // File matches, delete all extensions of this artist
                    foreach ( $allow_ext as $ext ) {

                        // Delete file
                        if ( is_file( "data/images/" . $this->pawtunes->extDel( $file ) . ".{$ext}" ) ) {
                            @unlink( "data/images/" . $this->pawtunes->extDel( $file ) . ".{$ext}" );
                        }

                    }
                    return true; // stop loop

                }
            }

            return false;

        }


        /**
         * This will be passed to the view
         *
         * @param $data
         *
         * @return array
         */
        public function mapArtworkSourcesView( $data ): array {

            $finalData = [];
            foreach ( $this->artworkSources as $key => $value ) {
                if ( array_key_exists( $key, $data ) ) {

                    $finalData[ $key ] = array_merge(
                        is_array( $value ) ? $value : [],
                        is_array( $data[ $key ] ) ? $data[ $key ] : [] // Ensure $post[$key] is an array
                    );

                } else {

                    $finalData[ $key ] = $value;
                }
            }

            // Include keys from $post that aren't in artworkSources
            foreach ( $data as $key => $value ) {
                if ( !array_key_exists( $key, $this->artworkSources ) ) {
                    $finalData[ $key ] = $value;
                }
            }

            // Neat multi-sort
            uasort( $finalData, static function( $a, $b ) {
                if ( isset( $a[ 'index' ], $b[ 'index' ] ) ) {
                    return $a[ 'index' ] <=> $b[ 'index' ];
                }
                return isset( $a[ 'index' ] ) ? -1 : 1;
            } );

            return $finalData;

        }


        /**
         * Parsed into config
         *
         * @param $post
         *
         * @return array
         */
        public function mapArtworkSourcesPost( $post ): array {

            $finalData = [];
            foreach ( $post as $key => $value ) {
                if ( array_key_exists( $key, $this->artworkSources ) ) {

                    unset( $_POST[ $key ] );
                    $finalData[ $key ] = $value;
                    $finalData[ $key ][ 'index' ] = array_search( $key, array_keys( $post ), true );

                }
            }

            return $finalData;

        }


        /**
         * Data uploads handler (returns (array) or (string) error)
         *
         * @param        $form_name
         * @param string $path
         * @param string $filename
         *
         * @return array
         */
        public function upload( $form_name, string $path = 'data/uploads/', string $filename = '' ): array {

            // Extension variable
            $extension = $this->pawtunes->extGet( $_FILES[ $form_name ][ 'name' ] );

            // Filename
            if ( empty( $filename ) ) { // If filename is empty, use uploaded file filename

                $filename = $_FILES[ $form_name ][ 'name' ];

            } else if ( $filename === '.' ) { // If we used dot, generate random filename

                $filename = uniqid( '', true ) . '.' . $extension;

            } else { // If filename is set, add extension to it

                $filename .= '.' . $extension;

            }

            // Check if path for upload exists, if not create it
            if ( !is_dir( $path ) && !mkdir( $path, 0755, true ) && !is_dir( $path ) ) {
                throw new RuntimeException( sprintf( 'Directory "%s" was not created', $path ) );
            }

            // ERR Handler
            $errors = [
                "UPLOAD_ERR_OK"         => "",
                "UPLOAD_ERR_INI_SIZE"   => "Larger than upload_max_filesize.",
                "UPLOAD_ERR_FORM_SIZE"  => "Your upload is too big !",
                "UPLOAD_ERR_PARTIAL"    => "Upload partially completed !",
                "UPLOAD_ERR_NO_FILE"    => "No file specified !",
                "UPLOAD_ERR_NO_TMP_DIR" => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_NO_TMP_DIR</span>",
                "UPLOAD_ERR_CANT_WRITE" => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_CANT_WRITE</span>",
                "UPLOAD_ERR_EXTENSION"  => "Woops, server error. Please contact us! <span style=\"display:none\">UPLOAD_ERR_EXTENSION</span>",
                "UPLOAD_ERR_EMPTY"      => "File is empty.",
                "UPLOAD_ERR_NOT_MOVED"  => "Error while saving file !",
            ];


            // Handle results & do last touches
            if ( !empty ( $_FILES[ $form_name ][ 'error' ] ) ) {
                $error = $errors[ $_FILES[ $form_name ][ 'error' ] ];
            }

            // Try to move uploaded file from TEMP directory to our new set directory
            if ( !move_uploaded_file( $_FILES[ $form_name ][ 'tmp_name' ], $path . $filename ) ) {
                $error = $errors[ "UPLOAD_ERR_NOT_MOVED" ];
            }

            // Handle return array
            return [
                'filename'  => $filename,
                'path'      => $path . $filename,
                'extension' => $extension,
                'mimetype'  => $_FILES[ $form_name ][ 'type' ],
                'size'      => $_FILES[ $form_name ][ 'size' ],
                'error'     => $error ?? null,
            ];

        }


        /**
         * @param $message
         *
         * @return void
         */
        public function sendError( $message ): void {

            http_response_code( 400 );
            echo $message;
            exit;

        }

    }