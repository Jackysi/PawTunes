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
 * JS-positioned tooltips, replacing CSS pseudo-element tooltips.
 * Supports: default (above), .left (to the left), auto-flip when near edges.
 */
(function () {

    let tip = null;
    let arrow = null;
    let currentEl = null;

    function show(el) {
        let title = el.getAttribute('data-title');
        if (!title) return;

        // An open confirm popover sits where the tooltip would, and the button's purpose is clear by then
        if (el.classList.contains('confirm-open')) return;

        // Already showing for this element, skip
        if (tip && currentEl === el) return;

        if (tip) tip.remove();
        currentEl = el;

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

        // Keep a local reference, hide() may null `tip` before the next frame
        let shown = tip;
        requestAnimationFrame(function () { shown.classList.add('visible'); });
    }

    function hide() {
        if (tip) { tip.remove(); tip = null; currentEl = null; }
    }

    // Hover is for real mice only: a touch tap also fires pointerover/pointerout, which would flash the tooltip
    document.addEventListener('pointerover', function (e) {
        if (e.pointerType !== 'mouse') return;
        let el = e.target.closest('.css-hint[data-title]');
        if (el) show(el);
    });

    document.addEventListener('pointerout', function (e) {
        if (e.pointerType !== 'mouse') return;
        let el = e.target.closest('.css-hint[data-title]');
        if (!el) return;

        // Don't hide if moving to a child element within the same hint
        let related = e.relatedTarget;
        if (related && el.contains(related)) return;

        hide();
    });

    // Keyboard users reach hints with Tab, so keyboard focus shows the tooltip as well. Mouse and programmatic
    // focus (e.g. Bootstrap returning focus after a modal) are not :focus-visible, so they leave it hidden.
    document.addEventListener('focusin', function (e) {
        let el = e.target.closest && e.target.closest('.css-hint[data-title]');
        if (el && el.matches(':focus-visible')) show(el);
    });

    document.addEventListener('focusout', function (e) {
        if (e.target.closest && e.target.closest('.css-hint[data-title]')) hide();
    });

    // Pointer type of the last press: click events do not carry it in every browser
    let lastPointer = 'mouse';
    document.addEventListener('pointerdown', function (e) {
        lastPointer = e.pointerType;
    }, {capture: true});

    // Touch screens have no hover: a tap on an info hint toggles its tooltip, a tap anywhere else closes it.
    // preventDefault also stops hints inside a <label> from toggling the checkbox or focusing the input.
    document.addEventListener('click', function (e) {
        let el = e.target.closest('.info-hint');
        if (!el) {
            if (tip && currentEl && currentEl.classList.contains('info-hint')) hide();
            return;
        }

        e.preventDefault();
        if (lastPointer === 'mouse') return; // Hover already shows it, a click must not close it

        if (tip && currentEl === el) hide();
        else show(el);
    });

    // A click on a data-confirm element opens its popover above it: the tooltip shown by the hover must give way
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-confirm]')) hide();
    }, {capture: true});

    // The tooltip is position: fixed, so it would drift away from its element on scroll
    window.addEventListener('scroll', hide, {passive: true, capture: true});

})();

/**
 * Artwork browser (Tools > Artwork Manager, AI > Stored AI Artwork): search filter, List/Gallery view and a
 * larger preview on hover. Every listener is delegated, so items a page renders later work too. After adding
 * or removing items, call refreshArtworkBrowser(browser) so the count and the active filter follow.
 */
(function () {

    const storageKey = 'pawtunes.panel.artwork-view.';
    const previewSize = 280; // the default stored artwork size, so the preview is not upscaled
    const previewGap = 16;   // distance between the cursor and the preview

    let preview = null;
    let previewFor = null;

    // Letters and digits only, any case: "ace of base" finds "ace.of.base"
    function normalize(text) {
        return text.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
    }

    function setView(browser, view) {
        browser.classList.toggle('is-list', view === 'list');
        browser.classList.toggle('is-gallery', view === 'gallery');
        browser.querySelectorAll('[data-view]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.view === view));
        });
    }

    function filter(browser) {
        if (!browser) return;

        let input = browser.querySelector('.artwork-search input');
        let query = normalize(input ? input.value : '');
        let items = browser.querySelectorAll('.artwork-item');
        let shown = 0;

        items.forEach(function (item) {
            // Normalized once per item, the names do not change while the page is open
            if (item.dataset.search === undefined) item.dataset.search = normalize(item.dataset.name || '');

            item.hidden = query !== '' && !item.dataset.search.includes(query);
            if (!item.hidden) shown++;
        });

        let count = browser.querySelector('.artwork-count');
        if (count) count.textContent = (query === '') ? items.length + (items.length === 1 ? ' image' : ' images') : shown + ' of ' + items.length;

        let noMatch = browser.querySelector('.artwork-no-match');
        if (noMatch) noMatch.hidden = shown > 0 || items.length === 0;
    }

    window.refreshArtworkBrowser = filter;

    function init() {
        document.querySelectorAll('.artwork-browser').forEach(function (browser) {
            let view = null;
            try { view = localStorage.getItem(storageKey + browser.dataset.viewKey); } catch (e) {}

            setView(browser, (view === 'list' || view === 'gallery') ? view : (browser.dataset.defaultView || 'list'));
            filter(browser);
        });
    }

    document.addEventListener('input', function (e) {
        if (e.target.matches('.artwork-search input')) filter(e.target.closest('.artwork-browser'));
    });

    // Escape clears the search (type=search does that natively in some browsers only)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !e.target.matches('.artwork-search input') || e.target.value === '') return;

        e.preventDefault();
        e.target.value = '';
        filter(e.target.closest('.artwork-browser'));
    });

    document.addEventListener('click', function (e) {
        let button = e.target.closest('.artwork-browser [data-view]');
        if (!button) return;

        let browser = button.closest('.artwork-browser');
        setView(browser, button.dataset.view);
        try { localStorage.setItem(storageKey + browser.dataset.viewKey, button.dataset.view); } catch (err) {}
    });

    // Preview beside the cursor, flipped to the other side near the viewport edges
    function movePreview(e) {
        let left = e.clientX + previewGap, top = e.clientY + previewGap;
        if (left + previewSize > window.innerWidth) left = e.clientX - previewGap - previewSize;
        if (top + previewSize > window.innerHeight) top = e.clientY - previewGap - previewSize;

        preview.style.left = Math.max(8, left) + 'px';
        preview.style.top = Math.max(8, top) + 'px';
    }

    function hidePreview() {
        if (preview) preview.classList.remove('in');
        previewFor = null;
    }

    // Mouse only: a touch tap also fires pointerover, and there is no pointerout to close it again
    document.addEventListener('pointerover', function (e) {
        if (e.pointerType !== 'mouse') return;

        let thumb = e.target.closest('.artwork-browser .artwork-thumb');
        if (!thumb || thumb === previewFor) return;

        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'image-preview artwork-preview';
            preview.innerHTML = '<img alt="" width="' + previewSize + '" height="' + previewSize + '">';
            document.body.appendChild(preview);
        }

        previewFor = thumb;
        preview.firstChild.src = thumb.currentSrc || thumb.src;
        movePreview(e);
        preview.classList.add('in');
    });

    document.addEventListener('pointermove', function (e) {
        if (previewFor && e.target === previewFor) movePreview(e);
    });

    document.addEventListener('pointerout', function (e) {
        if (previewFor && e.target === previewFor) hidePreview();
    });

    // The preview is position: fixed, so it would stay behind on scroll
    window.addEventListener('scroll', hidePreview, {passive: true, capture: true});

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

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

                // Close the menu when clicking outside of it. Namespaced: a bare off('click') would also unbind the
                // delegated data-confirm handler, and confirmations would silently stop working after one dropdown
                $(document).off('click.dropdown-close').one('click.dropdown-close', function () {
                    $menu.removeClass('active').stop(true, true).fadeOut(150);
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
            $('.confirm-open').removeClass('confirm-open');

            let popover = $('<div class="confirm-popover">' +
                '<span>Are you sure?</span>' +
                '<div class="confirm-popover-actions">' +
                '<a href="#" class="confirm-yes btn btn-danger btn-small">Yes</a>' +
                '<a href="#" class="confirm-no btn btn-default btn-small">No</a>' +
                '</div></div>');

            // Fixed and on <body>, like the tooltip: inside the button it was clipped by scroll containers (the
            // artwork list) and inherited the button's own styles (sizes of the artwork action buttons)
            $('body').append(popover);
            btn.addClass('confirm-open');

            // Above the button, right edges aligned. Below it when there is no room above.
            const gap = 8;
            const rect = btn[0].getBoundingClientRect();
            const below = rect.top - popover.outerHeight() - gap < gap;
            popover.toggleClass('below', below).css({
                top: below ? rect.bottom + gap : rect.top - popover.outerHeight() - gap,
                left: Math.max(gap, rect.right - popover.outerWidth())
            });

            requestAnimationFrame(function () { popover.addClass('visible'); });

            // A fixed popover would drift away from its button on scroll or resize, so both close it
            const dismiss = function () {
                popover.remove();

                // A late dismiss (the 5 s timer) of a replaced popover must not unmark a button that has a new one
                if (!$('.confirm-popover').length) btn.removeClass('confirm-open');

                window.removeEventListener('scroll', dismiss, true);
                window.removeEventListener('resize', dismiss);
            };

            window.addEventListener('scroll', dismiss, {passive: true, capture: true});
            window.addEventListener('resize', dismiss);

            popover.find('.confirm-yes').on('click', function (ev) {
                ev.preventDefault();
                dismiss();

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
                dismiss();
            });

            // Auto-dismiss after 5s
            setTimeout(dismiss, 5000);

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

                    // EventSource can only GET, so the CSRF token travels in the query string (checked in panel/api.php)
                    const sse = new EventSource('index.php?page=api&action=update-postscript&_token=' + encodeURIComponent(csrfToken));
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
                        $.post('./index.php?page=api&action=delete-log', {_token: csrfToken}, null, 'json').done(function (result) {
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