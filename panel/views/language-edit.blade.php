@php
    /**
     * Hint IDE already defined variables from parent (this file is part of bigger whole)
     *
     * @var Forms  $form
     * @var string|null $iso       ISO code of the translation being edited, null while adding a new one
     * @var string|null $message
     * @var array  $languages
     */
@endphp
@section('content')
    {!! $message !!}
    <form action="index.php?page=language&{{( ( $iso === null ) ? 'add' : 'edit=' . $iso )}}" method="POST" accept-charset="UTF-8">
        {!! $panel->csrfField() !!}
        <input type="hidden" name="action" value="save">
        <div class="panel">
            <div class="content">
                <p>
                    This player supports a multi-language setup which means that (if enabled) the player will automatically choose a language fit for the user's browser setting.<br>
                    You can also disable multi-language support under the <b>Settings</b> tab.
                </p>

                @if ( $iso === null )
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="isocode">Language</label>
                        <div class="col-sm-4">
                            <select name="isocode" class="form-control" id="isocode">
                                @foreach( $languages as $index => $lang )
                                    <option data-html="<i class='fi ico-left fi-{{$lang['flag']}}'></i> {{$lang['name']}}" value="{{$index}}">
                                        {{$lang['name']}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @else
                    @php
                        $form->append(
                            [
                                'label' => 'Language',
                                'name'  => 'isocode',
                                'type'  => 'static',
                                'value' => '<i class="fi fi-' . $languages[ $iso ]['flag'] . '"></i> <b>' . $languages[ $iso ]['name'] . '</b> (' . strtoupper( $iso ) . ')',
                            ]
                        );
                    @endphp
                @endif
                @php

                    $form->fields = array_merge(
                        $form->fields,
                        [
                            [
                                'label'       => 'Loading Message',
                                'placeholder' => 'Loading, please wait...',
                                'name'        => 'loading_message',
                                'reset'       => true,
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Settings',
                                'placeholder' => 'Select stream quality',
                                'name'        => 'ui_settings',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Channels List',
                                'placeholder' => 'Channels list',
                                'name'        => 'ui_channels',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Play Button',
                                'placeholder' => 'Start playing',
                                'name'        => 'ui_play',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Stop Button',
                                'placeholder' => 'Stop playing',
                                'name'        => 'ui_stop',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Volume Circle',
                                'placeholder' => 'Drag to change volume',
                                'name'        => 'ui_volume_circle',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Playlists Text',
                                'placeholder' => 'Listen in your favourite player',
                                'name'        => 'ui_playlists',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Back',
                                'placeholder' => 'Back',
                                'name'        => 'ui_back',
                                'reset'       => true,
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Status: Loading',
                                'placeholder' => 'Loading {STREAM}...',
                                'name'        => 'status_init',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                                'description' => '({STREAM} will be replaced by current channel name)',
                            ],
                            [
                                'label'       => 'Status: Playing',
                                'placeholder' => 'Playing {STREAM}...',
                                'name'        => 'status_playing',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                                'description' => '({STREAM} will be replaced by current channel name)',
                            ],
                            [
                                'label'       => 'Status: Stopped',
                                'placeholder' => 'Player stopped.',
                                'name'        => 'status_stopped',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Status: Volume',
                                'placeholder' => 'Volume: {LEVEL}',
                                'name'        => 'status_volume',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                                'description' => '({LEVEL} will be replaced by current volume level)',
                            ],
                            [
                                'label'       => 'Status: Muted',
                                'placeholder' => 'Player muted.',
                                'name'        => 'status_muted',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Status: Reconnecting',
                                'placeholder' => 'Connection lost, reconnecting...',
                                'name'        => 'status_reconnecting',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Song History',
                                'placeholder' => 'Song History',
                                'name'        => 'song_history',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Show History',
                                'placeholder' => 'Show Track History',
                                'name'        => 'ui_history',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Artist/Title',
                                'placeholder' => 'Artist/Title',
                                'name'        => 'history_artist_title',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Added',
                                'placeholder' => 'Added',
                                'name'        => 'history_added',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Hour(s) ago',
                                'placeholder' => 'hr ago',
                                'name'        => 'history_hour_ago',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Minute(s) ago',
                                'placeholder' => 'min ago',
                                'name'        => 'history_min_ago',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Second(s) ago',
                                'placeholder' => 'sec ago',
                                'name'        => 'history_sec_ago',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Just now',
                                'placeholder' => 'just now',
                                'name'        => 'history_just_now',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'No history',
                                'placeholder' => 'No history available at this time.',
                                'name'        => 'history_no_history',
                                'reset'       => true,
                                'class'       => 'col-sm-6',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Tab: Now Playing',
                                'placeholder' => 'Now',
                                'name'        => 'tab_now',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Tab: History',
                                'placeholder' => 'Played',
                                'name'        => 'tab_played',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Tab: Channels',
                                'placeholder' => 'Stations',
                                'name'        => 'tab_stations',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Up Next',
                                'placeholder' => 'Up next',
                                'name'        => 'up_next',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Live Badge',
                                'placeholder' => 'LIVE',
                                'name'        => 'live_badge',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Sleep Timer',
                                'placeholder' => 'Sleep timer',
                                'name'        => 'sleep_timer',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Sleep Timer: Off',
                                'placeholder' => 'Off',
                                'name'        => 'sleep_off',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Sleep Timer: Remaining',
                                'placeholder' => 'Stops in {TIME}',
                                'name'        => 'sleep_remaining',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                                'description' => '({TIME} will be replaced by the time left)',
                            ],
                            [
                                'label'       => 'Hours (short)',
                                'placeholder' => 'h',
                                'name'        => 'unit_hour_short',
                                'reset'       => true,
                                'class'       => 'col-sm-2',
                            ],
                            [
                                'label'       => 'Minutes (short)',
                                'placeholder' => 'min',
                                'name'        => 'unit_min_short',
                                'reset'       => true,
                                'class'       => 'col-sm-2',
                            ],
                            [
                                'label'       => 'Seconds (short)',
                                'placeholder' => 's',
                                'name'        => 'unit_sec_short',
                                'reset'       => true,
                                'class'       => 'col-sm-2',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Cast Button',
                                'placeholder' => 'Cast to a device',
                                'name'        => 'ui_cast',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'AirPlay Button',
                                'placeholder' => 'AirPlay',
                                'name'        => 'ui_airplay',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Cast: Connecting',
                                'placeholder' => 'Connecting...',
                                'name'        => 'cast_connecting',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Cast: Playing',
                                'placeholder' => 'Playing on {DEVICE}',
                                'name'        => 'cast_playing_on',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                                'description' => '({DEVICE} will be replaced by the device name)',
                            ],
                            [
                                'label'       => 'Cast: Failed',
                                'placeholder' => 'The device could not play this stream, playing here instead.',
                                'name'        => 'cast_failed',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Cast: Unsupported',
                                'placeholder' => 'This stream cannot be cast, playing here instead.',
                                'name'        => 'cast_unsupported',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'AirPlay: Hint',
                                'placeholder' => 'Start playback first, then choose an AirPlay device.',
                                'name'        => 'cast_airplay_hint',
                                'reset'       => true,
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Listeners',
                                'placeholder' => 'Listeners',
                                'name'        => 'listeners_title',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Listeners: None',
                                'placeholder' => 'Nobody is listening right now.',
                                'name'        => 'listeners_none',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Listeners: Error',
                                'placeholder' => 'Listener information is unavailable.',
                                'name'        => 'listeners_error',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'Listeners: Unknown Device',
                                'placeholder' => 'Unknown',
                                'name'        => 'listeners_unknown',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'Share',
                                'placeholder' => 'Share',
                                'name'        => 'share',
                                'reset'       => true,
                                'class'       => 'col-sm-4',
                            ],
                            [
                                'label'       => 'Twitter Post',
                                'placeholder' => 'I am listening to {TRACK}!',
                                'name'        => 'twitter_share',
                                'reset'       => true,
                                'class'       => 'col-sm-6',
                                'description' => '({TRACK} will be replaced by current playing track)',
                            ],
                            '<div class="divider"></div>',
                            [
                                'label'       => 'ERROR',
                                'placeholder' => 'ERROR',
                                'name'        => 'error',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'ERROR: definition',
                                'placeholder' => 'NO CHANNELS DEFINED',
                                'name'        => 'error_defined',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'ERROR: create',
                                'placeholder' => 'Unable to find channels, please create one!',
                                'name'        => 'error_create',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'ERROR: invalid',
                                'placeholder' => 'Invalid Channel!',
                                'name'        => 'error_invalid',
                                'reset'       => true,
                            ],
                            [

                                'label'       => 'ERROR: stream',
                                'placeholder' => 'ERROR: The specified or selected stream does not exist!',
                                'name'        => 'error_stream',
                                'reset'       => true,
                            ],
                            [

                                'label'       => 'ERROR: network',
                                'placeholder' => 'ERROR: Network error occurred!',
                                'name'        => 'error_network',
                                'reset'       => true,
                            ],
                            [
                                'label'       => 'ERROR: playback',
                                'placeholder' => 'ERROR: Playback failed, loading stream failed!',
                                'name'        => 'error_playback',
                                'reset'       => true,
                            ],
                        ]
                    );

                    $form->generateForm();
                @endphp
            </div>
        </div>
        <button type="submit" class="btn btn-success"><i class="icon fa fa-floppy-disk"></i> Save</button>
        <a href="index.php?page=language" class="btn btn-danger"><i class="icon fa fa-times"></i> Cancel</a>
    </form>
@endsection
@include('template')