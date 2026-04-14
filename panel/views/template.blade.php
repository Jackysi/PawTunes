@include( 'template.header' )
@include( 'template.navigation' )
@php
    $flashes = $panel->getFlashMessages();
@endphp
@if (!empty($flashes))
    <script>
        window.addEventListener('load', function() {
            @foreach ($flashes as $flash)
                toast({!! json_encode($flash['message']) !!}, '{{ $flash['type'] }}');
            @endforeach
        });
    </script>
@endif
@yield( 'content')
@include( 'template.footer' )