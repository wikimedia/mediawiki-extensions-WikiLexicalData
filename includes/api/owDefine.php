<?php

/** O m e g a W i k i   A P I ' s   D e f i n e   c l a s s
 *
 * HISTORY
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
 * - Add optional translation list option. &trans=on. default off
 * - Add optional translation limit option. &transLang=nan-POJ|eng
 * - Add optional lang parameter. &lang=cmn-Hant
 * - readjust defining and definingByAnyLanguage functions into class Define.
 *		Express Class now extends Define Class
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

		// Optional parameter
		if ( $params['e'] ) {
			$options['e'] = $params['e'];
		}
		if ( $params['lang'] ) {
			$this->languageId = $params['lang'];
			$defined = defining( $params['dm'], $params['lang'], $options, $this->getModuleName() );
		} else {
			$defined = definingForAnyLanguage( $params['dm'], $options, $this->getModuleName() );
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
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be defined',
			'lang' => 'The language id to be defined',
			'e' => 'The expression to be defined'
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'Get a definition from a defined meaning id only.',
			'api.php?action=ow_define&dm=8218&format=xml',
			'Get a definition from a defined meaning id and a language id.',
			'api.php?action=ow_define&dm=8218&lang=87&format=xml' ,
			'When a definition is not available for a language id, ',
			'the definition will default to English',
			'api.php?action=ow_define&dm=8218&lang=107&format=xml'
		);
	}

}

/** Define expression when the language is not specified.
 *
 */
function defining( $definedMeaningId, $languageId, $options = null, $moduleName = null ) {

	if ( !$moduleName ) {
		$moduleName = 'ow_define';
	}

	if ( $options['e'] ) {
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
		$languageId = 85;
		$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
	}

	$definitionLanguageId = getDefinedMeaningDefinitionLanguageIdForDefinition( $definedMeaningId, $text );
	$definitionLanguage = getLanguageIdLanguageNameFromIds( $definitionLanguageId, $definitionLanguageId );
	$definitionSpelling = getSpellingForLanguageId( $definedMeaningId, $DefinitionLanguageId, 85 );

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
			)
		)
	);

}

/** Define expression when the language is not specified.
 *
 */
function definingForAnyLanguage( $definedMeaningId, $options = null, $moduleName = null ) {

	$remove_langIdArray = 0;
	if ( !$moduleName ) {
		$moduleName = 'ow_define';
	}

	if ( $options['e'] ) {
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
		unset( $definition[$moduleName]['langid']);
		unset( $definition[$moduleName]['lang']);
	}

	return $definition;

}
