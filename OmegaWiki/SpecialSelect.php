<?php

if ( !defined( 'MEDIAWIKI' ) ) die();

require_once( "Wikidata.php" );
require_once( "WikiDataGlobals.php" );

class SpecialSelect extends SpecialPage {
	function SpecialSelect() {
		parent::__construct( 'Select', 'UnlistedSpecialPage' );
	}

	function execute( $par ) {
		require_once( 'languages.php' );
		require_once( 'Transaction.php' );
		global $wgOut, $wgUser, $wgRequest;

		$wgOut->disable();

		$dc = wdGetDataSetContext();
		$optionAttribute = $wgRequest->getVal( WLD_OPTION_ATTRIBUTE );
		$attributeObject = $wgRequest->getVal( 'attribute-object', 0 );
		$lang_code = owDatabaseAPI::getUserLanguage();
		$lang_id = getLanguageIdForCode( $lang_code );

		$dbr = wfGetDB( DB_SLAVE );

		$objectLanguage = 0 ;
		if ( $attributeObject != 0 ) {
			$objectLanguage = $dbr->selectField(
				array(
					'synt' => "{$dc}_syntrans",
					'exp' => "{$dc}_expression"
				),
				'language_id',
				array(
					'synt.syntrans_sid' => $attributeObject,
					'synt.remove_transaction_id' => null
				), __METHOD__,
				array(),
				array( 'exp' => array( 'JOIN', array(
					'exp.expression_id = synt.expression_id',
					'exp.remove_transaction_id' => null
				)))
			);
			// language is not always defined, for example for a DM Option Attribute
			if ( ! $objectLanguage ) $objectLanguage = 0 ;
		}

		$options_res = $dbr->select(
			"{$dc}_option_attribute_options",
			array( 'option_id', 'option_mid' ),
			array(
				'attribute_id' => $optionAttribute,
				'language_id' => array( $objectLanguage, 0 ),
				'remove_transaction_id' => null
			), __METHOD__
		);

		$optionsString = '';
		$optionsArray = array() ;
		foreach ( $options_res as $options_row ) {
			$spelling_row = null;
			$lang_id = getLanguageIdForCode( $lang_code );

			// try to find something with lang_id
			if ( $lang_id ) {
				$spelling_row = $dbr->selectRow(
					array( 'synt' => "{$dc}_syntrans", 'exp' => "{$dc}_expression" ),
					'exp.spelling',
					array(
						'synt.defined_meaning_id' => $options_row->option_mid,
						'exp.language_id' => $lang_id,
						'exp.expression_id = synt.expression_id',
						'exp.remove_transaction_id' => null,
						'synt.remove_transaction_id' => null
					), __METHOD__
				);
			}

			if ( !$spelling_row ) {
				// nothing found, try in English
				$spelling_row = $dbr->selectRow(
					array( 'synt' => "{$dc}_syntrans", 'exp' => "{$dc}_expression" ),
					'exp.spelling',
					array(
						'synt.defined_meaning_id' => $options_row->option_mid,
						'exp.language_id' => WLD_ENGLISH_LANG_ID,
						'exp.expression_id = synt.expression_id',
						'exp.remove_transaction_id' => null,
						'synt.remove_transaction_id' => null
					), __METHOD__
				);
			}

			$optionsArray[$options_row->option_id] = $spelling_row->spelling ;
		}

		asort( $optionsArray ) ;
		foreach ($optionsArray as $option_id => $spelling ) {
			if ( $optionsString != '' ) $optionsString .= "\n";
			$optionsString .= $option_id . ';' . $spelling ;
		}

	echo $optionsString;
	}
}
