@section('content')
    <div class="panel">
        <div class="heading"><i class="icon fa fa-medkit"></i> Connection Test & Debug</div>
        <div class="content">
            <p>
                This function allows you to test internet connectivity issues and problems. The initial idea was to make this tool available to test port connectivity because
                some web hosting providers are blocking uncommon internet ports for their "security". In most cases contacting the provider to unblock the port will fix the issue.
            </p>
            <div class="row">
                <div class="col-sm-4" style="padding-right:5px;">
                    <select class="form-control" name="debug-server">
                        @if (count( $channels ) >= 1)
                            <option value="user">All configured channels</option>
                        @endif
                        <option value="ssl_check">PawTunes Remote API & iTunes Server</option>
                        <option value="centovacast">Centovacast (Port: 2199)</option>
                        <option value="ports">Shoutcast & Icecast (Port: 8000)</option>
                        <option value="all">Test ports 8000, 2199 and 443</option>
                    </select>
                </div>
                <button class="btn btn-primary start-debug mobile-ms-2">
                    <span class="spinner in-progress hidden"> STOP</span>
                    <span class="normal center"><i class="icon fa fa-play"></i> Start Test</span>
                </button>
            </div>
            <pre class="debug-output commands-pre" style="display: none; margin-top: 15px; margin-bottom: 0; max-height: 60vh; overflow: auto;"></pre>
        </div>
    </div>

    @if ( !empty($theme_message))
        {!! $theme_message !!}
    @endif
    <div class="panel">
        <div class="heading"><i class="icon fa fa-folder"></i> Custom Color Scheme(s)</div>
        <div class="content" id="theme-tool">
            <p>
                This option allows you to create your own color scheme for the player.<br>
                The generated color scheme will be saved as <b>theme-name.css</b> file under <b>/templates/(your chosen template)/custom/</b> directory.
            </p>
            <div class="scheme-manager-preloader spinner mb-5">Scanning for color schemes, please wait...</div>
            <div class="schemes-manager mb-5 overflow-auto" style="display: none">
                <table class="table vertical-center hover">
                    <thead>
                        <tr>
                            <th class="col-sm-3">Name</th>
                            <th>Template</th>
                            <th>Path</th>
                            <th>File size</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <button href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target=".compile-custom-scheme">
                <i class="icon fa fa-plus"></i> Compile new
            </button>
        </div>
    </div>

    <div class="loadArtwork text-center">
        Loading, please wait...<br><br>
        <div class="preloader-spin align-middle"></div>
        <br><br>
    </div>
    <div class="panel artworkManager hidden">
        <div class="heading"><i class="icon fa fa-photo-video"></i> Artwork Manager</div>
        <div class="content">
            <p>
                This option allows you to set your own images for various artists and their tracks. These images also have higher priority over LastFM or any other API's.
            </p>
            <div class="artwork-manager">
                <table class="table vertical-center hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th>File name (formatted)</th>
                            <th>Image Path</th>
                            <th>File size</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <a href="#" class="btn btn-warning pull-right" data-bs-toggle="modal" data-bs-target=".check-artwork-modal"><i class="icon fa fa-search"></i> Lookup</a>
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target=".upload-artwork-modal"><i class="icon fa fa-cloud-upload"></i> Upload</a>
            <a href="#" class="btn btn-info" data-bs-toggle="modal" data-bs-target=".import-artwork-modal"><i class="icon fa fa-download"></i> Import</a>
        </div>
    </div>

    <!-- Compile Colour Scheme Modal -->
    <div class="modal fade compile-custom-scheme" tabindex="-1" role="dialog" aria-labelledby="Upload Artwork">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" id="compileColorScheme" action="index.php?page=tools#theme-tool">
                <input type="hidden" name="form" value="compile">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="icon fa fa-folder"></i> Custom Color Scheme(s)</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            If you use the same name of the color scheme that already exists, it will override it.
                        </p>
                        <br>
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="filename">New theme name:</label>
                            <div class="col-sm-4">
                                <input class="form-control" type="text" name="filename" placeholder="base.color" value="" id="filename" required>
                            </div>
                            <div class="help-block"> (If you enter name of existing theme, this will overwrite it)</div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="base-theme">Select template:</label>
                            <div class="col-sm-4">
                                <select class="form-control" name="template" id="template">
                                    <option value="" disabled selected>None</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group hidden">
                            <label class="col-sm-2 control-label" for="base-theme">Select theme base:</label>
                            <div class="col-sm-3 base-container">
                                <select class="form-control spectrum with-add-on" name="base-theme" id="base-theme"></select>
                            </div>
                            <div class="help-block"> (Selected theme will be used as a "base" for the new color scheme)</div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label" style="padding-top: 4px;">Accent color:</label>
                            <div class="col-sm-3">
                                <input class="form-control" id="base-color" value="#3498db" name="base-color" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label" style="padding-top: 4px;">Background color:</label>
                            <div class="col-sm-3">
                                <input class="form-control" id="bg-color" value="#F5F5F5" name="bg-color" required>
                            </div>
                            <div class="col-sm-7">
                                <div class="help-block">(Might not be supported by template)</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">
                            <i class="icon fa fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-success">
                            <span class="spinner in-progress hidden"> Compiling...</span>
                            <span class="normal center"><i class="icon fa fa-floppy-disk"></i> Compile</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Artwork Modal -->
    <div class="modal fade upload-artwork-modal" tabindex="-1" role="dialog" aria-labelledby="Upload Artwork">
        <div class="modal-dialog modal-lg" role="document">
            <form enctype="multipart/form-data" id="artworkUpload">
                <input type="hidden" name="form" value="artwork">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="icon fa fa-cloud-upload"></i> Upload Artwork</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            Artwork name will not be preserved because some filesystems do not support special characters.
                            Player will match artwork literally, but you can use "ARTIST" or "ARTIST - TITLE" format as well <br>
                        </p>
                        <div class="form-grid">
                            <div class="form-group-grid">
                                <label class="control-label" for="track">Name</label>
                                <div class="row col-sm-8">
                                    <input class="form-control" type="text" name="track" placeholder="David Guetta or David Guetta - Memories" value="" id="track">
                                </div>
                            </div>
                            <div class="form-group-grid">
                                <label class="control-label" for="artist-image">Artwork</label>
                                <div>
                                    <div class="file-input">
                                        <input type="file" id="artist-image" name="image">
                                        <div class="input-group col-sm-8">
                                            <input type="text" class="form-control file-name" placeholder="Select an image">
                                            <div class="input-group-btn">
                                                <a href="#" class="btn btn-info"><i class="icon fa fa-folder-open"></i> Browse</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="croparea">
                                        <label for="artist-image" style="display: block; text-align: center;">
                                            <i class="icon fa fa-image" style="font-size: 30px; padding:55px 0; color: #E0E0E0;"></i>
                                        </label>
                                    </div>
                                    <input type="hidden" name="cropX" value="0">
                                    <input type="hidden" name="cropY" value="0">
                                    <i>JPEG, JPG, PNG, WEBP and SVG accepted. <br>If an image aspect ratio doesn't fit, you can move the crop area.</i>
                                </div>
                            </div>
                        </div>

                        <div id="artwork-message"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">
                            <i class="icon fa fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner in-progress hidden"> Uploading...</span>
                            <span class="normal center"><i class="icon fa fa-cloud-upload"></i> Upload</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Artwork Modal -->
    <div class="modal fade import-artwork-modal" tabindex="-1" role="dialog" aria-labelledby="Import Artwork">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="index.php?page=tools" id="import-tool">
                    <input type="hidden" name="form" value="import">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="icon fa fa-download"></i> Import Artwork</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body import-tool">
                        <p>
                            Allows you to import images from various sources. All imported tracks will be renamed and resized to the appropriate format, so the player can read it.
                            Your images (the ones you will import) should use format something like "Artist - Title" or simply "Artist" otherwise the player won't read them.
                            Player will scan for files with the following extensions: {{ implode(', ', $extensions) }}.
                            <br><br>
                            <i>Note: The existing artwork with the same naming will be simply replaced with newly imported, so be careful what you import.<br> The path should be relative to the player folder.</i>
                        </p>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="col-sm-1 control-label" for="path" style="text-align: left;">Path:</label>
                            <div class="col-sm-8">
                                <input class="form-control" type="text" name="import_path" placeholder="data/artwork-images/" id="path">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <div class="col-sm-offset-1 col-sm-8">
                                <div class="help-block">You can also use FTP e.g.: ftp://username:password@ftp.server.com/path/to</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-offset-1 col-sm-11">
                                <pre class="import-output commands-pre" style="display: none; margin: 8px 0 0; max-height: 350px;"></pre>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">
                            <i class="icon fa fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner in-progress hidden"> Importing data...</span>
                            <span class="normal center"><i class="icon fa fa-download"></i> Import</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Check Artwork Modal -->
    <div class="modal fade check-artwork-modal" tabindex="-1" role="dialog" aria-labelledby="Check Artwork">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="GET" action="index.php?page=api&action=artwork-lookup" id="artworkLookup">
                    <input type="hidden" name="form" value="artwork-lookup">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="icon fa fa-download"></i> Search Artwork</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            Simple tool to search artwork from preconfigured API methods in the Settings. If the image is not found, the default image is returned.
                            This function mimic's the way player gets images and is used for simple debugging/test purposes.
                            <br>
                            <i class="text-red">Note: Artwork manager pictures have the highest priority, they will not be ignored!</i>
                        </p>

                        <div class="form-grid">
                            <div class="form-group-grid">
                                <label class="control-label" for="text">Artist Name</label>
                                <div class="col-sm-6">
                                    <input class="form-control" type="text" name="text" placeholder="Avicii" id="artist_lookup">
                                </div>
                            </div>
                            <div class="form-group-grid">
                                <label class="control-label" for="ignore_cache">Ignore cache</label>
                                <div class="col-sm-9">
                                    <div class="checkbox">
                                        <label for="override" tabindex="0">
                                            <input type="checkbox" value="true" name="ignore_cache" id="override"><span class="icon fa fa-check"></span>
                                            <span class="description">Obtain image directly from API (disable caching)</span>
                                        </label>
                                    </div>
                                </div>

                            </div>
                            <div class="form-group-grid">
                                <div></div>
                                <div class="col-sm-10" id="check-artwork-image"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close"><i class="icon fa fa-times"></i> Close</button>
                        <button type="submit" class="btn btn-success"><i class="icon fa fa-search"></i> Search Artwork</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/javascript">

        // Templates list
        let templates = @json( $templates );
        let source    = null;

        // Initial window function (executed on body load)
        window.loadInit = function() {

            // Append templates to form (simple
            $.each( templates, function( key, val ) {

                let html = $( '<option \>', {
                    value: key,
                    html : val.name
                } );

                $( '#template' ).append( html );

            } );

            // Modals
            $( '.upload-artwork-modal' ).on( 'hidden.bs.modal', function() {

                loadArtwork();
                $( '#artworkUpload' )[ 0 ].reset();
                $( '#artwork-message' ).empty();

            } );

            $( '.check-artwork-modal' ).on( 'hidden.bs.modal', function() {

                $( '#check-artwork-image' ).empty();

            } );

            $( '.import-artwork-modal' ).on( 'hidden.bs.modal', function() {

                $( '.import-output' ).empty().hide();

            } );


            /**
             * Handle artwork loading and handling
             */
            function loadArtwork() {

                $( '.artworkManager' ).find( 'tbody' ).empty();
                $.ajax( { url: "index.php?page=api", dataType: "json", cache: false, data: { action: "get-artwork" } } )
                    .then( function( data ) {

                        if ( data.length >= 1 ) {
                            $( data ).each( function( key, val ) {

                                let tableRow = $(
                                    '<tr><td><img alt="Artwork" src="./../' + val.path + '" width="24" height="24" data-preview="true" class="pull-left"></td>' +
                                    '<td class="artist-name">' + val.name + '</td><td>' + val.path + '</td><td>' + val.size + '</td>' +
                                    '<td><a href="#" class="edit-img btn btn-primary btn-small"><i class="icon fa fa-edit"></i> Replace</a> ' +
                                    ( ( val[ 'name' ].match( /default\./ ) ) ? '' : '<a href="#" class="delete-img btn btn-danger btn-small"><i class="icon fa fa-times"></i> Delete</a>' ) +
                                    '</td></tr>'
                                );

                                // Bind edit
                                tableRow.find( '.edit-img' ).on( 'click', function() {

                                    $( '.upload-artwork-modal' ).modal( 'show' );
                                    let artist_name = $( this ).closest( 'tr' ).find( '.artist-name' ).text();
                                    $( 'input#track' ).val( artist_name ).focus();
                                    $( '.croparea' ).html( `<img alt="Cropped Image" height="140" src="${$( this ).closest( 'tr' ).find( 'img' ).attr( 'src' )}" width="140">` );

                                    return false;

                                } );

                                // Bind delete
                                tableRow.find( '.delete-img' ).on( 'click', function() {

                                    let artist_name = $( this ).closest( 'tr' ).find( '.artist-name' ).text(), elm = $( this );
                                    $.get( 'index.php?page=api&action=delete-artwork&name=' + artist_name, function() {
                                        $( elm ).closest( 'tr' ).remove();
                                    } );

                                    return false;
                                } );

                                // Hover Artist Image
                                tableRow.find( 'img[data-preview]' ).on( 'mouseover', function() { // Hover

                                    let elm = $( this );

                                    // Not yet hovered before
                                    if ( !elm.next().hasClass( 'image-preview' ) ) {

                                        let imageFile = ( elm.attr( 'data-preview' ) != 'true' ) ? elm.attr( 'data-preview' ) : elm.attr( 'src' );
                                        elm.after( `<div class="image-preview"><img alt="Preview Image" height="180"  width="180" src="${imageFile}"></div>` );

                                    }

                                    elm.next( '.image-preview' ).addClass( 'in' );
                                    elm.on( 'mousemove', function( e ) { // Mouse Move
                                        let calcOffset = ( window.screen.width > 1024 ) ? 90 : 0;
                                        elm.next( '.image-preview' ).css(
                                            {
                                                'left': ( e.clientX - calcOffset ) + 'px',
                                                'top' : ( e.clientY - 190 ) + 'px'
                                            }
                                        );
                                    } );

                                } );

                                // Mouse Out on Artist image
                                tableRow.find( 'img[data-preview]' ).on( 'mouseout', function() { // Mouse Out
                                    $( this ).next( '.image-preview' ).removeClass( 'in' );
                                } );

                                $( '.artworkManager' ).find( 'tbody' ).append( tableRow );

                            } );

                        }

                        $( '.artworkManager' ).removeClass( 'hidden' );
                        $( '.loadArtwork' ).remove();

                    } );
            }

            /**
             * Bind templates to show schemes
             */
            $( '#template' ).on( 'change', function() {

                // Might need
                let elm = $( this );

                // Check a list
                if ( elm.val() != '' && typeof ( templates[ elm.val() ][ 'schemes' ] ) !== 'undefined' ) {

                    let base = $( '<select \>', { "name": "base-theme", "id": "base-theme" } );
                    $( '.base-container' ).empty().append( base );

                    // Append first "not selected" option
                    base.append( '<option value="" disabled">None</option>' );

                    // Loop through schemes
                    $.each( templates[ elm.val() ][ 'schemes' ], function( key, val ) {

                        // If no compile provided, don't show...
                        if ( typeof ( val.compile ) !== 'undefined' ) {

                            let html_option = $( '<option \>', { text: val.name } );
                            base.append( html_option );

                        }

                    } );

                    base.selectbox();
                    base.closest( '.form-group' ).removeClass( 'hidden' );

                } else {

                    $( '.base-container' ).closest( '.form-group' ).addClass( 'hidden' );

                }

            } );

            /**
             * Bind debug button
             */
            $( '.start-debug' ).on( 'click', function( e ) {

                e.preventDefault();
                let elm        = $( this );
                let closeEvent = () => {
                    elm.find( '.in-progress' ).addClass( 'hidden' );
                    elm.find( '.normal' ).removeClass( 'hidden' );
                    source.close();
                }

                // Allow stopping
                if ( source !== null && source?.readyState !== EventSource.CLOSED ) {
                    closeEvent();
                    return;
                }

                elm.find( '.in-progress' ).removeClass( 'hidden' );
                elm.find( '.normal' ).addClass( 'hidden' );

                $( '.debug-output' ).show().html( '<b>Starting a debugging session...</b><br>' );

                source = new EventSource( 'index.php?page=api&action=debug&test=' + $( '[name="debug-server"]' ).val() )
                source.addEventListener( 'debug', ( event ) => {

                    if ( event.data === 'close' ) {
                        closeEvent();
                        return;
                    }

                    $( '.debug-output' ).append( atob( event.data ) + '<br>' );

                } );

                source.onerror = ( error ) => {
                    console.error( 'EventSource failed:', error );
                    closeEvent();
                };

                return false;

            } );


            /**
             * Bind artwork uploader
             */
            $( '#artworkUpload' ).on( 'submit', function( e ) {

                e.preventDefault();
                let elm = $( this ).find( 'button' );
                elm.find( '.in-progress' ).removeClass( 'hidden' );
                elm.find( '.normal' ).addClass( 'hidden' );

                let msg  = $( '#artwork-message' )
                let file = $( this ).find( 'input[type="file"]' )[ 0 ].files[ 0 ]

                if ( !file ) {

                    msg.html( '<div class="text-danger mt-2">No file selected</div>' );
                    elm.find( '.in-progress' ).addClass( 'hidden' );
                    elm.find( '.normal' ).removeClass( 'hidden' );
                    return;

                }

                let formData = new FormData();
                formData.append( 'image', file );
                formData.append( 'track', $( this ).find( 'input[name="track"]' ).val() );
                formData.append( 'form', 'artwork' );
                formData.append( 'cropX', $( this ).find( 'input[name="cropX"]' ).val() );
                formData.append( 'cropY', $( this ).find( 'input[name="cropY"]' ).val() );

                $.ajax(
                    {
                        url        : '?page=tools',
                        type       : 'POST',
                        data       : formData,
                        processData: false,
                        contentType: false,
                    }
                ).then( function( response ) {

                    console.log( response );
                    msg.html( '<div class="text-success mt-2">' + response + '</div>' );
                    elm.find( '.in-progress' ).addClass( 'hidden' );
                    elm.find( '.normal' ).removeClass( 'hidden' );

                    // Reset form
                    $( '#artworkUpload' )[ 0 ].reset();
                    $( '.croparea' ).html( '<label for="artist-image" style="display: block; text-align: center;">' +
                                           '<i class="icon fa fa-image" style="font-size: 30px; padding:55px 0; color: #e0e0e0;"></i></label>' );

                } ).fail( function( jqXHR ) {

                    msg.html( '<div class="text-danger mt-2">' + jqXHR.responseText + '</div>' );
                    elm.find( '.in-progress' ).addClass( 'hidden' );
                    elm.find( '.normal' ).removeClass( 'hidden' );

                } );

            } );

            /**
             * Bind an upload artwork form
             */
            $( '#compileColorScheme' ).on( 'submit', function() {

                let elm = $( this ).find( 'button' );
                elm.find( '.in-progress' ).removeClass( 'hidden' );
                elm.find( '.normal' ).addClass( 'hidden' );

            } );

            /**
             * Bind import button
             * ftps://pawtunes:0hp&nQ089@ftp.defikon.com/
             */
            $( 'form#import-tool' ).on( 'submit', function( e ) {

                e.preventDefault();
                if ( source !== null && source?.readyState !== EventSource.CLOSED ) {
                    return false;
                }

                let elm        = $( this );
                let output     = $( '.import-output' );
                let closeEvent = () => {
                    elm.find( '.in-progress' ).addClass( 'hidden' );
                    elm.find( '.normal' ).removeClass( 'hidden' );
                    source.close();
                }

                elm.find( '.in-progress' ).removeClass( 'hidden' );
                elm.find( '.normal' ).addClass( 'hidden' );

                output.show().html( '<b>Starting Artwork Import, hand tight this may take a while...</b><br>' );

                source = new EventSource( 'index.php?page=api&action=import-artwork&path=' + encodeURIComponent( $( '[name="import_path"]' ).val() ) )
                source.addEventListener( 'artwork', function( event ) {

                    if ( event.data === 'close' ) {
                        closeEvent();
                        return;
                    }

                    output.append( atob( event.data ) + '<br>' );

                } );

                source.onerror = function( error ) {
                    console.error( 'EventSource failed:', error );
                    closeEvent();
                };

                return false;

            } );

            /**
             * When artist artwork browse is changed, show preview/crop
             */
            $( "input[type='file']" ).on( "change", function() {

                    // Change form input
                    let cVal = $( this ).val().replace( /.*\\fakepath\\/, '' );
                    $( this ).parent( '.file-input' ).find( 'input.file-name' ).val( cVal );

                    // Preview image and crop area
                    let url = $( this ).val();
                    let ext = url.substring( url.lastIndexOf( '.' ) + 1 ).toLowerCase();

                    if ( this.files && this.files[ 0 ] && ( ext == "svg" || ext == "png" || ext == "jpeg" || ext == "jpg" || ext == "webp" ) ) {

                        let reader = new FileReader();
                        let image  = new Image();

                        reader.onload = function( e ) {

                            image.src    = e.target.result;
                            image.onload = function() {
                                $( '.croparea' ).imagearea( this.src, { width: 140, height: 140 } );
                            };
                        };

                        reader.readAsDataURL( this.files[ 0 ] );

                    }
                }
            );

            /**
             * Handle colour schemes loading/deleting
             */
            $.ajax( { url: "./index.php?page=api", dataType: "json", cache: false, data: { action: "get-themes-list" } } )
                .then( function( data ) {

                    let manager = $( '.schemes-manager' );
                    if ( data.length >= 1 ) {

                        $( data ).each( function( index, scheme ) {

                            let tableRow = $( `<tr>
                                <td>${scheme.name.replace( /\.css$/, '' )}</td>
                                <td>${scheme.template}</td>
                                <td class="scheme-path">${scheme.path}</td>
                                <td>${scheme.size}</td>
                                <td>
                                    <a class="view btn btn-primary btn-small css-hint" data-title="Minified source code" href="#">
                                        <i class="icon fa fa-external-link"></i> View
                                    </a>
                                    <a class="delete btn btn-danger btn-small" href="#"><i class="icon fa fa-times"></i> Delete</a>
                                </td>
                            </tr>` );

                            // Bind view
                            tableRow.find( '.view' ).on( 'click', function() {

                                let path = $( this ).closest( 'tr' ).find( '.scheme-path' ).text();
                                window.open( './../' + path, '_blank' );
                                return false;

                            } );

                            // Bind delete
                            tableRow.find( '.delete' ).on( 'click', function() {

                                if ( confirm( 'Are you sure?' ) ) {

                                    let path = $( this ).closest( 'tr' ).find( '.scheme-path' ).text();
                                    let elm  = $( this );

                                    $.get( './index.php?page=api&action=delete-theme&path=' + encodeURI( path ) ).then( function( response ) {
                                        if ( response && response.success ) {

                                            $( elm ).closest( 'tr' ).remove();
                                            if ( manager.find( 'tbody > tr' ).length < 1 ) {
                                                manager.hide();
                                            }

                                        } else {

                                            alert( 'Error deleting theme' );

                                        }
                                    } );

                                }

                                return false;

                            } );


                            // Mouse Out on Artist image
                            manager.find( 'tbody' ).append( tableRow );

                        } );

                    }

                    if ( manager.find( 'tbody > tr' ).length >= 1 ) {
                        manager.show();
                    }

                    $( '.scheme-manager-preloader' ).hide();

                } );

            /**
             * Search artwork
             */
            $( '#artworkLookup' ).on( 'submit', function( e ) {

                    // Set preloader
                    e.preventDefault();
                    $( '#check-artwork-image' ).html( '<div class="preloader-spin align-middle"></div>' );

                    // Get image
                    $.get( 'index.php?page=api&action=artwork-lookup&' + $( this ).serialize() ).done( function( response ) {

                        // On success
                        if ( response.artwork ) {

                            // Fix local images
                            if ( response.artwork.startsWith( './data/' ) )
                                response.artwork = response.image = './../' + response.artwork;

                            // Append image to modal
                            $( '#check-artwork-image' )
                                .append( '<img src="' + response.artwork + '" style="display:none; max-width: 100%;" ' +
                                         'alt="Artwork" class="align-middle" onload="$(this).prev(\'div\').remove(); this.style.display=\'block\'">' );

                        }

                        if ( response.error ) {

                            $( '#check-artwork-image' ).html( '<div class="alert alert-danger">Failed to get Artwork image, reason: ' + response.error + '</div>' );

                        }

                    } ).fail( function( e ) {

                        // Different possible messages
                        let msg = e.statusText;
                        if ( e.responseJSON && e.responseJSON.error ) {

                            msg = e.responseJSON.error;

                        } else if ( e.responseText ) {

                            msg = e.responseText;

                        }

                        $( '#check-artwork-image' ).html( '<div class="alert alert-danger">Failed to get Artwork image, reason: <pre>' + msg + '</pre></div>' );

                    } );

                    return false;

                }
            );

            $( "#base-color" ).spectrum(
                {
                    preferredFormat       : "hex",
                    appendTo              : '.modal.compile-custom-scheme',
                    showPalette           : true,
                    hideAfterPaletteSelect: true,
                    showInput             : true,
                    palette               : [
                        [ '#1abc9c', '#16a085', '#2ecc71', '#27ae60' ],
                        [ '#3498db', '#2980b9', '#9b59b6', '#9b50ba' ],
                        [ '#34495e', '#2c3e50', '#f1c40f', '#f39c12' ],
                        [ '#e74c3c', '#c0392b', '#ecf0f1', '#bdc3c7' ],
                        [ '#95a5a6', '#7f8c8d' ]
                    ]
                }
            );

            $( "#bg-color" ).spectrum(
                {
                    preferredFormat       : "hex",
                    showPalette           : true,
                    hideAfterPaletteSelect: true,
                    appendTo              : '.modal.compile-custom-scheme',
                    showInput             : true,
                    palette               : [
                        [ '#1abc9c', '#16a085', '#2ecc71', '#27ae60' ],
                        [ '#3498db', '#2980b9', '#9b59b6', '#9b50ba' ],
                        [ '#34495e', '#2c3e50', '#f1c40f', '#f39c12' ],
                        [ '#e74c3c', '#c0392b', '#ecf0f1', '#bdc3c7' ],
                        [ '#95a5a6', '#7f8c8d' ]
                    ]
                }
            );

            loadArtwork();

        };
    </script>
    <script type="text/javascript" src="./assets/js/jquery.imagecrop.min.js"></script>
    <link href="./assets/css/spectrum.min.css" rel="stylesheet" type="text/css">
@endsection
@include('template')