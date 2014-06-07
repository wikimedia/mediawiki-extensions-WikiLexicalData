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

	function bindToDefinedMeaning( $definedMeaningId, $identicalMeaning = "true" ) {
		createSynonymOrTranslation( $definedMeaningId, $this->id, $identicalMeaning );
	}

	function assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning ) {
		if ( !$this->isBoundToDefinedMeaning( $definedMeaningId ) ) {
			$this->bindToDefinedMeaning( $definedMeaningId, $identicalMeaning );
		}
	}
}

class Expressions {

	public function __construct() {
	}

	/** @brief creates a new Expression entry.
	 *
	 * @param spelling   req'd str
	 * @param languageId req'd int
	 * @param option     opt'l arr
	 *
	 *	options:
	 *		updateId int Inserts a transaction id instead of the updated one.
	 *		dc       str The data set
	 *
	 * @note Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::createExpressionId instead.
	 */
	public static function createId( $spelling, $languageId, $options = array() ) {
		if ( isset( $options['dc'] ) ) {
			$dc = $options['dc'];
		} else {
			$dc = wdGetDataSetContext();
		}
		$dbw = wfGetDB( DB_MASTER );

		$expressionId = newObjectId( "{$dc}_expression" );
		if ( isset( $options['updateId'] ) ) {
			$updateId = $options['updateId'];
		} else  {
			$updateId = getUpdateTransactionId();
		}
		$dbw->insert(
			"{$dc}_expression",
			array( 'expression_id' => $expressionId,
				'spelling' => $spelling,
				'language_id' => $languageId,
				'add_transaction_id' => $updateId
			), __METHOD__
		);
		return $expressionId;
	}

	/**
	 * returns the total number of "Expressions"
	 *
	 * else returns null
	 */
	public static function getNumberOfExpressions( $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond[] = null;

		$queryResult = $dbr->select(
			"{$dc}_expression",
			array(
				'total' => 'count(expression_id)',
			),
			array(
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$total = null;
		foreach ( $queryResult as $tot ) {
			$total = $tot->total;
		}

		if ( $total ) {
			return $total;
		}
		return null;
	}

	/**
	 * returns an array of "Expression" objects
	 * for a language
	 *
	 * else returns null
	 */
	public static function getLanguageIdExpressions( $languageId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT']= $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET']= $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$queryResult = $dbr->select(
			"{$dc}_expression",
			'spelling',
			array(
				'language_id' => $languageId,
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$expression = array();
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}

	/**
	 * returns an array of "Expression" objects
	 * for a defined meaning id for a language
	 *
	 * else returns null
	 */
	public static function getDefinedMeaningIdAndLanguageIdExpressions( $languageId, $definedMeaningId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT']= $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET']= $options['OFFSET'];
		}

		$cond[] = 'DISTINCT';

		$queryResult = $dbr->select(
			array(
				'synt' => "{$dc}_syntrans",
				'exp' => "{$dc}_expression"
			),
			'spelling',
			array(
				'synt.expression_id = exp.expression_id',
				'language_id' => $languageId,
				'defined_meaning_id' => $definedMeaningId,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$expression = array();
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp->spelling;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}

}
