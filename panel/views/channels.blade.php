@section('content')
    @if ( isset( $mode ) && $mode === 'custom' )
        {!!$panel->alert( 'Drag & Drop table rows to change channels sorting.', 'info' )!!}
    @endif

    @if ( isset( $mode ) && $mode === 'custom' )
        <form method="POST" action="index.php?page=channels&sort=custom">
            @endif

            @if ( count( $channels ) <= 0 )
                {!!$panel->alert( 'You did not yet configure any channels, please do that first.' )!!}
            @endif
            {!! $message !!}

            <div class="panel">
                <div class="content">
                    <p>
                        PawTunes supports multichannel configuration(s) but If a single channel is configured or a single stream, the player will hide the unused buttons.
                        Other settings that affect all channels are covered in the <b>Settings tab</b>.
                    </p>
                    @if ( count( $channels ) >= 1 )
                        <div class="overflow-auto">
                            <table class="table vertical-center hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th class="col-sm-3">Channel Name</th>
                                        <th class="mobile-hidden">Last Cache Entry</th>
                                        <th>Info Type</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach( $channels as $key => $channel )

                                        @php
                                            $method = str_replace( '-', ' ', ( ( empty( $channel[ 'stats' ][ 'method' ] ) ) ? 'disabled' : $channel[ 'stats' ][ 'method' ] ) );
                                        @endphp

                                        @if ( ( $cached = $pawtunes->cache->get( 'stream.info.historic.' . $key ) ) !== false )
                                            @php
                                                $cached_song = $pawtunes->shorten( $cached[ 'artist' ] . ' - ' . $cached[ 'title' ], 60 );
                                            @endphp
                                        @else
                                            @php
                                                $cached_song = 'Cache for this channel is outdated or non-existing.';
                                            @endphp
                                        @endif

                                        <tr>
                                            <td>{{( $key + 1 )}} <input type="hidden" name="ids[]" value="{{$key}}"></td>
                                            <td>{{$channel[ 'name' ]}}</td>
                                            <td class="mobile-hidden"><i>{{$cached_song}}</i></td>
                                            <td>{{ucwords( $method )}}</td>
                                            <td class="text-right">
                                                <a class="btn btn-primary btn-small" href="index.php?page=channels&action=edit&channel={{$key}}"><i class="icon fa fa-edit"></i> Edit</a>
                                                <a class="btn btn-danger btn-small" onclick="return confirm('Are you sure?');" href="index.php?page=channels&delete&id={{$key}}">
                                                    <i class="icon fa fa-times"></i> Delete
                                                </a>
                                            </td>
                                        </tr>

                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="dropdown pull-right mobile-nofloat">
                <button type="button" class="btn btn-warning dropdown-toggle"> Change Sorting <i class="icon fa fa-angle-down icon-right"></i></button>
                <ul class="dropdown-menu" role="menu">
                    <li><a href="index.php?page=channels&sort=asc"><i class="icon fa fa-chevron-up"></i> Sort Ascending</a></li>
                    <li><a href="index.php?page=channels&sort=desc"><i class="icon fa fa-chevron-down"></i> Sort Descending</a></li>
                    <li><a href="index.php?page=channels&sort=custom"><i class="icon fa fa-fire"></i> Custom</a></li>
                </ul>
            </div>
            <a onclick="return confirm('Are you sure?');" href="index.php?page=channels&cache=flush" class="btn btn-danger pull-right mobile-nofloat" style="margin-right: 8px;">
                <i class="icon fa fa-trash"></i> Flush Cache
            </a>
            @if ( isset( $mode ) && $mode === 'custom' )
                <a class="btn btn-success mt-2" onclick="$(this).closest('form').submit(); return false;" href="index.php?page=channels"><i class="icon fa fa-floppy-disk"></i> Save Changes</a>
                <a class="btn btn-danger mt-2" href="index.php?page=channels"><i class="icon fa fa-times"></i> Cancel</a>
        </form>
        <script type="text/javascript">
            window.addEventListener( 'load', function() {
                $( '.table' ).rowSorter( /*options*/ );
                $( '.table' ).addClass( 'sortable-table' ).addClass( 'entire' ).removeClass( 'hover' );
            } )
        </script>
    @else
        <a class="btn btn-success mobile-mt" href="index.php?page=channels&action=add"><i class="icon fa fa-plus"></i> Add Channel</a>
    @endif
@endsection
@include('template')