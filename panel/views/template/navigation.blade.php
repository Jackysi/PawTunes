@php
    ## Array of available Tab links
    $tabs = [
    '<i class="icon fa fa-circle-play"></i> Player'           => 'home',
    '<i class="icon fa fa-radio"></i> Channels'               => 'channels',
    '<i class="icon fa fa-hands"></i> Language'               => 'language',
    '<i class="icon fa fa-toolbox"></i> Tools'                => 'tools',
    '<i class="icon fa fa-wand-magic-sparkles"></i> AI'       => 'ai',
    '<i class="icon fa fa-screwdriver-wrench"></i> Settings'  => 'settings',
    '<i class="icon fa fa-cloud-download"></i> Updates'       => 'updates',
    ];

    ## The channel editor (page "channel") belongs to the Channels tab
    $currentPage = ( ( $_GET[ 'page' ] ?? '' ) === 'channel' ) ? 'channels' : ( $_GET[ 'page' ] ?? '' );
@endphp
<div class="menu">
    <div class="container">
        <ul class="nav nav-menu tabs d-mobile-none">
            @foreach( $tabs as $tab => $link )
                @php
                    $active = ( $currentPage === $link || ( $currentPage === '' && $link === 'home' ) ) ? ' class="active"' : '';
                @endphp
                <li{!! $active !!}>
                    <a id="tab-{{$link}}" href="index.php?page={{$link}}">{!!$tab!!}</a>
                </li>
            @endforeach
        </ul>
        <div class="dropdown d-desktop-none d-mobile-block align-self-center">
            <a href="#" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static"><i class="icon fa fa-bars"></i> Menu</a>
            <ul class="dropdown-menu">
                @foreach( $tabs as $tab => $link )
                    @php
                        $active = ( $currentPage === $link || ( $currentPage === '' && $link === 'home' ) ) ? ' class="active"' : '';
                    @endphp
                    <li{!! $active !!}>
                        <a id="tab-{{$link}}" href="index.php?page={{$link}}">{!!$tab!!}</a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="script-version">
            <div class="day-night" title="Toggle Dark Mode">
                <i class="icon far fa-moon"></i>
                <a class="clickable" href="#">
                    <i class="icon fa fa-toggle-on" id="on" style="display:none"></i>
                    <i class="icon fa fa-toggle-on fa-rotate-180" id="off" style="display:none"></i>
                </a>
                <i class="icon far fa-sun"></i>
            </div>
        </div>
    </div>
</div>
<section class="container main">