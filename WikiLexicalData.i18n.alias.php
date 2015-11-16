<?php
/**
 * Aliases for special pages of the extension WikiexicalData
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'Languages' => array( 'Languages' , 'LanguageManager' , 'OwLanguages' , 'OwLanguageManager' ),
	'AddCollection' => array( 'AddCollection' ,'OwAddCollection' , 'CreateCollection' ,'OwCreateCollection' ),
	'ConceptMapping' => array( 'ConceptMapping' , 'OwConceptMapping' ),
	'ow_data_search' => array( 'ow_data_search' , 'OwDataSearch' ),
	'ImportLangNames' => array( 'ImportLangNames' , 'ImportLanguageNames' , 'OwImportLangNames' , 'OwImportLanguageNames' ),
	'NeedsTranslation' => array( 'NeedsTranslation' , 'OwNeedsTranslation' ),
	'ow_statistics' => array( 'ow_statistics' , 'OwStatistics' , 'OwStatistic'  ),
	'ow_downloads' => array( 'ow_downloads' , 'OwDownloads' ),
	'ExportTSV' => array( 'ExportTSV' , 'OwExportTSV' ),
	'ImportTSV' => array( 'ImportTSV' , 'OwImportTSV' ),
	// unlisted:
	'Suggest' => array( 'Suggest' ),
	'Copy' => array( 'Copy' ),
	'PopupEditor' => array( 'PopsUpEditor' ),
	'Transaction' => array( 'Transaction' ),
	'Select' => array( 'Select' ),
);
