@section('content')
    <div class="panel">
        <div class="content">
            <p>
                This player supports a multi-language setup which means that (if enabled) the player will automatically choose a language fit for the user's browser setting.<br>
                You can also disable multi-language support under the <b>Settings</b> tab.
            </p>
            {!! $message !!}
            @if ( count( $translations ) < 1 )
                <div class="empty-state">
                    <i class="icon fa fa-language"></i>
                    <h3>No translation files</h3>
                    <p>Add a language to enable multi-language support.</p>
                    <a href="index.php?page=language&add" class="btn btn-primary"><i class="icon fa fa-plus-circle"></i> Add Language</a>
                </div>
            @else
                <table class="table vertical-center hover">
                    <thead>
                        <tr>
                            <th>Language</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($translations as $translation )
                            @php
                                $language = $pawtunes->extDel( $translation );
                            @endphp
                            <tr>
                                <td class="col-sm-9">
                                    <i class="fi fi-{{$languages[ $language ]['flag']}}"></i> <b>{{$languages[ $language ]['name']}} </b> ({{strtoupper( $language )}})
                                </td>
                                <td class="row-btns">
                                    <a class="btn btn-default btn-small css-hint" data-title="Edit" href="index.php?page=language&edit={{$pawtunes->extDel( $translation )}}">
                                        <i class="icon fa fa-edit"></i>
                                    </a>
                                    @if ( $translation !== 'en.php' )
                                        <a class="btn btn-danger btn-small css-hint" data-title="Delete" data-confirm href="index.php?page=language&delete={{$language}}">
                                            <i class="icon fa fa-times"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
    <a href="index.php?page=language&add" class="btn btn-success">
        <i class="icon fa fa-plus-circle"></i> Add Language
    </a>
@endsection
@include('template')