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


    constructor( pawtunes: PawTunes ) {
        this.pawtunes = pawtunes;
    }


    isWebSocketActive() {
        return ( this.socket && this.socket.readyState !== WebSocket.CLOSED );
    }


    close() {
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


    connectToSocket( url: string, station: string ) {

        this.pawtunes.showLoading();
        this.socket        = new WebSocket( url );
        this.socket.onopen = () => {

            const socket      = this.socket as WebSocket;
            const stationName = `station:${station}`;

            socket.send( JSON.stringify( { "subs": { [ stationName ]: { "recover": true } } } ) );

            this.pawtunes.hideLoading();
            console.log( "Websocket connection established" );

            socket.onmessage = ( event ) => {

                let data = JSON.parse( event.data );

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

            socket.onerror = ( event ) => {

                this.pawtunes.hideLoading();
                console.log( "Websocket connection error: " + event );

                // Retry connection
                if ( this.pawtunes.state === 'online' ) {

                    this.connectToSocket( url, station );

                }

            }

            socket.onclose = () => {

                console.log( "Websocket connection closed" );
                this.pawtunes.hideLoading();

                // Retry connection
                this.connectToSocket( url, station );

            }

        }

    }


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