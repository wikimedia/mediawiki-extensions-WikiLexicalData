<?php

require_once( 'WikiDataGlobals.php' );
require_once( 'WikiDataAPI.php' );

class Expression {
	public $id;
	public $spelling;
	public $languageId;
	public $meaningIds = array();
	public $dataset;

	function __construct( $id, $spelling, $languageId, $dc = null ) {
		$this->id = $id;
		$this->spelling = $spelling;
		$this->languageId = $languageId;
		if ( is_null( $dc ) ) {
			$this->dataset = wdGetDataSetContext();
		} else {
			$this->dataset = $dc;
		}
	}

	function createNewInDatabase() {
		$wikipage = $this->createExpressionPage();
		createInitialRevisionForPage( $wikipage, 'Created by adding expression' );
	}

	function createExpressionPage() {
		return createPage( NS_EXPRESSION, getPageTitle( $this->spelling ) );
	}

	function isBoundToDefinedMeaning( $definedMeaningId ) {
		return expressionIsBoundToDefinedMeaning( $definedMeaningId, $this->id );
	}

	function bindToDefinedMeaning( $definedMeaningId, $identicalMeaning ) {
		createSynonymOrTranslation( $definedMeaningId, $this->id, $identicalMeaning );
	}

	function assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning ) {
		if ( !$this->isBoundToDefinedMeaning( $definedMeaningId ) ) {
			$this->bindToDefinedMeaning( $definedMeaningId, $identicalMeaning );
		}
	}
}
