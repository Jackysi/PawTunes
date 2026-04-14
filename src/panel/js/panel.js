/**
 * PawTunes Project - Open Source Radio Player
 *
 * Written by: Jaka Prasnikar
 * Website: https://prahec.com
 * Project URL: https://prahec.com/pawtunes
 *
 * This file is part of the PawTunes open-source project.
 * Feel free to contribute or provide feedback via the project URL.
 */

/**
 * Toast notification system
 */
window.toast = function (message, type, duration) {

    type = type || 'info';
    duration = duration !== undefined ? duration : 5000;

    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    let el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.innerHTML = '<span class="toast-message">' + message + '</span>' +
        '<button class="toast-close">&times;</button>';

    container.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('toast-visible'); });

    let dismiss = function () {
        el.classList.remove('toast-visible');
        el.addEventListener('transitionend', function () { el.remove(); });
    };

    el.querySelector('.toast-close').addEventListener('click', dismiss);
    if (duration > 0) setTimeout(dismiss, duration);
};

/**
 * JS-positioned tooltips — replaces CSS pseudo-element tooltips.
 * Supports: default (above), .left (to the left), auto-flip when near edges.
 */
(function () {

    let tip = null;
    let arrow = null;

    function show(el) {
        let title = el.getAttribute('data-title');
        if (!title) return;

        if (tip) tip.remove();

        tip = document.createElement('div');
        tip.className = 'js-tooltip';
        tip.textContent = title;

        arrow = document.createElement('div');
        arrow.className = 'js-tooltip-arrow';
        tip.appendChild(arrow);

        document.body.appendChild(tip);

        let rect = el.getBoundingClientRect();
        let th = tip.offsetHeight;
        let tw = tip.offsetWidth;
        let gap = 8;
        let pos, left, top;

        if (el.classList.contains('left')) {
            // Position to the left of the element
            left = rect.left - tw - gap;
            top = rect.top + rect.height / 2 - th / 2;
            pos = 'left';

            // Flip to right if off-screen
            if (left < gap) {
                left = rect.right + gap;
                pos = 'right';
            }
        } else {
            // Default: above
            left = rect.left + rect.width / 2 - tw / 2;
            top = rect.top - th - gap;
            pos = 'top';

            // Flip below if off-screen
            if (top < gap) {
                top = rect.bottom + gap;
                pos = 'bottom';
            }
        }

        // Keep horizontally within viewport
        if (left < gap) left = gap;
        if (left + tw > window.innerWidth - gap) left = window.innerWidth - tw - gap;

        // Keep vertically within viewport
        if (top < gap) top = gap;
        if (top + th > window.innerHeight - gap) top = window.innerHeight - th - gap;

        tip.setAttribute('data-pos', pos);
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';

        // Position arrow relative to element center
        if (pos === 'top' || pos === 'bottom') {
            let arrowLeft = rect.left + rect.width / 2 - left;
            arrow.style.left = Math.max(8, Math.min(arrowLeft, tw - 8)) + 'px';
        } else {
            let arrowTop = rect.top + rect.height / 2 - top;
            arrow.style.top = Math.max(8, Math.min(arrowTop, th - 8)) + 'px';
        }

        requestAnimationFrame(function () { tip.classList.add('visible'); });
    }

    function hide() {
        if (tip) { tip.remove(); tip = null; }
    }

    document.addEventListener('mouseover', function (e) {
        let el = e.target.closest('.css-hint[data-title]');
        if (el) show(el);
    });

    document.addEventListener('mouseout', function (e) {
        let el = e.target.closest('.css-hint[data-title]');
        if (el) hide();
    });

})();

/**
 * Compares two version strings.
 *
 * @returns {boolean} - Returns 1 if v1 > v2, -1 if v1 < v2, or 0 if equal.
 * @param serverVersion
 * @param localVersion
 * @param segments
 */
function shouldUpdate(serverVersion, localVersion, segments = 3) {

    const normalize = (version) => {
        return version
            .split('.')
            .map(Number) // Convert to numbers for consistency
            .map(part => String(part).padStart(2, '0')) // Pad with zeros
            .concat(Array(segments).fill('00')) // Ensure enough segments
            .slice(0, segments) // Trim excess segments
            .join(''); // Combine into a single string
    };

    const normalizedServer = normalize(serverVersion);
    const normalizedLocal = normalize(localVersion);

    return normalizedServer > normalizedLocal;
}

(function ($, version) {

    // On document ready
    $(document).ready(function () {

        /**
         * Adds a reset button to inputs and textareas with the 'allow-reset' attribute.
         * Clicking the reset button will reset the field to its placeholder value.
         */
        $('input[allow-reset], textarea[allow-reset]').each(function () {
            const $field = $(this);

            // Wrap the field in a container
            $field.wrap('<div class="input-append"></div>');

            // Create the reset button
            const $resetButton = $('<div class="append resetico css-hint" data-title="Reset field to its default value"><a href="#"><i class="icon fa fa-refresh"></i></a></div>');

            // Handle reset button click
            $resetButton.on('click', function () {
                $field.val($field.attr('placeholder'));
                $field.trigger('change');
                return false;
            });

            // Insert the reset button after the field
            $field.after($resetButton);

        });

        /**
         * Allows labels to activate associated checkboxes or radio buttons when focused and the space bar is pressed.
         * Improves accessibility for keyboard navigation.
         */
        document.querySelectorAll('label').forEach((label) => {
            label.addEventListener('keydown', (e) => {
                if (e.key === ' ') {

                    e.preventDefault();
                    const controlId = label.getAttribute('for');
                    const associatedControl = document.getElementById(controlId);
                    if (associatedControl) {
                        associatedControl.checked = !associatedControl.checked;
                    }

                }
            });
        });

        /**
         * Implements copy-to-clipboard functionality for elements with the 'data-copy' attribute.
         * The attribute value can be a selector or a string to copy.
         */
        $('[data-copy]').each(function () {

            const $element = $(this);
            const content = $element.attr('data-copy');

            // Attach click event to trigger copy action
            $element.on('click', function (e) {

                e.preventDefault();
                const originalContent = $element.html();
                let tempElement;

                // If 'content' is a selector, copy the text from the selected element
                if (content.startsWith('#')) {

                    const $copyTextarea = $(content);
                    if ($copyTextarea.length) {
                        $copyTextarea.focus().select().blur();
                    }

                } else {

                    // Otherwise, create a temporary textarea to hold the content
                    tempElement = document.createElement('textarea');
                    tempElement.value = content;
                    document.body.appendChild(tempElement);
                    tempElement.select();

                }

                try {

                    const successful = document.execCommand('copy');
                    if (successful) {
                        $element.html('<i class="fa fa-check"></i>&nbsp; SUCCESS!');
                        setTimeout(() => {
                            $element.html(originalContent);
                        }, 2000);
                    }

                } catch (err) {

                    $element.html('<i class="fa fa-times"></i>&nbsp; FAILED!');
                    setTimeout(() => {
                        $element.html(originalContent);
                    }, 4000);
                    console.error('Unable to copy:', err);

                }

                // Clean up a temporary element if it was created
                if (tempElement) {
                    document.body.removeChild(tempElement);
                }

                return false;
            });
        });

        /**
         * Handles dropdown menu toggling.
         */
        $('.dropdown-toggle').on('click', function () {

            const $menu = $(this).next('.dropdown-menu');
            if ($menu.hasClass('active')) {

                $menu.removeClass('active').stop(true, true).fadeOut(150);

            } else {

                $menu.addClass('active').stop(true, true).fadeIn(250);

                // Close the menu when clicking outside of it
                $(document).on('click', function () {
                    $menu.removeClass('active').stop(true, true).fadeOut(150);
                    $(document).off('click');
                });
            }

            return false;

        });

        /**
         * Toggles dark mode when the day-night button is clicked.
         */
        $('.day-night .clickable').on('click', function () {

            darkMode.toggle();
            return false;

        });

        /**
         * Popover delete confirmations.
         * Add data-confirm to any button/link to enable.
         */
        $(document).on('click', '[data-confirm]', function (e) {

            let btn = $(this);
            e.preventDefault();

            // Remove any existing popover
            $('.confirm-popover').remove();

            let popover = $('<div class="confirm-popover">' +
                '<span>Are you sure?</span>' +
                '<div class="confirm-popover-actions">' +
                '<a href="#" class="confirm-yes btn btn-danger btn-small">Yes</a>' +
                '<a href="#" class="confirm-no btn btn-default btn-small">No</a>' +
                '</div></div>');

            // Position relative to button
            btn.css('position', 'relative').append(popover);
            requestAnimationFrame(function () { popover.addClass('visible'); });

            popover.find('.confirm-yes').on('click', function (ev) {
                ev.preventDefault();
                popover.remove();

                // If it's a link with a real href, navigate
                if (btn.is('a') && btn.attr('href') && btn.attr('href') !== '#') {
                    window.location.href = btn.attr('href');
                } else {
                    // For JS-bound deletes, flag and re-trigger
                    btn.data('confirmed-action', true);
                    btn.removeAttr('data-confirm').trigger('click').attr('data-confirm', '');
                }
            });

            popover.find('.confirm-no').on('click', function (ev) {
                ev.preventDefault();
                popover.remove();
            });

            // Auto-dismiss after 5s
            setTimeout(function () { popover.remove(); }, 5000);

        });

        /**
         * Checks for updates every specified interval using localStorage to track the next check time.
         */
        $.getJSON('./index.php?page=api&action=update-check')
            .then((data) => {

                // No releases
                if (!data.releases || data.releases.length <= 0) {
                    return false;
                }

                // Check if an update is available
                const lastRelease = data.releases[0];
                const lastReleaseVersion = lastRelease.tag_name.replace('v', '')
                if (shouldUpdate(lastReleaseVersion, version)) {

                    // Indicate that an update is available
                    $('#tab-updates').append(`&nbsp;&nbsp;<span class="label label-important">v${lastReleaseVersion}</span>`);

                }


            });

        /**
         * Checks for system warnings and displays them accordingly.
         */
        $.getJSON('index.php?page=api&action=check-warnings').done(function (response) {

            // Iterate over each warning
            $.each(response, function (index, warning) {

                const $mainContainer = $('.container.main');

                // Display warnings and info messages
                if (warning.type === 'warning') {

                    $mainContainer.prepend(`<div class="alert alert-warning alert-icon"><div class="content">${markdown(warning.message)}</div></div>`);

                } else if (warning.type === 'info') {

                    $mainContainer.prepend(`<div class="alert alert-info alert-icon"><div class="content">${markdown(warning.message)}</div></div>`);

                } else if (warning.type === 'finish-upgrade') {

                    // Display an upgrade completion message with an iframe to run the post-update script
                    $mainContainer.prepend(
                        `<div class="alert alert-warning alert-icon">
                            <div class="content">
                                Running a post-update script, please do not interrupt this process...
                                <pre class="post-update">Processing...</pre>
                            </div>
                        </div>`
                    );

                    const sse = new EventSource('index.php?page=api&action=update-postscript');
                    sse.addEventListener('update', (event) => {

                        if (event.data === 'close') {
                            sse.close();
                            return;
                        }

                        let data = atob(event.data);
                        $('.post-update').append(data);

                    });

                } else if (warning.type === 'log-warning' && !window.location.href.includes('?page=logs')) {

                    // Display a log warning with options to view or delete the log file
                    const $alert = $(
                        `<div class="alert alert-warning alert-icon">
                            <div class="content">
                                Player may be experiencing some issues that are being logged into a file.
                                You can <a href="${window.location.pathname}?page=logs">view <i class="icon fa fa-external-link"></i></a>
                                or <a class="delete" href="#">delete <i class="icon fa fa-times"></i></a> the file.
                            </div>
                        </div>`
                    );

                    // Handle delete action
                    $alert.find('a.delete').on('click', function () {

                        if (!confirm('Are you sure you wish to delete the file?')) return false;

                        $alert.find('.content').text('Attempting to delete errors.log file...');
                        $.getJSON('./index.php?page=api&action=delete-log').done(function (result) {
                            if (result.success) {
                                $alert.remove();
                            } else {
                                $alert.find('.content').text('Unable to remove log file, please remove file manually!');
                            }
                        });

                        return false;

                    });

                    // Prepend the alert to the main container
                    $mainContainer.prepend($alert);

                }
            });

        });
    });
})(jQuery, version);