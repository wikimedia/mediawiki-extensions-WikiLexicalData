<?php

if ( !defined( 'MEDIAWIKI' ) ) die( 'Invalid entry point.' );

$dir = dirname( __FILE__ ) . '/';

$dir = str_replace( '\\', '/', $dir );

require_once( $dir . 'OmegaWiki/WikiDataGlobals.php' );
require_once( $dir . 'OmegaWiki/Wikidata.php' );

require_once( $wgWldScriptPath . 'SpecialLanguages.php' );

// API
require_once( $wgWldAPIScriptPath . 'OmegaWikiExt.php');

$wgExtensionCredits['other'][] = array(
	'path'            => __FILE__,
	'name'            => 'WikiLexicalData',
	'version'         => '0.1.0',
	'author'          => array(
		'Erik Möller',
		'Kim Bruning',
		'Maarten van Hoof',
		'André Malafaya Baptista',
		'Kipcool'
	),
	'url'             => 'http://www.mediawiki.org/wiki/Extension:WikiLexicalData',
	'descriptionmsg'  => 'wikidata-desc',
);

$wgExtensionMessagesFiles['Wikidata'] = $dir . 'Wikidata.i18n.php';

// Resource modules

$resourcePathArray = array(
	'localBasePath' => dirname( __FILE__ ) . '/resources',
	'remoteExtPath' => 'WikiLexicalData/resources'
);

// separated css with position "top" to avoid
// so-called Flash of unstyled content
$wgResourceModules['ext.Wikidata.css'] = $resourcePathArray + array(
	'styles' => array( 'suggest.css', 'tables.css' ),
	'position' => 'top'
);

$wgResourceModules['ext.Wikidata.ajax'] = $resourcePathArray + array(
	'scripts' => 'omegawiki-ajax.js'
);

$wgResourceModules['ext.Wikidata.edit'] = $resourcePathArray + array(
	'scripts' => 'omegawiki-edit.js'
);

$wgResourceModules['ext.Wikidata.suggest'] = $resourcePathArray + array(
	'scripts' => 'suggest.js',
	'messages' => array( 'ow_suggest_clear', 'ow_suggest_previous', 'ow_suggest_next' )
);

$wgAutoloadClasses['WikiLexicalDataHooks'] = $dir . 'Wikidata.hooks.php';

$wgAutoloadClasses['WikidataArticle'      ] = $dir . 'includes/WikidataArticle.php';
$wgAutoloadClasses['WikidataEditPage'     ] = $dir . 'includes/WikidataEditPage.php';
$wgAutoloadClasses['WikidataPageHistory'  ] = $dir . 'includes/WikidataPageHistory.php';

# FIXME: Rename this to reduce chance of collision.
$wgAutoloadClasses['OmegaWiki'] = $dir . 'OmegaWiki/OmegaWiki.php';
$wgAutoloadClasses['DataSet'] = $dir . 'OmegaWiki/Wikidata.php';
$wgAutoloadClasses['DefaultWikidataApplication'] = $dir . 'OmegaWiki/Wikidata.php';
$wgAutoloadClasses['DefinedMeaning'] = $dir . 'OmegaWiki/DefinedMeaning.php';
$wgAutoloadClasses['DefinedMeaningModel'] = $dir . 'OmegaWiki/DefinedMeaningModel.php';
$wgAutoloadClasses['NeedsTranslationTo'] = $dir . 'OmegaWiki/NeedsTranslationTo.php';
$wgAutoloadClasses['Search'] = $dir . 'OmegaWiki/Search.php';

// Special Pages
require_once( $wgWldSetupScriptPath . "OWSpecials.php" );

# FIXME: These should be modified to make Wikidata more reusable.
$wgAvailableRights[] = 'editwikidata-uw';
$wgAvailableRights[] = 'deletewikidata-uw';
$wgAvailableRights[] = 'wikidata-copy';
$wgAvailableRights[] = 'languagenames';
$wgAvailableRights[] = 'addcollection';
$wgAvailableRights[] = 'editClassAttributes';

$wgGroupPermissions['*']['editClassAttributes'] = false;

$wgGroupPermissions['wikidata-omega']['editwikidata-uw'] = true;
$wgGroupPermissions['wikidata-omega']['deletewikidata-uw'] = true;
$wgGroupPermissions['wikidata-copy']['wikidata-copy'] = true;
$wgGroupPermissions['wikidata-omega']['wikidata-copy'] = true;

$wgGroupPermissions['bureaucrat']['languagenames'] = true;
$wgGroupPermissions['bureaucrat']['addcollection'] = true;
$wgGroupPermissions['bureaucrat']['editClassAttributes'] = true;

// WikiLexicalData Configuration.

# Array of namespace ids and the handler classes they use.
$wdHandlerClasses = array();
# Path to the handler class directory, will be deprecated in favor of autoloading shortly.
//$wdHandlerPath = '';

# The term dataset prefix identifies the Wikidata instance that will
# be used as a resource for obtaining language-independent strings
# in various places of the code. If the term db prefix is empty,
# these code segments will fall back to (usually English) strings.
# If you are setting up a new Wikidata instance, you may want to
# set this to ''.
$wdTermDBDataSet = 'uw';

# This is the dataset that should be shown to all users by default.
# It _must_ exist for the Wikidata application to be executed
# successfully.
$wdDefaultViewDataSet = 'uw';

$wdShowCopyPanel = false;
$wdShowEditCopy = true;

# FIXME: These should be modified to make Wikidata more reusable.
$wdGroupDefaultView = array();
$wdGroupDefaultView['wikidata-omega'] = 'uw';
# $wdGroupDefaultView['wikidata-umls']='umls';
# $wdGroupDefaultView['wikidata-sp']='sp';

$wgCommunity_dc = 'uw';
$wgCommunityEditPermission = 'editwikidata-uw';

$wdCopyAltDefinitions = false;
$wdCopyDryRunOnly = false;

# The site prefix allows us to have multiple sets of customized
# messages (for different, typically site-specific UIs)
# in a single database.
if ( !isset( $wdSiteContext ) ) $wdSiteContext = "uw";

#
## Hooks
#
$wgHooks['BeforePageDisplay'][] = 'WikiLexicalDataHooks::onBeforePageDisplay';
$wgHooks['GetPreferences'][] = 'WikiLexicalDataHooks::onGetPreferences';
$wgHooks['ArticleFromTitle'][] = 'WikiLexicalDataHooks::onArticleFromTitle';
$wgHooks['CustomEditor'][] = 'WikiLexicalDataHooks::onCustomEditor';
$wgHooks['MediaWikiPerformAction'][] = 'WikiLexicalDataHooks::onMediaWikiPerformAction';
$wgHooks['AbortMove'][] = 'WikiLexicalDataHooks::onAbortMove';
$wgHooks['NamespaceIsMovable'][] = 'WikiLexicalDataHooks::onNamespaceIsMovable';
$wgHooks['SpecialSearchNogomatch'][] = 'WikiLexicalDataHooks::onNoGoMatchHook';
$wgHooks['SearchGetNearMatchBefore'][] = 'WikiLexicalDataHooks::onGoClicked';
$wgHooks['PageContentLanguage'][] = 'WikiLexicalDataHooks::onPageContentLanguage';
$wgHooks['SkinTemplateNavigation'][] = 'WikiLexicalDataHooks::onSkinTemplateNavigation';

// Jobs
require_once( $wgWldSetupScriptPath . "OWJobs.php" );

// LocalApp.php is optional. Its function is like LocalSettings.php,
// if you want to separate the MediaWiki configuration from the Wikidata configuration
if ( file_exists ( dirname(__FILE__) . "LocalApp.php" )) {
	require_once( dirname(__FILE__) . "LocalApp.php" );
}
