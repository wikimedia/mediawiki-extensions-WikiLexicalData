<?php

// Take credit for your work.
$wgExtensionCredits['other'][] = array(
	// The full path and filename of the file. This allows MediaWiki
	// to display the Subversion revision number on Special:Version.
	'path' => __FILE__,

	// The name of the extension, which will appear on Special:Version.
	'name' => 'OmegaWiki',

	// Alternatively, you can specify a message key for the description.
	'descriptionmsg' => 'apiow-desc',

	// The version of the extension, which will appear on Special:Version.
	// This can be a number or a string.
	'version' => '1.1',

	// Your name, which will appear on Special:Version.
	'author' => array( 'Hiong3-eng5', 'Kip' , '[http://www.omegawiki.org/User:Purodha Purodha]'),

	// The URL to a wiki page/web page with information about the extension,
	// which will appear on Special:Version.
	'url' => 'https://www.omegawiki.org/Help:OmegaWiki_API',
);

// Map class name to filename for autoloading
$wgAutoloadClasses['SynonymTranslation'] = dirname( __FILE__ ) . '/owSyntrans.php';
$wgAutoloadClasses['Define'] = dirname( __FILE__ ) . '/owDefine.php';
$wgAutoloadClasses['Express'] = dirname( __FILE__ ) . '/owExpress.php';
$wgAutoloadClasses['AddDefinition'] = dirname( __FILE__ ) . '/owAddDefinition.php';
$wgAutoloadClasses['AddSyntrans'] = dirname( __FILE__ ) . '/owAddSyntrans.php';
$wgAutoloadClasses['AddAnnotation'] = dirname( __FILE__ ) . '/owAddAnnotation.php';
$wgAutoloadClasses['AddToCollection'] = dirname( __FILE__ ) . '/owAddToCollection.php';

// Map module name to class name
$wgAPIModules['ow_syntrans'] = 'SynonymTranslation';
$wgAPIModules['ow_define'] = 'Define';
$wgAPIModules['ow_express'] = 'Express';
$wgAPIModules['ow_add_definition'] = 'AddDefinition';
$wgAPIModules['ow_add_syntrans'] = 'AddSyntrans';
$wgAPIModules['ow_add_annotation'] = 'AddAnnotation';
$wgAPIModules['ow_add_to_collection'] = 'AddToCollection';

// Load the internationalization file
$wgMessagesDirs['OmegaWiki'] = dirname( __FILE__ ) . '/../../i18n/omegawiki';
$wgExtensionMessagesFiles['OmegaWiki'] = dirname( __FILE__ ) . '/OmegaWiki.i18n.php';

// Return true so that MediaWiki continues to load extensions.
return true;
