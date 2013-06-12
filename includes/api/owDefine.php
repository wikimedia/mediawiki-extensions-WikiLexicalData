<?php

/** O m e g a W i k i   A P I ' s   D e f i n e   c l a s s
 *
 * HISTORY
 * - 2013-06-12: Add optional translation list option. &syntrans= (syn, trans or all)
 * 		added ability to add syntrans.
 * - 2013-06-05: Readjusted defining and definingByAnyLanguage functions into
 *		class Define. Express Class now extends Define Class.
 * - 2013-05-31: Readjusted the array produced. The Language Name added.
 * 		Language name defaults to the language of their respective
 *		expression/definition.  When the language name does not exist,
 *		the language name is given in English. language_id is retained
 *		so that those who are using the API might be able to use it in case
 *		the language name changes from its current unpredictable ways.
 *		( Who knows, the script currently calls Spanish as Castillian in English,
 *		but may use a different one in the future ). ~he
 * - 2013-05-23: Created separate defining and definingByAnyLanguage functions.
 *		Can be useful for E x p r e s s   c l a s s  ~he
 * - 2013-03-14: Creation date ~he
 *
 * TODO
 * - Add optional translation limit option. &trans_lang=385|85
 * - Add optional translation limit option. &trans_lang_iso=nan-POJ|eng
 * - Add optional lang parameter. &lang_iso=cmn-Hant
 *
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );

class Define extends SynonymTranslation {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;
		$options = array();

		// Get the parameters
		$params = $this->extractRequestParams();

		// Required parameter
		// Check if dm is valid
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding syntrans is missing', 'param dm is missing' );
		} else {
			// check that defined_meaning_id exists
			if ( !verifyDefinedMeaningId( $params['dm'] ) ) {
				$this->dieUsage( 'Non existent dm id (' . $params['dm'] . ').', "dm not found." );
			}
		}

		// Optional parameter
		$options = array();
		$part = 'off';

		if ( isset( $params['syntrans'] ) ) {
			$part = $params['syntrans'];
		}

		// error if $params['part'] is empty
		if ( $part == '' ) {
			$this->dieUsage( 'parameter part for adding syntrans is empty', 'param part is empty' );
		}

		// get syntrans
		// When returning synonyms or translation only
		if ( $part == 'syn' or $part == 'trans' or $part == 'all' ) {
			if ( !isset( $params['lang'] ) ) {
				$this->dieUsage( 'parameter lang for adding syntrans is missing', 'param lang is missing' );
			}
			$options['part'] = $part;
		}

		if ( $params['e'] ) {
			$trueOrFalse = getExpressionId( $params['e'], $params['lang']);
			if ( $trueOrFalse == true ) {
				$options['e'] = $params['e'];
			}
		}

		if ( $params['e'] && !isset( $options['e'] ) ) {
			$this->dieUsage( 'parameter e for adding syntrans does not exist', 'param e does not exist' );
		}

		if ( $params['lang'] ) {
			$trueOrFalse = LanguageIdExist( $params['lang']);
			if ( $trueOrFalse == true ) {
				$options['lang'] = $params['lang'];
				$defined = $this->defining( $params['dm'], $params['lang'], $options, $this->getModuleName() );
			} else {
				$this->dieUsage( 'parameter lang for adding syntrans does not exist', 'param lang does not exist' );
			}
		} else {
			if ( $part == 'syn' or $part == 'trans' or $part == 'all' ) {
				$this->dieUsage( 'parameter lang for adding syntrans is empty', 'param lang empty' );
			}
			$defined = $this->definingForAnyLanguage( $params['dm'], $options, $this->getModuleName() );
		}

		$defined = $defined[ $this->getModuleName() ];
		$this->getResult()->addValue( null, $this->getModuleName(), $defined );
		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'Get the definition of a defined meaning.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'dm' => array (
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'lang' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
			'e' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
			'syntrans' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be defined',
			'lang' => 'The language id to be defined',
			'e' => 'The expression to be defined',
			'syntrans' => 'include syntrans to the definition'
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'Get a definition from a defined meaning id only.',
			'api.php?action=ow_define&dm=8218',
			'',
			'Get a definition from a defined meaning id and a language id.',
			'api.php?action=ow_define&dm=8218&lang=87',
			'When a definition is not available for a language id, ',
			'the definition will default to English',
			'api.php?action=ow_define&dm=8218&lang=107',
			'',
			'When you want to include synonyms',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=syn',
			'When you want to include translations',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=trans',
			'When you want to include both synonyms and translations',
			'api.php?action=ow_define&dm=8218&lang=87&syntrans=all',
			'',
			'NOTE: You can not include syntrans without the language id.',
			'',
			'In case the expression is also given with the language id,',
			'the expression is excluded from the list of syntrans.',
			'',
			'Get the synonyms and translations of a defined meaning id with lang',
			'api.php?action=ow_define&dm=8218&syntrans=all&e=字母&lang=107',
			'Get the synonyms of a defined meaning id',
			'api.php?action=ow_define&dm=8218&syntrans=syn&e=aksara&lang=231',
		);
	}

	/**
	 * Define expression when the language is not specified.
	 */
	protected function defining( $definedMeaningId, $languageId, $options = array(), $moduleName = null ) {
		$syntrans = array();

		if ( is_null( $moduleName ) ) {
			$moduleName = 'ow_define';
		}

		if ( isset( $options['e'] ) ) {
			$spelling = $options['e'];
		} else {
			$spelling = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $languageId );
		}
		$spellingLanguageId = $languageId;

		// get language name. Use English as fall back.
		$spellingLanguage = getLanguageIdLanguageNameFromIds( $spellingLanguageId, $spellingLanguageId );
		if ( !$spellingLanguage ) {
			$spellingLanguage = getLanguageIdLanguageNameFromIds( $spellingLanguageId, WLD_ENGLISH_LANG_ID );
		}

		$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $spellingLanguageId );

		if ( !$text ) {
			$languageId = WLD_ENGLISH_LANG_ID;
			$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
		}

		$definitionLanguageId = getDefinedMeaningDefinitionLanguageIdForDefinition( $definedMeaningId, $text );
		$definitionLanguage = getLanguageIdLanguageNameFromIds( $definitionLanguageId, $definitionLanguageId );
		$definitionSpelling = getSpellingForLanguageId( $definedMeaningId, $definitionLanguageId, WLD_ENGLISH_LANG_ID );

		if ( isset( $options['part'] ) ) {
			if( $options['part'] == 'all' ) {
				$options['part'] = null;
			}
			$syntrans = $this->synTrans( $definedMeaningId, $options );
		}

		return array(
			$moduleName => array(
				'dmid' => $definedMeaningId,
				'spelling' => $spelling,
				'langid' => $spellingLanguageId,
				'lang' => $spellingLanguage,
				'definition' => array (
					'spelling' => $definitionSpelling,
					'langid' => $definitionLanguageId,
					'lang' => $definitionLanguage,
					'text' => $text
				),
				'syntrans' => $syntrans
			)
		);

	}

	/**
	 * Define expression when the language is not specified.
	 */
	protected function definingForAnyLanguage( $definedMeaningId, $options = array(), $moduleName = null ) {
		$languageId = null;
		$language = null;

		$remove_langIdArray = 0;
		if ( is_null( $moduleName ) ) {
			$moduleName = 'ow_define';
		}

		if ( isset( $options['e'] ) ) {
			$spelling = $options['e'];
			$languageId = getLanguageIdForDefinedMeaningAndExpression( $definedMeaningId, $spelling );
			$language = getLanguageIdLanguageNameFromIds( $languageId, $languageId );
			if ( !$language ) {
				$language = getLanguageIdLanguageNameFromIds( $languageId, WLD_ENGLISH_LANG_ID );
			}
		} else {
			$remove_langIdArray = 1;
		}

		$text = getDefinedMeaningDefinition( $definedMeaningId );
		$definitionLanguageId = getDefinedMeaningDefinitionLanguageIdForDefinition( $definedMeaningId, $text );
		$definitionLanguage = getLanguageIdLanguageNameFromIds( $definitionLanguageId, $definitionLanguageId );
		$definitionSpelling = getSpellingForLanguageId( $definedMeaningId, $definitionLanguageId, WLD_ENGLISH_LANG_ID );

		$definition = array(
			$moduleName => array(
				'dmid' => $definedMeaningId,
				'langid' => $languageId,
				'lang' => $language,
				'definition' => array (
					'spelling' => $definitionSpelling,
					'langid' => $definitionLanguageId,
					'lang' => $definitionLanguage,
					'text'=> $text
				)
			)
		);

		if ( $remove_langIdArray == 1 ) {
			unset( $definition[$moduleName]['langid'] );
			unset( $definition[$moduleName]['lang'] );
		}

		return $definition;

	}
}
