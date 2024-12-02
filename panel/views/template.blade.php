@include( 'template.header' )
@include( 'template.navigation' )
@php
    $panel->flashMessages();
@endphp
@yield( 'content')
@include( 'template.footer' )