@section('content')

    {!! $message !!}

    <div class="update-content">
        <div class="update-preloader text-center">
            <br>Loading, please wait...<br><br>
            <div class="preloader-spin"></div>
        </div>
    </div>
    <div class="panel update-message" style="display:none">
        <div class="heading"><i class="icon fas fa-cloud-download"></i> PawTunes Update</div>
        <div class="content">
            <div class="hide-on-progress">
                <span class="version-available"></span>
                <p>
                    Please <strong>backup</strong> your installation before proceeding! If in doubt, please contact support.
                </p>
                <div class="changelog latest mb-5" style="display:none">
                    Loading...
                </div>
                <a href="#" class="btn btn-success start-update">
                    <i class="fas icon fa-arrow-circle-right"></i> Start Update
                </a>
            </div>
            <div class="update-progress-bar" style="display:none;">
                <span>Please wait, do not interrupt this process...</span>
                <div class="progress-bar">
                    <div class="bar" style="width: 0;"><p>0%</p></div>
                    <
                </div>
                <pre class="m-0 p-0 update-progress">Starting update process, please wait...<br></pre>
            </div>
        </div>
    </div>
    <div class="panel update-history" style="display:none">
        <div class="heading">
            <i class="fas fa-history"></i> Update History
            <button type="button" class="btn btn-default btn-sm ms-auto toggle-all">Expand all</button>
        </div>
        <div class="content p-0">
            <div class="changelog history"></div>
        </div>
    </div>
    <script type="text/javascript">
        window.loadInit = function () {

            let sse;
            let totalProgress = 0;

            let closeEvent = () => {
                sse.close();
            }

            function progressUI() {

                let bar = $('.update-progress-bar .bar');
                let text = $('.update-progress-bar .bar p');
                if (bar && text) {
                    bar.css('width', totalProgress + '%');
                    text.html(totalProgress + '%');

                    if (totalProgress > 49 && !text.hasClass('alter')) {
                        text.addClass('alter');
                    }
                }

                if (totalProgress === 100) {
                    $('.update-progress-bar').hide();
                }
            }

            /**
             * Helper to calculate in-between percentage
             */
            function mapPercentageToRange($percentage, $min, $max) {

                return Math.floor($min + ($percentage / 100) * ($max - $min));

            }

            /**
             * Start SSE
             * Global progress:
             * 0-10 = Connecting/starting
             * 10-50 = Downloading
             * 50-90 = Extracting
             * 90-100 = Post processing
             */
            function startUpdate(version) {

                sse = new EventSource('index.php?page=api&action=update&version=' + version);
                sse.addEventListener('update', (event) => {

                    if (event.data === 'close') {
                        closeEvent();
                        return;
                    }

                    let data = atob(event.data);
                    let curProgress = 0;

                    // Various messages
                    if (data.match(/downloading/i)) {

                        curProgress = $(data).find('.progress').text();
                        totalProgress = mapPercentageToRange(curProgress, 5, 50);

                    } else if (data.match(/extracting/i)) {

                        curProgress = $(data).find('.progress').text();
                        totalProgress = mapPercentageToRange(curProgress, 50, 95);

                    } else if (data.match(/completed/i)) {

                        totalProgress = 100;
                        $('.update-progress').after('<a class="btn btn-success mt-2" href="index.php?page=updates"><i class="fas icon fa-refresh"></i> Reload Page</a>');

                    }

                    progressUI();
                    $('.update-progress-bar').show();
                    $('.update-progress').html(data);

                })

                sse.onerror = () => {

                    $('.update-progress').html('<div class="text-danger">Update error occurred, please try again later. If the issue persists, please contact support.</div>');
                    sse.close();

                }

            }

            $.ajax({cache: false, url: 'index.php?page=api&action=update-check'}).then(function (data) {

                if (data.error && data.message) {

                    $('.update-content').html('<div class="alert alert-danger alert-icon"><div class="content">Sorry, checking for updates has failed, error message: ' +
                        '<pre>' + data.message + '</pre></div></div>');
                    return false;

                }

                if (!data.releases || data.releases.length <= 0) {

                    $('.update-content').html('{!!$panel->alert( 'Sorry but there are no updates available, please check again later!' )!!}');
                    return false;

                }

                const lastRelease = data.releases[0];
                const lastReleaseVersion = lastRelease.tag_name.replace('v', '')
                if (!shouldUpdate(lastReleaseVersion, version)) {

                    $('.update-content').html('{!!$panel->alert( 'Great news! You are running the latest version!', 'success' )!!}');

                } else {

                    let parsedDate = new Date(lastRelease.published_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    // Add an alert message
                    $('.version-available').html(`Update version <b>${lastReleaseVersion}</b> released on <b>${parsedDate}</b> is ready.`);
                    $('.update-preloader').hide();
                    $('.update-message').show();

                    $('.start-update').on('click', function () {

                        if (!confirm('Are you sure you want to start the update?')) {
                            return false;
                        }

                        $(this).hide();
                        $('.update-progress-bar').show();
                        $('.hide-on-progress').hide();
                        startUpdate(lastRelease.tag_name);

                    })

                }

                // Release notes use the same layout as the history below, free-form notes fall back to plain markdown
                const latestNotes = parseChangelog(lastRelease.body)[0];
                if (latestNotes && latestNotes.groups.length) {

                    $('.changelog.latest').html(renderChangelogBody(latestNotes));

                } else if (lastRelease.body) {

                    $('.changelog.latest').html(markdown(lastRelease.body));

                } else {

                    $('.changelog.latest').html('Release notes for this update are unavailable.');

                }

                // Show the change log
                $('.changelog.latest').show();
                $('.update-panel').removeClass('hidden');

            }).catch(function (xhr, status, error) {

                $('.update-content').html('{!!$panel->alert( 'Connection to the update server has failed! See the details below: <pre id="ajax-error"></pre>', 'error' )!!}');

                let log_text = "Unknown reason";

                // If some other error occurred, responded with error, write that instead
                if (typeof xhr !== 'undefined') {
                    log_text = xhr;
                }

                $('pre#ajax-error').html(log_text);

            });

            $.ajax({cache: false, url: 'index.php?page=api&action=update-history'}).then(function (data) {

                // CHANGELOG.md only ships with release builds, keep the panel hidden without it
                const releases = parseChangelog(data);
                if (!releases.length) {
                    return;
                }

                $('.changelog.history').html(renderChangelog(releases, version));
                $('.update-history').show();

                const $releases = $('.changelog.history .changelog-release');
                const $toggle = $('.toggle-all');
                const allOpen = () => $releases.toArray().every(el => el.open);

                // Releases are native <details>, so the label follows them whether opened one by one or all at once
                $releases.on('toggle', () => $toggle.text(allOpen() ? 'Collapse all' : 'Expand all'));
                $toggle.on('click', () => $releases.prop('open', !allOpen()));

            });
        };

    </script>
@endsection
@include('template')