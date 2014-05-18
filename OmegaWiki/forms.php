<?php

require_once( 'languages.php' );

function getTextBox( $name, $value = "", $onChangeHandler = "", $disabled = false ) {
	if ( $onChangeHandler != "" )
		$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
	else
		$onChangeAttribute = '';

	$disableText = $disabled ? 'disabled="disabled" ' : '' ;
	$inputHTML = '<input ' . $disableText . 'type="text" id="' . $name . '" name="' . $name .
		'" value="' . htmlspecialchars( $value ) . '"' . $onChangeAttribute .
		' style="width: 100%; padding: 0px; margin: 0px;"/>' ;

	return $inputHTML ;
}
 
function getTextArea( $name, $text = "", $rows = 5, $columns = 80, $disabled = false ) {
	if ( $disabled ) {
		// READONLY alone is not enough: apparently, some browsers ignore it
		return '<textarea disabled="disabled" name="' . $name . '" rows="' . $rows . '" cols="' . $columns . '" READONLY>' . htmlspecialchars( $text ) . '</textarea>';
	} else {
		return '<textarea name="' . $name . '" rows="' . $rows . '" cols="' . $columns . '">' . htmlspecialchars( $text ) . '</textarea>';
	}
}

function checkBoxCheckAttribute( $isChecked ) {
	if ( $isChecked ) {
		return ' checked="checked"';
	}
	return '';
}
 
function getCheckBox( $name, $isChecked, $disabled = false ) {
	// a disabled checkbox returns no value, as if unchecked
	// therefore the value of a disabled, but checked, checkbox must be sent with a hidden input
	if ( $disabled ) {
		if ( $isChecked ) {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/><input type="hidden" name="' . $name . '" value="1"/>';
		} else {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/>';
		}
	} else {
		return '<input type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '/>';
	}
}

function getCheckBoxWithClass( $name, $isChecked, $class, $disabled = false ) {
	if ( $disabled ) {
		if ( $isChecked ) {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '"/><input type="hidden" name="' . $name . '" value="1"/>';
		} else {
			return '<input disabled="disabled" type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . '"/>';
		}
	} else {
		return '<input type="checkbox" name="' . $name . '"' . checkBoxCheckAttribute( $isChecked ) . ' class="' . $class . '"/>';
	}
}

function getRemoveCheckBox( $name ) {
	global $wgUser;
	$dc = wdGetDataSetContext();
	if ( ($dc == "uw") and (! $wgUser->isAllowed( 'deletewikidata-uw' ) ) ) {
		// do not print the checkbox
		return '';
	} else {
		return getCheckBoxWithClass( $name, false, "remove-checkbox" );
	}
}

# $options is an array of [value => text] pairs
function getSelect( $name, $options, $selectedValue = "", $onChangeHandler = "" ) {
	if ( $onChangeHandler != "" ) {
		$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
	} else {
		$onChangeAttribute = '';
	}

	$result = '<select id="' . $name . '" name="' . $name . '"' . $onChangeAttribute . '>';
 
	asort( $options );

	foreach ( $options as $value => $text ) {
		if ( $value == $selectedValue )
			$selected = ' selected="selected"';
		else
			$selected = '';

		$result .= '<option value="' . $value . '"' . $selected . '>' . htmlspecialchars( $text ) . '</option>';
	}

	return $result . '</select>';
}

function getFileField( $name, $onChangeHandler = "" ) {
	if ( $onChangeHandler != "" )
		$onChangeAttribute = ' onchange="' . $onChangeHandler . '"';
	else
		$onChangeAttribute = '';

	return '<input type="file" id="' . $name . '" name="' . $name . '"' . $onChangeAttribute . ' style="width: 100%; padding: 0px; margin: 0px;"/>';
}
 

/**
 *
 * Returns HTML for an autocompleted form field.
 *
 * @param String unique identifier for this form field
 * @param String type of query to run
 * @param Integer Default value
 * @param String How default value will be shown
 * @param Array Override column titles
 * @param DataSet Override standard dataset
 *
*/
function getSuggest( $name, $query, $parameters = array(), $value = 0, $label = '', $displayLabelColumns = array( 0 ), DataSet $dc = null ) {
	global
		$wgScriptPath;

	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	if ( $label == "" ) {
		$label = '&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;';
	}

	$result = Html::openElement('span', array( 'class' => 'suggest' ) );

	// the input that will contain the value selected with suggest.js
	$inputOptions = array(
		'id' => $name,
		'name' => $name,
		'value' => $value,
		'type' => 'hidden'
	);
	$result .= Html::element('input', $inputOptions);

	$spanOptions = array(
		'id' => $name . '-suggest-link',
		'name' => $name . '-suggest-link',
		'class' => 'suggest-link',
		'title' => wfMessage( "ow_SuggestHint" )->text(),
		'query' => $query,
		'offset' => 0,
		'label-columns' => implode( ', ', $displayLabelColumns ),
		'dataset' => $dc
	);

	foreach( $parameters as $parameter => $parameterValue ) {
		// parameters like level, definedMeaningId, annotationAttributeId, syntransId
		$spanOptions[$parameter] = $parameterValue;
	}

	$result .= Html::rawElement('span', $spanOptions, $label);

	$result .= Html::closeElement('span');

	// The table that then allows to select from a dropdown list
	// is generated with javascript (cf. suggest.js)

	return $result;
}

function getStaticSuggest( $name, $suggestions, $idColumns = 1, $value = 0, $label = '', $displayLabelColumns = array( 0 ) ) {
	if ( $label == "" ) {
		$label = '&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;&#160;';
	}

	$result = Html::openElement('span', array( 'class' => 'suggest' ) );

	// the input that will contain the value selected with suggest.js
	$inputOptions = array(
		'id' => $name,
		'name' => $name,
		'value' => $value,
		'type' => 'hidden'
	);
	$result .= Html::element('input', $inputOptions);
	$spanOptions = array(
		'id' => $name . '-suggest-link',
		'name' => $name . '-suggest-link',
		'class' => 'suggest-link',
		'title' => wfMessage( "ow_SuggestHint" )->text(),
		'query' => $query,
		'offset' => 0,
		'label-columns' => implode( ', ', $displayLabelColumns ),
		'dataset' => $dc
	);

	if ( $idColumns > 1 ) {
		$spanOptions['id-columns'] = $idColumns;
	}

	$result .= Html::rawElement('span', $spanOptions, $label);

	$result .= Html::closeElement('span');

	return $result;
}

function getLanguageOptions( $languageIdsToExclude = array() ) {
	$userLanguage = owDatabaseAPI::getUserLanguage();
	$idNameIndex = getLangNames( $userLanguage );

	$result = array();

	foreach ( $idNameIndex as $id => $name )
		if ( !in_array( $id, $languageIdsToExclude ) )
			$result[$id] = $name;

	return $result;
}

// @note unused	function
function getLanguageSelect( $name, $languageIdsToExclude = array() ) {
	$userLanguageId = owDatabaseAPI::getUserLanguageId();

	return getSelect( $name, getLanguageOptions( $languageIdsToExclude ), $userLanguageId );
}

function getSubmitButton( $name, $value ) {
	return '<input type="submit" name="' . $name . '" value="' . $value . '"/>';
}

function getOptionPanel( $fields, $action = '', $buttons = array( "show" => null ) ) {
	global
		$wgTitle;

	$result =
		'<div class="option-panel">' .
			'<form method="GET" action="">' .
				'<table cellpadding="0" cellspacing="0">' .
					'<input type="hidden" name="title" value="' . $wgTitle->getNsText() . ':' . htmlspecialchars( $wgTitle->getText() ) . '"/>';

	if ( $action && $action != '' )
		$result .= '<input type="hidden" name="action" value="' . $action . '"/>';

	foreach ( $fields as $caption => $field )
		$result .= '<tr><th>' . $caption . '</th><td class="option-field">' . $field . '</td></tr>';

	$buttonHTML = "";

	foreach ( $buttons as $name => $caption )
	{
		if ( $caption == null ) {
			// Default parameter/value => Show
			$caption = wfMessage( 'ow_show' )->text();
		}
		$buttonHTML .= getSubmitButton( $name, $caption );
	}

	$result .=
					'<tr><th/><td>' . $buttonHTML . '</td></tr>' .
				'</table>' .
			'</form>' .
		'</div>';

	return $result;
}

function getOptionPanelForFileUpload( $fields, $action = '', $buttons = array( "upload" => null ) ) {
	global
		$wgTitle;

	$result =
		'<div class="option-panel">' .
			'<form method="POST" enctype="multipart/form-data" action="">' .
				'<table cellpadding="0" cellspacing="0">' .
					'<input type="hidden" name="title" value="' . $wgTitle->getNsText() . ':' . htmlspecialchars( $wgTitle->getText() ) . '"/>';

	if ( $action && $action != '' )
		$result .= '<input type="hidden" name="action" value="' . $action . '"/>';

	foreach ( $fields as $caption => $field )
		$result .= '<tr><th>' . $caption . '</th><td class="option-field">' . $field . '</td></tr>';

	$buttonHTML = "";

	foreach ( $buttons as $name => $caption )
	{
		if ( $caption == null ) {
			// Default parameter/value => Upload
			$caption = wfMessage( 'ow_upload' )->text();
		}
		$buttonHTML .= getSubmitButton( $name, $caption );
	}

	$result .=
					'<tr><th/><td>' . $buttonHTML . '</td></tr>' .
				'</table>' .
			'</form>' .
		'</div>';

	return $result;
}

