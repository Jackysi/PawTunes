<?php

    /**
     * @var $languages
     * @var $channels
     * @var $templates
     * @var $templatesList
     * @var $pawtunes
     */

    $form = new Forms;

    // General Player Settings
    $general = [
        [
            'label'       => 'Player Title',
            'name'        => 'title',
            'placeholder' => 'PawTunes',
            'size'        => 64,
            'description' => '(SEO)',
        ],
        [
            'label'       => 'Player Description',
            'name'        => 'description',
            'type'        => 'textarea',
            'description' => '(SEO)',
        ],
        [
            'label'       => 'Site Title',
            'name'        => 'site_title',
            'size'        => 64,
            'placeholder' => 'PawTunes Radio',
            'description' => '(Optional, more at <a href="http://ogp.me/" target="_blank">http://ogp.me/</a>)',
        ],
        [
            'label'       => 'Cache Path',
            'name'        => 'cache_path',
            'class'       => 'col-sm-5',
            'value'       => './data/cache',
            'placeholder' => './data/cache',
            'reset'       => true,
            'description' => ' (Images and API cache)',
        ],
        [
            'label'       => 'Google Analytics',
            'name'        => 'google_analytics',
            'placeholder' => 'UA-1113571-5',
            'description' => '(Tracking ID)',
        ],
        'override-share-image',
        [
            'label'       => 'Search Engine Index',
            'name'        => 'disable_index',
            'value'       => "true",
            'type'        => 'checkbox',
            'class'       => 'col-sm-9',
            'description' => 'Disable search engine indexing (does not instantly remove search results, more at <a href="https://support.google.com/webmasters/answer/93710?hl=en" target="_blank">Google FAQ</a>)',
        ],
        'default_language',
        [
            'label'       => 'Multi-language Support',
            'name'        => 'multi_lang',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' When checked, player will also support auto language detection based on browser settings.',
        ],
        [
            'label'       => 'Auto Play',
            'name'        => 'autoplay',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Start playback automatically (some devices and browsers do not support this feature)',
        ],
        [
            'label'   => 'Debug Mode',
            'class'   => 'col-sm-4',
            'name'    => 'debugging',
            'type'    => 'select',
            'options' => [ 'log-only' => 'Logging only (recommended)', 'enabled' => 'Enabled', 'disabled' => 'Disabled' ],
        ],
        [
            'label'       => 'Initial Channel',
            'name'        => 'default_channel',
            'class'       => 'col-sm-4',
            'type'        => 'select',
            'description' => ' (Used when no hash or previous selection present)',
            'options'     => $channels,
        ],
        [
            'label'       => 'Initial Volume',
            'name'        => 'default_volume',
            'class'       => 'col-sm-2',
            'type'        => 'number',
            'description' => 'in percent % (only used for new listeners)',
            'max'         => '100',
            'min'         => 0,
            'placeholder' => '50',
            'reset'       => true,
        ],
    ];

    // Track Information
    $trackInfo = [
        [
            'label' => 'Default Artist',
            'name'  => 'artist_default', 'placeholder' => 'Various Artists',
            'class' => 'col-sm-4', 'description' => '(if there is no stream information or stat\'s is not responding, this will be shown)',
        ],
        [
            'label' => 'Default Title',
            'name'  => 'title_default', 'placeholder' => 'Unknown Track',
            'class' => 'col-sm-4', 'description' => '(if there is no stream information or stat\'s is not responding, this will be shown)',
        ],
        [
            'label'       => 'Dynamic Title',
            'name'        => 'dynamic_title',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Dynamic popup window title (show currently playing Track in window title bar)',
        ],
        [

            'label'       => 'Artist Max Length',
            'name'        => 'artist_maxlength', 'placeholder' => 48,
            'class'       => 'col-sm-2',
            'description' => '<b>0 = disabled</b> (maximum number of characters before shortening artist name)',
            'type'        => 'number',
        ],
        [
            'label'       => 'Title Max Length',
            'name'        => 'title_maxlength',
            'placeholder' => 58,
            'class'       => 'col-sm-2',
            'description' => '<b>0 = disabled</b> (maximum number of characters before shortening track name)',
            'type'        => 'number',
        ],
        // Little hacky
        'stats_refresh',
        [
            'label'       => 'Player API',
            'name'        => 'api',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Enable support for external JSONP API requests (<a target="_blank" href="https://doc.prahec.com/pawtunes#live-information-external-api"><i class="icon fa fa-question-circle"></i> Documentation</a>)',
        ],
        [
            'label'       => 'Artist/Title regex',
            'name'        => 'track_regex',
            'class'       => 'col-sm-5',
            'placeholder' => "(?P<artist>[^-]*)[ ]?-[ ]?(?P<title>.*)",
            'reset'       => true,
            'description' => '<span class="text-red">(only change if you know what you are doing)</span>',
        ],
        [
            'label'       => 'Show Track History',
            'name'        => 'history',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' When enabled listeners will be able to see their playback history or from stream (if supported/enabled)',
        ],
    ];

    $artworks = [
        [
            'label'       => 'Cache Images',
            'name'        => 'cache_images',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Cache images on the server (crop & optimize artwork images)',
        ],
        [
            'label'       => 'Serve via Web',
            'name'        => 'serve_via_web',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Uses Apache/LiteSpeed (X-Sendfile) or Nginx (X-Accel-Redirect) to serve images, significantly reducing server load.',
        ],
        [
            'label'       => 'Artist Images Mode',
            'name'        => 'artist_images_only',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' When selected, only images for artists will be cached (greatly reduces artwork collection)',
        ],
        [

            'label'       => 'Lazy Artwork Loading',
            'name'        => 'artwork_lazy_loading',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => ' Lazy loading of artwork images (artwork is lazy loaded after track info is displayed)',
        ],
    ];

    $others = [
        [
            'label'        => 'Username',
            'name'         => 'admin_username',
            'class'        => 'col-sm-4',
            'placeholder'  => 'admin',
            'autocomplete' => 'new-username',
        ],
        [
            'label'        => 'Password',
            'name'         => 'admin_password',
            'class'        => 'col-sm-4',
            'type'         => 'password',
            'placeholder'  => 'min. 5 characters',
            'autocomplete' => 'new-password',
        ],
        [
            'label'        => 'Confirm  Password',
            'name'         => 'admin_pass2',
            'class'        => 'col-sm-4',
            'type'         => 'password',
            'placeholder'  => 'min. 5 characters',
            'autocomplete' => 'new-alt-password',
        ],

    ];
?>
@section('content')
    <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">

        {!! $message !!}
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-screwdriver-wrench"></i> General
            </div>
            <div class="content form-content">
                @foreach ( $general as $input )

                    @if ( $input === 'override-share-image' )
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="override_share_image">Override Share Image</label>
                            <div class="col-sm-10">
                                <div class="input-group col-sm-6">
                                    <input type="text" name="override_share_image" id="override_share_image" class="form-control" value="{{$_POST[ 'override_share_image' ] ?? ''}}">
                                    <div class="input-group-btn">
                                        <label for="override_image" class="btn btn-primary">
                                            <input type="file" id="override_image" class="hidden" name="override_image"><i class="icon fa fa-folder-open"></i> Browse
                                        </label>
                                    </div>
                                </div>
                                <i>JPEG, JPG, PNG, WEBP and SVG accepted. You can also provide URL to the image!</i>
                                @if ( !empty( $_POST[ 'override_share_image' ] ) && is_file( $_POST[ 'override_share_image' ] ) )
                                    <div class="logo-container"><br>
                                        <div class="override-image">
                                            <img src="{{$_POST[ 'override_share_image' ]}}" width="auto" height="80">
                                        </div>
                                        <a href="#" class="delete-override"><i class="icon fa fa-times"></i> Delete</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @continue
                    @endif

                    @if ( $input === 'default_language' )
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="isocode">Default Language</label>
                            <div class="col-sm-4">
                                <select name="default_lang" class="form-control" id="isocode">
                                    @foreach( $languages as $index => $lang )
                                        <option data-html="<i class='fi ico-left fi-{{$lang['flag']}}'></i> {{$lang['name']}}" value="{{$index}}"{{( ( $index === $_POST['default_lang' ] ) ? 'selected' : '' )}}>
                                            {{$lang['name']}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="help-block">(Used if language is not found or Multi-language support is disabled)</div>
                        </div>
                        @continue
                    @endif

                    {!! $form->field( $input ) !!}
                @endforeach

                <!-- Templates (not generated in Form class, so we can add btn to reset cache) -->
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="template">Template</label>
                    <div class="col-sm-4 pe-0">
                        <select name="template" class="form-control" id="template">
                            @foreach ( $templatesList as $index => $name )
                                <option value="{{$index}}"{!! ( ( $index === $_POST[ 'template' ] ) ? 'selected' : '' ) !!}>{{$name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <a href="index.php?page=settings&reset=templates" class="btn btn-danger">
                            <i class="icon fa fa-trash-can"></i> Flush Cache
                        </a>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-8 col-sm-offset-2">
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#template-options">
                            <i class="icon fas fa-gear"></i> Template Options
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-server"></i> Live Information
            </div>
            <div class="content form-content">
                @foreach($trackInfo as $info)

                    @if ($info === 'stats_refresh')
                        <div class="form-group"><label for="stats_refresh" class="col-sm-2 control-label">Stats refresh speed</label>
                            <div class="col-sm-2">
                                <div class="input-append">
                                    <div class="append">sec</div>
                                    <input type="number" name="stats_refresh" class="form-control" id="stats_refresh" placeholder="15" required="" value="{{$_POST[ 'stats_refresh' ]}}">
                                </div>
                            </div>
                            <div class="help-block">(Default: 15 - <span class="text-red">Caution: this may have big performance impact on your web server!</span>)</div>
                        </div>
                        @continue
                    @endif

                    {!! $form->field($info); !!}
                @endforeach
            </div>
        </div>
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-photo-video"></i> Artwork Settings
            </div>
            <div class="content form-content">

                {!! $form->generateForm( $artworks ) !!}

                <div class="form-group"><label class="col-sm-2 control-label" for="images_size">Artist images size</label>
                    <div class="col-sm-2">
                        <div class="input-append">
                            <div class="append">pix</div>
                            <input class="form-control" id="images_size" name="images_size" placeholder="280" type="number" value="{{( !empty( $_POST[ 'images_size' ] ) ? $_POST[ 'images_size' ] : '' )}}">
                        </div>
                    </div>
                    <div class="help-block">(Default: 280 - <span class="text-red">Caution: this may have big performance impact on your web server!</span>)</div>
                </div>

                <div class="divider mt-5"></div>
                <h5>Artwork Sources</h5>
                <p>
                    Configure order and settings for each artwork system, drag & drop to change the order. Checkboxes are used to enable/disable the artwork source.
                    If all checkboxes are left unchecked, the artwork system will be disabled and only default artwork will be shown.
                    <span class="text-danger">Note: This should be used with caution, if you have a lot of switching between various tracks, it may cause many requests to the APIs.</span>
                </p>
                <table class="table hover artwork-apis sortable-table mt-4 vertical-center">
                    <tbody>
                        @foreach($artworkAPIs as $key => $api)
                            <tr>
                                <td style="width: 2rem">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="{{$key}}[state]" value="enabled" {!! ( ( isset($api['state']) && $api['state'] === 'enabled' ) ? 'checked' : '' ) !!}>
                                            <span class="icon fa fa-check"></span>
                                        </label>
                                    </div>
                                </td>
                                <td class="sort-handle-cell">
                                    <i class="fas sort-handle fa-grip-vertical"></i>
                                </td>
                                <td style="width:30%">
                                    <input type="hidden" name="{{$key}}[index]" value="$key">
                                    <b>{{$api['name']}}</b>
                                </td>
                                <td class="table-input">
                                    @if (isset($api['field']))
                                        <div class="input-append">
                                            <input class="form-control" type="text"
                                                   name="{{$key}}[{{$api['field']['name']}}]"
                                                   placeholder="{{$api['field']['placeholder']}}"
                                                   value="{{( !empty( $api[ $api['field']['name'] ] ) ? $api[$api['field']['name'] ] : '' )}}">
                                            <div class="append css-hint left" data-title="{{$api['field']['helpText'] ?? $api['field']['placeholder']}}">
                                                @if (!empty($api['field']['helpURL']))
                                                    <a href="{{$api['field']['helpURL']}}" target="_blank">
                                                        <i class="fas fa-external-link"></i>
                                                    </a>
                                                @else
                                                    <i class="fas fa-question-circle"></i>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-tachometer-alt"></i> Control Panel
            </div>
            <div class="content form-content">
                @php $form->generateForm( $others ) @endphp
                <div class="row">
                    <div class="col-sm-9 col-sm-offset-2">
                        <b>Note</b>: Your password will be one-way encrypted (hashed).
                                   To regain access, remove "admin_password" configuration key from the configuration file (<strong>inc/config/general.php</strong>).
                                   That will reset the password to default (password).
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <button type="submit" class="btn btn-success"><i class="icon fa fa-floppy-disk"></i> Save</button>
                <a href="index.php?page=settings" class="btn btn-danger"><i class="icon fa fa-times"></i> Cancel</a>
            </div>
        </div>
    </form>

    <!-- Advanced Template(s) Options Modal -->
    <div class="modal fade" tabindex="-1" role="dialog" id="template-options" aria-labelledby="Advanced Template(s) Options">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="icon fas fa-cog"></i> Template(s) Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="tabs nav nav-tabs templates-tabs">
                        @foreach ( $templates as $index => $data )
                            <li>
                                <a {!! ( ( $index === array_keys( $templates )[ 0 ] ) ? ' class="active"' : '' ) !!}data-bs-toggle="tab"
                                   data-bs-target="#{{$index}}" href="#{{$index}}">{{$data[ 'name' ]}}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <!-- Tab panes -->
                    <div class="tab-content">
                        @foreach ( $templates as $index => $data )
                            <div role="tabpanel" id="{{$index}}" class="tab-pane{{( ( $index === array_keys( $templates )[ 0 ] ) ? ' active' : '' )}}">
                                <form method="POST" data-action="index.php?page=settings&advanced-options" class="templateOptions" enctype="multipart/form-data">
                                    @if ( empty( $data[ 'extra' ] ) )
                                        {!! $panel->alert( 'Sorry, there are no advanced options available for this template.', 'info' ) !!}
                                    @else
                                        <input type="hidden" name="template" value="{{$index}}">
                                        {{-- Parse selected options --}}
                                        @php $opts = $pawtunes->getAdvancedTemplateOptions( $index ); @endphp
                                        @foreach ( $data[ 'extra' ] as $key => $value )
                                            @php
                                                echo $form->add([ 'label-full' => true, 'class'=> 'col-sm-12'] + $value,$opts[ $key ] ?? null);
                                            @endphp
                                        @endforeach
                                    @endif
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">
                        <i class="icon fa fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">

        // Bind document ready
        $( document ).ready( function() {

            $( '.table.artwork-apis' ).rowSorter( {
                handler: '.sort-handle',
            } );

            // Delete existing logo
            $( '.delete-override' ).on( 'click', function() {

                let elm = $( this );
                $.get( 'index.php?page=settings&delete=override-share-image', function() {
                    $( elm ).closest( '.logo-container' ).remove();
                } );

                return false;

            } );

            // Change input value for browse
            $( 'input[type="file"]' ).on( 'change', function() {
                let cVal = $( this ).val().replace( /.*\\fakepath\\/, '' );
                let elm  = $( this ).closest( '.form-group' );
                elm.find( 'input#override_share_image' ).val( cVal );
                elm.find( '.logo-container' ).remove();
            } );

            let timeout = null;
            $( '.templateOptions' ).find( 'select, input, textarea' ).each( function() {
                let form = $( this ).closest( 'form' );
                $( this ).on( 'change', function() {

                    clearTimeout( timeout );
                    timeout = setTimeout( function() {

                        $.post( $( form ).attr( 'data-action' ), $( form ).serialize(), function() {}, 'json' );

                    }, 600 );

                } );
            } );

        } );

    </script>
@endsection
@include('template')