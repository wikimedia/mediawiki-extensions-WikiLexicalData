<?php

require_once( 'Attribute.php' );
require_once( 'Record.php' );
require_once( 'RecordSet.php' );
require_once( 'Wikidata.php' );

/**
 * Transaction.php
 *
 * Manage internal transactions (NOT mysql transactions... confuzzeled yet?)
 *
 * To use:
 *
 * startNewTransaction($userId, $userIP, $comment, $dc)
 * then do a getUpdateTransactionId() to find the id you need for
 * add_transaction_id.
 *
 * Since this is not a mysql transaction, I don't THINK you need
 * to close it. If you do, it wasn't documented.
 *
 * There's also something to do with restrictions. This is the only
 * documentation you get so far. You're on your own now :-P
 * (Please document anything else that's really needed for anything)
 */


interface QueryTransactionInformation {
	public function getRestriction( Table $table );
	public function getTables();
	public function versioningAttributes();
	public function versioningFields( $tableName );
	public function versioningOrderBy();
	public function versioningGroupBy( Table $table );
	public function setVersioningAttributes( Record $record, $row );
}

class DefaultQueryTransactionInformation implements QueryTransactionInformation {
	public function getRestriction( Table $table ) {
		return "1";
	}

	public function getTables() {
		return array();
	}

	public function versioningAttributes() {
		return array();
	}

	public function versioningFields( $tableName ) {
		return array();
	}

	public function versioningOrderBy() {
		return array();
	}

	public function versioningGroupBy( Table $table ) {
		return array();
	}

	public function setVersioningAttributes( Record $record, $row ) {
	}

	public function __toString() {
		return "QueryTransactionInformation (...)";
	}
}

class QueryLatestTransactionInformation extends DefaultQueryTransactionInformation {
	public function getRestriction( Table $table ) {
		return getLatestTransactionRestriction( $table->getIdentifier() );
	}

	public function setVersioningAttributes( Record $record, $row ) {
	}
}

class QueryHistoryTransactionInformation extends DefaultQueryTransactionInformation {
	public function versioningAttributes() {

		$o = OmegaWikiAttributes::getInstance();

		return array( $o->recordLifeSpan );
	}

	public function versioningFields( $tableName ) {
		return array( $tableName . '.add_transaction_id', $tableName . '.remove_transaction_id', $tableName . '.remove_transaction_id IS NULL AS is_live' );
	}

	public function versioningOrderBy() {
		return array( 'is_live DESC', 'add_transaction_id DESC' );
	}

	public function setVersioningAttributes( Record $record, $row ) {

		$o = OmegaWikiAttributes::getInstance();

		$record->recordLifeSpan = getRecordLifeSpanTuple( $row['add_transaction_id'], $row['remove_transaction_id'] );
	}
}

class QueryAtTransactionInformation extends DefaultQueryTransactionInformation {
	protected $transactionId;
	protected $addAttributes;

	public function __construct( $transactionId, $addAttributes ) {
		$this->transactionId = $transactionId;
		$this->addAttributes = $addAttributes;
	}

	public function getRestriction( Table $table ) {
		return getAtTransactionRestriction( $table->getIdentifier(), $this->transactionId );
	}

	public function versioningAttributes() {

		$o = OmegaWikiAttributes::getInstance();

		if ( $this->addAttributes )
			return array( $o->recordLifeSpan );
		else
			return array();
	}

	public function versioningFields( $tableName ) {
		return array( $tableName . '.add_transaction_id', $tableName . '.remove_transaction_id', $tableName . '.remove_transaction_id IS NULL AS is_live' );
	}

	public function setVersioningAttributes( Record $record, $row ) {

		$o = OmegaWikiAttributes::getInstance();

		if ( $this->addAttributes )
			$record->recordLifeSpan = getRecordLifeSpanTuple( $row['add_transaction_id'], $row['remove_transaction_id'] );
	}
}

class QueryUpdateTransactionInformation extends DefaultQueryTransactionInformation {
	protected $transactionId;

	public function __construct( $transactionId ) {
		$this->transactionId = $transactionId;
	}

	public function getRestriction( Table $table ) {
		return
			" " . $table->getIdentifier() . ".add_transaction_id =" . $this->transactionId .
			" OR " . $table->getIdentifier() . ".removeTransactionId =" . $this->transactionId;
	}

//	public function versioningAttributes() {
//		global
//			$recordLifeSpanAttribute;
//
//		return array();
//	}

//	public function versioningFields($tableName) {
//		return array($tableName . '.add_transaction_id', $tableName . '.remove_transaction_id', $tableName . '.remove_transaction_id IS NULL AS is_live');
//	}

//	public function setVersioningAttributes($record, $row) {
//		global
//			$recordLifeSpanAttribute;
//
//		$record->setAttributeValue($recordLifeSpanAttribute, getRecordLifeSpanTuple($row['add_transaction_id'], $row['remove_transaction_id']));
//	}
}


global
	$updateTransactionId;

function startNewTransaction( $userID, $userIP, $comment, $dc = null ) {

	global
		$updateTransactionId;

	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}

	$dbw = wfGetDB( DB_MASTER );
	$timestamp = wfTimestampNow();


	// do not store IP for logged in users
	if ( $userID > 0 ) {
		$userIP = "";
	}

	$dbw->insert(
		"{$dc}_transactions",
		array( 'user_id' => $userID,
			'user_ip' => $userIP,
			'timestamp' => $timestamp,
			'comment' => $comment
		), __METHOD__
	);
	$updateTransactionId = $dbw->insertId();
	// return is not really needed, as the global variable is set,
	// but this solves the case where $trans = startNewTransaction(...) would be used
	return $updateTransactionId;
}

function getUpdateTransactionId() {
	global $updateTransactionId;

	if ( ! isset ( $updateTransactionId ) ) {
		// normally startNewTransaction is invoqued before this function
		// but actually we can call it from here directly, it should work the same
		global $wgUser;
		startNewTransaction( $wgUser->getID(), wfGetIP(), '' );
	}

	return $updateTransactionId;
}

function getLatestTransactionId() {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	// false if not found
	$transactionId = $dbr->selectField(
		"{$dc}_transactions",
		'MAX(transaction_id)',
		'',
		__METHOD__
	);

	if ( $transactionId ) {
		return $transactionId;
	}
	return 0;
}

function getLatestTransactionRestriction( $table ) {
	return ' ' . $table . '.remove_transaction_id IS NULL ';
}

function getAtTransactionRestriction( $table, $transactionId ) {
	return ' ' . $table . '.add_transaction_id <= ' . $transactionId . ' AND (' .
				$table . '.remove_transaction_id > ' . $transactionId . ' OR ' . $table . '.remove_transaction_id IS NULL) ';
}

function getViewTransactionRestriction( $table ) {
	global
		$wgRequest;

	$action = $wgRequest->getText( 'action' );

	if ( $action == 'edit' ) {
		return getLatestTransactionRestriction( $table );
	} elseif ( $action == 'history' ) {
		return '1';
	} else {
		return getLatestTransactionRestriction( $table );
	}
}

function getOperationSelectColumn( $table, $transactionId ) {
	return " IF($table.add_transaction_id=$transactionId, 'Added', 'Removed') AS operation ";
}

function getInTransactionRestriction( $table, $transactionId ) {
	return " ($table.add_transaction_id=$transactionId OR $table.remove_transaction_id=$transactionId) ";
}

function getUserName( $userId ) {
	$dbr = wfGetDB( DB_SLAVE );
	$userName = $dbr->selectField(
		'user',
		'user_name',
		array(
			'user_id' => $userId
		), __METHOD__
	);

	if ( $userName ) {
		return $userName;
	}
	return '';
}

function getUserLabel( $userId, $userIP ) {
	if ( $userId > 0 ) {
		return getUserName( $userId );
	} elseif ( $userIP != "" ) {
		return $userIP;
	} else {
		return "Unknown";
	}
}

function expandUserIDsInRecordSet( RecordSet $recordSet, Attribute $userID, Attribute $userIP ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$record->setAttributeValue(
			$userIDAttribute,
			getUserLabel(
				$record->$userIDAttribute,
				$record->$userIP
			)
		);
	}
}

function expandTransactionIdsInRecordSet( RecordSet $recordSet ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$record->transaction = getTransactionRecord( $record->transactionId );
	}
}

function getTransactionRecord( $transactionId ) {

	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$result = new ArrayRecord( $o->transactionStructure );
	$result->transactionId = $transactionId;

	if ( $transactionId > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$queryResult = $dbr->query( "SELECT user_id, user_ip, timestamp, comment FROM {$dc}_transactions WHERE transaction_id=$transactionId" );

		if ( $transaction = $dbr->fetchObject( $queryResult ) ) {
			$result->user = getUserLabel( $transaction->user_id, $transaction->user_ip );
			if ( $result->user == null ) $result->user = "userId " . $transaction->user_id . " not found" ;
			$result->timestamp = $transaction->timestamp;
			$result->summary = $transaction->comment;
		}
	}
	else {
		if ( $transactionId != null )
			$result->user = "Unknown";
		else
			$result->user = "";

		$result->timestamp = "";
		$result->summary = "";
	}

	return $result;
}

function getRecordLifeSpanTuple( $addTransactionId, $removeTransactionId ) {

	$o = OmegaWikiAttributes::getInstance();

	$result = new ArrayRecord( $o->recordLifeSpanStructure );
	$result->addTransaction = getTransactionRecord( $addTransactionId );
	$result->removeTransaction = getTransactionRecord( $removeTransactionId );

	return $result;
}

function getTransactionLabel( $transactionId ) {

	$o = OmegaWikiAttributes::getInstance();

	if ( $transactionId > 0 ) {
		$record = getTransactionRecord( $transactionId );

		$label =
			timestampAsText( $record->timestamp ) . ', ' .
			$record->user;

		$summary = $record->summary;

		if ( $summary != "" )
			$label .= ', ' . $summary;

		return $label;
	}
	else
		return "";
}

class Transactions {

	/**
	 * Checks the latest transaction id from syntrans (added transactions).
	 *
	 * Other transaction ids were excluded because of the slow speed to generate them.
	 * (any search for transaction ids which are not present will search for all
	 * the rows in a column which causes the long query time).
	 * Syntrans seems to cover most of the changes. This function will change
	 * when a new table is in place to cover this. Doing so will make the function
	 * faster (since the latest ids are already set) and accurate ( since it would
	 * not be limited to added syntrans only ).
	 *
	 * @param $languageId integer
	 * @param $dc string
	 *
	 * @return $transaction_id integer The latest transaction_id.
	 * else @return -1 If the language_id is non numeric or no transaction_id was found
	 * else @return -2 if a there are any current jobs pending, this function is skipped
	 */
	public static function getLanguageIdLatestTransactionId( $languageId, $options = array(), $dc = null ) {

		// If non numeric, skip this function and return -1
		if ( !is_numeric( $languageId ) ) {
			return -1;
		}

		$dbr = wfGetDB( DB_SLAVE );

		// If jobs exist, skip this function and return -2
		// unless if it is the same job that requires a transaction_id
		$jobExists = $dbr->selectField(
			"job",
			'job_id',
			null, __METHOD__,
			array(
				'LIMIT' => 1
			)
		);

		if ( !isset( $options['is_the_job'] ) ) {
			$options['is_the_job'] = false;
		}

		if ( $jobExists && !$options['is_the_job'] ) {
			return -2;
		}

		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}

		$DefinedMeanings = new DefinedMeanings;
		$Transactions = new Transactions;

		$query = Transactions::getLanguageIdLatestSynonymsAndTranslationsTransactionIdQuery( $languageId );

		$result = $dbr->query( $query );

		$tid = array();
		foreach ( $result as $row ) {
			$tid[] = $row->tid;
		}
		sort( $tid );
		$transaction_id = array_pop( $tid );

		if ( $transaction_id ) {
			return $transaction_id;
		}
		return -1;
	}

	public static function getLanguageIdLatestSynonymsAndTranslationsTransactionIdQuery( $languageId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}

		return "(SELECT " .
		"CASE WHEN synt.add_transaction_id IS NULL THEN -1 ELSE " .
		"synt.add_transaction_id END AS tid FROM " .
		"{$dc}_expression AS exp, " .
		"{$dc}_syntrans AS synt " .
		"WHERE language_id = $languageId " .
		"AND synt.expression_id = exp.expression_id " .
		"AND exp.remove_transaction_id IS NULL " .
		"AND synt.remove_transaction_id IS NULL " .
		"ORDER BY syntrans_sid DESC LIMIT 1)";
	}

}
