<?php
/**
 * A Special Page Setup
 *
 * TODO: transfer all special page configuration to this script
 */

 # Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo 'To install my extension, put the following line in LocalSettings.php:' .
	"\n" .
	'require_once( "' . $wgWldScriptPath . 'OWSpecials.php" );
	';
	exit( 1 );
}

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialCopy',
	'author' => [
		'Erik Möller',
		'Kim Bruning',
		'Alan Smithee',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialConceptMapping',
	'author' => [
		'Erik Möller',
		'Kim Bruning',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialSuggest',
	'author' => [
		'Peter-Jan Roes',
		'Kim Bruning',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialPopupEditor',
	'author' => [
		'Kipcool',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialAddCollection',
	'author' => [
		'Erik Möller',
		'Kim Bruning',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialDatasearch',
	'author' => [
		'Peter-Jan Roes',
		'Karsten Uil',
		'Kipcool',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialNeedsTranslation',
	'author' => [
		'Peter-Jan Roes',
		'Kipcool',
	],
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialOWStatistics',
	'author' => 'Kipcool',
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialOWDownloads',
	'author' => 'Hiong3-eng5',
];

$wgExtensionCredits['specialpage'][] = [
	'name' => 'SpecialAddFromExternalAPI',
	'author' => 'Hiong3-eng5',
];

# Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgAutoloadClasses['SpecialCopy'] = $dir . 'OmegaWiki/SpecialCopy.php';
$wgAutoloadClasses['SpecialSelect'] = $dir . 'OmegaWiki/SpecialSelect.php';
$wgAutoloadClasses['SpecialSuggest'] = $dir . 'OmegaWiki/SpecialSuggest.php';
$wgAutoloadClasses['SpecialPopupEditor'] = $dir . 'OmegaWiki/SpecialPopupEditor.php';

$wgAutoloadClasses['SpecialAddCollection'] = $dir . 'OmegaWiki/SpecialAddCollection.php';
$wgAutoloadClasses['SpecialConceptMapping'] = $dir . 'OmegaWiki/SpecialConceptMapping.php';
$wgAutoloadClasses['SpecialDatasearch'] = $wgWldSpecialsScriptPath . 'SpecialDatasearch.php';
$wgAutoloadClasses['SpecialImportLangNames'] = $dir . 'OmegaWiki/SpecialImportLangNames.php';
$wgAutoloadClasses['SpecialNeedsTranslation'] = $dir . 'OmegaWiki/SpecialNeedsTranslation.php';

$wgAutoloadClasses['SpecialOWStatistics'] = $wgWldSpecialsScriptPath . 'SpecialOWStatistics.php';
$wgAutoloadClasses['SpecialOWDownloads'] = $wgWldSpecialsScriptPath . 'SpecialOWDownloads.php';

$wgAutoloadClasses['SpecialExportTSV'] = $wgWldSpecialsScriptPath . 'SpecialExportTSV.php';
$wgAutoloadClasses['SpecialImportTSV'] = $wgWldSpecialsScriptPath . 'SpecialImportTSV.php';

$wgAutoloadClasses['SpecialOWAddFromExternalAPI'] = $wgWldSpecialsScriptPath . 'SpecialOWAddFromExternalAPI.php';

// $wgAutoloadClasses['SpecialTransaction'] = $dir . 'OmegaWiki/SpecialTransaction.php';

# Tell MediaWiki about the new special page and its class name
$wgSpecialPages['Copy'] = 'SpecialCopy';
$wgSpecialPages['Select'] = 'SpecialSelect';
$wgSpecialPages['Suggest'] = 'SpecialSuggest';
$wgSpecialPages['PopupEditor'] = 'SpecialPopupEditor';

$wgSpecialPages['AddCollection'] = 'SpecialAddCollection';
$wgSpecialPages['ConceptMapping'] = 'SpecialConceptMapping';
$wgSpecialPages['ow_data_search'] = 'SpecialDatasearch';
$wgSpecialPages['ImportLangNames'] = 'SpecialImportLangNames';
$wgSpecialPages['NeedsTranslation'] = 'SpecialNeedsTranslation';

$wgSpecialPages['ow_statistics'] = 'SpecialOWStatistics';
$wgSpecialPages['ow_downloads'] = 'SpecialOWDownloads';

$wgSpecialPages['ExportTSV'] = 'SpecialExportTSV';
$wgSpecialPages['ImportTSV'] = 'SpecialImportTSV';

$wgSpecialPages['ow_addFromExtAPI'] = 'SpecialOWAddFromExternalAPI';

// $wgSpecialPages['Transaction'] = 'SpecialTransaction';

global $wgWldProcessExternalAPIClasses, $wgWldExtenalResourceLanguages, $wgWldScriptPath;
$wgWldProcessExternalAPIClasses = [];
$wgWldExtenalResourceLanguages = [];

if ( file_exists( $wgWldScriptPath . '/external/wordnik/wordnik/Swagger.php' ) ) {
	$wgAutoloadClasses['WordnikExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgAutoloadClasses['WordnikWiktionaryExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgAutoloadClasses['WordnikWordnetExtension' ] = $wgWldSpecialsScriptPath . 'ExternalWordnik.php';
	$wgWldProcessExternalAPIClasses['WordnikExtension'] = 'Wordnik';
	$wgWldProcessExternalAPIClasses['WordnikWiktionaryExtension'] = 'Wordnik Wiktionary';
	$wgWldProcessExternalAPIClasses['WordnikWordnetExtension'] = 'Wordnik Wordnet';
	$wgWldExtenalResourceLanguages[WLD_ENGLISH_LANG_ID] = 'English';
	require_once $wgWldScriptPath . '/external/wordnikConfig.php';
}

if ( $wgWldProcessExternalAPIClasses ) {
	$wgResourceModules['ext.OwAddFromExtAPI.js'] = $resourcePathArray + [
		'scripts' => 'omegawiki-addExtAPI.js'
	];
}

# Location of an aliases file (Tell MediaWiki to load this file)
// $wgExtensionMessagesFiles[ 'OWSpecialsAlias' ] = #dir . '../i18n/OWSpecials.alias.php';

// Return true so that MediaWiki continues to load extensions.
return true;
