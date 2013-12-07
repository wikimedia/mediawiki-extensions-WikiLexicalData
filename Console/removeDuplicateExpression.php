<?php

/**
* Maintenance script to remove duplicate expessions
*/

$baseDir = dirname( __FILE__ ) . '/../../..' ;
require_once( $baseDir . '/maintenance/Maintenance.php' );
require_once( $baseDir . '/extensions/WikiLexicalData/OmegaWiki/WikiDataGlobals.php' );

echo "start\n";

class RemoveDuplicateExpressions extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Maintenance tool to remove duplicated expressions\n"
			. 'Example usage: php removeDuplicateExpression.php --test=true ' . "\n"
			. ' or simply' . "\n"
			. 'php removeDuplicateExpression.php' . "\n";
		$this->addOption( 'test', 'true for test mode. e.g. --test=true' );
	}

	public function execute() {

		global $wdCurrentContext;

		$this->test = false;
		if ( $this->hasOption( 'test' ) ) {
			$this->test = true;
		}

		$this->output( "Starting remove duplicate expressions function...\n" );
		// check if there are duplicates greater than two
		$this->output( "Finding duplicates\n" );
		$duplicates = $this->getDuplicateExpressions();

		$haveDuplicates = 0;
		$syntransHaveDuplicates = 0;
		$sid = array();
		if ( $duplicates ) {
			$haveDuplicates = 1;
			foreach ( $duplicates as $rows ) {
				$expression = $this->getSpellingExpressionId( $rows['spelling'], $rows['language_id'] );
				$this->output( "process {$rows['spelling']} ({$rows['language_id']}) - expression id: original is {$expression[0]}; duplicate is {$expression[1]}\n");
				$syntrans = $this->getSyntransToUpdate( $expression );

				if ( $syntrans ) {
					$syntransHaveDuplicates = 1;
					foreach( $syntrans as $sids ) {
						$sid[] = $sids;
						if ( !$this->test ) {
							// correct the duplication
							$this->correctDuplication( $sids, $expression );
						}
					}
				}

				if ( !$this->test ) {
					// remove the duplicate
					$this->output( "removing duplicate id {$expression[1]}\n");
					$this->deleteDuplicate( $expression[1], $rows['language_id'] );

				}

			}
		}

		$this->duplicateFound = 0;
		if ( $sid ) {
			$totalSids = count( $sid );
			$this->output( "There are a total of {$totalSids} corrected\n");
			$this->removeDuplicateSyntrans();
			$this->duplicateFound = 1;
			$runRemoveDuplicateSyntrans = "\n\nKindly run:\n\nphp removeDuplicateSyntrans.php\n\n to check for duplicate Synonyms/Translations";
		}

		if ( !$haveDuplicates ) {
			$this->output( "Congratulations! No duplicates found\n" );
			if ( $this->duplicateFound ) {
				$this->output( $runRemoveDuplicateSyntrans );
			}
			return true;
		}

		if ( !$syntransHaveDuplicates ) {
			$this->output( "Congratulations! No syntrans have the duplicate expressions\n" );
			if ( $this->duplicateFound ) {
				$this->output( $runRemoveDuplicateSyntrans );
			}
		}

	}

	protected function deleteDuplicate( $expressionId, $languageId, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond = null;

		// remove instead of delete. Lazier way out... for now.
		// reviving expression is now limited to one expression
		// to avoid duplicates from being revived.
		// Adding to TODO list ~ he

/*		$queryResult = $dbr->delete(
			"{$dc}_expression",
			array(
				'remove_transaction_id' => null,
				'expression_id' => $expressionId,
				'language_id' => $languageId
			),
			__METHOD__
		);

*/

		$transactionId = getUpdateTransactionId();
		$queryResult = $dbr->update(
			"{$dc}_expression",
			array(
				'remove_transaction_id' => $transactionId,
			),
			array(
				'remove_transaction_id' => null,
				'expression_id' => $expressionId,
			),
			__METHOD__,
			$cond
		);
	}

	protected function correctDuplication( $syntransSid, $expressionId, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond = null;

		$queryResult = $dbr->update(
			"{$dc}_syntrans",
			array(
				'expression_id' => $expressionId[0],
			),
			array(
				'remove_transaction_id' => null,
				'expression_id' => $expressionId[1],
				'syntrans_sid' => $syntransSid
			),
			__METHOD__,
			$cond
		);

	}

	protected function getSyntransToUpdate( $expressionIds, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond = null;

		$queryResult = $dbr->select(
			"{$dc}_syntrans",
			array(
				'syntrans_sid',
			),
			array(
				'remove_transaction_id' => null,
				'expression_id' => $expressionIds[1]
			),
			__METHOD__,
			$cond
		);

		$sid = array();
		foreach ( $queryResult as $sids ) {
			$sid[] = $sids->syntrans_sid;
		}

		if ( $sid ) {
			return $sid;
		}
		return array();
	}

	protected function getSpellingExpressionId( $spelling, $languageId, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond['ORDER BY'] = 'expression_id';
		$cond['LIMIT']= 2;

		$queryResult = $dbr->select(
			"{$dc}_expression",
			'expression_id',
			array(
				'remove_transaction_id' => null,
				'spelling' => $spelling,
				'language_id' => $languageId
			),
			__METHOD__,
			$cond
		);

		$expressionId = array();
		foreach ( $queryResult as $expressionIds ) {
			$expressionId[] = $expressionIds->expression_id;
		}

		if ( $expressionId ) {
			return $expressionId;
		}
		return array();

	}

	protected function getDuplicateExpressions( $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond['ORDER BY'] = 'count(spelling) DESC';
		$cond['GROUP BY'] = array(
			'spelling',
			'language_id'
		);

		$queryResult = $dbr->select(
			"{$dc}_expression",
			array(
				'spelling',
				'language_id',
				'number' => 'count(spelling)'
			),
			array(
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$duplicates = array();
		foreach ( $queryResult as $dup ) {
			if ( $dup->number > 1 ) {
				$duplicates[] = array(
					'spelling' => $dup->spelling,
					'language_id' => $dup->language_id
				);
			}
		}

		if ( $duplicates ) {
			return $duplicates;
		}
		return array();

	}

}

$maintClass = 'RemoveDuplicateExpressions';
require_once( RUN_MAINTENANCE_IF_MAIN );
