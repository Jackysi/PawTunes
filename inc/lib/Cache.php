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

    class Cache {

        /**
         * This class options are all here, useful for later use (file cache etc)
         *
         * @var array
         */
        protected $options;

        /**
         * This is temporary variable with object of a caching method (Memcache, Memcached, APC)
         *
         * @var
         */
        protected $object;

        /**
         * Simple bool variable that lets us know if script started properly or not
         *
         * @var bool
         */
        protected $startup = false;

        /**
         * When the stack is initiated, this variable is filled with cache information
         *
         * @var bool
         */
        private $store = [];


        /**
         * cache constructor.
         *
         * @param array $options
         */
        public function __construct( array $options = [] ) {

            // Default Cache class options
            $this->options = $options + [
                    'path'    => realpath( getcwd() ) . '/cache', // Location where to cache items
                    'host'    => '127.0.0.1:11211', // Memcached/Redis host (memcached default)
                    'ext'     => '.cache',          // Disk cache file extension
                    'encrypt' => false,             // Disk cache basic file encryption
                    'mode'    => 'disk',            // Modes: disk, apc, apcu, redis, memcache, memcached
                    'extra'   => [],                // Additional options (only redis, memcached and memcache supports at the moment)
                    'prefix'  => '',                // Key prefix (all modes)
                ];


            // Check settings & caching mode
            if ( $this->checkSettings() ) {

                // Start cache keys collector
                $this->startup = true;
                $this->stack();

            }

        }


        /**
         * Before starting up the caching class this must be done. Here we check if the options
         * are valid and the connections to Memcache/Memcached are working
         *
         * @return bool
         * @throws \RedisException
         */
        private function checkSettings(): bool {

            // Various tests & connect
            switch ( $this->options[ 'mode' ] ) {

                case 'disk': // disk cache

                    // Check if path exists, if not create it
                    if ( !is_dir( $this->options[ 'path' ] ) ) { // if not create it recursively
                        if ( !mkdir( $this->options[ 'path' ], 0755 ) && !is_dir( $this->options[ 'path' ] ) ) {

                            return false;

                        }
                    }

                    return true;

                case 'apc': // php_apc
                    if ( extension_loaded( 'apc' ) && ini_get( 'apc.enabled' ) ) {

                        return true;

                    }
                    break;

                case 'apcu': // php_apcu
                    if ( extension_loaded( 'apcu' ) && ini_get( 'apc.enabled' ) ) {

                        return true;

                    }
                    break;

                case 'redis':

                    if ( extension_loaded( 'redis' ) ) {

                        $this->object = new \Redis();

                        // Use socket
                        if ( !preg_match( '/(.*):(\d+)/', $this->options[ 'host' ], $host ) ) {

                            if ( $this->object->pconnect( $this->options[ 'host' ], 0, 2 ) ) {

                                return true;

                            }

                        } else if ( $this->object->pconnect( $host[ 1 ], $host[ 2 ], 2 ) ) {

                            return true;

                        }

                        // Special case, authorization...
                        if ( isset( $this->options[ 'extra' ][ 'auth' ] ) && !$this->object->auth( $this->options[ 'extra' ][ 'auth' ] ) ) {

                            return false;

                        }

                        // You can pass additional options to the Memcached handler
                        if ( count( $this->options[ 'extra' ] ) > 1 && isset( $this->options[ 'extra' ][ 'auth' ] ) ) {
                            foreach ( $this->options[ 'extra' ] as $opt => $val ) {

                                $this->object->setOption( $opt, $val );

                            }
                        }

                    }

                    break;

                case 'memcache': // php_memcache

                    // Extension must be loaded obviously
                    if ( extension_loaded( 'memcache' ) ) {

                        // Initiate Memcache object
                        $this->object = new \Memcache;

                        // Use socket
                        if ( !preg_match( '/(.*):(\d+)/', $this->options[ 'host' ], $host ) ) {

                            if ( $this->object->addServer( $this->options[ 'host' ], 0 ) ) {
                                return true;
                            }

                        } else if ( $this->object->addServer( $host[ 1 ], $host[ 2 ] ) ) {

                            return true;

                        }
                    }

                    break;

                case 'memcached': // php_memcached

                    // Extension must be loaded obviously
                    if ( extension_loaded( 'memcached' ) ) {

                        // Create new Memcached object
                        $this->object = new \Memcached();
                        $servers = $this->object->getServerList();

                        // Check list of servers added to the Memcached extension
                        if ( count( $servers ) > 1 ) {
                            return true;
                        }

                        if ( !preg_match( '/(.*):(\d+)/', $this->options[ 'host' ], $host ) ) { // Use socket, faster

                            if ( $this->object->addServer( $this->options[ 'host' ], 0, true ) ) {

                                return true;

                            }

                        } else if ( $this->object->addServer( $host[ 1 ], $host[ 2 ], true ) ) {

                            return true;

                        }

                        // You can pass additional options to the Memcached handler
                        if ( count( $this->options[ 'extra' ] ) > 1 ) {

                            foreach ( $this->options[ 'extra' ] as $opt => $val ) {

                                $this->object->setOption( $opt, $val );

                            }

                        }

                    }

                    break;

            }

            return false;

        }


        /**
         * Cache storage, stores all cached keys and their time out, not their values
         *
         * @param string $act clean, check, delete or any value will re-read store
         * @param string $key name of the key you wish to check/delete from store
         *
         * @return array|bool
         */
        protected function stack( string $act = 'init', string $key = '' ) {

            // If class failed on startup, quit now!
            if ( !$this->startup ) {
                return false;
            }

            // First call should setup store variable which will contain cache keys
            if ( $act === 'init' && count( $this->store ) === 0 ) {

                // Get from cache
                $v = $this->get( "cache_store" );

                // Check if cache returned proper result
                if ( $v !== false && is_array( $v ) ) {

                    $this->store = $v;

                } else {

                    $this->store = [];

                }

            }


            // Switch actions
            switch ( $act ) {

                // Check key existence/expiration
                case 'check':

                    // First check if key even exists
                    if ( isset( $this->store[ $key ] ) ) {

                        // Check if key exists and if it expired (used at GET method)
                        if ( $this->store[ $key ][ 'expires' ] === 0 || time() < $this->store[ $key ][ 'expires' ] ) {

                            return true;

                        }

                    }

                    return false;


                // Delete a single key from cache
                case 'delete':

                    if ( count( $this->store ) <= 1 ) {
                        return false;
                    }

                    ## Add key -> ttl to cache_status
                    unset( $this->store[ $key ] );
                    $this->set( 'cache_store', $this->store, 0 );
                    return true;


                // Flush whole cache, meaning delete all keys in store
                case 'flush':

                    // At least return empty array
                    $clean_status = [];

                    // Store not empty?
                    if ( count( $this->store ) > 0 ) {

                        // Loop through stored cache entries and delete them
                        foreach ( $this->store as $index => $more ) {

                            $clean_status[] = $index;
                            $this->delete( $index );

                        }

                        // Clear script cache
                        $this->store = [];

                    }

                    return $clean_status;

            }

            return false;

        }


        /**
         * Get existing record from cache, if it does not exist false is returned
         *
         * @param $key
         *
         * @return bool|mixed|string
         */
        public function get( $key ) {

            // If class failed to startup, quit now!
            if ( !$this->startup ) {
                return false;
            }

            // Use Prefix
            $name = $this->parseKey( $key );
            $data = false;

            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':

                    // Fetch from store
                    $apc = apc_fetch( $name, $success );

                    // If successful, return the data
                    if ( $success ) {
                        $data = $apc;
                    }
                    break;


                // APCu extension uses its own calls
                case 'apcu':

                    // Fetch from store
                    $apc = apcu_fetch( $name, $success );

                    // If successful, return the data
                    if ( $success ) {
                        $data = $apc;
                    }
                    break;


                // Redis method (simple)
                case 'memcache':
                case 'memcached':
                case 'redis':
                    $data = $this->object->get( $name );
                    break;

                // Default is always disk cache
                default:

                    // Check if cache exists
                    if ( is_file( "{$this->options[ 'path' ]}/{$name}{$this->options[ 'ext' ]}" ) ) {

                        // Validate key expiration date and data (allow cache_store without actual valid expiration)
                        if ( $key === 'cache_store' || $this->stack( 'check', $key ) === true ) {

                            $cache_data = file_get_contents( "{$this->options[ 'path' ]}/{$name}{$this->options[ 'ext' ]}" );             ## Read file into variable
                            $cache_data = ( ( $this->options[ 'encrypt' ] === true ) ? base64_decode( $cache_data ) : $cache_data );      ## Encryption
                            $serialized = @unserialize( $cache_data, [ 'allowed_classes' => true ] );

                            // If un-serialize function returned ANY sort of data, return it
                            if ( $serialized !== false ) {

                                $data = $serialized;

                            } else { // Nope, just data

                                $data = $cache_data;

                            }

                        }

                    }

                    break;

            }

            // Update hit count if data isn't false
            if ( $data !== false && isset( $this->store[ $key ][ 'hits' ] ) ) {
                $this->store[ $key ][ 'hits' ]++;
            }

            // Return information received from a method
            return $data;

        }


        /**
         * Set cache by key data and expiration time
         *
         * @param string       $key  Name of the key to store
         * @param string|array $data Value to store (string, array, int, float or object)
         * @param int          $ttl  how long cache should be stored (0 = unlimited)
         *
         * @return array|bool
         */
        public function set( $key, $data, $ttl = 600 ) {

            // If class failed to startup, quit now!
            if ( !$this->startup ) {
                return false;
            }

            // Prefix / Default response
            $name = $this->parseKey( $key );
            $return = false;


            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':
                    $return = apc_store( $name, $data, $ttl );
                    break;


                // APCu extension uses its own calls
                case 'apcu':
                    $return = apcu_store( $name, $data, $ttl );
                    break;


                // Redis method
                case 'redis':
                    $return = $this->object->set( $name, $data, $ttl );
                    break;


                // Memcache method
                case 'memcache':

                    // Try to replace key, else make new one
                    if ( !$return = $this->object->replace( $name, $data, false, $ttl ) ) {
                        $return = $this->object->set( $name, $data, false, $ttl );
                    }

                    break;


                // Memcached
                case 'memcached':

                    // Try to replace key, else make new one
                    if ( !$return = $this->object->replace( $name, $data, $ttl ) ) {
                        $return = $this->object->set( $name, $data, $ttl );
                    }

                    break;


                // Default is always disk cache
                default:

                    // Encryption
                    if ( $this->options[ 'encrypt' ] === true ) {
                        $data = base64_encode( $data );
                    }

                    // Write cache if its writable
                    if ( is_writable( $this->options[ 'path' ] ) && is_dir( $this->options[ 'path' ] ) ) {

                        // Serialize arrays & objects
                        if ( is_array( $data ) || is_object( $data ) ) {
                            $data = serialize( $data );
                        }

                        file_put_contents( "{$this->options[ 'path' ]}/{$name}{$this->options[ 'ext' ]}", $data );
                        $return = true;

                    }

                    break;


            }

            // The cache_store key is little different because it has no expiration
            if ( $key === 'cache_store' ) {

                return $return;

            }

            // Also set expire/hits ONLY if SET was success
            if ( $return !== false ) {

                // Reset store hits on SET, logical...
                $this->store[ $key ][ 'hits' ] = 0;

                // Set expire TTL (basically just expire time)
                $this->store[ $key ][ 'expires' ] = ( ( $ttl === 0 ) ? 0 : time() + $ttl );

            }

            // Return success/false
            return $return;

        }


        /**
         * Delete key from cache by the definition
         *
         * @param $key
         *
         * @return bool
         */
        public function delete( $key ) {

            // If class failed to startup, quit now!
            if ( !$this->startup ) {
                return false;
            }

            // Use prefix
            $name = $this->parseKey( $key );
            $deleted = false;

            // Various Modes / Actions
            switch ( $this->options[ 'mode' ] ) {

                // APC extension uses its own calls
                case 'apc':
                    $deleted = apc_delete( $name );
                    break;

                // APCu extension uses its own calls
                case 'apcu':
                    $deleted = apcu_delete( $name );
                    break;

                // Redis method
                case 'memcache':
                case 'memcached':
                case 'redis':
                    $deleted = $this->object->delete( $name );
                    break;

                // Default is always disk cache
                default:

                    if ( is_file( "{$this->options[ 'path' ]}/{$name}{$this->options[ 'ext' ]}" ) ) {

                        // Del cache
                        $deleted = @unlink( "{$this->options[ 'path' ]}/{$name}{$this->options[ 'ext' ]}" );

                    }

                    break;

            }

            // If cache key was successfully deleted, also clean it from cache_store
            // Ignore cache_store, should never be deleted
            if ( ( $deleted === true ) && $key !== 'cache_store' ) {
                $this->stack( 'delete', $key );
            }

            return $deleted;

        }


        /**
         * Small function to convert keys to proper values supported by all caching modes
         *
         * @param $key
         *
         * @return mixed
         */
        protected function parseKey( $key ) {

            return str_replace( ' ', '_', $this->options[ 'prefix' ] . $key );

        }


        /**
         * Useful function to delete all keys with specific REGEX match, comes very handy when using caching for different things
         * Example: deleteAll( 'page_cache_.*' );
         *
         * @param string $regex
         *
         * @return array|bool
         */
        public function deleteAll( $regex = '.*' ) {

            // If class failed to startup, quit now!
            if ( !$this->startup || count( $this->store ) < 1 ) {
                return false;
            }

            // Default variable
            $deleted = [];

            // We loop through whole cache store
            foreach ( $this->store as $key => $expire ) {

                // Skip cache store
                if ( $key === 'cache_store' ) {
                    continue;
                }

                // Use regex
                if ( preg_match( '/' . $regex . '/i', $key ) ) {    ## Use regex for deleteAll

                    $deleted[] = $key;
                    $this->delete( $key );

                }

            }

            // Return list of deleted keys (useful)
            return $deleted;

        }


        /**
         * Access the caching object directly, useful for memcached, memcache, redis, apcu and apc.
         *
         * @return mixed
         */
        public function direct() {

            // If class failed on startup, quit now!
            if ( !$this->startup ) {
                return false;
            }

            // If using disk method, return this object
            if ( $this->options[ 'mode' ] === 'disk' ) {
                return $this;
            }

            // Return whichever object we're using
            return $this->object;

        }


        /**
         * Simple function to clean up absolute/missing caches from cache store
         *
         * @return array
         */
        public function clean() {

            // Pre-defined array
            $cleaned = [];

            // Check if store is array
            if ( $this->startup && count( $this->store ) > 0 ) {

                // Loop through stored cache entries and delete them
                foreach ( $this->store as $key => $more ) {

                    // Check if key is active, if not, clean it from store
                    if ( !$this->get( $key ) ) {

                        // Remove from store and add key to array of cleaned so far
                        $this->stack( 'delete', $key );
                        $cleaned[] = $key;

                        // When using disk cache, we can also remove absolute file from drive
                        if ( $this->options[ 'mode' ] === 'disk' ) {
                            $this->delete( $key );
                        }

                    }

                }

            }

            return $cleaned;

        }


        /**
         * Flush whole cache created with this extension, use with extreme caution!
         *
         * @return array|bool
         */
        public function flush() {

            // Attempt cleaning up cache_store
            $tmp = $this->stack( 'flush' );

            // We call this here so changes are permanent
            $this->__destruct();

            // Return what ever we got from stack clean
            return $tmp;

        }


        /**
         * This function must always be run after you have completed working with cache
         * it ensures that cache_store is written to the caching method
         */
        public function __destruct() {

            // Save cache_store
            if ( $this->startup !== false && count( $this->store ) > 0 ) {

                $this->set( 'cache_store', $this->store, 0 );

            }

        }

    }