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

( function( $ ) {

    "use strict";

    /**
     * Default options
     * @type {{duration: number, btnText: string, autoOverflow: boolean, classes: {container: string, options: string, placeholder: string, btn: string, currentValue: string, selected: string, open: string, search_box: string}, autoWidth: boolean, events: {transitionEnd: string}}}
     */
    let defaults = {
        duration    : 75,
        autoOverflow: true,
        autoWidth   : false,
        btnText     : '<svg fill="currentColor" width="24px" height="24px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="drop-arrow"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.4 9.2C8.84183 8.86863 9.46863 8.95817 9.8 9.4L12 12.3333L14.2 9.4C14.5314 8.95817 15.1582 8.86863 15.6 9.2C16.0418 9.53137 16.1314 10.1582 15.8 10.6L13.2 14.0667C12.6 14.8667 11.4 14.8667 10.8 14.0667L8.2 10.6C7.86863 10.1582 7.95817 9.53137 8.4 9.2Z"></path></svg>',
        classes     : {
            container   : "select-box",
            currentValue: "current-value",
            placeholder : "placeholder",
            search_box  : "search-string",
            btn         : "drop-arrow",
            options     : "options",
            selected    : "selected",
            open        : "opened"
        },
        events      : {
            'transitionEnd': 'transitionend webkitTransitionEnd oTransitionEnd'
        }
    };

    /**
     * Call against any select element or collection containing select elements
     * Non-select elements will be ignored.
     *
     * @return {jQuery}         The jQuery object.
     */
    $.fn.selectbox = function( options ) {

        // Check provided options
        options = options || {};

        // Merge defaults and options to make settings
        let settings = $.extend( true, {}, defaults, options );

        // Search string should be global in the plugin
        let searchString = '';

        // Maintain a dictionary of values in the select
        // so they can be matched as the user types
        let Dictionary = function( select ) {

            let optionsHtml = "";
            let searchTimer = null;
            let dict        = {};

            const clearSearchString = function() {
                searchString = "";
                select.next( '.' + settings.classes.container ).trigger( 'clear_search' );
            };

            // JS doesn't notify us when 3rd-party code modifies the option
            // list, so we get the option HTML as a string and compare it
            // to the last one we stored. If they're different, we rebuild
            // the dictionary.
            const rebuild = function( optional ) {

                if ( select.html() !== optionsHtml || optional === 'ignore' ) {

                    $.each( select.find( "option" ), function() {

                        let el = $( this );
                        if ( el.text().length ) {

                            dict[ $.trim( el.text() ) ] = el.val();

                        }

                    } );

                    optionsHtml = select.html();

                }

            };

            // Restarts the countdown to clear the search string
            const resetSearch = function() {

                clearTimeout( searchTimer );
                searchTimer = setTimeout( clearSearchString, 1500 );

            };

            return {
                find : function( text ) {

                    let textLength;
                    let selectedOption = $( select.find( "option" ).get( select.get( 0 ).selectedIndex ) );
                    let result         = false;

                    rebuild();
                    resetSearch();

                    searchString += text; // Single key search, append + before = if you want word search
                    textLength = searchString.length;

                    // Trigger update
                    select.next( '.' + settings.classes.container ).trigger( 'update_search', [ searchString ] );

                    // Search if string and selected different
                    if ( !selectedOption.length || selectedOption.text().substring( 0, textLength ).toUpperCase() !== searchString ) {

                        // search (previously we only searched if nothing was selected)
                        if ( textLength ) {
                            $.each( dict, function( key, value ) {

                                if ( key.substring( 0, textLength ).toUpperCase() === searchString ) {

                                    result = value;
                                    return false;

                                }

                            } );
                        }

                    }

                    return result;

                },
                clear: function() {

                    clearTimeout( searchTimer );
                    clearSearchString();

                }

            };

        };

        /**
         * Setup each <select> in the provided collection.
         */
        this.each( function() {

            let el   = $( this );
            let self = this;
            let options;
            let anchors;
            let dict = new Dictionary( el );

            /**
             * Create HTML elements within JS (not yet appended to DOM)
             */
            let search_box   = $( "<div />", { class: settings.classes.search_box } );
            let container    = $( "<div/>", { "class": settings.classes.container, css: {/* "position": "relative" */ } } );
            let currentValue = $( "<div/>", { "class": settings.classes.currentValue, tabindex: 0 } );
            let placeholder  = $( "<div/>", { "class": settings.classes.placeholder } );
            let list         = $( "<ul/>", { "class": settings.classes.options, css: {/*position: "absolute", left: 0*/ } } );
            let btn          = $( settings.btnText );

            // If the current element is not a <select> element, skip it.
            if ( !el.is( "select" ) ) return;

            // Check to see if this element has already been converted to "selectbox"
            if ( el.data( "selectbox" ) === true ) {

                // Clean out select box to re-create it
                el.next( "." + settings.classes.container ).remove();

            }

            // Get collection of <option> elements
            options = el.find( "option" );
            currentValue.append( placeholder, btn );


            /**
             * Go through each option and add it to the list
             */
            options.each( function() {

                let self = $( this );
                let li   = $( "<li/>" );
                let a    = $( "<a/>", {
                    href : "#",
                    text : self.text(),
                    class: ( self.prop( 'disabled' ) ) ? 'disabled' : ''
                } );

                a.on( 'click', function( e ) {

                    e.preventDefault();
                    if ( self.prop( 'disabled' ) ) return false;

                    if ( el.val() !== self.val() ) {

                        if ( self.attr( 'data-html' ) != null ) {

                            placeholder.html( self.attr( 'data-html' ) );

                        } else {

                            placeholder.text( self.text() );

                        }

                        list.find( "a" ).removeClass( settings.classes.selected );
                        a.addClass( settings.classes.selected );
                        el.val( self.val() ).trigger( "change", [ { selectbox: true } ] );

                    }

                    container.removeClass( settings.classes.open );
                    container.trigger( 'clear_search' );

                } );

                li.append( a );
                list.append( li );

                // Add an option to use HTML as text
                if ( self.attr( 'data-html' ) != null )
                    a.html( self.attr( 'data-html' ) );

            } );


            /**
             * Option to use data-value on select box (fixed)
             */
            if ( el.attr( 'data-value' ) !== null && el.attr( 'data-value' ) !== '' ) {

                // Only select value if found in select box!
                let attr_value = el.attr( 'data-value' );
                if ( el.find( 'option[value="' + attr_value + '"]' ).length >= 1 ) {
                    el.val( attr_value );
                }

            }


            /**
             * Handle a list open/close.
             */
            currentValue.on( "click", function( e ) {

                e.preventDefault();

                // Don't open if disabled
                if ( $( container ).hasClass( 'disabled' ) ) return false;

                // Only on OPEN not CLOSE
                if ( !container.hasClass( settings.classes.open ) ) {

                    // Open
                    container.addClass( settings.classes.open );

                    // Calculations (slitted for easier understanding)
                    let calculate_height = currentValue.outerHeight() + list.outerHeight();
                    let calculate_scroll = $( window ).scrollTop() + $( window ).height() - currentValue.offset().top;

                    // If an element goes bellow document height, make it go up instead of down
                    if ( calculate_height > calculate_scroll ) {

                        list.css( { 'top': 'auto', 'bottom': currentValue.outerHeight() } );

                    } else { // Else, go down

                        list.css( { 'bottom': 'auto', 'top': currentValue.outerHeight() } );

                    }

                    // Add scrollbars only if overflowing
                    if ( list[ 0 ].scrollHeight > list.innerHeight() ) list.css( { 'overflow-y': 'scroll', 'overflow-x': 'hidden' } );
                    else list.css( 'overflow', 'hidden' );

                    // Do your stuff
                    el.trigger( 'recalculate' );
                    container.trigger( 'calibrate_search' );


                } else {

                    // Close
                    container.removeClass( settings.classes.open );

                }

            } );


            /**
             * On type start (search)
             */
            currentValue.on( "keydown", function( e ) {

                let match;
                let key          = e.charCode || e.keyCod;
                let keyChar      = String.fromCharCode( key );
                let intercept    = false;
                let currentIndex = self.selectedIndex;

                // Down arrow, right arrow
                if ( key === 40 || key === 39 ) {
                    intercept          = true;
                    self.selectedIndex = Math.min( options.length - 1, self.selectedIndex + 1 );
                }

                // Up arrow, left arrow
                if ( key === 38 || key === 37 ) {
                    intercept          = true;
                    self.selectedIndex = Math.max( 0, self.selectedIndex - 1 );
                }

                // Enter key, space bar
                // Causes the option list to close (if it's open)
                if ( key === 13 || key === 32 ) {
                    currentValue.trigger( 'click' );
                    intercept = true;
                }

                // Here we bind backspace/delete
                if ( key === 8 || key === 46 ) {
                    dict.clear();
                    intercept = true;
                }

                // Stop processing if it's a command key (i.e., directional arrow)
                if ( intercept ) {

                    e.preventDefault();
                    if ( currentIndex !== self.selectedIndex ) {

                        dict.clear();
                        el.trigger( "change" );

                    }

                    return false;

                }


                // Match other keystrokes - used for typing values into the select
                // and having it match them
                if ( /[a-zA-Z0-9-_ !@#$%^&*\(\)+\/\\?><;:'"|]/.test( keyChar ) ) {

                    match           = dict.find( keyChar, placeholder );
                    dict.search_obj = placeholder;

                    // Attempt matching
                    if ( match ) {
                        el.val( match ).trigger( "change" );
                    }

                }

            } );


            /**
             * Search bind clear
             */
            container.on( 'clear_search', function() {

                // Wait animation end before removing text
                search_box.removeClass( 'inSearch' )
                    .off( settings.events.transitionEnd )
                    .on( settings.events.transitionEnd, function() {

                        // Only when class is removed, otherwise wait another end
                        if ( !search_box.is( '.inSearch' ) )
                            search_box.empty();

                    } );

            } );


            /**
             * Search bind update
             */
            container.on( 'update_search', function( event, string ) {

                search_box.text( string ).addClass( 'inSearch' );

            } );


            // After completion, run calibration
            /*container.on('calibrate_search', function() {

                if ( container.hasClass( settings.classes.open ) ) {

                    // Search indicator container
                    search_box.addClass( 'inSearch' );

                } else {

                    search_box.removeClass( 'inSearch' );

                }

            } );*/


            /**
             * Bind Re-calculate selection event (new)
             */
            el.on( 'recalculate', function() {

                let index            = this.selectedIndex;
                let listScrollOffset = Math.abs( list.scrollTop() );
                let selectedAnchor   = $( anchors.get( index ) );
                let positionOffset   = parseInt( selectedAnchor.outerHeight(), 10 );
                let selectionOffset  = selectedAnchor.offset().top - anchors.first().offset().top;

                // Scroll the option list up if selection is above the visible
                // portion of the list
                if ( selectionOffset < listScrollOffset ) {

                    // Usually up
                    list.clearQueue().animate( { scrollTop: positionOffset + selectionOffset - container.outerHeight() }, settings.duration );

                } else if ( selectionOffset > listScrollOffset + list.height() - selectedAnchor.outerHeight() ) {

                    // Usually down
                    list.clearQueue().animate( { scrollTop: positionOffset + selectionOffset - list.height() + selectedAnchor.outerHeight() - container.outerHeight() }, settings.duration );

                }

            } );


            /**
             * Listen for change events on the original select element
             */
            el.on( "change", function() {

                let index          = this.selectedIndex;
                let selectedAnchor = $( anchors.get( index ) );

                // Add HTML support
                if ( $( options.get( index ) ).attr( 'data-html' ) == null ) {

                    placeholder.text( $( options.get( index ) ).text() );

                } else {

                    placeholder.html( $( options.get( index ) ).attr( 'data-html' ) );

                }

                // Disable/Enable select box
                if ( $( el ).prop( 'disabled' ) ) $( container ).addClass( 'disabled' );
                else $( container ).removeClass( 'disabled' );

                // Select a selected option on the list
                anchors.removeClass( settings.classes.selected );
                selectedAnchor.addClass( settings.classes.selected );

                // Trigger recalculating (to fix position of selection)
                el.trigger( 'recalculate' );

            } );


            /**
             * Close the list if the user clicks elsewhere on the page.
             */
            $( document ).on( "mouseup", function( e ) {
                if ( !container.has( e.target ).length ) {

                    container.trigger( 'clear_search' );
                    container.removeClass( settings.classes.open );

                }
            } );


            // Get an anchor list
            anchors = list.find( "a" );

            // Set initial placeholder text
            if ( $( options.get( el[ 0 ].selectedIndex ) ).attr( 'data-html' ) == null ) {

                placeholder.text( $( options.get( el[ 0 ].selectedIndex ) ).text() );

            } else {

                placeholder.html( $( options.get( el[ 0 ].selectedIndex ) ).attr( 'data-html' ) );

            }

            // Set initial anchor styling
            $( anchors.get( el[ 0 ].selectedIndex ) ).addClass( settings.classes.selected );

            // Check if auto width is enabled
            if ( settings.autoWidth ) {

                placeholder.width( el.width() );

            }

            /**
             * Finally, append everything to DOM
             */
            container.append( currentValue, list );
            container.append( search_box );
            el.hide().after( container ).data( "selectbox", true );
            el.trigger( 'selectbox.init' );


        } );

        return this;

    };

}( jQuery ) );