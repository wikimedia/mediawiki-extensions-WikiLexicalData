<?php

// Take credit for your work.
$wgExtensionCredits['other'][] = [
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
	'author' => [ 'Hiong3-eng5', 'Kip' , '[http://www.omegawiki.org/User:Purodha Purodha]' ],

	// The URL to a wiki page/web page with information about the extension,
	// which will appear on Special:Version.
	'url' => 'https://www.omegawiki.org/Help:OmegaWiki_API',
];

// Map class name to filename for autoloading
$wgAutoloadClasses['SynonymTranslation'] = __DIR__ . '/owSyntrans.php';
$wgAutoloadClasses['Define'] = __DIR__ . '/owDefine.php';
$wgAutoloadClasses['Express'] = __DIR__ . '/owExpress.php';
$wgAutoloadClasses['AddDefinition'] = __DIR__ . '/owAddDefinition.php';
$wgAutoloadClasses['AddSyntrans'] = __DIR__ . '/owAddSyntrans.php';
$wgAutoloadClasses['AddAnnotation'] = __DIR__ . '/owAddAnnotation.php';
$wgAutoloadClasses['AddToCollection'] = __DIR__ . '/owAddToCollection.php';

// Map module name to class name
$wgAPIModules['ow_syntrans'] = 'SynonymTranslation';
$wgAPIModules['ow_define'] = 'Define';
$wgAPIModules['ow_express'] = 'Express';
$wgAPIModules['ow_add_definition'] = 'AddDefinition';
$wgAPIModules['ow_add_syntrans'] = 'AddSyntrans';
$wgAPIModules['ow_add_annotation'] = 'AddAnnotation';
$wgAPIModules['ow_add_to_collection'] = 'AddToCollection';

// Load the internationalization file
$wgMessagesDirs['OmegaWiki'] = __DIR__ . '/../../i18n/omegawiki';

// Return true so that MediaWiki continues to load extensions.
return true;
