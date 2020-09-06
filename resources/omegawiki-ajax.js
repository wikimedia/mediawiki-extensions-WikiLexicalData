jQuery( document ).ready( function ( $ ) {
	/*
	 * Some javascript that is run when the page finished loading
	 */

	// add and manage arrows to navigate the tabs
	if ( $( '.wd-tablist' ).length ) {
		initializeTabs();

		$( window ).on( 'resize', function () {
			updateTabs();
		} );
	}

	// sticky explang
	var explangUrl = document.URL.match( /explang=\d+/gi );
	if ( explangUrl !== null ) {
		var explangNb = explangUrl[ 0 ].replace( 'explang=', '' );
		$( '#ca-edit, #ca-history, #ca-view' ).find( 'a' ).attr( 'href', function ( i, val ) {
			var bigoudi = '&';
			if ( val.match( /\?/gi ) === null ) { bigoudi = '?'; }
			return val + bigoudi + 'explang=' + explangNb;
		} );
	}

	/*
	 * Some more javascript events
	 */

	// toggle the togglable elements
	// delegated event
	$( 'body' ).on( 'click', '.toggle', function ( event ) {
		$( this ).children( '.prefix' ).toggle();
		$( this ).parent().next().fadeToggle( 'fast' );
	} );

	$( 'a' ).on( 'click', function ( event ) {
		// avoid the toggling if a link is clicked
		event.stopPropagation();
	} );

	// toggle the annotation popups
	$( '.togglePopup' ).on( 'click', function () {
		$( this ).children( 'span' ).toggle();

		// if no corresponding popupToggleable (in edit mode): create it
		// and get the values
		if ( $( this ).next( '.popupToggleable' ).length === 0 ) {

			var popupOpenHideLink = this,
				myAction = $( this ).attr( 'action' ),
				URL = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' );
			URL = URL + '/Special:PopupEditor';

			var postdata = {
				type: 'annotation',
				syntransid: $( popupOpenHideLink ).attr( 'syntransid' ),
				dmid: $( popupOpenHideLink ).attr( 'dmid' ),
				idpathflat: $( popupOpenHideLink ).attr( 'idpathflat' )
			};

			if ( myAction === 'history' ) {
				postdata.action = 'history';
			}
			$.post( URL, postdata, function ( data ) {
				// insert the data and show it
				$( popupOpenHideLink ).after( data );
				$( popupOpenHideLink ).next( '.popupToggleable' ).show( 100 );
			} );

		} else {
			// there is already data, toggle it
			$( this ).next( '.popupToggleable' ).toggle( 100 );
		}
	} );

	// POPUP EDITING BUTTONS
	// *** Edit ***
	$( 'body' ).on( 'click', '.owPopupEdit', function ( event ) {

		var popupContent = $( this ).parents( '.popupToggleable' ),
			popupOpenHideLink = $( popupContent ).prev( '.togglePopup' ),
			URL = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' );
		URL = URL + '/Special:PopupEditor';

		var postdata = {
			type: 'annotation',
			syntransid: $( popupOpenHideLink ).attr( 'syntransid' ),
			dmid: $( popupOpenHideLink ).attr( 'dmid' ),
			idpathflat: $( popupOpenHideLink ).attr( 'idpathflat' ),
			action: 'edit'
		};
		$.post( URL, postdata, function ( data ) {
			// insert the data and show it
			$( popupContent ).replaceWith( data );
			// it has been replaced, we need to get the new element
			popupContent = $( popupOpenHideLink ).next( '.popupToggleable' );
			$( popupContent ).find( '.owPopupEdit' ).hide();
			$( popupContent ).find( '.owPopupSave' ).show();
			$( popupContent ).find( '.owPopupCancel' ).show();
			// open all links
			$( popupContent ).find( '[class*="expand"]' ).show();
			$( popupContent ).find( '[class*="collapse"]' ).hide();
			// slow show, because a simple show() does not draw the element correctly
			$( popupContent ).show( 100 );
		} );
	} );

	// *** Cancel ***
	// just reload the normal view
	// alternatively, we could store the old element instead of reloading a new one
	$( 'body' ).on( 'click', '.owPopupCancel', function ( event ) {

		var popupContent = $( this ).parents( '.popupToggleable' ),
			popupOpenHideLink = $( popupContent ).prev( '.togglePopup' ),
			URL = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' );
		URL = URL + '/Special:PopupEditor';

		var postdata = {
			type: 'annotation',
			syntransid: $( popupOpenHideLink ).attr( 'syntransid' ),
			dmid: $( popupOpenHideLink ).attr( 'dmid' ),
			idpathflat: $( popupOpenHideLink ).attr( 'idpathflat' )
		};
		$.post( URL, postdata, function ( data ) {
			// insert the data and show it
			$( popupContent ).replaceWith( data );
			popupContent = $( popupOpenHideLink ).next( '.popupToggleable' );
			$( popupContent ).find( '.owPopupEdit' ).show();
			$( popupContent ).find( '.owPopupSave' ).hide();
			$( popupContent ).find( '.owPopupCancel' ).hide();
			// slow show, because a simple show() does not draw the element correctly
			$( popupContent ).show( 100 );
		} );
	} );

	// *** Save ***
	$( 'body' ).on( 'click', '.owPopupSave', function ( event ) {

		var popupContent = $( this ).parents( '.popupToggleable' ),
			popupOpenHideLink = $( popupContent ).prev( '.togglePopup' ),
			URL = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScript' );
		URL = URL + '/Special:PopupEditor';

		var postdata = {
			type: 'annotation',
			syntransid: $( popupOpenHideLink ).attr( 'syntransid' ),
			dmid: $( popupOpenHideLink ).attr( 'dmid' ),
			idpathflat: $( popupOpenHideLink ).attr( 'idpathflat' ),
			action: 'save'
		};

		// find the values to update / add / remove
		// several items in $(popupContent).find('input, textarea, select')
		$( popupContent ).find( 'input, textarea, select' ).each( function () {
			var thisName = $( this ).attr( 'name' ),
				thisVal = $( this ).val(),
				thisType = $( this ).attr( 'type' );

			// but the value for checkboxes does not indicate that it is checked
			if ( thisType && thisType === 'checkbox' ) {
				if ( $( this ).is( ':checked' ) ) {
					// checkbox is checked, send its data
					postdata[ thisName ] = thisVal;
				}
			} else {
				// not a checkbox, normal behavior
				if ( thisName && thisVal ) {
					// add it to the data sent to the server
					postdata[ thisName ] = thisVal;
				}
			}
		} );

		$.post( URL, postdata, function ( data ) {
			// insert the data and show it
			$( popupContent ).replaceWith( data );
			popupContent = $( popupOpenHideLink ).next( '.popupToggleable' );
			$( popupContent ).find( '.owPopupEdit' ).show();
			$( popupContent ).find( '.owPopupSave' ).hide();
			$( popupContent ).find( '.owPopupCancel' ).hide();
			// slow show, because a simple show() does not draw the element correctly
			$( popupContent ).show( 100 );
		} );
	} );

	/*
	 * initializeTabs adds tabs on the top of a page to navigate between languages
	 * when an expression exists in several languages
	 */
	function initializeTabs() {
		var previousArrow = '<span class="wd-previousArrow">' + '❮' + '</span>',
			nextArrow = '<span class="wd-nextArrow">' + '❯' + '</span>';
		// add visible class to every item by default
		$( '.wd-tablist' ).children().addClass( 'visibleTab' );
		// remove right padding for the last element
		$( '.wd-tablist .wd-tabitem:last' ).css( 'padding-right', '0px' );

		// remove the tabs that are out of the window
		// theoretically, overflow:hidden should work, but
		// setting overflow and span and float seems a bit tricky
		var tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
		while ( tablistright + 30 > $( window ).width() ) {
			// remove tabs on the right until it fits in the window
			$( '.wd-tablist .visibleTab:last' )
				.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
				.hide();
			tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
		}

		// add arrows
		$( '.wd-tablist .wd-tabitem:first' ).before( previousArrow );
		$( '.wd-previousArrow' ).hide();
		$( '.wd-tablist ' ).after( nextArrow );

		// if the last element is visible, we don't need the nextArrow
		if ( $( '.wd-tablist .wd-tabitem:last' ).hasClass( 'visibleTab' ) ) {
			$( '.wd-nextArrow' ).hide();
		}

		// next arrow click
		$( '.wd-nextArrow' ).on( 'click', function () {
			// show next tab = first hidden tab after the next visible tab
			$( '.wd-tablist .visibleTab:last' ).next()
				.addClass( 'visibleTab' ).removeClass( 'hiddenTab' )
				.show();

			// show previous arrow
			$( '.wd-previousArrow' ).show();

			// remove visible tabs on the left until it fits the window
			var tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			while ( tablistright + 30 > $( window ).width() ) {
				// remove visible tabs on the left until it fits in the window
				$( '.wd-tablist .visibleTab:first' )
					.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
					.hide();
				tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			}

			// check if maybe we can display more elements on the right
			while ( ( tablistright + 30 < $( window ).width() ) && $( '.wd-tabitem:last' ).hasClass( 'hiddenTab' ) ) {
				$( '.wd-tablist .visibleTab:last' ).next()
					.addClass( 'visibleTab' ).removeClass( 'hiddenTab' )
					.show();
				tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			}
			// remove last tab if we have been to far
			if ( tablistright + 30 > $( window ).width() ) {
				$( '.wd-tablist .visibleTab:last' )
					.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
					.hide();
			}

			// remove next arrow if last tab is visible
			if ( $( '.wd-tablist .wd-tabitem:last' ).hasClass( 'visibleTab' ) ) {
				$( '.wd-nextArrow' ).hide();
			}

		} ); // nextArrow click

		// previous arrow click
		$( '.wd-previousArrow' ).on( 'click', function () {
			// show previous tab = the one before the first visible tab
			$( '.wd-tablist .visibleTab:first' ).prev()
				.addClass( 'visibleTab' ).removeClass( 'hiddenTab' )
				.show();

			// show next arrow
			$( '.wd-nextArrow' ).show();

			// remove previous arrow if first tab is visible
			if ( $( '.wd-tablist .wd-tabitem:first' ).hasClass( 'visibleTab' ) ) {
				$( '.wd-previousArrow' ).hide();
			} // if

			// remove visible tabs on the right until it fits the window
			var tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			while ( tablistright + 30 > $( window ).width() ) {
				// remove tabs on the right until it fits in the window
				$( '.wd-tablist .visibleTab:last' )
					.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
					.hide();
				tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			}
		} ); // click
	} // initializeTabs

	function updateTabs() {
		// check the situation
		var tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;

		if ( tablistright + 30 > $( window ).width() ) {
			// the window is now too small, we need to remove some tabs (on the right)
			while ( tablistright + 30 > $( window ).width() ) {
				// remove tabs on the right until it fits in the window
				$( '.wd-tablist .visibleTab:last' )
					.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
					.hide();
				tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			}
			$( '.wd-nextArrow' ).show();
		} else {
			// the window has been enlarged, there might be extra space available
			while ( ( tablistright + 30 < $( window ).width() ) && $( '.wd-tabitem:last' ).hasClass( 'hiddenTab' ) ) {
				$( '.wd-tablist .visibleTab:last' ).next()
					.addClass( 'visibleTab' ).removeClass( 'hiddenTab' )
					.show();
				tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
			}

			// remove last tab if we have been to far
			if ( tablistright + 30 > $( window ).width() ) {
				$( '.wd-tablist .visibleTab:last' )
					.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
					.hide();
			} else {
				// all tabs on the right are now visible
				$( '.wd-nextArrow' ).hide();

				// maybe we can still add more tabs on the left?
				while ( ( tablistright + 30 < $( window ).width() ) && $( '.wd-tabitem:first' ).hasClass( 'hiddenTab' ) ) {
					$( '.wd-tablist .visibleTab:first' ).prev()
						.addClass( 'visibleTab' ).removeClass( 'hiddenTab' )
						.show();
					tablistright = $( '.wd-tablist' ).outerWidth( true ) + $( '.wd-tablist' ).offset().left;
				}
				// remove first tab if we have been to far
				if ( tablistright + 30 > $( window ).width() ) {
					$( '.wd-tablist .visibleTab:first' )
						.addClass( 'hiddenTab' ).removeClass( 'visibleTab' )
						.hide();
				} else {
					$( '.wd-previousArrow' ).hide();
				}
			}
		}
	} // updateTabs

} );

// @todo convert the functions below to jQuery...

window.MD5 = function ( string ) {

	function RotateLeft( lValue, iShiftBits ) {
		return ( lValue << iShiftBits ) | ( lValue >>> ( 32 - iShiftBits ) );
	}

	function AddUnsigned( lX, lY ) {
		var lX4, lY4, lX8, lY8, lResult;
		lX8 = ( lX & 0x80000000 );
		lY8 = ( lY & 0x80000000 );
		lX4 = ( lX & 0x40000000 );
		lY4 = ( lY & 0x40000000 );
		lResult = ( lX & 0x3FFFFFFF ) + ( lY & 0x3FFFFFFF );
		if ( lX4 & lY4 ) {
			return ( lResult ^ 0x80000000 ^ lX8 ^ lY8 );
		}
		if ( lX4 | lY4 ) {
			if ( lResult & 0x40000000 ) {
				return ( lResult ^ 0xC0000000 ^ lX8 ^ lY8 );
			} else {
				return ( lResult ^ 0x40000000 ^ lX8 ^ lY8 );
			}
		} else {
			return ( lResult ^ lX8 ^ lY8 );
		}
	}

	function F( x, y, z ) { return ( x & y ) | ( ( ~x ) & z ); }
	function G( x, y, z ) { return ( x & z ) | ( y & ( ~z ) ); }
	function H( x, y, z ) { return ( x ^ y ^ z ); }
	function I( x, y, z ) { return ( y ^ ( x | ( ~z ) ) ); }

	function FF( a, b, c, d, x, s, ac ) {
		a = AddUnsigned( a, AddUnsigned( AddUnsigned( F( b, c, d ), x ), ac ) );
		return AddUnsigned( RotateLeft( a, s ), b );
	}

	function GG( a, b, c, d, x, s, ac ) {
		a = AddUnsigned( a, AddUnsigned( AddUnsigned( G( b, c, d ), x ), ac ) );
		return AddUnsigned( RotateLeft( a, s ), b );
	}

	function HH( a, b, c, d, x, s, ac ) {
		a = AddUnsigned( a, AddUnsigned( AddUnsigned( H( b, c, d ), x ), ac ) );
		return AddUnsigned( RotateLeft( a, s ), b );
	}

	function II( a, b, c, d, x, s, ac ) {
		a = AddUnsigned( a, AddUnsigned( AddUnsigned( I( b, c, d ), x ), ac ) );
		return AddUnsigned( RotateLeft( a, s ), b );
	}

	function ConvertToWordArray( string ) {
		var lWordCount,
			lMessageLength = string.length,
			lNumberOfWords_temp1 = lMessageLength + 8,
			lNumberOfWords_temp2 = ( lNumberOfWords_temp1 - ( lNumberOfWords_temp1 % 64 ) ) / 64,
			lNumberOfWords = ( lNumberOfWords_temp2 + 1 ) * 16,
			lWordArray = Array( lNumberOfWords - 1 ),
			lBytePosition = 0,
			lByteCount = 0;
		while ( lByteCount < lMessageLength ) {
			lWordCount = ( lByteCount - ( lByteCount % 4 ) ) / 4;
			lBytePosition = ( lByteCount % 4 ) * 8;
			lWordArray[ lWordCount ] = ( lWordArray[ lWordCount ] | ( string.charCodeAt( lByteCount ) << lBytePosition ) );
			lByteCount++;
		}
		lWordCount = ( lByteCount - ( lByteCount % 4 ) ) / 4;
		lBytePosition = ( lByteCount % 4 ) * 8;
		lWordArray[ lWordCount ] = lWordArray[ lWordCount ] | ( 0x80 << lBytePosition );
		lWordArray[ lNumberOfWords - 2 ] = lMessageLength << 3;
		lWordArray[ lNumberOfWords - 1 ] = lMessageLength >>> 29;
		return lWordArray;
	}

	function WordToHex( lValue ) {
		var WordToHexValue = '', WordToHexValue_temp = '', lByte, lCount;
		for ( lCount = 0; lCount <= 3; lCount++ ) {
			lByte = ( lValue >>> ( lCount * 8 ) ) & 255;
			WordToHexValue_temp = '0' + lByte.toString( 16 );
			WordToHexValue = WordToHexValue + WordToHexValue_temp.substr( WordToHexValue_temp.length - 2, 2 );
		}
		return WordToHexValue;
	}

	function Utf8Encode( string ) {
		string = string.replace( /\r\n/g, '\n' );
		var utftext = '';

		for ( var n = 0; n < string.length; n++ ) {

			var c = string.charCodeAt( n );

			if ( c < 128 ) {
				utftext += String.fromCharCode( c );
			} else if ( ( c > 127 ) && ( c < 2048 ) ) {
				utftext += String.fromCharCode( ( c >> 6 ) | 192 );
				utftext += String.fromCharCode( ( c & 63 ) | 128 );
			} else {
				utftext += String.fromCharCode( ( c >> 12 ) | 224 );
				utftext += String.fromCharCode( ( ( c >> 6 ) & 63 ) | 128 );
				utftext += String.fromCharCode( ( c & 63 ) | 128 );
			}

		}

		return utftext;
	}

	var x = [],
		k, AA, BB, CC, DD, a, b, c, d,
		S11 = 7, S12 = 12, S13 = 17, S14 = 22,
		S21 = 5, S22 = 9, S23 = 14, S24 = 20,
		S31 = 4, S32 = 11, S33 = 16, S34 = 23,
		S41 = 6, S42 = 10, S43 = 15, S44 = 21;

	string = Utf8Encode( string );

	x = ConvertToWordArray( string );

	a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;

	for ( k = 0; k < x.length; k += 16 ) {
		AA = a; BB = b; CC = c; DD = d;
		a = FF( a, b, c, d, x[ k + 0 ], S11, 0xD76AA478 );
		d = FF( d, a, b, c, x[ k + 1 ], S12, 0xE8C7B756 );
		c = FF( c, d, a, b, x[ k + 2 ], S13, 0x242070DB );
		b = FF( b, c, d, a, x[ k + 3 ], S14, 0xC1BDCEEE );
		a = FF( a, b, c, d, x[ k + 4 ], S11, 0xF57C0FAF );
		d = FF( d, a, b, c, x[ k + 5 ], S12, 0x4787C62A );
		c = FF( c, d, a, b, x[ k + 6 ], S13, 0xA8304613 );
		b = FF( b, c, d, a, x[ k + 7 ], S14, 0xFD469501 );
		a = FF( a, b, c, d, x[ k + 8 ], S11, 0x698098D8 );
		d = FF( d, a, b, c, x[ k + 9 ], S12, 0x8B44F7AF );
		c = FF( c, d, a, b, x[ k + 10 ], S13, 0xFFFF5BB1 );
		b = FF( b, c, d, a, x[ k + 11 ], S14, 0x895CD7BE );
		a = FF( a, b, c, d, x[ k + 12 ], S11, 0x6B901122 );
		d = FF( d, a, b, c, x[ k + 13 ], S12, 0xFD987193 );
		c = FF( c, d, a, b, x[ k + 14 ], S13, 0xA679438E );
		b = FF( b, c, d, a, x[ k + 15 ], S14, 0x49B40821 );
		a = GG( a, b, c, d, x[ k + 1 ], S21, 0xF61E2562 );
		d = GG( d, a, b, c, x[ k + 6 ], S22, 0xC040B340 );
		c = GG( c, d, a, b, x[ k + 11 ], S23, 0x265E5A51 );
		b = GG( b, c, d, a, x[ k + 0 ], S24, 0xE9B6C7AA );
		a = GG( a, b, c, d, x[ k + 5 ], S21, 0xD62F105D );
		d = GG( d, a, b, c, x[ k + 10 ], S22, 0x2441453 );
		c = GG( c, d, a, b, x[ k + 15 ], S23, 0xD8A1E681 );
		b = GG( b, c, d, a, x[ k + 4 ], S24, 0xE7D3FBC8 );
		a = GG( a, b, c, d, x[ k + 9 ], S21, 0x21E1CDE6 );
		d = GG( d, a, b, c, x[ k + 14 ], S22, 0xC33707D6 );
		c = GG( c, d, a, b, x[ k + 3 ], S23, 0xF4D50D87 );
		b = GG( b, c, d, a, x[ k + 8 ], S24, 0x455A14ED );
		a = GG( a, b, c, d, x[ k + 13 ], S21, 0xA9E3E905 );
		d = GG( d, a, b, c, x[ k + 2 ], S22, 0xFCEFA3F8 );
		c = GG( c, d, a, b, x[ k + 7 ], S23, 0x676F02D9 );
		b = GG( b, c, d, a, x[ k + 12 ], S24, 0x8D2A4C8A );
		a = HH( a, b, c, d, x[ k + 5 ], S31, 0xFFFA3942 );
		d = HH( d, a, b, c, x[ k + 8 ], S32, 0x8771F681 );
		c = HH( c, d, a, b, x[ k + 11 ], S33, 0x6D9D6122 );
		b = HH( b, c, d, a, x[ k + 14 ], S34, 0xFDE5380C );
		a = HH( a, b, c, d, x[ k + 1 ], S31, 0xA4BEEA44 );
		d = HH( d, a, b, c, x[ k + 4 ], S32, 0x4BDECFA9 );
		c = HH( c, d, a, b, x[ k + 7 ], S33, 0xF6BB4B60 );
		b = HH( b, c, d, a, x[ k + 10 ], S34, 0xBEBFBC70 );
		a = HH( a, b, c, d, x[ k + 13 ], S31, 0x289B7EC6 );
		d = HH( d, a, b, c, x[ k + 0 ], S32, 0xEAA127FA );
		c = HH( c, d, a, b, x[ k + 3 ], S33, 0xD4EF3085 );
		b = HH( b, c, d, a, x[ k + 6 ], S34, 0x4881D05 );
		a = HH( a, b, c, d, x[ k + 9 ], S31, 0xD9D4D039 );
		d = HH( d, a, b, c, x[ k + 12 ], S32, 0xE6DB99E5 );
		c = HH( c, d, a, b, x[ k + 15 ], S33, 0x1FA27CF8 );
		b = HH( b, c, d, a, x[ k + 2 ], S34, 0xC4AC5665 );
		a = II( a, b, c, d, x[ k + 0 ], S41, 0xF4292244 );
		d = II( d, a, b, c, x[ k + 7 ], S42, 0x432AFF97 );
		c = II( c, d, a, b, x[ k + 14 ], S43, 0xAB9423A7 );
		b = II( b, c, d, a, x[ k + 5 ], S44, 0xFC93A039 );
		a = II( a, b, c, d, x[ k + 12 ], S41, 0x655B59C3 );
		d = II( d, a, b, c, x[ k + 3 ], S42, 0x8F0CCC92 );
		c = II( c, d, a, b, x[ k + 10 ], S43, 0xFFEFF47D );
		b = II( b, c, d, a, x[ k + 1 ], S44, 0x85845DD1 );
		a = II( a, b, c, d, x[ k + 8 ], S41, 0x6FA87E4F );
		d = II( d, a, b, c, x[ k + 15 ], S42, 0xFE2CE6E0 );
		c = II( c, d, a, b, x[ k + 6 ], S43, 0xA3014314 );
		b = II( b, c, d, a, x[ k + 13 ], S44, 0x4E0811A1 );
		a = II( a, b, c, d, x[ k + 4 ], S41, 0xF7537E82 );
		d = II( d, a, b, c, x[ k + 11 ], S42, 0xBD3AF235 );
		c = II( c, d, a, b, x[ k + 2 ], S43, 0x2AD7D2BB );
		b = II( b, c, d, a, x[ k + 9 ], S44, 0xEB86D391 );
		a = AddUnsigned( a, AA );
		b = AddUnsigned( b, BB );
		c = AddUnsigned( c, CC );
		d = AddUnsigned( d, DD );
	}

	var temp = WordToHex( a ) + WordToHex( b ) + WordToHex( c ) + WordToHex( d );

	return temp.toLowerCase();
};
