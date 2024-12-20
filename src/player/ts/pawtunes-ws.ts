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

import PawTunes from "./pawtunes";

export default class PawtunesWS {

    private socket: WebSocket | null = null;
    private pawtunes: PawTunes;

    // Used to track retries
    private retryTimes: number = 1;

    constructor( pawtunes: PawTunes ) {

        this.pawtunes = pawtunes;

    }


    /**
     * Check if the web socket is active
     * @returns {boolean}
     */
    isWebSocketActive(): boolean {
        return <boolean>( this.socket && this.socket.readyState !== WebSocket.CLOSED );
    }


    /**
     * Closes the current web socket connection
     * @returns {Promise.<boolean>}
     */
    close(): Promise<boolean> {
        return new Promise( ( resolve ) => {
            if ( this.isWebSocketActive() ) {

                const socket   = this.socket as WebSocket;
                socket.onclose = () => {
                    console.log( "Websocket connection closed" );
                    this.socket = null;
                    resolve( true );
                }

                socket.onerror = () => {
                    this.socket = null;
                    resolve( true );
                }

                socket.close();
                return;

            }

            resolve( true );

        } );
    }


    /**
     * Connects to a custom Web Socket
     */
    connectToSocket() {

        this.pawtunes.showLoading();
        this.socket = new WebSocket( this.pawtunes.channel.ws.url );
        this.bindSocketEvents();

    }

    /**
     * Binds web socket events
     */
    bindSocketEvents() {

        if ( this.socket === null ) {
            return;
        }

        // Let's make TS happy
        const socket = this.socket as WebSocket;

        // Open web socket (initial established connection)
        socket.onopen = () => {

            this.retryTimes = 1;
            this.pawtunes.hideLoading();
            console.log( "Websocket connection established" );

            // Azura cast requires sending "subscribe" message on open
            if ( this.pawtunes.channel.ws.method === 'azuracast' ) {

                const stationName = `station:${this.pawtunes.channel.ws.station}`;
                socket.send( JSON.stringify( { "subs": { [ stationName ]: { "recover": true } } } ) );

            }

        };

        // Messages are expected in JSON
        socket.onmessage = ( event ) => {

            let data = JSON.parse( event.data );
            if ( !data ) {
                return;
            }

            if ( this.pawtunes.channel.ws.method === 'azuracast' ) {

                this.azuraSocketHandler( data );
                return;

            }

            this.customSocketHandler( data );

        }

        socket.onerror = ( event ) => {

            this.pawtunes.hideLoading();
            this.pawtunes.handleOnAirError( event );

            console.log( this.retryTimes * 2500 );

        }

        socket.onclose = () => {

            console.log( "Websocket connection closed" );
            this.pawtunes.hideLoading();

            // Retry connection
            if ( this.pawtunes.state === 'online' ) {
                setTimeout( () => {

                    this.connectToSocket();
                    this.retryTimes++;

                }, 2500 * this.retryTimes )
            }

        }

    }


    /**
     * Handles custom Web Socket data
     * @param {any} data - incoming data from Web Socket
     */
    customSocketHandler( data: any ) {

        // Take care of artworks
        if ( !data.artwork ) {
            data.artwork = this.pawtunes.pawArtworkURL( data.artist, data.title );
        }

        /**
         * Handle Artwork Loading & History Time Ago
         * Some providers may use played_at instead of time, so let's try both
         * Notes: we also convert to milliseconds here
         */
        if ( data.history ) {
            for ( let history of data.history ) {

                // Take care of artworks
                if ( !history.artwork ) {
                    history.artwork = this.pawtunes.pawArtworkURL( history.artist, history.title );
                }

                if ( history.played_at ) {
                    history.time = new Date( history.played_at ).getTime() || history.played_at;
                }

            }
        }

        this.pawtunes.handleOnAirResponse( data );

    }

    /**
     * Azura Cast sends initial data differently than other messages
     */
    azuraSocketHandler( data: any ) {

        const stationName = `station:${this.pawtunes.channel.ws.station}`;

        // Handle initial push
        if ( data.connect && data.connect.subs[ stationName ] ) {

            let initialStation = data.connect.subs[ stationName ];
            if ( initialStation.publications && initialStation.publications.length >= 1 && initialStation.publications[ 0 ]?.data?.np ) {
                this.handleAzuraData( initialStation.publications[ 0 ].data.np );
            }

        }

        // Regular data
        if ( data.channel === stationName && data.pub?.data?.np?.now_playing ) {
            this.handleAzuraData( data.pub.data.np );
        }


    }

    /**
     * Handles AzuraCast WS messages
     *
     * @param {object} data JSON object received from AzuraCast websocket
     */
    handleAzuraData( data: any ) {

        let pass = {
            artist : null,
            title  : null,
            artwork: null,
            history: [] as any[]
        };

        let song = data.now_playing.song;

        // Since currently only AzuraCast is supported, we modify the response a little
        pass.artist  = song.artist;
        pass.title   = song.title;
        pass.artwork = ( !this.pawtunes.channel.ws.useRemoteCovers || !song.art ) ? this.pawtunes.pawArtworkURL( song.artist, song.title ) : song.art;

        // History
        if ( this.pawtunes.channel.ws.history && data.song_history ) {
            data.song_history.forEach( ( track: any ) => {
                pass.history.push( {
                    artist : track.song.artist,
                    title  : track.song.title,
                    artwork: ( !this.pawtunes.channel.ws.useRemoteCovers || !track.song.art ) ? this.pawtunes.pawArtworkURL( track.song.artist, track.song.title ) : track.song.art,
                    time   : track.played_at * 1000
                } );
            } );
        }

        // Handle playing data
        this.pawtunes.handleOnAirResponse( pass );

    }

}