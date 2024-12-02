@section('content')
    <div class="log-content row">
        <div class="col-sm-4 col-sm-offset-4">
            <div class="text-center">
                <div class="mt-2">
                    <strong class="loadPercent">Loading, please wait...</strong>
                    <div class="loadProgress progress-bar progress-sm" style="display: none;">
                        <div class="bar primary"></div>
                    </div>
                </div>
                <div class="preloader-spin align-middle"></div>
            </div>
        </div>
    </div>
    <div class="panel panel-log hidden">
        <div class="heading">Player Log</div>
        <div class="content">
            <div id="player-log">
                <div class="commands-pre">
                    <pre>Loading...</pre>
                </div>
            </div>
            <br><a class="btn btn-danger deleteLog" href="#"><i class="icon fa fa-times"></i> Delete Log</a>
        </div>
    </div>
    <script type="text/javascript">
        window.loadInit = function() {

            // Delete log
            $( '.deleteLog' ).on( 'click', function() {

                if ( !confirm( 'Are you sure you wish to delete the file?' ) ) return false;
                $( this ).text( 'Deleting...' );
                let t = $( this );
                $.getJSON( './index.php?page=api&action=delete-log' ).done( function( r ) {

                    t.remove();
                    if ( !r.success ) alert( "Unable to delete log file, please remove file manually!" );
                    window.location.href = './index.php';

                } );

            } );

            // Request options
            let ajax_options = {
                xhr: function() {
                    let xhr = new window.XMLHttpRequest();

                    // Download progress
                    xhr.addEventListener( "progress", function( evt ) {
                        if ( evt.lengthComputable ) {

                            let percentComplete = Math.round( evt.loaded / evt.total * 100 );

                            // Do something with download progress
                            $( '.loadProgress' ).show().find( '.bar' ).width( percentComplete + '%' );
                            $( '.loadPercent' ).text( 'Loading (' + percentComplete + '%), please wait...' );

                        }
                    }, false );

                    return xhr;

                },
                url: './index.php?page=api&action=get-log'
            };

            $.ajax( ajax_options ).then( function( data ) {

                $( '.panel-log' ).removeClass( 'hidden' );
                $( '.log-content' ).hide();

                // Parse log contents
                if ( data !== '' ) {

                    $( '.commands-pre pre' ).html( data );

                } else {

                    $( '.commands-pre pre' ).html( "Your log is empty, that's a good thing!" );

                }

                return false;

            } ).catch( function( xhr, status ) {

                $( '.log-content' ).html( '{!! $panel->alert( 'Connection to the update server has failed! See the details below: <pre id="ajax-error"></pre>', 'error' ) !!}}' );

                let logText;
                switch ( status ) {

                    case 'timeout':
                        logText = 'Request timed out, this is probably server error and you will have to check log file manually!';
                        break;

                    default:
                        logText = status;
                        break;

                }

                $( 'pre#ajax-error' ).html( logText );

            } );

        };

    </script>
@endsection
@include('template')