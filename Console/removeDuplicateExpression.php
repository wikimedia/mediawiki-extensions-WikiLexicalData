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
	//	$this->test = true;
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
		}

		if ( !$haveDuplicates ) {
			$this->output( "Congratulations! No duplicates found\n" );
			if ( $this->duplicateFound ) {
				$this->removeDuplicateSyntrans();
			}
			return true;
		}

		if ( !$syntransHaveDuplicates ) {
			$this->output( "Congratulations! No syntrans have the duplicate expressions\n" );
			if ( $this->duplicateFound ) {
				$this->removeDuplicateSyntrans();
			}
		}

	}

	function removeDuplicateSyntrans( $dc = null ) {
		$this->output( "\nStarting remove duplicate syntrans function...\n" );
		// check if there are duplicates greater than two
		$this->output( "Finding duplicates\n" );
		$duplicates = $this->getDuplicateSyntrans();

		$haveDuplicates = 0;
		$syntransHaveDuplicates = 0;
		$sid = array();
		if ( $duplicates ) {
			$haveDuplicates = 1;
			foreach ( $duplicates as $rows ) {
				$syntrans = $this->getDuplicateSyntransSyntransToUpdate( $rows['expression_id'], $rows['defined_meaning_id'] );

				if ( $syntrans ) {
					$syntransHaveDuplicates = 1;
					$this->output( "processing: original is {$syntrans[0]}; duplicate is {$syntrans[1]}\n");

					$sid[] = $syntrans[0];
						// correct the duplication
						$this->correctSyntransDuplication( $syntrans );
					if ( !$this->test ) {
						if ( is_null( $dc ) ) {
							$dc = wdGetDataSetContext();
						}

						$dbr = wfGetDB( DB_SLAVE );

						$queryResult = $dbr->delete(
							"{$dc}_syntrans",
							array(
								'remove_transaction_id' => null,
								'syntrans_sid' => $syntrans[1]
							),
							__METHOD__
						);
					}

				}

			}

			if ( $duplicates ) {
				$totalSids = count( $duplicates );
				$this->output( "There are a total of {$totalSids} corrected\n");
			}

			if ( $this->textAttribute ) {
				$totalSids = count( $this->textAttribute );
				$this->output( "There are a total of {$totalSids} text attributes corrected\n");
			}

			if ( $this->optionAttribute ) {
				$totalSids = count( $this->optionAttribute );
				$this->output( "There are a total of {$totalSids} option attributes corrected\n");
			}

			if ( $this->urlAttribute ) {
				$totalSids = count( $this->urlAttribute );
				$this->output( "There are a total of {$totalSids} url attributes corrected\n");
			}

			if ( $this->tcAttribute ) {
				$totalSids = count( $this->urlAttribute );
				$this->output( "There are a total of {$totalSids} translation attributes corrected\n");
			}

			if ( $this->relations1Attribute ) {
				$totalSids = count( $this->relations1Attribute );
				$this->output( "There are a total of {$totalSids} relations 1 attributes corrected\n");
			}

			if ( $this->relations2Attribute ) {
				$totalSids = count( $this->relations2Attribute );
				$this->output( "There are a total of {$totalSids} relations 2 attributes corrected\n");
			}

			if ( !$duplicates ) {
				$this->output( "Congratulations! No duplicates found\n" );
				return true;
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

	protected function correctSyntransDuplication( $syntrans, $dc = null ) {
		// find attributes with $syntrans[1] and replace them with $syntrans[0]
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond = null;

		// options syntrans
		$this->output( " checking options attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_option_attribute_values",
			array(
				'object_id',
			),
			array(
				'remove_transaction_id' => null,
				'object_id' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->optionAttribute = array();
		foreach ( $queryResult as $oav ) {
			$this->optionAttribute[] = $oav->object_id;
			echo "  oId:{$oav->object_id}\n";
		}

		if ( $this->optionAttribute ) {
			$this->output( " update {$dc}_option_attribute_values object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_option_attribute_values",
					array(
						'object_id' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'object_id' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

		// text syntrans
		$this->output( " checking text attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_text_attribute_values",
			array(
				'object_id',
			),
			array(
				'remove_transaction_id' => null,
				'object_id' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->textAttribute = array();
		foreach ( $queryResult as $tav ) {
			$this->textAttribute[] = $tav->object_id;
			echo "  oId:{$tav->object_id}\n";
		}

		if ( $this->textAttribute ) {
			$this->output( " update {$dc}_text_attribute_values object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_text_attribute_values",
					array(
						'object_id' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'object_id' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

		// url syntrans
		$this->output( " checking url attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_url_attribute_values",
			array(
				'object_id',
			),
			array(
				'remove_transaction_id' => null,
				'object_id' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->urlAttribute = array();
		foreach ( $queryResult as $uav ) {
			$this->urlAttribute[] = $uav->object_id;
			echo "  oId:{$uav->object_id}\n";
		}

		if ( $this->urlAttribute ) {
			$this->output( " update {$dc}_url_attribute_values object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_url_attribute_values",
					array(
						'object_id' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'object_id' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

		// translated content syntrans
		$this->output( " checking translation attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_translated_content_attribute_values",
			array(
				'object_id',
			),
			array(
				'remove_transaction_id' => null,
				'object_id' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->tcAttribute = array();
		foreach ( $queryResult as $tc ) {
			$this->tcAttribute[] = $tc->object_id;
			echo "  oId:{$tc->object_id}\n";
		}

		if ( $this->tcAttribute ) {
			$this->output( " update {$dc}_translated_content_attribute_values object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_translated_content_attribute_values",
					array(
						'object_id' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'object_id' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

		// relations 1 syntrans
		$this->output( " checking relations 1 attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_meaning_relations",
			array(
				'meaning1_mid',
			),
			array(
				'remove_transaction_id' => null,
				'meaning1_mid' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->relations1Attribute = array();
		foreach ( $queryResult as $mra ) {
			$this->relations1Attribute[] = $mra->object_id;
			echo "  oId:{$mra->object_id}\n";
		}

		if ( $this->relations1Attribute ) {
			$this->output( " update {$dc}_meaning_relations object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_meaning_relations",
					array(
						'meaning1_mid' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'meaning1_mid' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

		// relations 2 syntrans
		$this->output( " checking relations 2 attributes ...\n");
		$queryResult = $dbr->select(
			"{$dc}_meaning_relations",
			array(
				'meaning2_mid',
			),
			array(
				'remove_transaction_id' => null,
				'meaning2_mid' => $syntrans[1]
			),
			__METHOD__,
			$cond
		);

		$this->relations2Attribute = array();
		foreach ( $queryResult as $mrb ) {
			$this->relations2Attribute[] = $mrb->object_id;
			echo "  oId:{$mrb->object_id}\n";
		}

		if ( $this->relations2Attribute ) {
			$this->output( " update {$dc}_meaning_relations object from {$syntrans[0]} to {$syntrans[1]}\n");
			if ( !$this->test ) {
				// remove the duplication by update
				$queryResult = $dbr->update(
					"{$dc}_meaning_relations",
					array(
						'meaning2_mid' => $syntrans[0],
					),
					array(
						'remove_transaction_id' => null,
						'meaning2_mid' => $syntrans[1]
					),
					__METHOD__,
					$cond
				);
			}
		}

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

	protected function getDuplicateSyntransSyntransToUpdate( $expressionId, $definedMeaningId, $dc = null ) {
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
				'defined_meaning_id' => $definedMeaningId,
				'expression_id' => $expressionId
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

/*
SELECT defined_meaning_id, expression_id, count(*) as count
FROM `uw_syntrans` 
WHERE remove_transaction_id is null
group by defined_meaning_id, expression_id
order by count(*) desc
*/
	protected function getDuplicateSyntrans( $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond['ORDER BY'] = 'count(*) DESC';
		$cond['GROUP BY'] = array(
			'defined_meaning_id',
			'expression_id'
		);

		$queryResult = $dbr->select(
			"{$dc}_syntrans",
			array(
				'defined_meaning_id',
				'expression_id',
				'number' => 'count(*)'
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
					'defined_meaning_id' => $dup->defined_meaning_id,
					'expression_id' => $dup->expression_id
				);
			}
		}

		if ( $duplicates ) {
			return $duplicates;
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
