@section('content')
    <div class="panel">
        <div class="heading">
            <i class="icon fa fa-external-link-square"></i>
            Popup Embed Player
            <small><span>(1024 x 650)</span></small>
        </div>
        <div class="content">
            <p>
                This method is recommended. When the player is used as a popup window, it allows users to continue interacting with your website without any problems.
                It also ensures that the player is responsive on mobile devices and that it fits the screen well.
                See the code below to embed this method into your website.
            </p>
            <b>Example 1:</b><br>
            <a class="btn btn-primary launchPlayer mt-2" href="#">
                <i class="icon fa fa-external-link-square"></i> Open Popup
            </a>
            <br><br>
            <b>Example 2:</b><br>
            <a href="#" class="launchPlayer" style="margin-top: 8px; display:block;">
                <img width="auto" height="256" src="./../assets/img/popup-banner.webp" alt="Open Popup">
            </a>
            <br>
            <div class="row">
                <div class="col-sm-8">
                    <a href="#" class="btn btn-success btn-sm pull-right" data-copy="#popup"><i class="icon fa fa-clipboard"></i> COPY</a>
                    <b>Code:</b><br>
                    <textarea class="form-control" id="popup">
{!! htmlentities( '<a href="#" onclick="window.open(\'\', \'pawtunes\', \'width=1024, height=650\'); return false;">Open Popup</a>' ) !!}
</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="heading">
            <i class="fas fa-window-maximize"></i>
            iFrame Embed Player
            <small><span>(1024  x 650)</span></small>
        </div>
        <div class="content">
            <p>
                To embed the player on any page, use the code below. It is straightforward to deploy the player using iframe, it will work as some YouTube video.
                The values written in brackets are recommended sizes for specific player embedding types.
            </p>
            <iframe id="iframe-preview" class="d-mobile-none" src="" width="1024 " height="650" style="margin-top: 8px;border: 0;"></iframe>
            <br><br>
            <div class="row">
                <div class="col-sm-8">
                    <a href="#" class="btn btn-success btn-sm pull-right" data-copy="#iframe"><i class="icon fa fa-clipboard"></i> COPY</a>
                    <b>Code:</b><br><textarea class="form-control" id="iframe">
{!! htmlentities( '<iframe width="1024" height="650" style="border: 0; box-shadow: 1px 1px 0  #fff;" src=""></iframe>' ) !!}
</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="heading">
            <i class="fas fa-users-viewfinder"></i>
            Preview & Embedding Options
        </div>
        <div class="content">
            <p>
                This section allows you to preview the player and set various options. You can also customize the player's appearance and size.
                These options are there to OVERRIDE any default settings. So use these only if you use different settings for different pages or if you wish to preview different options.
            </p>
            <div class="form-group">
                <label class="col-sm-12">Desired Player Size</label>
                <div class="col-sm-12">
                    <input type="number" step="1" min="300" max="99999" name="width" value="1024" class="form-control mouse-num" style="width: 80px; display: inline-block"> x
                    <input type="number" step="1" min="75" max="99999" name="height" value="650" class="form-control mouse-num" style="width: 80px; display: inline-block"> pixels
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-12" for="channel">Channel</label>
                <div class="col-sm-4 pe-0">
                    <select name="channel" class="form-control" id="channel">
                        @foreach( $def_channel as $index => $name )
                            <option value="{{$index}}">{{$name}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-12" for="language">Language</label>
                <div class="col-sm-4 pe-0">
                    <select name="language" class="form-control" id="language">
                        <option value="" selected>None</option>
                        @foreach( $languages as $index => $lang )
                            <option data-html="<i class='fi ico-left fi-{{$lang['flag']}}'></i> {{$lang['name']}}" value="{{$index}}">
                                {{$lang['name']}}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-12" for="template">Template</label>
                <div class="col-sm-4 pe-0">
                    <select name="template" class="form-control" id="template">
                        @foreach( $templatesList as $index => $name )
                            <option value="{{$index}}">{{$name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-12">
                    <div class="help-block">
                        Note: If you override template, make sure the size is set correctly.
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" value="true" name="autoplay" id="autoplay"><span class="icon fa fa-check" aria-hidden="true"></span>
                            <span class="description">Start playback automatically (Some devices and browsers do not support this feature)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" value="true" name="https" id="https"><span class="icon fa fa-check" aria-hidden="true"></span>
                            <span class="description">Use HTTPS (SSL) secure URL for the player popup and embeddable code</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="heading">
            <i class="icon fa fa-music"></i>
            Now Playing Widget
        </div>
        <div class="content">
            <p>
                A small card with the current artwork, artist, title and channel name that updates itself. Embed it on your website with an iframe,
                or add the URL as a browser source in OBS (use the transparent theme there).
            </p>
            @if( ! $apiEnabled )
                {!! $panel->alert( 'The widget is part of the public API, which is disabled. Enable <b>Public API</b> in <a href="index.php?page=settings">Settings</a> to use it.', 'info' ) !!}
            @endif
            <fieldset id="widget-options"{{ $apiEnabled ? '' : ' disabled' }}>
                <div class="form-group">
                    <label class="col-sm-12" for="widget-channel">Channel</label>
                    <div class="col-sm-4 pe-0">
                        <select class="form-control" id="widget-channel"{{ $apiEnabled ? '' : ' disabled' }}>
                            @foreach( $channelNames as $name )
                                <option value="{{$name}}">{{$name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-12" for="widget-theme">Theme</label>
                    <div class="col-sm-4 pe-0">
                        <select class="form-control" id="widget-theme"{{ $apiEnabled ? '' : ' disabled' }}>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                            <option value="transparent">Transparent (OBS, overlays)</option>
                        </select>
                    </div>
                </div>
                @if( $apiEnabled )
                    <iframe id="widget-preview" src="" width="360" height="84" style="margin: 8px 0 16px; border: 0; border-radius: 8px; background: #8a8f98;"></iframe>
                @endif
                <div class="row">
                    <div class="col-sm-8">
                        <a href="#" class="btn btn-success btn-sm pull-right" data-copy="#widget"><i class="icon fa fa-clipboard"></i> COPY</a>
                        <b>Code:</b><br><textarea class="form-control" id="widget"></textarea>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>
    <script type="text/javascript">

        // Player URL
        let url = 'http://{{$_SERVER[ 'SERVER_NAME' ] . preg_replace( '!/panel/(.*)!', '', $_SERVER[ 'REQUEST_URI' ] ) }}/index.php';

        // Fields (order matters!)
        let previewFields = {
            'width'   : true,
            'height'  : true,
            'https'   : ( value ) => { return ( value === 'true' ) },
            'language': ( value ) => { return `language=${value}` },
            'template': ( value ) => { return `template=${value}` },
            'autoplay': ( value ) => { return 'autoplay=' + ( ( value ) ? 'true' : 'false' )},
            'channel' : ( value ) => { return `#${value}` },
        };

        // Initial load
        window.loadInit = function() {

            let timeout;
            let params = '';
            let width  = 1024;
            let height = 650;

            // On player size change event
            $( document ).on( 'playerSize', function() {

                $( '.panel .heading small span' ).text( '(' + width + ' x ' + height + ')' );

            } );

            // Launch bind
            $( '.launchPlayer' ).on( 'click', function() {

                // Open popup player
                window.open( url + params, 'pawtunes', 'width=' + width + ', height=' + height );
                return false;

            } );

            /**
             * Simple function to handle all stuff that has to change on the page
             */
            function genHome( changeInput ) {

                // Get text areas
                let temp_embed = $( 'textarea#iframe' );
                let temp_popup = $( 'textarea#popup' )

                // Replace URL
                $( '#iframe-preview' ).attr( 'src', './../index.php' + params );
                temp_embed.val( temp_embed.val().replace( /src="(.*)"/, 'src="' + url + params + '"' ) );
                temp_popup.val( temp_popup.val().replace( /window\.open\('([^']*)'/, 'window.open(\'' + url + params + '\'' ) );

                // Width & Height
                $( '#iframe-preview' ).width( width ).height( height );
                temp_embed.val( temp_embed.val().replace( /width="[0-9]+"/, 'width="' + width + '"' ).replace( /height="[0-9]+"/, 'height="' + height + '"' ) );
                temp_popup.val( temp_popup.val().replace( /width=([0-9]+)/, 'width=' + width + '' ).replace( /height=([0-9]+)/, 'height=' + height + '' ) );

                // Trigger event
                $( document ).trigger( 'playerSize' );

                // If changeInput is true (parameter) also set input
                if ( changeInput === true ) {

                    $( 'input[name="width"]' ).val( width );
                    $( 'input[name="height"]' ).val( height );

                }

            }


            /**
             * Bind input width change
             */
            $( Object.keys( previewFields ).map( field => `[name="${field}"]` ).join( ', ' ) ).on( 'change', function() {

                clearTimeout( timeout );
                timeout = setTimeout( function() {

                    // Set new width & height
                    width  = $( 'input[name="width"]' ).val();
                    height = $( 'input[name="height"]' ).val();

                    // Check other options, this SUCKS, but it works
                    params = '';
                    $.each( previewFields, function( field, callback ) {

                        let elm = $( '[name="' + field + '"]' );
                        switch ( field ) {

                            case 'width':
                            case 'height':
                                break;

                            case 'https':
                                url = url.replace( /https?:\/\//, ( ( elm.is( ':checked' ) ) ? 'https://' : 'http://' ) );
                                break;

                            case 'autoplay':
                                params += ( params.length === 0 ) ? '?' : '&';
                                params += callback( elm.is( ':checked' ) );
                                break;

                            default:
                                if ( elm.val() === undefined || elm.val() === '' ) {
                                    break;
                                }

                                if ( field !== 'channel' ) {
                                    params += ( params.length === 0 ) ? '?' : '&';
                                }

                                params += callback( elm.val() );
                                break;

                        }

                    } );

                    // Now change elements on the page
                    genHome();

                }, 500 );

                return true;

            } );

            @if( !empty( $w ) && !empty( $h ) )
                width = '{{$w}}';
            height    = '{{$h}}';
            @endif

            genHome( true );

            /**
             * Now playing widget: embed code and preview follow the channel/theme selects and the HTTPS toggle
             */
            function genWidget() {

                let query = '?widget&channel=' + encodeURIComponent( $( '#widget-channel' ).val() || '' ) + '&theme=' + $( '#widget-theme' ).val();

                // Built as text: the channel name is URL-encoded and the textarea value is never parsed as HTML
                $( '#widget' ).val( '<iframe src="' + url + query + '" width="360" height="84" style="border: 0;" title="Now playing"></iframe>' );
                $( '#widget-preview' ).attr( 'src', './../index.php' + query );

            }

            $( '#widget-channel, #widget-theme, [name="https"]' ).on( 'change', function() {
                setTimeout( genWidget, 600 ); // after the preview options above updated the HTTPS scheme of url
            } );

            genWidget();

        };

    </script>
@endsection
@include('template')