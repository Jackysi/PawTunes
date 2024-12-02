<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{( ( empty( $settings[ 'title' ] ) ) ? 'PawTunes Radio Player' : $settings[ 'title' ] )}} :: Control Panel</title>
        <link rel="shortcut icon" href="./assets/favicon.ico">
        <link rel="icon" type="image/png" href="./assets/favicon.png" sizes="32x32" />

        <!-- PawTunes Radio Control Panel Style Sheet -->
        <link rel="stylesheet" href="./assets/css/pawtunes-panel.css" type="text/css">

        <script src="./assets/js/jquery.min.js"></script>
        <script>

            /**
             * Dark Mode class
             */
            let darkMode = {

                isLocalStorageSupported() {
                    return ( window.localStorage );
                },

                get( key ) {

                    if ( !this.isLocalStorageSupported() ) {
                        return false;
                    }

                    let localData = localStorage.getItem( key );
                    return ( localData ) ? localData : null;

                },

                set( key, data ) {

                    if ( !this.isLocalStorageSupported() ) {
                        return false;
                    }

                    return window.localStorage.setItem( key, data );

                },

                isDark() {
                    return this.get( 'pawtunes.panel.dark-mode' ) == 'true';
                },

                setMode( value ) {
                    this.set( 'pawtunes.panel.dark-mode', value );
                },

                toggle( value ) {

                    if ( value === undefined ) {
                        value = !this.isDark();
                    }

                    //  Body Class
                    document.documentElement.classList.toggle( 'dark-mode', value );
                    this.toggleButtons( value );

                    // Cookie last
                    this.setMode( value );

                },

                toggleButtons( value ) {

                    let btnLight = document.querySelector( '.day-night .clickable #on' );
                    let btnDark  = document.querySelector( '.day-night .clickable #off' );

                    if ( !btnLight || !btnDark ) {
                        return false;
                    }

                    // Buttons
                    if ( value === true ) {

                        btnLight.style.display = 'none';
                        btnDark.style.display  = '';

                    } else {

                        btnLight.style.display = '';
                        btnDark.style.display  = 'none';

                    }

                }

            }

            // Initial load
            darkMode.toggle( darkMode.isDark() );
            document.addEventListener( 'DOMContentLoaded', function() {
                darkMode.toggleButtons( darkMode.isDark() );
            } );

            var version = "{{$version}}";
        </script>
    </head>
    <body>
        @if ( $panel->isAuthorized() )
            <section class="intro small">
                <div class="container content">
                    <div class="heading">
                        <div class="logo">
                            <img width="auto" height="70" src="./assets/logo.svg" alt="PawTunes Logo">
                            <div class="version">Release <span class="release">{{$version}}</span></div>
                        </div>
                        <h1>PawTunes</h1>
                    </div>
                    <a href="?logout" class="btn btn-danger logout-btn"><i class="icon fa fa-sign-out"></i> Logout</a>
                </div>
            </section>
@endif