<?php

define( 'NS_EXPRESSION', 16 );
define( 'NS_DEFINEDMEANING', 24 );
define( 'WD_ENGLISH_LANG_ID', 85 );

// Achtung: the following defines should match the strings used in
// the Javascript files
define ( 'WD_ALTERNATIVE_DEF', "altDef" );
define ( 'WD_ALTERNATIVE_DEFINITIONS', "altDefs" );
define ( 'WD_CLASS_ATTRIBUTES', "classAtt" );
define ( 'WD_CLASS_MEMBERSHIP', "classMembers" );
define ( 'WD_COLLECTION_MEMBERSHIP', "colMembers" );
define ( 'WD_DEFINED_MEANING', "dm" );
define ( 'WD_DEFINED_MEANING_ATTRIBUTES', "dmAtt" );
define ( 'WD_DEFINITION', "def" );
define ( 'WD_EXPRESSION', "exp" );
define ( 'WD_EXPRESSION_APPROX_MEANINGS', "approx" );
define ( 'WD_EXPRESSION_EXACT_MEANINGS', "exact" );
define ( 'WD_EXPRESSION_MEANINGS', "meanings" );
define ( 'WD_IDENTICAL_MEANING', "identMeaning" );
define ( 'WD_INCOMING_RELATIONS', "incomingRel" );
define ( 'WD_LINK_ATTRIBUTE', "linkAtt" );
define ( 'WD_LINK_ATTRIBUTE_VALUES', "linkAttVal" );
define ( 'WD_OBJECT_ATTRIBUTES', "objAtt" );
define ( 'WD_OPTION_ATTRIBUTE', "optnAtt" );
define ( 'WD_OPTION_ATTRIBUTE_OPTION', "optnAttOptn" ); // WD_OPTION_ATTRIBUTE . WD_OPTION_SUFFIX
define ( 'WD_OPTION_ATTRIBUTE_VALUES', "optnAttVal" ); // WD_OPTION_ATTRIBUTE . "Val"
define ( 'WD_OPTION_SUFFIX', "Optn" );
define ( 'WD_OTHER_DEFINED_MEANING', "otherDm" );
define ( 'WD_RELATIONS', "rel" );
define ( 'WD_SYNONYMS_TRANSLATIONS', "syntrans" );
define ( 'WD_TEXT_ATTRIBUTES_VALUES', "txtAttVal" );
define ( 'WD_TRANSLATED_TEXT', "transl" );


require_once( "Wikidata.php" );
require_once( "GotoSourceTemplate.php" );
require_once( "PropertyToColumnFilter.php" );

# Global context override. This is an evil hack to allow saving, basically.
global $wdCurrentContext;
$wdCurrentContext = null;

global $wgIso639_3CollectionId;
$wgIso639_3CollectionId = null;

// Defined meaning editor
global $wdDefinedMeaningAttributesOrder;
	
$wdDefinedMeaningAttributesOrder = array(
	WD_DEFINITION,
	WD_ALTERNATIVE_DEFINITIONS,
	WD_SYNONYMS_TRANSLATIONS,
	WD_DEFINED_MEANING_ATTRIBUTES,
	WD_CLASS_MEMBERSHIP,
	WD_CLASS_ATTRIBUTES,
	WD_COLLECTION_MEMBERSHIP,
	WD_INCOMING_RELATIONS
);


global $wgGotoSourceTemplates;

$wgGotoSourceTemplates = array();	// Map of collection id => GotoSourceTemplate

// Page titles

global $wgUseExpressionPageTitlePrefix;

$wgUseExpressionPageTitlePrefix = true;	# malafaya: Use the expression prefix "Multiple meanings:" from message ow_Multiple_meanings

// Search page

global
	$wgSearchWithinExternalIdentifiersDefaultValue,
	$wgSearchWithinWordsDefaultValue,
	$wgShowSearchWithinExternalIdentifiersOption,
	$wgShowSearchWithinWordsOption;

$wgSearchWithinExternalIdentifiersDefaultValue = true;
$wgSearchWithinWordsDefaultValue = true;
$wgShowSearchWithinExternalIdentifiersOption = true;
$wgShowSearchWithinWordsOption = true;

global
	$wgPropertyToColumnFilters;
	
/** 
 * $wgPropertyToColumnFilters is an array of property to column filters 
 * 
 * Example:
 *   $wgPropertyToColumnFilters = array(
 *     new PropertyToColumnFilter("references", "References", array(1000, 2000, 3000)) // Defined meaning ids are the attribute ids to filter
 *   )
 * 
 */
$wgPropertyToColumnFilters = array();



/**
* A Wikidata application can manage multiple data sets.
* The current "context" is dependent on multiple factors:
* - the URL can have a dataset parameter
* - there is a global default
* - there can be defaults for different user groups
* @param $dc	optional, for convenience.
*		if the dataset context is already set, will
		return that value, else will find the relevant value
* @return prefix (without underscore)
**/
function wdGetDataSetContext( $dc = null ) {
	global $wgRequest, $wdDefaultViewDataSet, $wdGroupDefaultView, $wgUser,
		$wdCurrentContext;

	# overrides
	if ( !is_null( $dc ) )
		return $dc; # local override
	if ( !is_null( $wdCurrentContext ) )
		return $wdCurrentContext; # global override

	$datasets = wdGetDataSets();
	$groups = $wgUser->getGroups();
	$dbs = wfGetDB( DB_SLAVE );
	$pref = $wgUser->getOption( 'ow_uipref_datasets' );

	$trydefault = '';
	foreach ( $groups as $group ) {
		if ( isset( $wdGroupDefaultView[$group] ) ) {
			# We don't know yet if this prefix is valid.
			$trydefault = $wdGroupDefaultView[$group];
		}
	}

	# URL parameter takes precedence over all else
	if ( ( $ds = $wgRequest->getText( 'dataset' ) ) && array_key_exists( $ds, $datasets ) && $dbs->tableExists( $ds . "_transactions" ) ) {
		return $datasets[$ds];
	# User preference
	} elseif ( !empty( $pref ) && array_key_exists( $pref, $datasets ) ) {
		return $datasets[$pref];
	}
	# Group preference
	elseif ( !empty( $trydefault ) && array_key_exists( $trydefault, $datasets ) ) {
		return $datasets[$trydefault];
	} else {
		return $datasets[$wdDefaultViewDataSet];
	}
}


/**
* Load dataset definitions from the database if necessary.
*
* @return an array of all available datasets
**/
function &wdGetDataSets() {

	static $datasets, $wgGroupPermissions;
	if ( empty( $datasets ) ) {
		// Load defs from the DB
		$dbs = wfGetDB( DB_SLAVE );
		$res = $dbs->select( 'wikidata_sets', array( 'set_prefix' ) );

		while ( $row = $dbs->fetchObject( $res ) ) {

			$dc = new DataSet();
			$dc->setPrefix( $row->set_prefix );
			if ( $dc->isValidPrefix() ) {
				$datasets[$row->set_prefix] = $dc;
				wfDebug( "Imported data set: " . $dc->fetchName() . "\n" );
			} else {
				wfDebug( $row->set_prefix . " does not appear to be a valid dataset!\n" );
			}
		}
	}
	return $datasets;
}






// Hook: replace the proposition to "create new page" by a custom, allowing to create new expression as well
$wgHooks[ 'SpecialSearchNogomatch' ][] = 'owNoGoMatchHook';

function owNoGoMatchHook( &$title ) {
	global $wgOut,$wgDisableTextSearch;
	$wgOut->addWikiMsg( 'search-nonefound' );
	$wgOut->addWikiMsg( 'ow_searchnoresult', wfEscapeWikiText( $title ) );
//	$wgOut->addWikiMsg( 'ow_searchnoresult', $title );

	$wgDisableTextSearch = true ;
	return true;
}



// Hook: The Go button should search (first) in the Expression namespace instead of Article namespace
$wgHooks[ 'SearchGetNearMatchBefore' ][] = 'owGoClicked';

function owGoClicked( $allSearchTerms, &$title ) {
	$term = $allSearchTerms[0] ;
	$title = Title::newFromText( $term ) ;
	if ( is_null( $title ) ){
		return true;
	}

	# Replace normal namespace with expression namespace
	if ( $title->getNamespace() == NS_MAIN ) {
		$title = Title::newFromText( $term, NS_EXPRESSION ) ; // $expressionNamespaceId ) ;
	}

	if ( $title->exists() ) {
		return false; // match!
	}
	return true; // no match
}

?>
