@php

    /**
     * @var $languages
     * @var $channels
     * @var $templates
     * @var $selectedTemplate
     * @var $pawtunes
     * @var array $values  Field values by field name: the stored settings, or the form as it was just submitted
     */

    $form = new Forms( $values );

    // General Player Settings
    // Inline text stays one short line, longer explanations go into 'hint' (an info icon tooltip next to the label)
    $general = [
        [
            'label'       => 'Player Title',
            'name'        => 'title',
            'placeholder' => 'PawTunes',
            'size'        => 64,
            'hint'        => 'Shown in the browser tab, search results and playlist files.',
        ],
        [
            'label' => 'Player Description',
            'name'  => 'description',
            'type'  => 'textarea',
            'hint'  => 'Meta description used by search engines and link previews.',
        ],
        [
            'label'       => 'Site Title',
            'name'        => 'site_title',
            'size'        => 64,
            'placeholder' => 'PawTunes Radio',
            'description' => 'Optional',
            'hint'        => 'Site name shown in social media link previews (Open Graph og:site_name).',
        ],
        [
            'label'       => 'Google Analytics',
            'name'        => 'google_analytics',
            'placeholder' => 'G-XXXXXXXXXX',
            'description' => 'Measurement ID',
        ],
        'override-share-image',
        'app-icon',
        [
            'label'       => 'Search Engine Index',
            'name'        => 'disable_index',
            'value'       => "true",
            'type'        => 'checkbox',
            'class'       => 'col-sm-9',
            'description' => 'Hide the player from search engines',
            'hint'        => 'Adds a noindex robots tag. Pages that are already indexed only disappear after search engines crawl them again.',
        ],
        'default_language',
        [
            'label'       => 'Multi-language',
            'name'        => 'multi_lang',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Detect the listener\'s language from the browser',
        ],
        [
            'label'       => 'Auto Play',
            'name'        => 'autoplay',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Start playback when the player opens',
            'hint'        => 'Many browsers and devices block autoplay with sound until the listener interacts with the page.',
        ],
        [
            'label'       => 'Casting',
            'name'        => 'cast',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Show Chromecast and AirPlay buttons',
            'hint'        => 'Chromecast loads Google\'s Cast SDK from gstatic.com in Chromium browsers (Chrome, Edge, Opera, Android), which may need a mention in your privacy policy (GDPR). AirPlay uses Safari\'s built-in support and loads nothing.',
        ],
        [
            'label'   => 'Debug Mode',
            'class'   => 'col-sm-4',
            'name'    => 'debugging',
            'type'    => 'select',
            'options' => [ 'log-only' => 'Logging only (recommended)', 'enabled' => 'Enabled', 'disabled' => 'Disabled' ],
            'hint'    => 'Enabled shows error details in the player, use it only while troubleshooting. Logs are written to data/logs.',
        ],
        [
            'label'   => 'Initial Channel',
            'name'    => 'default_channel',
            'class'   => 'col-sm-4',
            'type'    => 'select',
            'options' => $channels,
            'hint'    => 'Played when the link names no channel and the listener has no saved choice.',
        ],
        [
            'label'       => 'Initial Volume',
            'name'        => 'default_volume',
            'class'       => 'col-sm-2',
            'type'        => 'number',
            'description' => 'Percent, new listeners only',
            'max'         => '100',
            'min'         => 0,
            'placeholder' => '50',
            'reset'       => true,
        ],
    ];

    // Cache (general.php "cache" array). The script below shows only the rows the chosen mode uses (see cacheRows).
    $cacheSettings = [
        'cache_status',
        [
            'label'   => 'Cache Mode',
            'name'    => 'cache_mode',
            'class'   => 'col-sm-4',
            'type'    => 'select',
            'options' => $cacheModeOptions,
            'hint'    => 'Where the player keeps track info, artwork lookups and the templates list. Disk works everywhere. APCu, Redis and Memcached keep it in memory, which helps busy players. When the chosen backend fails, PawTunes uses the disk cache until it is back.',
        ],
        [
            'label'       => 'Cache Path',
            'name'        => 'cache_path',
            'class'       => 'col-sm-5',
            'placeholder' => './data/cache',
            'reset'       => true,
            'description' => 'Empty uses the default',
            'hint'        => 'Folder for the disk cache and downloaded artwork, relative to the PawTunes folder. Also the fallback for the other modes, so it must be writable in every mode.',
        ],
        [
            'label'       => 'Server',
            'name'        => 'cache_host',
            'class'       => 'col-sm-4',
            'placeholder' => '127.0.0.1:6379',
            'description' => 'host:port or socket path',
            'hint'        => 'Without a port Redis uses 6379 and Memcached 11211. A unix socket path such as /var/run/redis/redis.sock works too.',
        ],
        [
            'label'        => 'Redis Password',
            'name'         => 'cache_auth',
            'class'        => 'col-sm-4',
            'type'         => 'password',
            'autocomplete' => 'new-password',
            'description'  => 'Optional',
            'hint'         => 'For Redis servers with requirepass. An ACL user with a password is set in inc/config/general.php instead (cache > extra > auth as [user, password]).',
        ],
        [
            'label'       => 'Key Prefix',
            'name'        => 'cache_prefix',
            'class'       => 'col-sm-4',
            'placeholder' => $pawtunes->defaultCachePrefix(),
            'description' => 'Empty uses the default',
            'hint'        => 'Starts every cache key, so several PawTunes installs can share one server. The default is derived from this install\'s folder.',
        ],
    ];

    // Track Information
    $trackInfo = [
        [
            'label'       => 'Default Artist',
            'name'        => 'artist_default',
            'placeholder' => 'Various Artists',
            'class'       => 'col-sm-4',
            'hint'        => 'Shown when the stream has no track information or its stats server does not respond.',
        ],
        [
            'label'       => 'Default Title',
            'name'        => 'title_default',
            'placeholder' => 'Unknown Track',
            'class'       => 'col-sm-4',
            'hint'        => 'Shown when the stream has no track information or its stats server does not respond.',
        ],
        [
            'label'       => 'Dynamic Title',
            'name'        => 'dynamic_title',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Show the playing track in the window title',
        ],
        [
            'label'       => 'Artist Max Length',
            'name'        => 'artist_maxlength',
            'placeholder' => 48,
            'class'       => 'col-sm-2',
            'description' => '0 = no limit',
            'hint'        => 'Longer artist names are shortened with an ellipsis.',
            'type'        => 'number',
        ],
        [
            'label'       => 'Title Max Length',
            'name'        => 'title_maxlength',
            'placeholder' => 58,
            'class'       => 'col-sm-2',
            'description' => '0 = no limit',
            'hint'        => 'Longer track titles are shortened with an ellipsis.',
            'type'        => 'number',
        ],
        // Little hacky
        'stats_refresh',
        [
            'label'       => 'Public API',
            'name'        => 'api',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Enable JSONP, CORS and the now playing widget &middot; <a target="_blank" href="https://doc.prahec.com/pawtunes#live-information-external-api">Docs</a>',
        ],
        [
            'label'       => 'Artist/Title regex',
            'name'        => 'track_regex',
            'class'       => 'col-sm-5',
            'placeholder' => "(?P<artist>[^-]*)[ ]?-[ ]?(?P<title>.*)",
            'reset'       => true,
            'hint'        => 'Splits the stream title with the named groups "artist" and "title". Only change it when your stream uses a different format, the AI page can suggest one from samples.',
        ],
        [
            'label'       => 'Show Track History',
            'name'        => 'history',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Show recently played tracks &middot; <a target="_blank" href="https://doc.prahec.com/pawtunes#track-history">Docs</a>',
            'hint'        => 'PawTunes records each channel\'s tracks whenever track info is fetched and adds the stream server\'s own history when the source provides one. Set up the cron job from the docs to keep recording while nobody listens.',
        ],
        [
            'label'       => 'Track History Length',
            'name'        => 'historyLength',
            'placeholder' => 20,
            'min'         => 1,
            'max'         => lib\PawTunes\History::MAX_LENGTH,
            'class'       => 'col-sm-2',
            'description' => 'tracks per channel',
            'hint'        => 'How many recently played tracks are kept and shown. The whole list is part of every track info response, so a long one is best combined with artwork lazy loading.',
            'type'        => 'number',
        ],
    ];

    $artworks = [
        [
            'label'       => 'Cache Images',
            'name'        => 'cache_images',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Download, crop and serve artwork from this server',
        ],
        [
            'label'       => 'Cache API Requests',
            'name'        => 'cache_api_requests',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Remember artwork lookups',
            'hint'        => 'Found artwork is remembered for 7 days, misses for 1 day. Avoids repeated lookups and API rate limits.',
        ],
        [
            'label'       => 'Clean Searches',
            'name'        => 'clean_artwork_search',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Strip edition notes from artwork searches',
            'hint'        => 'Removes "(Radio Edit)", "(Remastered 2011)", "feat. Guest" and similar, and skips results by a different artist.',
        ],
        [
            'label'       => 'Serve via Web',
            'name'        => 'serve_via_web',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Let the web server send artwork files',
            'hint'        => 'Uses X-Sendfile (Apache, LiteSpeed) or X-Accel-Redirect (nginx), which greatly reduces PHP load. Needs Cache Images and a web server configured for it.',
        ],
        [
            'label'       => 'Artist Images Mode',
            'name'        => 'artist_images_only',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'One image per artist instead of per track',
            'hint'        => 'Greatly reduces the number of lookups and stored images.',
        ],
        [
            'label'       => 'Lazy Artwork Loading',
            'name'        => 'artwork_lazy_loading',
            'class'       => 'col-sm-9',
            'value'       => 'true',
            'type'        => 'checkbox',
            'description' => 'Load artwork after the track info',
            'hint'        => 'Track info is returned right away and artwork is fetched separately, so a slow artwork source never delays the title.',
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
@endphp
@section('content')
    <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
        {!! $panel->csrfField() !!}
        <input type="hidden" name="action" value="save">

        {!! $message !!}
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-screwdriver-wrench"></i> General
            </div>
            <div class="content form-content">
                @foreach ( $general as $input )

                    @if ( $input === 'override-share-image' )
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="share_image_override">Share Image  <div class="help-block">{!! Forms::hint( 'Image shown when the player link is shared on social media. JPG, PNG, WebP or SVG.' ) !!}</div></label>
                            {{-- Same column layout as Forms fields, so the hint sits right of the control like everywhere else --}}
                            <div class="col-sm-6">
                                {{-- .input-group is display: table, a col-* class is what stretches it to the column width --}}
                                <div class="input-group col-sm-12">
                                    <input type="text" name="share_image_override" id="share_image_override" class="form-control" value="{{$values[ 'share_image_override' ] ?? ''}}" placeholder="Image URL, or upload one">
                                    <div class="input-group-btn">
                                        <label for="override_image" class="btn btn-primary">
                                            <input type="file" id="override_image" class="hidden" name="override_image" accept="image/jpeg,image/png,image/webp,image/svg+xml"><i class="icon fa fa-folder-open"></i> Browse
                                        </label>
                                    </div>
                                </div>
                            </div>
                            @if ( !empty( $values[ 'share_image_override' ] ) && is_file( $values[ 'share_image_override' ] ) )
                                <div class="col-sm-10 col-sm-offset-2 logo-container">
                                    <div class="override-image">
                                        <img src="{{$values[ 'share_image_override' ]}}" width="auto" height="80">
                                    </div>
                                    <a href="#" class="delete-override"><i class="icon fa fa-times"></i> Delete</a>
                                </div>
                            @endif
                        </div>
                        @continue
                    @endif

                    @if ( $input === 'app-icon' )
                        @php $appIcons = $pawtunes->getAppIcons(); @endphp
                        <div class="form-group">
                            <label class="col-sm-2 control-label" for="app_icon">App Icon</label>
                            <div class="col-sm-10">
                                {{-- Preview of the icon in use (custom or bundled), the file is stored when the form is saved --}}
                                <div class="app-icon-picker">
                                    <img class="app-icon-preview" src="./../{{ltrim( $appIcons[ 192 ], './' )}}" width="56" height="56" alt="Current app icon">
                                    <div class="app-icon-body">
                                        <div class="app-icon-buttons">
                                            <label for="app_icon" class="btn btn-default btn-sm">
                                                <input type="file" id="app_icon" class="hidden" name="app_icon" accept="image/png,image/jpeg,image/webp">
                                                <i class="icon fa fa-upload"></i> {{ $appIcons[ 'custom' ] ? 'Replace' : 'Upload' }}
                                            </label>
                                            @if ( $appIcons[ 'custom' ] )
                                                <button type="submit" form="remove-app-icon" class="btn btn-link btn-sm remove-app-icon" data-confirm><i class="icon fa fa-times"></i> Remove</button>
                                            @endif
                                            {!! Forms::hint( 'Icon shown when listeners add the player to their home screen. Cropped to a square, the PawTunes icon is used until you upload one.' ) !!}
                                        </div>
                                        <div class="app-icon-status">
                                            {{ $appIcons[ 'custom' ] ? 'Custom icon' : 'PawTunes icon' }} &middot; square JPG, PNG or WebP, at least 512 &times; 512 px
                                        </div>
                                    </div>
                                </div>
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
                                        <option data-html="<i class='fi ico-left fi-{{$lang['flag']}}'></i> {{$lang['name']}}" value="{{$index}}"{{( ( $index === ( $values['default_lang' ] ?? '' ) ) ? 'selected' : '' )}}>
                                            {{$lang['name']}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="help-block">{!! Forms::hint( 'Used when the listener\'s language is not available, or for everyone when Multi-language is off.' ) !!}</div>
                        </div>
                        @continue
                    @endif

                    {!! $form->field( $input ) !!}
                @endforeach
            </div>
        </div>
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-database"></i> Cache
            </div>
            <div class="content form-content">
                <p class="text-muted">Changed cache settings are tested before they are saved, and the new cache starts empty.</p>
                @foreach ( $cacheSettings as $input )

                    @if ( $input === 'cache_status' )
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Status</label>
                            <div class="col-sm-10">
                                <p class="form-control-static {{ $cacheStatus[ 'ok' ] ? 'text-success' : 'text-warning' }}">
                                    <i class="icon fa {{ $cacheStatus[ 'ok' ] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i> {{ $cacheStatus[ 'text' ] }}
                                </p>
                            </div>
                        </div>
                        @continue
                    @endif

                    {!! $form->field( $input ) !!}
                @endforeach
            </div>
        </div>
        <div class="panel template-panel">
            <div class="heading">
                <i class="icon fa fa-palette"></i> Template
                <button type="submit" form="reset-templates" class="btn btn-default btn-sm ms-auto css-hint" data-title="Re-scan the templates folder">
                    <i class="icon fa fa-rotate"></i> Flush Cache
                </button>
            </div>
            <div class="content form-content">
                <p class="template-picker-intro">
                    Click a template to use it for the player. The <i class="icon fa fa-gear"></i> button opens options for that template, which are saved instantly.
                </p>
                {{-- Radio-backed cards: the selection posts with the main form, the cog button sits outside the label so it never toggles it --}}
                <div class="template-picker">
                    @foreach ( $templates as $index => $data )
                        <div class="template-card">
                            <input type="radio" class="template-card-input" name="template" id="template-{{$index}}" value="{{$index}}"{{( ( $index === $selectedTemplate ) ? ' checked' : '' )}}>
                            <label class="template-card-body" for="template-{{$index}}">
                                <span class="template-card-preview">
                                    @if ( !empty( $data[ 'screenshotURL' ] ) )
                                        <img src="{{$data[ 'screenshotURL' ]}}" alt="{{$data[ 'name' ]}} preview" loading="lazy" decoding="async">
                                    @else
                                        <span class="template-card-placeholder"><i class="icon fa fa-image"></i> No preview</span>
                                    @endif
                                </span>
                                <span class="template-card-check"><i class="fa fa-check"></i></span>
                                <span class="template-card-info">
                                    <span class="template-card-name">{{$data[ 'name' ]}}</span>
                                    <span class="template-card-meta">
                                        @if ( !empty( $data[ 'size' ] ) )
                                            {{str_replace( 'x', ' × ', $data[ 'size' ] )}} &middot;
                                        @endif
                                        {{count( $data[ 'schemes' ] ?? [] )}} {{( count( $data[ 'schemes' ] ?? [] ) === 1 ) ? 'scheme' : 'schemes'}}
                                    </span>
                                </span>
                            </label>
                            @if ( !empty( $data[ 'extra' ] ) )
                                <button type="button" class="template-card-options css-hint" data-title="Template options" aria-label="{{$data[ 'name' ]}} options"
                                        data-bs-toggle="modal" data-bs-target="#template-options-{{$index}}">
                                    <i class="fa fa-gear"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
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
                                    <input type="number" name="stats_refresh" class="form-control" id="stats_refresh" placeholder="15" required="" value="{{$values[ 'stats_refresh' ] ?? ''}}">
                                </div>
                            </div>
                            <div class="help-block">{!! Forms::hint( 'How often each listener asks for track info. Lower values mean more requests to your web and stream servers.' ) !!}Default 15</div>
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

                <div class="form-group"><label class="col-sm-2 control-label" for="images_size">Artwork Size</label>
                    <div class="col-sm-2">
                        <div class="input-append">
                            <div class="append">px</div>
                            <input class="form-control" id="images_size" name="images_size" placeholder="280" type="number" value="{{( !empty( $values[ 'images_size' ] ) ? $values[ 'images_size' ] : '' )}}">
                        </div>
                    </div>
                    <div class="help-block">{!! Forms::hint( 'Square size artwork is cropped to. Larger images cost more disk space, bandwidth and processing.' ) !!}Default 280</div>
                </div>

                <div class="divider mt-5"></div>
                <h5>Artwork Sources{!! Forms::hint( 'Sources are asked in this order until one has artwork. Each enabled source can add an API request per new track, so enable only the ones you need.' ) !!}</h5>
                <p class="text-muted">Tick to enable, drag to reorder. With none enabled only the default artwork is shown.</p>
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
                                    {{-- Sources define either one 'field' or a list of 'fields' --}}
                                    <div class="input-row">
                                        @foreach(($api['fields'] ?? (isset($api['field']) ? [$api['field']] : [])) as $field)
                                            {{-- A secret field never carries its saved value (Panel::mapArtworkSourcesView() empties it): blank keeps it --}}
                                            @php $saved = in_array( $field['name'], $api['saved'] ?? [], true ); @endphp
                                            <div class="input-append {{$field['size'] ?? ''}}">
                                                <input class="form-control" type="{{$field['type'] ?? 'text'}}"
                                                       name="{{$key}}[{{$field['name']}}]"
                                                       placeholder="{{$saved ? 'Saved, leave blank to keep it' : $field['placeholder']}}"
                                                       autocomplete="{{!empty( $field['secret'] ) ? 'new-password' : 'off'}}"
                                                       value="{{$api[$field['name']] ?? ''}}">
                                                <div class="append css-hint left" data-title="{{$field['helpText'] ?? $field['placeholder']}}">
                                                    @if (!empty($field['helpURL']))
                                                        <a href="{{$field['helpURL']}}" target="_blank">
                                                            <i class="fas fa-external-link"></i>
                                                        </a>
                                                    @else
                                                        <i class="fas fa-question-circle"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($saved)
                                                <div class="checkbox">
                                                    <label class="css-hint" data-title="Remove the saved key when the settings are saved">
                                                        <input type="checkbox" name="{{$key}}[forget_{{$field['name']}}]" value="true">
                                                        <span class="icon fa fa-check"></span> <span class="description">Remove</span>
                                                    </label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
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
                    <div class="col-sm-9 col-sm-offset-2 help-block">
                        Leave the password empty to keep the current one.
                        {!! Forms::hint( 'Passwords are stored hashed. Locked out? Remove the "admin_password" key from inc/config/general.php and the password resets to "password".' ) !!}
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

    {{-- App icon removal: its own POST form (CSRF-checked), the Remove button inside the settings form targets it --}}
    <form method="POST" action="index.php?page=settings" id="remove-app-icon">
        {!! $panel->csrfField() !!}
        <input type="hidden" name="action" value="remove-app-icon">
    </form>

    {{-- Templates cache reset: its own POST form (CSRF-checked), the Flush Cache button in the Template heading targets it --}}
    <form method="POST" action="index.php?page=settings" id="reset-templates">
        {!! $panel->csrfField() !!}
        <input type="hidden" name="action" value="reset-templates">
    </form>

    <!-- Template Options Modals (one per template, outside the main form since forms can't be nested) -->
    @foreach ( $templates as $index => $data )
        @if ( !empty( $data[ 'extra' ] ) )
            <div class="modal fade template-options-modal" tabindex="-1" role="dialog" id="template-options-{{$index}}" aria-labelledby="template-options-{{$index}}-title">
                {{-- Scrollable: long option lists scroll inside the body, the save status and Done stay in view --}}
                <div class="modal-dialog modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="template-options-{{$index}}-title"><i class="icon fas fa-gear"></i> {{$data[ 'name' ]}} Options</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" data-action="index.php?page=settings" class="template-options-form">
                                {!! $panel->csrfField() !!}
                                <input type="hidden" name="action" value="save-template-options">
                                <input type="hidden" name="template" value="{{$index}}">
                                @php
                                    $opts  = $pawtunes->getAdvancedTemplateOptions( $index );
                                    $group = null;
                                @endphp
                                {{-- One row per option: title and description, with the switch on the right.
                                     Other controls go under the description at the row's full width.
                                     A heading starts each run of options sharing a manifest "group". --}}
                                @foreach ( $data[ 'extra' ] as $key => $option )
                                    @php
                                        $id       = "{$index}_{$option['name']}";
                                        $type     = $option[ 'type' ] ?? 'text';
                                        $isSwitch = $type === 'checkbox';
                                        $isWide   = in_array( $type, [ 'text', 'url', 'email', 'password', 'textarea' ] );

                                        // Older manifests put a checkbox's whole text into "description": show it as the title
                                        $title       = $option[ 'label' ] ?? $option[ 'description' ] ?? $key;
                                        $description = isset( $option[ 'label' ] ) ? ( $option[ 'description' ] ?? '' ) : '';
                                        $optionGroup = $option[ 'group' ] ?? '';
                                    @endphp
                                    @if ( $optionGroup !== $group && $optionGroup !== '' )
                                        <h6 class="option-group-title">{{ $optionGroup }}</h6>
                                    @endif
                                    @php $group = $optionGroup; @endphp
                                    <div class="option-row{{ $isWide ? ' is-wide' : '' }}">
                                        {{-- Manifest texts are trusted HTML (some use <code> or <strong>), like everywhere Forms renders them --}}
                                        <label class="option-text" for="{{ $id }}">
                                            <span class="option-title">{!! $title !!}</span>
                                            @if ( $description !== '' )
                                                <span class="option-description">{!! $description !!}</span>
                                            @endif
                                        </label>
                                        @if ( $isSwitch )
                                            @php $checkedValue = $option[ 'value' ] ?? 'true'; @endphp
                                            <input type="checkbox" class="switch" role="switch" name="{{ $option[ 'name' ] }}" id="{{ $id }}" value="{{ $checkedValue }}"{{ ( isset( $opts[ $key ] ) && $opts[ $key ] == $checkedValue ) ? ' checked' : '' }}>
                                        @else
                                            {{-- The texts are rendered above, Forms only draws the control --}}
                                            <div class="option-control">
                                                {!! $form->field( [ 'id' => $id, 'class' => '' ] + array_diff_key( $option, array_flip( [ 'label', 'description', 'group' ] ) ), $opts[ $key ] ?? null ) !!}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </form>
                        </div>
                        <div class="modal-footer">
                            <span class="template-options-status" aria-live="polite">Changes are saved automatically</span>
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                <i class="icon fa fa-check"></i> Done
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
    <script type="text/javascript">

        // Bind document ready
        $(document).ready(function() {

            $('.table.artwork-apis').rowSorter({
                handler: '.sort-handle',
            });

            // Cache section: show only the rows the chosen mode uses. Hidden rows still post their values,
            // so switching modes back and forth keeps them.
            let cacheRows = {
                cache_host: ['redis', 'memcache', 'memcached'],
                cache_auth: ['redis'],
                cache_prefix: ['apcu', 'redis', 'memcache', 'memcached'],
            };
            let cacheHosts = {redis: '127.0.0.1:6379', memcache: '127.0.0.1:11211', memcached: '127.0.0.1:11211'};
            let cacheMode = $('#cache_mode');
            let cacheHost = $('#cache_host');
            let showCacheRows = function() {

                let mode = cacheMode.val();
                $.each(cacheRows, function(id, modes) {
                    $('#' + id).closest('.form-group').toggle(modes.indexOf(mode) !== -1);
                });

                cacheHost.attr('placeholder', cacheHosts[mode] || cacheHosts.memcache);

            };

            cacheMode.on('change', function() {

                // Another backend's default host becomes this one's, a custom host is left alone
                let mode = cacheMode.val();
                if (cacheHosts[mode] && Object.values(cacheHosts).indexOf(cacheHost.val()) !== -1) {
                    cacheHost.val(cacheHosts[mode]);
                }

                showCacheRows();

            });
            showCacheRows();

            // Delete existing logo
            $('.delete-override').on('click', function() {

                let elm = $(this);
                // POST with the CSRF token: a GET that deletes files could be triggered by any page the admin visits
                $.post('index.php?page=settings', {action: 'delete-share-image', _token: '{{$panel->csrfToken()}}'}, function() {
                    $(elm).closest('.logo-container').remove();
                });

                return false;

            });

            // Share image: show the picked file name in the URL field
            $('#override_image').on('change', function() {
                let cVal = $(this).val().replace(/.*\\fakepath\\/, '');
                let elm = $(this).closest('.form-group');
                elm.find('#share_image_override').val(cVal);
                elm.find('.logo-container').remove();
            });

            // App icon: preview the picked file and check its size before upload (the server checks again)
            $('#app_icon').on('change', function() {

                let file = this.files && this.files[0];
                if (!file) return;

                let picker = $(this).closest('.app-icon-picker');
                let status = picker.find('.app-icon-status');
                let url = URL.createObjectURL(file);
                let probe = new Image();

                probe.onload = function() {
                    let tooSmall = probe.naturalWidth < 512 || probe.naturalHeight < 512;
                    picker.find('.app-icon-preview').attr('src', url);
                    status.toggleClass('is-error', tooSmall).text(tooSmall
                        ? file.name + ' is ' + probe.naturalWidth + ' × ' + probe.naturalHeight + ' px, it needs at least 512 × 512 px'
                        : file.name + ' · saved when you click Save');
                };
                probe.onerror = function() {
                    status.addClass('is-error').text(file.name + ' is not a readable image');
                };
                probe.src = url;

            });

            // Template options save on change (debounced), progress is reported in the modal footer
            let timeout = null;
            let saveTemplateOptions = function(form) {

                let status = form.closest('.modal-content').find('.template-options-status');
                status.removeClass('is-saved is-error').text('Saving...');

                $.post(form.attr('data-action'), form.serialize(), null, 'json')
                    .done(function() {
                        status.addClass('is-saved').html('<i class="icon fa fa-check"></i> All changes saved');
                    })
                    .fail(function(xhr) {
                        let error = (xhr.responseJSON && xhr.responseJSON.error) || 'Unable to save options, please try again!';
                        status.addClass('is-error').text(error);
                    });

            };

            $('.template-options-form').on('change', 'select, input, textarea', function() {

                let form = $(this).closest('form');

                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    saveTemplateOptions(form);
                }, 250);

            }).on('submit', function(e) {

                // Enter in a text field would otherwise post this partial form to the main settings handler
                e.preventDefault();
                clearTimeout(timeout);
                saveTemplateOptions($(this));

            });

        });

    </script>
@endsection
@include('template')