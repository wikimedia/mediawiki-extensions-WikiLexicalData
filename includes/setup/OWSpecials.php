<?php
/**
 * A Special Page Setup
 *
 * TODO: transfer all special page configuration to this script
 */
global $wgWldScriptPath, $wgWldSpecialsScriptPath;

 # Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo
	'To install my extension, put the following line in LocalSettings.php:' .
	"\n" .
	'require_once( "' . $wgWldScriptPath . 'OWSpecials.php" );
	';
	exit( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SpecialConceptMapping',
	'author' => 'Kim Bruning',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SpecialCopy',
	'author' => 'Alan Smithee',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SpecialDatasearch',
	'author' => 'Kipcool',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SpecialOWStatistics',
	'author' => 'Kipcool',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SpecialOWDownloads',
	'author' => 'Hiong3-eng5',
);

# Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgAutoloadClasses['SpecialDatasearch'] = $wgWldSpecialsScriptPath . 'SpecialDatasearch.php';
$wgAutoloadClasses['SpecialOWStatistics'] = $wgWldSpecialsScriptPath . 'SpecialOWStatistics.php';
$wgAutoloadClasses[ 'SpecialOWDownloads' ] = $wgWldSpecialsScriptPath . 'SpecialOWDownloads.php';

# Tell MediaWiki about the new special page and its class name
$wgSpecialPages['ow_data_search'] = 'SpecialDatasearch';
$wgSpecialPages['ow_statistics'] = 'SpecialOWStatistics';
$wgSpecialPages['ow_downloads'] = 'SpecialOWDownloads';

# Tell MediaWiki about which group the new special page belongs to
$wgSpecialPageGroups[ 'ow_data_search' ] = 'wiki';
$wgSpecialPageGroups[ 'ow_statistics' ] = 'wiki';
$wgSpecialPageGroups[ 'ow_downloads' ] = 'wiki';

# Location of an aliases file (Tell MediaWiki to load this file)
//$wgExtensionMessagesFiles[ 'OWSpecialsAlias' ] = #dir . '../i18n/OWSpecials.alias.php';
