<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{( ( empty( $settings[ 'title' ] ) ) ? 'PawTunes Radio Player' : $settings[ 'title' ] )}} :: Control Panel</title>
        <link rel="shortcut icon" href="./assets/favicon.ico">
        <link rel="icon" type="image/png" href="./assets/favicon.png" sizes="32x32" />

        <!-- PawTunes Radio Control Panel Style Sheet. The file time in the URL makes browsers fetch a rebuilt or
             updated file at once: without it they kept a stale copy, alone or next to a fresh panel.min.js -->
        <link rel="stylesheet" href="./assets/css/pawtunes-panel.css?v={{ filemtime('panel/assets/css/pawtunes-panel.css') }}" type="text/css">

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

            // CSRF token for AJAX POSTs and EventSource URLs (panel/index.php checks it on every authenticated POST)
            var csrfToken = "{{ $panel->isAuthorized() ? $panel->csrfToken() : '' }}";
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
                    <button type="submit" form="logout-form" class="btn btn-danger logout-btn" title="Logout" aria-label="Logout"><i class="icon fa fa-sign-out"></i> <span class="d-mobile-none">Logout</span></button>
                </div>
            </section>
            {{-- Logout is a POST (CSRF-checked), so a third-party page can not log the admin out with a plain link --}}
            <form method="POST" action="index.php?logout" id="logout-form">
                {!! $panel->csrfField() !!}
            </form>
@endif