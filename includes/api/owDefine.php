<?php

/** O m e g a W i k i   A P I ' s   D e f i n e   c l a s s
 *
 * Created on March 14, 2013
 *
 */

/** HISTORY
 * - 2013-05-23: Created separate defining and definingByAnyLanguage functions.
 *		Can be useful for E x p r e s s   c l a s s  ~he
 * - 2013-03-14: Creation date ~he
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );

class Define extends ApiBase {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;

		// Get the parameters
		$params = $this->extractRequestParams();

		// Optional parameter
		if ( $params['lang'] ) {
			$this->languageId = $params['lang'];
			$defined = defining( $params['dm'], $params['lang'], $this->getModuleName() );
		} else {
			$defined = definingForAnyLanguage( $params['dm'], $this->getModuleName() );
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
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be defined' ,
			'lang' => 'The language id to be defined'
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'Get a definition from a defined meaning id',
			'api.php?action=ow_define&dm=8218&format=xml',
			'Get a definition from a defined meaning id and a language id.',
			'When a definition is not available for a language id, ',
			'the definition will default to English',
			'api.php?action=ow_define&dm=8218&lang=87&format=xml' ,
			'api.php?action=ow_define&dm=8218&lang=107&format=xml'
		);
	}

}

function defining( $definedMeaningId, $languageId, $moduleName = null ) {

	if ( !$moduleName ) {
		$moduleName = 'ow_define';
	}

	$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
	$spelling = getDefinedMeaningSpellingForLanguage( $definedMeaningId, $languageId );
	$spellingLanguageId = $languageId ;

	if ( !$text ) {
		$languageId = 85;
		$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
	}

	// Add later when I have time
	//$definitionSpelling = getSpellingForLanguage( $definedMeaningId, $languageCode, 'en' );

	return array(
		$moduleName => array(
			'dmid'=> $definedMeaningId,
			'spelling'=>$spelling,
			'spelllang'=>$spellingLanguageId,
			'definition' =>array (
			/*	'spelling'=>$definitionSpelling,
			*/	'lang'=>$languageId,
				'text'=>$text
			)
		)
	);

}

function definingForAnyLanguage( $definedMeaningId, $moduleName = null ) {

	if ( !$moduleName ) {
		$moduleName = 'ow_define';
	}

	$languageId = getDefinedMeaningDefinitionLanguageForAnyLanguage( $definedMeaningId );
	$text = getDefinedMeaningDefinitionForAnyLanguage( $definedMeaningId );
	$spelling = getDefinedMeaningSpellingForAnyLanguage( $definedMeaningId );

	$spellingLanguageId = getDefinedMeaningSpellingLanguageId( $definedMeaningId );

	// Add later when I have time
	//$definitionSpelling = getSpellingForLanguage( $definedMeaningId, $languageCode, 'en' );

	return array(
		$moduleName => array(
			'dmid'=> $definedMeaningId,
			'spelling'=>$spelling,
			'spelllang'=>$spellingLanguageId,
			'definition' =>array (
			/*	'spelling'=>$definitionSpelling,
			*/	'lang'=>$languageId,
				'text'=>$text
			)
		)
	);

}
