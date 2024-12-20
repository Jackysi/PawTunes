/**
 * Compares two version strings.
 *
 * @returns {boolean} - Returns 1 if v1 > v2, -1 if v1 < v2, or 0 if equal.
 * @param serverVersion
 * @param localVersion
 * @param segments
 */
function shouldUpdate( serverVersion, localVersion, segments = 3 ) {

    const normalize = ( version ) => {
        return version
            .split( '.' )
            .map( Number ) // Convert to numbers for consistency
            .map( part => String( part ).padStart( 2, '0' ) ) // Pad with zeros
            .concat( Array( segments ).fill( '00' ) ) // Ensure enough segments
            .slice( 0, segments ) // Trim excess segments
            .join( '' ); // Combine into a single string
    };

    const normalizedServer = normalize( serverVersion );
    const normalizedLocal  = normalize( localVersion );

    console.warn( normalizedServer, normalizedLocal );

    return normalizedServer > normalizedLocal;
}

( function( $, version ) {

    // On document ready
    $( document ).ready( function() {

        /**
         * Adds a reset button to inputs and textareas with the 'allow-reset' attribute.
         * Clicking the reset button will reset the field to its placeholder value.
         */
        $( 'input[allow-reset], textarea[allow-reset]' ).each( function() {
            const $field = $( this );

            // Wrap the field in a container
            $field.wrap( '<div class="input-append"></div>' );

            // Create the reset button
            const $resetButton = $( '<div class="append resetico css-hint" data-title="Reset field to its default value"><a href="#"><i class="icon fa fa-refresh"></i></a></div>' );

            // Handle reset button click
            $resetButton.on( 'click', function() {
                $field.val( $field.attr( 'placeholder' ) );
                return false;
            } );

            // Insert the reset button after the field
            $field.after( $resetButton );
        } );

        /**
         * Allows labels to activate associated checkboxes or radio buttons when focused and the space bar is pressed.
         * Improves accessibility for keyboard navigation.
         */
        document.querySelectorAll( 'label' ).forEach( ( label ) => {
            label.addEventListener( 'keydown', ( e ) => {
                if ( e.key === ' ' ) {

                    e.preventDefault();
                    const controlId         = label.getAttribute( 'for' );
                    const associatedControl = document.getElementById( controlId );
                    if ( associatedControl ) {
                        associatedControl.checked = !associatedControl.checked;
                    }

                }
            } );
        } );

        /**
         * Implements copy-to-clipboard functionality for elements with the 'data-copy' attribute.
         * The attribute value can be a selector or a string to copy.
         */
        $( '[data-copy]' ).each( function() {

            const $element = $( this );
            const content  = $element.attr( 'data-copy' );

            // Attach click event to trigger copy action
            $element.on( 'click', function( e ) {

                e.preventDefault();
                const originalContent = $element.html();
                let tempElement;

                // If 'content' is a selector, copy the text from the selected element
                if ( content.startsWith( '#' ) ) {

                    const $copyTextarea = $( content );
                    if ( $copyTextarea.length ) {
                        $copyTextarea.focus().select().blur();
                    }

                } else {

                    // Otherwise, create a temporary textarea to hold the content
                    tempElement       = document.createElement( 'textarea' );
                    tempElement.value = content;
                    document.body.appendChild( tempElement );
                    tempElement.select();

                }

                try {

                    const successful = document.execCommand( 'copy' );
                    if ( successful ) {
                        $element.html( '<i class="fa fa-check"></i>&nbsp; SUCCESS!' );
                        setTimeout( () => {
                            $element.html( originalContent );
                        }, 2000 );
                    }

                } catch ( err ) {

                    $element.html( '<i class="fa fa-times"></i>&nbsp; FAILED!' );
                    setTimeout( () => {
                        $element.html( originalContent );
                    }, 4000 );
                    console.error( 'Unable to copy:', err );

                }

                // Clean up a temporary element if it was created
                if ( tempElement ) {
                    document.body.removeChild( tempElement );
                }

                return false;
            } );
        } );

        /**
         * Handles dropdown menu toggling.
         */
        $( '.dropdown-toggle' ).on( 'click', function() {

            const $menu = $( this ).next( '.dropdown-menu' );
            if ( $menu.hasClass( 'active' ) ) {

                $menu.removeClass( 'active' ).stop( true, true ).fadeOut( 150 );

            } else {

                $menu.addClass( 'active' ).stop( true, true ).fadeIn( 250 );

                // Close the menu when clicking outside of it
                $( document ).on( 'click', function() {
                    $menu.removeClass( 'active' ).stop( true, true ).fadeOut( 150 );
                    $( document ).off( 'click' );
                } );
            }

            return false;

        } );

        /**
         * Toggles dark mode when the day-night button is clicked.
         */
        $( '.day-night .clickable' ).on( 'click', function() {

            darkMode.toggle();
            return false;

        } );

        /**
         * Checks for updates every specified interval using localStorage to track the next check time.
         */
        $.getJSON( './index.php?page=api&action=update-check' )
            .then( ( data ) => {

                // No releases
                if ( !data.releases || data.releases.length <= 0 ) {
                    return false;
                }

                // Check if an update is available
                const lastRelease        = data.releases[ 0 ];
                const lastReleaseVersion = lastRelease.tag_name.replace( 'v', '' )
                if ( shouldUpdate( lastReleaseVersion, version ) ) {

                    // Indicate that an update is available
                    $( '#tab-updates' ).append( `&nbsp;&nbsp;<span class="label label-important">v${lastReleaseVersion}</span>` );

                }


            } );

        /**
         * Checks for system warnings and displays them accordingly.
         */
        $.getJSON( 'index.php?page=api&action=check-warnings' ).done( function( response ) {

            // Iterate over each warning
            $.each( response, function( index, warning ) {

                const $mainContainer = $( '.container.main' );

                // Display warnings and info messages
                if ( warning.type === 'warning' ) {

                    $mainContainer.prepend( `<div class="alert alert-warning alert-icon"><div class="content">${markdown( warning.message )}</div></div>` );

                } else if ( warning.type === 'info' ) {

                    $mainContainer.prepend( `<div class="alert alert-info alert-icon"><div class="content">${markdown( warning.message )}</div></div>` );

                } else if ( warning.type === 'finish-upgrade' ) {

                    // Display an upgrade completion message with an iframe to run the post-update script
                    $mainContainer.prepend(
                        `<div class="alert alert-warning alert-icon">
                            <div class="content">
                                Running a post-update script, please do not interrupt this process...
                                <pre class="post-update">Loading, please wait...</pre>
                            </div>
                        </div>`
                    );

                    const sse = new EventSource( 'index.php?page=api&action=update-postscript' );
                    sse.addEventListener( 'update', ( event ) => {

                        if ( event.data === 'close' ) {
                            sse.close();
                            return;
                        }

                        let data = atob( event.data );
                        $( '.post-update' ).append( data );

                    } );

                } else if ( warning.type === 'log-warning' && !window.location.href.includes( '?page=logs' ) ) {

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
                    $alert.find( 'a.delete' ).on( 'click', function() {

                        if ( !confirm( 'Are you sure you wish to delete the file?' ) ) return false;

                        $alert.find( '.content' ).text( 'Attempting to delete errors.log file...' );
                        $.getJSON( './index.php?page=api&action=delete-log' ).done( function( result ) {
                            if ( result.success ) {
                                $alert.remove();
                            } else {
                                $alert.find( '.content' ).text( 'Unable to remove log file, please remove file manually!' );
                            }
                        } );

                        return false;

                    } );

                    // Prepend the alert to the main container
                    $mainContainer.prepend( $alert );

                }
            } );

        } );
    } );
} )( jQuery, version );