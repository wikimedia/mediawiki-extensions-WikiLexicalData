jQuery(document).ready(function( $ ) {

	var suggestionTimeOut = null;

	$(".remove-checkbox").click(function(event) {
		$(this).parent().parent().toggleClass('to-be-removed');
	});

	// some delegated handlers for elements added dynamically
	$("body").on('click', ".suggest-next", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'next');
		var suggestOffset = document.getElementById(suggestPrefix + 'offset');
		suggestOffset.value = parseInt(suggestOffset.value) + 10;
		updateSuggestions(suggestPrefix);
	});

	$("body").on('click', ".suggest-previous", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'previous');
		var suggestOffset = document.getElementById(suggestPrefix + 'offset');
		suggestOffset.value = Math.max(parseInt(suggestOffset.value) - 10, 0);
		updateSuggestions(suggestPrefix);
	});

	$("body").on('click', ".suggest-close", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'close');
		$("#" + suggestPrefix + "div").hide();
	});

	$("body").on('click', ".suggest-clear", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'clear');
		updateSuggestValue(suggestPrefix, ""
			, "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
	});

	$("body").on('click', ".suggest-link", function(event) {
		var suggestLinkId = this.id;
		// removing the "link" at the end of the Id
		var suggestPrefix = getSuggestPrefix( this, 'link');

		if ( $("#" + suggestPrefix + "div").length == 0 ) {
			createSuggestStructure(this, suggestPrefix);
		}
		$("#" + suggestPrefix + "div").show();

		var suggestField = document.getElementById(suggestPrefix + "text");
		if (suggestField != null) {
			suggestField.focus();
			updateSuggestions(suggestPrefix);
		}
	});

	$("body").on('keyup', "input.suggest-text", function(event) {
		var suggestPrefix = getSuggestPrefix( this, 'text');
		scheduleUpdateSuggestions( suggestPrefix );
	});

	function getSuggestPrefix( node, postFix ) {
		var nodeId = node.id;
		return stripSuffix( nodeId, postFix );
	}

	function scheduleUpdateSuggestions( suggestPrefix ) {
		if ( suggestionTimeOut != null ) {
			clearTimeout( suggestionTimeOut );
		}
		$("#" + suggestPrefix + "offset").val( 0 );
		suggestionTimeOut = setTimeout(function() {
			updateSuggestions( suggestPrefix )
		}, 600);
	}

	function stripSuffix( source, suffix ) {
		return source.substr(0, source.length - suffix.length);
	}

	function createSuggestStructure( element, suggestPrefix ) {
		var imgPath =  mw.config.get( 'wgExtensionAssetsPath' ) + '/WikiLexicalData/Images/' ;
		var suggestStructure =
			'<div class="suggest-drop-down"><div id="' + suggestPrefix + 'div" class="suggest-div">' +
				'<table><tr>' +
					'<td><input type="text" id="' + suggestPrefix + 'text" autocomplete="off" class="suggest-text"/></td>' +
					'<td id="' + suggestPrefix + 'clear" class="suggest-clear">' + mw.message('ow_suggest_clear') + '</td>' +
					'<td id="' + suggestPrefix + 'previous" class="suggest-previous">' +
						'<img src="' + imgPath + 'ArrowLeft.png" alt="' + mw.message('ow_suggest_previous') + '"/> ' +
						mw.message('ow_suggest_previous') + '</td>' +
					'<td id="' + suggestPrefix + 'next" class="suggest-next">' + mw.message('ow_suggest_next') +
						'<img src="' + imgPath + 'ArrowRight.png" alt="' + mw.message('ow_suggest_next') + '"/></td>' +
					'<td id="' + suggestPrefix + 'close" class="suggest-close">[X]</td>' +
				'</tr></table>' +
				'<table id="' + suggestPrefix + 'table"><tr><td></td></tr></table>' +
			'</div></div>';

		$(element).after( suggestStructure );
	}

	function updateSuggestValue( suggestPrefix, value, displayValue ) {
		var suggestLink = document.getElementById(suggestPrefix + "link");
		var suggestValue = document.getElementById(suggestPrefix + "value");
		var suggestDiv = document.getElementById(suggestPrefix + "div");
		var suggestField = document.getElementById(stripSuffix(suggestPrefix, "-suggest-"));

		suggestField.value = value;

		suggestLink.innerHTML = displayValue;
		suggestDiv.style.display = 'none';
		suggestLink.focus();

		var suggestOnUpdate = document.getElementById(suggestPrefix + "parameter-onUpdate");
		if(suggestOnUpdate != null) {
			eval(suggestOnUpdate.value + "," + value + ")");
		}
	}

	/*
	* suggests a list (of languages, classes...) according to the letters typed in the query field
	* or to the arrows "next" "previous"
	*/

	function updateSuggestions( suggestPrefix ) {
		var table = document.getElementById(suggestPrefix + "table");
		var suggestQuery = document.getElementById(suggestPrefix + "query").value;
		var suggestOffset = document.getElementById(suggestPrefix + "offset").value;
		var dataSet = document.getElementById(suggestPrefix + "dataset").value;

		if ( table == null ) {
			// just in case
			return;
		}

		suggestText = $("#" + suggestPrefix + "text");
		suggestText.addClass("suggest-loading");
		var suggestTextVal = suggestText.val() ; // we copy the value to compare it later to the current value

		var suggestAttributesLevel = document.getElementById(suggestPrefix + "parameter-level");
		var suggestDefinedMeaningId = document.getElementById(suggestPrefix + "parameter-definedMeaningId");
		var suggestSyntransId = document.getElementById(suggestPrefix + "parameter-syntransId");
		var suggestAnnotationAttributeId = document.getElementById(suggestPrefix + "parameter-annotationAttributeId");

		URL = wgScript +
			'?title=Special:Suggest&search-text=' + encodeURI(suggestTextVal) +
			'&prefix=' + encodeURI(suggestPrefix) +
			'&query=' + encodeURI(suggestQuery) +
			'&offset=' + encodeURI(suggestOffset) +
			'&dataset='+dataSet;

		if (suggestAttributesLevel != null)
			URL = URL + '&attributesLevel=' + encodeURI(suggestAttributesLevel.value);
		
		if (suggestDefinedMeaningId != null)
			URL = URL + '&definedMeaningId=' + encodeURI(suggestDefinedMeaningId.value);

		if (suggestSyntransId != null)
			URL = URL + '&syntransId=' + encodeURI(suggestSyntransId.value);
			
		if (suggestAnnotationAttributeId != null)
			URL = URL + '&annotationAttributeId=' + encodeURI(suggestAnnotationAttributeId.value);

		$.get( URL, function(data) {
			var newTable = document.createElement('div');
			if (data != '') {
				newTable.innerHTML = leftTrim( data );
				
				// put the searched text in bold within the returned string
				if ( suggestTextVal != "" ) {
					var langnames = newTable.getElementsByTagName('td') ;
					var searchTxt = new String ( suggestTextVal ) ;
					// normalizeText removes diacritics (cf. omegawiki-ajax.js)
					searchTxt = normalizeText ( searchTxt.toLowerCase() ) ;

					for ( i=0 ; i < langnames.length ; i++ ) {
						var searchInTxt = normalizeText ( langnames[i].innerHTML.toLowerCase() ) ;
						var position = searchInTxt.indexOf( searchTxt );
						if ( position >= 0 ) {
							langnames[i].innerHTML = langnames[i].innerHTML.substr(0,position)
							+ "<b>"
							+ langnames[i].innerHTML.substr( position, searchTxt.length)
							+ "</b>"
							+ langnames[i].innerHTML.substr( position + searchTxt.length ) ;
						}
					}
				}
				$(table).replaceWith( newTable.firstChild );
			}
			suggestText.removeClass("suggest-loading");

			// comparing the stored value send in the URL, and the actual value
			if ( suggestTextVal != suggestText.val() ) {
				suggestionTimeOut = setTimeout( function() {
					updateSuggestions( suggestPrefix )
				}, 100);
			}
		});
	}

	function leftTrim( sString ) {
		while (sString.substring(0,1) == ' ' || sString.substring(0,1) == "\n") {
				sString = sString.substring(1, sString.length);
			}
		return sString;
	}

	// remove accents for comparison
	function normalizeText( text ) {
		text = text.replace(new RegExp("[àáâãäå]", 'g'),"a");
		text = text.replace(new RegExp("æ", 'g'),"ae");
		text = text.replace(new RegExp("ç", 'g'),"c");
		text = text.replace(new RegExp("[èéêë]", 'g'),"e");
		text = text.replace(new RegExp("[ìíîï]", 'g'),"i");
		text = text.replace(new RegExp("ñ", 'g'),"n");
		text = text.replace(new RegExp("[òóôõö]", 'g'),"o");
		text = text.replace(new RegExp("œ", 'g'),"oe");
		text = text.replace(new RegExp("[ùúûü]", 'g'),"u");
		text = text.replace(new RegExp("[ýÿ]", 'g'),"y");
		return text ;
	}

	function updateSelectOptions(id, objectId, value) {
		var URL = 'index.php';
		var location = "" + document.location;

		if (location.indexOf('index.php/') > 0) {
			URL = '../' + URL;
		}
		URL = URL + '/Special:Select?optnAtt=' + encodeURI(value) + '&attribute-object=' + encodeURI(objectId);

		$.get( URL, function(data) {
			var select = document.getElementById(id);
			select.options.length = 0;
			var options = data.split("\n");

			for (idx in options) {
				option = options[idx].split(";");
				select.add(new Option(option[1],option[0]),null);
			}
		});
	}

	/* some more functions to load only when ajax is complete
		* because the class "suggestion-row" is not known before that
		* and it does not work if the functions are defined outside
		* of ajaxcomplete
		* alternatively we could use delegated handlers
		*/

	$(document).ajaxComplete(function() {
		$(".suggestion-row").mouseover(function() {
			$(this).addClass('active');
		}).mouseout(function() {
			$(this).removeClass('active');
		});

		$(".suggestion-row").click(function() {
			var suggestPrefix = getSuggestPrefix(this.parentNode.parentNode.parentNode, "div");
			var idColumnsField = document.getElementById(suggestPrefix + "id-columns");
			var displayLabelField = document.getElementById(suggestPrefix + "label-columns");
			var displayLabelColumnIndices = displayLabelField.value.split(", ");
			var labels = new Array();

			for (var i = 0; i < displayLabelColumnIndices.length; i++) {
				var columnValue = this.getElementsByTagName('td')[displayLabelColumnIndices[i]].innerHTML;

				if (columnValue != "") {
					columnValue = columnValue.replace ("<b>","");
					columnValue = columnValue.replace ("</b>","");
					labels.push(columnValue);
				}
			}

			var idColumns = 1;

			if (idColumnsField != null) {
				idColumns = idColumnsField.value;
			}
			var values = this.id.split('-');
			var ids = new Array();

			for (var i = idColumns - 1; i >= 0; i--) {
				ids.push(values[values.length - i - 1]);
			}
			updateSuggestValue(suggestPrefix, ids.join('-'), labels.join(', '));
		});
	});

});
