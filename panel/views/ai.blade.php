@section('content')
    {!! $message !!}

    {{-- AI Assistant settings --}}
    <form method="POST" action="index.php?page=ai" autocomplete="off">
        {!! $panel->csrfField() !!}
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-wand-magic-sparkles"></i> AI Assistant
            </div>
            <div class="content form-content">
                <p>
                    A language model that reads stream titles your Track RegEx cannot split, and powers the tools below.
                    Each distinct stream title is asked about once and cached for 30 days, so a station costs cents per month.
                    It only ever sees text from your own stream server, never listener input.
                </p>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-enabled">Enable</label>
                    <div class="col-sm-9">
                        <div class="checkbox">
                            <label for="text-enabled" tabindex="0">
                                <input type="checkbox" name="text[enabled]" id="text-enabled" value="true"{{ $ai['text']['enabled'] ? ' checked' : '' }}>
                                <span class="icon fa fa-check"></span> <span class="description">Use AI Assistant (off by default)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-provider">Provider</label>
                    <div class="col-sm-4">
                        <select name="text[provider]" id="text-provider" class="form-control">
                            @foreach ($textProviders as $key => $provider)
                                <option value="{{ $key }}"{{ $ai['text']['provider'] === $key ? ' selected' : '' }}>{{ $provider['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-api-key">API Key</label>
                    <div class="col-sm-4">
                        <input type="password" name="text[api_key]" id="text-api-key" class="form-control" autocomplete="new-password"
                               placeholder="{{ $ai['text']['api_key'] !== '' ? 'Saved, leave blank to keep it' : 'Paste your API key' }}">
                    </div>
                    @if ($ai['text']['api_key'] !== '')
                        <div class="col-sm-3">
                            <div class="checkbox">
                                <label for="text-forget-key" tabindex="0">
                                    <input type="checkbox" name="text[forget_key]" id="text-forget-key" value="true">
                                    <span class="icon fa fa-check"></span> <span class="description">Remove the saved key</span>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-model">Model</label>
                    <div class="col-sm-4">
                        <input type="text" name="text[model]" id="text-model" class="form-control" value="{{ $ai['text']['model'] }}" placeholder="Provider default">
                    </div>
                    <div class="help-block">
                        Blank uses the default: <code>{{ implode(', ', array_column($textProviders, 'model')) }}</code>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-base-url">Base URL</label>
                    <div class="col-sm-4">
                        <input type="url" name="text[base_url]" id="text-base-url" class="form-control" value="{{ $ai['text']['base_url'] }}" placeholder="https://api.openai.com/v1">
                    </div>
                    <div class="help-block">
                        OpenAI-compatible servers only, e.g. <code>http://localhost:11434/v1</code> for a local Ollama (no API key, no cloud).
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="text-clean">Track Titles</label>
                    <div class="col-sm-9">
                        <div class="checkbox">
                            <label for="text-clean" tabindex="0">
                                <input type="checkbox" name="text[clean_metadata]" id="text-clean" value="true"{{ $ai['text']['clean_metadata'] ? ' checked' : '' }}>
                                <span class="icon fa fa-check"></span>
                                <span class="description">Fix stream titles the Track RegEx cannot read (ALL CAPS, odd separators, glued URLs); station IDs and ads keep the default track</span>
                            </label>
                        </div>
                    </div>
                </div>

                @if ($test)
                    <div class="row">
                        <div class="col-sm-9 col-sm-offset-2">
                            <b>Test:</b> <code>{{ $test['input'] }}</code><br>
                            @if ($test['track'])
                                Artist <b>{{ $test['track']['artist'] }}</b>, title <b>{{ $test['track']['title'] }}</b>
                            @else
                                The model did not recognize a song in the test title.
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div class="container mb-5">
            <div class="row">
                <button type="submit" name="action" value="save-text" class="btn btn-success"><i class="icon fa fa-floppy-disk"></i> Save</button>
                <button type="submit" name="action" value="test-text" class="btn btn-primary"><i class="icon fa fa-vial"></i> Save &amp; Test</button>
            </div>
        </div>
    </form>

    {{-- AI Artwork settings --}}
    <form method="POST" action="index.php?page=ai" autocomplete="off">
        {!! $panel->csrfField() !!}
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-palette"></i> AI Artwork
            </div>
            <div class="content form-content">
                <p>
                    Abstract artwork drawn by an image model for artists no artwork source knows. It is checked after every
                    real source, so a real cover always wins, and it never shows text, people or a cover layout.
                </p>
                <ul class="mb-4">
                    <li>Only artists your own stream played get artwork, a URL alone can never start a generation (WebSocket channels are not covered).</li>
                    <li>The artist and title never reach the image model, the prompt is built from fixed words, so no fake covers.</li>
                    <li>At most one attempt per artist per day, and a hard daily limit kept outside the cache (Flush Cache cannot reset it).</li>
                    <li>Images live in <code>data/ai-artwork</code>, which Flush Cache and updates never touch, so nothing is paid for twice.</li>
                </ul>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-enabled">Enable</label>
                    <div class="col-sm-9">
                        <div class="checkbox">
                            <label for="images-enabled" tabindex="0">
                                <input type="checkbox" name="images[enabled]" id="images-enabled" value="true"{{ $ai['images']['enabled'] ? ' checked' : '' }}>
                                <span class="icon fa fa-check"></span> <span class="description">Use AI Artwork (off by default, paid per image)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-provider">Provider</label>
                    <div class="col-sm-4">
                        <select name="images[provider]" id="images-provider" class="form-control">
                            @foreach ($imageProviders as $key => $provider)
                                <option value="{{ $key }}"{{ $ai['images']['provider'] === $key ? ' selected' : '' }}>{{ $provider['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-api-key">API Key</label>
                    <div class="col-sm-4">
                        <input type="password" name="images[api_key]" id="images-api-key" class="form-control" autocomplete="new-password"
                               placeholder="{{ $ai['images']['api_key'] !== '' ? 'Saved, leave blank to keep it' : 'Paste your API key' }}">
                    </div>
                    @if ($ai['images']['api_key'] !== '')
                        <div class="col-sm-3">
                            <div class="checkbox">
                                <label for="images-forget-key" tabindex="0">
                                    <input type="checkbox" name="images[forget_key]" id="images-forget-key" value="true">
                                    <span class="icon fa fa-check"></span> <span class="description">Remove the saved key</span>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-model">Model</label>
                    <div class="col-sm-4">
                        <input type="text" name="images[model]" id="images-model" class="form-control" value="{{ $ai['images']['model'] }}" placeholder="Provider default">
                    </div>
                    <div class="help-block">
                        Blank uses the default: <code>{{ implode(', ', array_column($imageProviders, 'model')) }}</code>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-base-url">Base URL</label>
                    <div class="col-sm-4">
                        <input type="url" name="images[base_url]" id="images-base-url" class="form-control" value="{{ $ai['images']['base_url'] }}" placeholder="https://api.openai.com/v1">
                    </div>
                    <div class="help-block">OpenAI-compatible servers only, e.g. a self-hosted LocalAI.</div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-limit">Daily Limit</label>
                    <div class="col-sm-2">
                        <div class="input-append">
                            <div class="append">/day</div>
                            <input type="number" name="images[daily_limit]" id="images-limit" class="form-control" min="0" max="500" value="{{ $ai['images']['daily_limit'] }}">
                        </div>
                    </div>
                    <div class="help-block">Used today: <b>{{ $usage['count'] }}</b>, all time: <b>{{ $usage['total'] }}</b>. Tests count too.</div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="images-verify">Check Images</label>
                    <div class="col-sm-9">
                        <div class="checkbox">
                            <label for="images-verify" tabindex="0">
                                <input type="checkbox" name="images[verify]" id="images-verify" value="true"{{ $ai['images']['verify'] ? ' checked' : '' }}>
                                <span class="icon fa fa-check"></span>
                                <span class="description">
                                    Let AI Assistant inspect every image (also contrast-enhanced, to catch hidden text) and discard any with text, people or a cover layout.
                                    @if (!$ai['text']['enabled'])
                                        <span class="text-danger">AI Assistant is off, so images are used unchecked.</span>
                                    @endif
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                @if ($imageTest)
                    <div class="row">
                        <div class="col-sm-9 col-sm-offset-2">
                            <p><b>Prompt:</b> {{ $imageTest['prompt'] }}</p>
                            <img src="{{ $imageTest['image'] }}" alt="Test image and its contrast-enhanced version" style="max-width: 100%; border-radius: .25rem;">
                            <p class="mt-2">
                                @if ($imageTest['reject'])
                                    <span class="text-danger"><i class="icon fa fa-ban"></i> Would be discarded: {{ $imageTest['reject'] }}</span>
                                @elseif ($ai['images']['verify'] && $ai['text']['enabled'])
                                    <i class="icon fa fa-check"></i> Passed the check, it would be used.
                                @else
                                    Not checked, it would be used as is.
                                @endif
                                (Test images are not stored.)
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div class="container mb-5">
            <div class="row">
                <button type="submit" name="action" value="save-images" class="btn btn-success"><i class="icon fa fa-floppy-disk"></i> Save</button>
                <button type="submit" name="action" value="test-images" class="btn btn-primary"><i class="icon fa fa-vial"></i> Save &amp; Generate Test Image</button>
            </div>
        </div>
    </form>

    @if (count($aiImages) > 0)
        <div class="panel">
            <div class="heading">
                <i class="icon fa fa-images"></i> Stored AI Artwork ({{ count($aiImages) }})
            </div>
            <div class="content form-content">
                @if (count($aiImages) > 48)
                    <p class="text-muted">Showing the 48 newest.</p>
                @endif
                <div class="ai-artwork-grid">
                    @foreach (array_slice($aiImages, 0, 48) as $file)
                        @php $name = pathinfo($file, PATHINFO_FILENAME); @endphp
                        <form method="POST" action="index.php?page=ai" class="ai-artwork">
                            {!! $panel->csrfField() !!}
                            <input type="hidden" name="name" value="{{ $name }}">
                            <img src="./../{{ ltrim($file, './') }}?v={{ filemtime($file) }}" alt="{{ $name }}" width="160" height="160" loading="lazy">
                            <span class="ai-artwork-name" title="{{ $name }}">{{ $name }}</span>
                            <button type="submit" name="action" value="delete-ai-image" class="ai-artwork-delete" title="Delete" aria-label="Delete {{ $name }}"><i class="fa fa-trash"></i></button>
                        </form>
                    @endforeach
                </div>
                <form method="POST" action="index.php?page=ai">
                    {!! $panel->csrfField() !!}
                    <button type="submit" name="action" value="delete-ai-artwork" class="btn btn-danger" data-confirm><i class="icon fa fa-trash"></i> Delete all AI artwork</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Tools, they need a working AI Assistant --}}
    <div class="panel">
        <div class="heading">
            <i class="icon fa fa-toolbox"></i> AI Tools
        </div>
        <div class="content">
            @if (!$ai['text']['enabled'])
                <p class="text-muted">Enable and save AI Assistant above to use these tools.</p>
            @else
                <h5>Track RegEx helper</h5>
                <p>Paste a few "now playing" titles exactly as your stream sends them, one per line. The suggested pattern is tested on them before you can use it.</p>
                <form method="POST" action="index.php?page=ai">
                    {!! $panel->csrfField() !!}
                    <textarea name="samples" class="form-control mb-3" rows="5" placeholder="QUEEN | BOHEMIAN RHAPSODY&#10;Daft Punk | One More Time">{{ isset($regex['samples']) ? implode("\n", $regex['samples']) : '' }}</textarea>
                    <button type="submit" name="action" value="suggest-regex" class="btn btn-primary"><i class="icon fa fa-wand-magic-sparkles"></i> Suggest RegEx</button>
                </form>

                @if ($regex)
                    <div class="mt-4">
                        <p><b>Suggested:</b> <code>{{ $regex['pattern'] }}</code><br>{{ $regex['explanation'] }}</p>
                        @if ($regex['problem'])
                            <p class="text-danger">{{ $regex['problem'] }} Try again, or add more samples.</p>
                        @else
                            <table class="table vertical-center">
                                <thead>
                                    <tr>
                                        <th>Stream title</th>
                                        <th>Artist</th>
                                        <th>Title</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($regex['preview'] as $row)
                                        <tr>
                                            <td><code>{{ $row['sample'] }}</code></td>
                                            @foreach (['artist', 'title'] as $part)
                                                <td>
                                                    @if ($row[$part] !== null)
                                                        {{ $row[$part] }}
                                                    @else
                                                        <span class="text-danger">no match</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <form method="POST" action="index.php?page=ai">
                                {!! $panel->csrfField() !!}
                                <input type="hidden" name="pattern" value="{{ $regex['pattern'] }}">
                                <button type="submit" name="action" value="use-regex" class="btn btn-success"><i class="icon fa fa-check"></i> Use as Track RegEx</button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="divider mt-5"></div>

                <h5>Translation helper</h5>
                <p>Creates a new language from the English texts and opens it in the language editor for review.</p>
                <form method="POST" action="index.php?page=ai" class="row">
                    {!! $panel->csrfField() !!}
                    <div class="col-sm-4 pe-1">
                        <select name="language" class="form-control">
                            @foreach ($missingLanguages as $iso => $language)
                                <option value="{{ $iso }}" data-html="<i class='fi ico-left fi-{{ $language['flag'] }}'></i> {{ $language['name'] }}">{{ $language['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" name="action" value="translate" class="btn btn-primary"><i class="icon fa fa-language"></i> Translate</button>
                </form>
            @endif
        </div>
    </div>
@endsection
@include('template')
