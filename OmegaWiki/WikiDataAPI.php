<?php

require_once( 'Transaction.php' );
require_once( 'WikiDataGlobals.php' );

class Expression {
	public $id;
	public $spelling;
	public $languageId;
	public $pageId;
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
		$this->pageId = $this->createPage();
		createInitialRevisionForPage( $this->pageId, 'Created by adding expression' );
	}
	
	function createPage() {
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

function getExpression( $expressionId, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );
	$expressionRecord = $dbr->selectRow(
		"{$dc}_expression",
		array( 'spelling', 'language_id' ),
		array( 'expression_id' => $expressionId ),
		__METHOD__
	);

	if ( $expressionRecord ) {
		$expression = new Expression( $expressionId, $expressionRecord->spelling, $expressionRecord->language_id );
		return $expression;
	} else {
		return null;
	}
}

function newObjectId( $table ) {
	$dc = wdGetDataSetContext();

	$dbw = wfGetDB( DB_MASTER );
	$uuid = $dbw->selectField( '', 'UUID()', '', __METHOD__);
	$dbw->insert(
		"{$dc}_objects",
		array(  '`table`' => $table, '`UUID`' => $uuid ),
		__METHOD__
	);

	return $dbw->insertId();
}

function getTableNameWithObjectId( $objectId ) {
	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_SLAVE );
	$table = $dbr->selectField(
		"{$dc}_objects",
		'table',
		array ( 'object_id' => $objectId ),
		__METHOD__
	);
	// false returned if not found
	if ( $table ) {
		return $table;
	}
	return "";
}

function getExpressionId( $spelling, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$expressionId = $dbr->selectField(
		"{$dc}_expression",
		'expression_id',
		array(
			'spelling' => $spelling,
			'language_id' => $languageId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $expressionId ) {
		return $expressionId;
	}
	return null;
}

function getRemovedExpressionId( $spelling, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$expressionId = $dbr->selectField(
		"{$dc}_expression",
		'expression_id',
		array(
			'spelling' => $spelling,
			'language_id' => $languageId,
			'remove_transaction_id IS NOT NULL'
		), __METHOD__
	);

	if ( $expressionId ) {
		return $expressionId;
	}
	return null;
}

function getExpressionIdFromSyntrans( $syntransId, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );
	$expressionId = $dbr->selectField(
		"{$dc}_syntrans",
		'expression_id',
		array( 'syntrans_sid' => $syntransId ),
		__METHOD__
	);

	if ( $expressionId ) {
		return $expressionId;
	}
	// shouldn't happen
	return null;
}

function createExpressionId( $spelling, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	
	$expressionId = newObjectId( "{$dc}_expression" );
	$updateId = getUpdateTransactionId();
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

function reviveExpression( $expressionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$dbw->update( "{$dc}_expression",
		array( /* SET */
			'remove_transaction_id' => null
		), array( /* WHERE */
			'expression_id' => $expressionId
		), __METHOD__
	);
}

function getPageTitle( $spelling ) {
	return str_replace( ' ', '_', $spelling );
}


function createPage( $namespace, $title ) {
	$dbw = wfGetDB( DB_MASTER );
	$timestamp = $dbw->timestamp();

	$pageId = $dbw->selectField(
		'page',
		'page_id',
		array( 'page_namespace' => $namespace, 'page_title' => $title ),
		__METHOD__
	);

	if ( $pageId ) {
		return $pageId;
	} else {
		$dbw->insert(
			'page',
			array( 'page_namespace' => $namespace,
				'page_title' => $title,
				'page_is_new' => 1,
				'page_touched' => $timestamp
			), __METHOD__
		);
		return $dbw->insertId();
	}
}

function setPageLatestRevision( $pageId, $latestRevision ) {
	$dbw = wfGetDB( DB_MASTER );
	$dbw->update( 'page',
		array( /* SET */
			'page_latest' => $latestRevision
		), array( /* WHERE */
			'page_id' => $pageId
		), __METHOD__
	);
}
function createInitialRevisionForPage( $pageId, $comment ) {
	global $wgUser;
		
	$dbw = wfGetDB( DB_MASTER );
	$userId = $wgUser->getID();
	$userName = $wgUser->getName();
	$timestamp = $dbw->timestamp();
	
	$dbw->insert(
		'revision',
		array( 'rev_page' => $pageId,
			'rev_comment' => $comment,
			'rev_user' => $userId,
			'rev_user_text' => $userName,
			'rev_timestamp' => $timestamp,
			'rev_parent_id' => 0
		), __METHOD__
	);

	$revisionId = $dbw->insertId();
	setPageLatestRevision( $pageId, $revisionId );
	
	return $revisionId;
}

/**
 * returns true if a spelling exists in the
 * expression table of the database
 */
function existSpelling( $spelling ) {
	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_SLAVE );
	$expressionId = $dbr->selectField(
		"{$dc}_expression",
		'expression_id',
		array( 'spelling' => $spelling, 'remove_transaction_id' => null ),
		__METHOD__
	);

	if ( $expressionId ) {
		return true;
	}
	return false;
}

/**
 * returns an expression object of the expression corresponding
 * to a given spelling and language, where the expression has
 * remove_transaction_id is null (i.e. still active)
 * null if not found
 */
function findExpression( $spelling, $languageId ) {
	$expressionId = getExpressionId( $spelling, $languageId );
	if ( ! is_null( $expressionId ) ) {
		return new Expression( $expressionId, $spelling, $languageId );
	}
	return null;
}

/**
 * returns an expression object of the expression corresponding
 * to a given spelling and language, where the expression has
 * remove_transaction_id is not null (i.e. deleted).
 * At the same time, the expression is revived (remove_transaction_id set to null)
 * returns null if not found
 */
function findRemovedExpression( $spelling, $languageId ) {
	$expressionId = getRemovedExpressionId( $spelling, $languageId );
	if ( ! is_null( $expressionId ) ) {
		reviveExpression( $expressionId );
		createPage( NS_EXPRESSION, $spelling );
		return new Expression( $expressionId, $spelling, $languageId );
	}
	return null;
}

function createExpression( $spelling, $languageId ) {
	$expression = new Expression( createExpressionId( $spelling, $languageId ), $spelling, $languageId );
	$expressionTitle = Title::makeTitle( NS_EXPRESSION , $spelling );
	if( !$expressionTitle->exists() ) {
		$expression->createNewInDatabase();
	}
	return $expression;
}

function findOrCreateExpression( $spelling, $languageId ) {
	$expression = findExpression( $spelling, $languageId );
	if ( ! is_null( $expression ) ) {
		return $expression;
	}
	// else
	$expression = findRemovedExpression( $spelling, $languageId );
	if ( ! is_null( $expression ) ) {
		return $expression;
	}
	// else
	return createExpression( $spelling, $languageId );
}

function getSynonymId( $definedMeaningId, $expressionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$syntransId = $dbr->selectField(
		"{$dc}_syntrans",
		'syntrans_sid',
		array( 'defined_meaning_id' => $definedMeaningId,
			'expression_id' => $expressionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $syntransId ) {
		return $syntransId;
	}
	// else
	return 0;
}

function createSynonymOrTranslation( $definedMeaningId, $expressionId, $identicalMeaning = "true" ) {
	
	$dc = wdGetDataSetContext();
	$synonymId = getSynonymId( $definedMeaningId, $expressionId );
	
	if ( $synonymId == 0 ) {
		$synonymId = newObjectId( "{$dc}_syntrans" );
	}
	
	$dbw = wfGetDB( DB_MASTER );
	if ( $identicalMeaning == "true" ) {
		$identicalMeaningInteger = 1;
	} else {
		// if ( $identicalMeaning == "false" )
		$identicalMeaningInteger = 0;
	}
	$transactionId = getUpdateTransactionId();
	$dbw->insert(
		"{$dc}_syntrans",
		array( 'syntrans_sid' => $synonymId,
			'defined_meaning_id' => $definedMeaningId,
			'expression_id' => $expressionId,
			'identical_meaning' => $identicalMeaningInteger,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

/**
 * returns true if the expression is one of the translations of the
 * given definedMeaningId
 */
function expressionIsBoundToDefinedMeaning( $definedMeaningId, $expressionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	
	$syntransId = $dbr->selectField(
		"{$dc}_syntrans",
		'syntrans_sid',
		array( 'defined_meaning_id' => $definedMeaningId,
			'expression_id' => $expressionId,
			'remove_transaction_id' => null
		), __METHOD__
	);
	
	if ( $syntransId ) {
		return true;
	}
	return false;
}

function addSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning ) {
	$expression = findOrCreateExpression( $spelling, $languageId );
	$expression->assureIsBoundToDefinedMeaning( $definedMeaningId, $identicalMeaning );
}

function getRelationId( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$relationId = $dbr->selectField(
		"{$dc}_meaning_relations",
		'relation_id',
		array( 'meaning1_mid' => $definedMeaning1Id,
			'meaning2_mid' => $definedMeaning2Id,
			'relationtype_mid' => $relationTypeId
		), __METHOD__
	);

	if ( $relationId ) {
		return $relationId;
	}
	return 0;
}

function relationExists( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) {
	if ( getRelationId( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) > 0 ) {
		return true;
	}
	return false;
}

function createRelation( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$relationId = getRelationId( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id );
	if ( $relationId == 0 ) {
		$relationId = newObjectId( "{$dc}_meaning_relations" );
	}

	$transactionId = getUpdateTransactionId();
	$dbw->insert(
		"{$dc}_meaning_relations",
		array( 'relation_id' => $relationId,
			'meaning1_mid' => $definedMeaning1Id,
			'meaning2_mid' => $definedMeaning2Id,
			'relationtype_mid' => $relationTypeId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function addRelation( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) {
	if ( !relationExists( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) )
		createRelation( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id );
}

function removeRelation( $definedMeaning1Id, $relationTypeId, $definedMeaning2Id ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_meaning_relations",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'meaning1_mid' => $definedMeaning1Id,
			'meaning2_mid' => $definedMeaning2Id,
			'relationtype_mid' => $relationTypeId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function removeRelationWithId( $relationId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_meaning_relations",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'relation_id' => $relationId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

/**
 * Return defined meaning id's for which ever part of the relation is unspecified.
 * If you specify the relation type and the left hand side of a relation, you'll
 * get an array of defined meaning id's found on the right hand side. And vice versa.
 * If you don't specify a relation type dmid but do give either a right hand side or
 * left hand side, you'll get all relations that exist in which the dm you did specify
 * is involved. 
 *
 * @param unknown_type $relationTypeId dmid of the relationtype, optional.
 * @param unknown_type $lhs dmid of the left hand side, optional.
 * @param unknown_type $dmId dmid of the right hand side, optional.
 * @param unknown_type $dc the dataset, optional
 */
function getRelationDefinedMeanings( $relationTypeId = null, $lhs = null, $rhs = null, $dc = null ) {
	$dc = wdGetDataSetContext( $dc );
	$dbr = wfGetDB( DB_SLAVE );

	$result = array();
	$queryResult = array();

	if ( $relationTypeId == null ) {
		if ( $lhs == null ) {
			if ( $rhs == null ) {
				return $result;
			}
			$queryResult = $dbr->select(
				array( 'rel' => "{$dc}_meaning_relations", 'dm' => "{$dc}_defined_meaning" ),
				'rel.relationtype_mid',
				array( /* WHERE */
					'rel.meaning2_mid' => $rhs,
					'rel.remove_transaction_id' => null
				), __METHOD__,
				array( 'GROUP BY' => 'rel.relationtype_mid' ),
				array( 'dm' => array( 'INNER JOIN', array(
						'rel.relationtype_mid = dm.defined_meaning_id',
						'dm.remove_transaction_id' => null
				)))
			);
		}
		elseif ( $rhs == null ) {
			$queryResult = $dbr->select(
				array( 'rel' => "{$dc}_meaning_relations", 'dm' => "{$dc}_defined_meaning" ),
				'rel.relationtype_mid',
				array( /* WHERE */
					'rel.meaning1_mid' => $lhs,
					'rel.remove_transaction_id' => null
				), __METHOD__,
				array( 'GROUP BY' => 'rel.relationtype_mid' ),
				array( 'dm' => array( 'INNER JOIN', array(
						'rel.relationtype_mid = dm.defined_meaning_id',
						'dm.remove_transaction_id' => null
				)))
			);
		}
		else {
			$queryResult = $dbr->select(
				array( 'rel' => "{$dc}_meaning_relations", 'dm' => "{$dc}_defined_meaning" ),
				'rel.relationtype_mid',
				array( /* WHERE */
					'rel.meaning1_mid' => $lhs,
					'rel.meaning2_mid' => $rhs,
					'rel.remove_transaction_id' => null
				), __METHOD__,
				array( ), /* options */
				array( 'dm' => array( 'INNER JOIN', array(
						'rel.relationtype_mid = dm.defined_meaning_id',
						'dm.remove_transaction_id' => null
				)))
			);
		}
	}
	elseif ( $lhs == null ) {
		if ( $rhs == null ) {
			return $result;
		}
		$queryResult = $dbr->select(
			array( 'rel' => "{$dc}_meaning_relations", 'dm' => "{$dc}_defined_meaning" ),
			'rel.meaning1_mid',
			array( /* WHERE */
				'rel.meaning2_mid' => $rhs,
				'rel.relationtype_mid' => $relationTypeId,
				'rel.remove_transaction_id' => null
			), __METHOD__,
			array( ), /* options */
			array( 'dm' => array( 'INNER JOIN', array(
					'rel.meaning1_mid = dm.defined_meaning_id',
					'dm.remove_transaction_id' => null
			)))
		);
	}
	else {
		$queryResult = $dbr->select(
			array( 'rel' => "{$dc}_meaning_relations", 'dm' => "{$dc}_defined_meaning" ),
			'rel.meaning2_mid',
			array( /* WHERE */
				'rel.meaning1_mid' => $lhs,
				'rel.relationtype_mid' => $relationTypeId,
				'rel.remove_transaction_id' => null
			), __METHOD__,
			array( ), /* options */
			array( 'dm' => array( 'INNER JOIN', array(
					'rel.meaning2_mid = dm.defined_meaning_id',
					'dm.remove_transaction_id' => null
			)))
		);
	}

	foreach ( $queryResult as $row ) {
		$result[] = $row[0];
	}

	return $result;
}

function addClassAttribute( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType ) {
	if ( !classAttributeExists( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType ) ) {
		createClassAttribute( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType );
	}
}

function getClassAttributeId( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$classAttributeId = $dbr->selectField(
		"{$dc}_class_attributes",
		'object_id',
		array( 'class_mid' => $classMeaningId,
			'level_mid' => $levelMeaningId,
			'attribute_mid' => $attributeMeaningId,
			'attribute_type' => $attributeType
		), __METHOD__
	);

	if ( $classAttributeId ) {
		return $classAttributeId;
	}
	return 0;
}

function classAttributeExists( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType ) {
	$classAttributeId = getClassAttributeId( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType );
	if ( $classAttributeId > 0 ) {
		return true;
	}
	return false;
}

function createClassAttribute( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$objectId = getClassAttributeId( $classMeaningId, $levelMeaningId, $attributeMeaningId, $attributeType );
	if ( $objectId == 0 ) {
		$objectId = newObjectId( "{$dc}_class_attributes" );
	}
	$transactionId = getUpdateTransactionId();
	$dbw->insert(
		"{$dc}_class_attributes",
		array( 'object_id' => $objectId,
			'class_mid' => $classMeaningId,
			'level_mid' => $levelMeaningId,
			'attribute_mid' => $attributeMeaningId,
			'attribute_type' => $attributeType,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function removeClassAttributeWithId( $classAttributeId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_class_attributes",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'object_id' => $classAttributeId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function getClassMembershipId( $classMemberId, $classId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$classMembershipId = $dbr->selectField(
		"{$dc}_class_membership",
		'class_membership_id',
		array( 'class_mid' => $classId, 'class_member_mid' => $classMemberId ),
		__METHOD__
	);

	if ( $classMembershipId ) {
		return $classMembershipId;
	}
	return 0;
}

function classMembershipExists( $classMemberId, $classId ) {
	$classMembershipId = getClassMembershipId( $classMemberId, $classId );
	if ( $classMembershipId > 0 ) {
		return true;
	}
	return false;
}

function createClassMembership( $classMemberId, $classId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$classMembershipId = getClassMembershipId( $classMemberId, $classId );
	if ( $classMembershipId == 0 ) {
		$classMembershipId = newObjectId( "{$dc}_class_membership" );
	}
	$transactionId = getUpdateTransactionId();
	$dbw->insert(
		"{$dc}_class_membership",
		array( 'class_membership_id' => $classMembershipId,
			'class_mid' => $classId,
			'class_member_mid' => $classMemberId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function addClassMembership( $classMemberId, $classId ) {
	if ( !classMembershipExists( $classMemberId, $classId ) ) {
		createClassMembership( $classMemberId, $classId );
	}
}

function removeClassMembership( $classMemberId, $classId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_class_membership",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'class_mid' => $classId,
			'class_member_mid' => $classMemberId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function removeClassMembershipWithId( $classMembershipId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_class_membership",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'class_membership_id' => $classMembershipId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

/**
 * removes a syntrans corresponding to a given $definedMeaningId
 * and $expressionId by setting remove_transaction_id to some value.
 */
function removeSynonymOrTranslation( $definedMeaningId, $expressionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_syntrans",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'defined_meaning_id' => $definedMeaningId,
			'expression_id' => $expressionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	// this function is called only by updateSynonymOrTranslation
	// which happens when the identicalMeaning value is modified
	// so the expressionId will still be in use, no need to check if remove needed
}

/**
 * removes a syntrans corresponding to a given $syntransId
 * by setting remove_transaction_id to some value.
 */
function removeSynonymOrTranslationWithId( $syntransId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_syntrans",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'syntrans_sid' => $syntransId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	// check if the corresponding expression is still in use
	$expressionId = getExpressionIdFromSyntrans( $syntransId );
	$result = $dbw->selectField(
		"{$dc}_syntrans",
		'syntrans_sid',
		array(
			'expression_id' => $expressionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $result == false ) {
		// the expression is not in use anymore, remove it
		$dbw->update( "{$dc}_expression",
			array( /* SET */
				'remove_transaction_id' => $transactionId
			), array( /* WHERE */
				'expression_id' => $expressionId
			), __METHOD__
		);
	}
}

function updateSynonymOrTranslation( $definedMeaningId, $expressionId, $identicalMeaning ) {
	removeSynonymOrTranslation( $definedMeaningId, $expressionId );
	createSynonymOrTranslation( $definedMeaningId, $expressionId, $identicalMeaning );
}

function updateSynonymOrTranslationWithId( $syntransId, $identicalMeaningInput ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	// check that $identicalMeaningInput has the correct form
	if ( $identicalMeaningInput != "true" && $identicalMeaningInput != "false" ) {
		// unknown value, no update possible
		return;
	}

	$syntrans = $dbr->selectRow(
		"{$dc}_syntrans",
		array( 'defined_meaning_id', 'expression_id', 'identical_meaning' ),
		array( 'syntrans_sid' => $syntransId, 'remove_transaction_id' => null ),
		__METHOD__
	);

	if ( $syntrans ) {
		// transform the identical_meaning value into the string form used in the html form
		$identicalMeaningDB = ( $syntrans->identical_meaning == 1 ) ? "true" : "false" ;

		// check if the "identicalMeaning" value of the database is different
		// from the value provided as an input in the html form.
		if ( $identicalMeaningInput != $identicalMeaningDB ) {
			updateSynonymOrTranslation( $syntrans->defined_meaning_id, $syntrans->expression_id, $identicalMeaningInput );
		}
	}
}

function updateDefinedMeaningDefinition( $definedMeaningId, $languageId, $text ) {
	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	
	if ( $definitionId != 0 ) {
		updateTranslatedText( $definitionId, $languageId, $text );
	}
}

function updateOrAddDefinedMeaningDefinition( $definedMeaningId, $languageId, $text ) {
	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	
	if ( $definitionId != 0 ) {
		updateTranslatedText( $definitionId, $languageId, $text );
	} else {
		addDefinedMeaningDefiningDefinition( $definedMeaningId, $languageId, $text );
	}
}

function updateTranslatedText( $setId, $languageId, $text ) {
	removeTranslatedText( $setId, $languageId );
	addTranslatedText( $setId, $languageId, $text );
}
 
function createText( $text ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$dbw->insert( "{$dc}_text",
		array( 'text_text' => $text ),
		__METHOD__
	);
	return $dbw->insertId();
}

function createTranslatedContent( $translatedContentId, $languageId, $textId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	$transactionId = getUpdateTransactionId();
	$dbw->insert( "{$dc}_translated_content",
		array(
			'translated_content_id' => $translatedContentId,
			'language_id' => $languageId,
			'text_id' => $textId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
	return $dbw->insertId();
}

function translatedTextExists( $textId, $languageId ) {
	if ( is_null( $textId ) ) {
		throw new Exception( "translatedTextExists - textId is null" );
	}
	if ( is_null( $languageId ) ) {
		throw new Exception( "translatedTextExists - languageId is null" );
	}
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$translatedContentId = $dbr->selectField(
		"{$dc}_translated_content",
		'translated_content_id',
		array(
			'translated_content_id' => $textId,
			'language_id' => $languageId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $translatedContentId ) {
		return true;
	}
	return false;
}

function addTranslatedText( $translatedContentId, $languageId, $text ) {
	$textId = createText( $text );
	createTranslatedContent( $translatedContentId, $languageId, $textId );
}

function addTranslatedTextIfNotPresent( $translatedContentId, $languageId, $text ) {
	if ( !translatedTextExists( $translatedContentId, $languageId ) ) {
		addTranslatedText( $translatedContentId, $languageId, $text );
	}
}

function getDefinedMeaningDefinitionId( $definedMeaningId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$meaningTextTcid = $dbr->selectField(
		"{$dc}_defined_meaning",
		'meaning_text_tcid',
		array(
			'defined_meaning_id' => $definedMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	return $meaningTextTcid;
}

function updateDefinedMeaningDefinitionId( $definedMeaningId, $definitionId ) {
	$dbw = wfGetDB( DB_MASTER );
	$dc = wdGetDataSetContext();
	$dbw->update( "{$dc}_defined_meaning",
		array( /* SET */
			'meaning_text_tcid' => $definitionId
		), array( /* WHERE */
			'defined_meaning_id' => $definedMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function newTranslatedContentId() {
	$dc = wdGetDataSetContext();
	return newObjectId( "{$dc}_translated_content" );
}

function addDefinedMeaningDefiningDefinition( $definedMeaningId, $languageId, $text ) {
	$definitionId = newTranslatedContentId();
	addTranslatedText( $definitionId, $languageId, $text );
	updateDefinedMeaningDefinitionId( $definedMeaningId, $definitionId );
}

function addDefinedMeaningDefinition( $definedMeaningId, $languageId, $text ) {
	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	
	if ( $definitionId == 0 ) {
		addDefinedMeaningDefiningDefinition( $definedMeaningId, $languageId, $text );
	} else {
		addTranslatedTextIfNotPresent( $definitionId, $languageId, $text );
	}
}

function createDefinedMeaningAlternativeDefinition( $definedMeaningId, $translatedContentId, $sourceMeaningId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->insert( "{$dc}_alt_meaningtexts",
		array(
			'meaning_mid' => $definedMeaningId,
			'meaning_text_tcid' => $translatedContentId,
			'source_id' => $sourceMeaningId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function addDefinedMeaningAlternativeDefinition( $definedMeaningId, $languageId, $text, $sourceMeaningId ) {
	$translatedContentId = newTranslatedContentId();
	
	createDefinedMeaningAlternativeDefinition( $definedMeaningId, $translatedContentId, $sourceMeaningId );
	addTranslatedText( $translatedContentId, $languageId, $text );
}

function removeTranslatedText( $translatedContentId, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_translated_content",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'translated_content_id' => $translatedContentId,
			'language_id' => $languageId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function removeTranslatedTexts( $translatedContentId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_translated_content",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'translated_content_id' => $translatedContentId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function removeDefinedMeaningAlternativeDefinition( $definedMeaningId, $definitionId ) {
	// Dilemma: 
	// Should we also remove the translated texts when removing an
	// alternative definition? There are pros and cons. For
	// now it is easier to not remove them so they can be rolled
	// back easier.      
//	removeTranslatedTexts($definitionId);

	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_alt_meaningtexts",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'meaning_text_tcid' => $definitionId,
			'meaning_mid' => $definedMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function removeDefinedMeaningDefinition( $definedMeaningId, $languageId ) {
	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	
	if ( $definitionId != 0 ) {
		removeTranslatedText( $definitionId, $languageId );
	}
}

function definedMeaningInCollection( $definedMeaningId, $collectionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$collectionId = $dbr->selectField(
		"{$dc}_collection_contents",
		'collection_id',
		array(
			'collection_id' => $collectionId,
			'member_mid' => $definedMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);
	if ( $collectionId ) {
		return true;
	}
	return false;
}

function addDefinedMeaningToCollection( $definedMeaningId, $collectionId, $internalId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->insert( "{$dc}_collection_contents",
		array(
			'collection_id' => $collectionId,
			'member_mid' => $definedMeaningId,
			'internal_member_id' => $internalId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function addDefinedMeaningToCollectionIfNotPresent( $definedMeaningId, $collectionId, $internalId ) {
	if ( !definedMeaningInCollection( $definedMeaningId, $collectionId ) ) {
		addDefinedMeaningToCollection( $definedMeaningId, $collectionId, $internalId );
	}
}

function getDefinedMeaningFromCollection( $collectionId, $internalMemberId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$memberMid = $dbr->selectField(
		"{$dc}_collection_contents",
		'member_mid',
		array(
			'collection_id' => $collectionId,
			'internal_member_id' => $internalMemberId,
			'remove_transaction_id' => null
		), __METHOD__
	);
	// null if not found
	return $memberMid;
}

function removeDefinedMeaningFromCollection( $definedMeaningId, $collectionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_collection_contents",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'collection_id' => $collectionId,
			'member_mid' => $definedMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function updateDefinedMeaningInCollection( $definedMeaningId, $collectionId, $internalId ) {
	removeDefinedMeaningFromCollection( $definedMeaningId, $collectionId );
	addDefinedMeaningToCollection( $definedMeaningId, $collectionId, $internalId );
}

function bootstrapCollection( $collection, $languageId, $collectionType ) {
	$expression = findOrCreateExpression( $collection, $languageId );
	$definedMeaningId = addDefinedMeaning( $expression->id );
	$expression->assureIsBoundToDefinedMeaning( $definedMeaningId, "true" );
	addDefinedMeaningDefinition( $definedMeaningId, $languageId, $collection );
	return addCollection( $definedMeaningId, $collectionType );
}

function getCollectionMeaningId( $collectionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$collectionMid = $dbr->selectField(
		"{$dc}_collection",
		'collection_mid',
		array(
			'collection_id' => $collectionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	return $collectionMid;
}

function getCollectionId( $collectionMeaningId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$collectionId = $dbr->selectField(
		"{$dc}_collection",
		'collection_id',
		array(
			'collection_mid' => $collectionMeaningId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	return $collectionId;
}

function addCollection( $definedMeaningId, $collectionType ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$collectionId = newObjectId( "{$dc}_collection" );
	$transactionId = getUpdateTransactionId();

	$dbw->insert( "{$dc}_collection",
		array(
			'collection_id' => $collectionId,
			'collection_mid' => $definedMeaningId,
			'collection_type' => $collectionType,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
	return $collectionId;
}

function addDefinedMeaning( $definingExpressionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$definedMeaningId = newObjectId( "{$dc}_defined_meaning" );
	$transactionId = getUpdateTransactionId();

	$dbw->insert( "{$dc}_defined_meaning",
		array(
			'defined_meaning_id' => $definedMeaningId,
			'expression_id' => $definingExpressionId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);

	$expression = getExpression( $definingExpressionId );
	$spelling = $expression->spelling;
	$pageId = createPage( NS_DEFINEDMEANING, getPageTitle( "$spelling ($definedMeaningId)" ) );
	createInitialRevisionForPage( $pageId, 'Created by adding defined meaning' );
	
	return $definedMeaningId;
}

function createNewDefinedMeaning( $definingExpressionId, $languageId, $text ) {
	$definedMeaningId = addDefinedMeaning( $definingExpressionId );
	createSynonymOrTranslation( $definedMeaningId, $definingExpressionId, "true" );
	addDefinedMeaningDefiningDefinition( $definedMeaningId, $languageId, $text );

	return $definedMeaningId;
}

function addTextAttributeValue( $objectId, $textAttributeId, $text ) {
	$dc = wdGetDataSetContext();
	$textValueAttributeId = newObjectId( "{$dc}_text_attribute_values" );
	createTextAttributeValue( $textValueAttributeId, $objectId, $textAttributeId, $text );
	
	return $textValueAttributeId;
}

function createTextAttributeValue( $textValueAttributeId, $objectId, $textAttributeId, $text ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->insert( "{$dc}_text_attribute_values",
		array(
			'value_id' => $textValueAttributeId,
			'object_id' => $objectId,
			'attribute_mid' => $textAttributeId,
			'text' => $text,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function removeTextAttributeValue( $textValueAttributeId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->update( "{$dc}_text_attribute_values",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'value_id' => $textValueAttributeId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function updateTextAttributeValue( $text, $textValueAttributeId ) {
	$textValueAttribute = getTextValueAttribute( $textValueAttributeId );
	removeTextAttributeValue( $textValueAttributeId );
	createTextAttributeValue( $textValueAttributeId, $textValueAttribute->object_id, $textValueAttribute->attribute_mid, $text );
}

function getTextValueAttribute( $textValueAttributeId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$textAttributeValue = $dbr->selectRow(
		"{$dc}_text_attribute_values",
		array( 'object_id', 'attribute_mid', 'text' ),
		array( 'value_id' => $textValueAttributeId, 'remove_transaction_id' => null ),
		__METHOD__
	);

	return $textAttributeValue;
}

function addLinkAttributeValue( $objectId, $linkAttributeId, $url, $label = "" ) {
	$dc = wdGetDataSetContext();
	$linkValueAttributeId = newObjectId( "{$dc}_url_attribute_values" );
	createLinkAttributeValue( $linkValueAttributeId, $objectId, $linkAttributeId, $url, $label );
}

function createLinkAttributeValue( $linkValueAttributeId, $objectId, $linkAttributeId, $url, $label = "" ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();
	$dbw->insert( "{$dc}_url_attribute_values",
		array(
			'value_id' => $linkValueAttributeId,
			'object_id' => $objectId,
			'attribute_mid' => $linkAttributeId,
			'url' => $url,
			'label' => $label,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function removeLinkAttributeValue( $linkValueAttributeId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();

	$dbw->update( "{$dc}_url_attribute_values",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'value_id' => $linkValueAttributeId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function updateLinkAttributeValue( $linkValueAttributeId, $url, $label = "" ) {
	$linkValueAttribute = getLinkValueAttribute( $linkValueAttributeId );
	removeLinkAttributeValue( $linkValueAttributeId );
	createLinkAttributeValue( $linkValueAttributeId, $linkValueAttribute->object_id, $linkValueAttribute->attribute_mid, $url, $label );
}

function getLinkValueAttribute( $linkValueAttributeId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$linkAttribute = $dbr->selectRow(
		"{$dc}_url_attribute_values",
		array( 'object_id', 'attribute_mid', 'url' ),
		array( 'value_id' => $linkValueAttributeId, 'remove_transaction_id' => null ),
		__METHOD__
	);

	return $linkAttribute;
}

function createTranslatedTextAttributeValue( $valueId, $objectId, $attributeId, $translatedContentId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();

	$dbw->insert( "{$dc}_translated_content_attribute_values",
		array(
			'value_id' => $valueId,
			'object_id' => $objectId,
			'attribute_mid' => $attributeId,
			'value_tcid' => $translatedContentId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function addTranslatedTextAttributeValue( $objectId, $attributeId, $languageId, $text ) {
	$dc = wdGetDataSetContext();
	$translatedTextValueAttributeId = newObjectId( "{$dc}_translated_content_attribute_values" );
	$translatedContentId = newTranslatedContentId();
	
	createTranslatedTextAttributeValue( $translatedTextValueAttributeId, $objectId, $attributeId, $translatedContentId );
	addTranslatedText( $translatedContentId, $languageId, $text );
}

function getTranslatedTextAttribute( $valueId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$translatedText = $dbr->selectRow(
		"{$dc}_translated_content_attribute_values",
		array( 'value_id', 'object_id', 'attribute_mid', 'value_tcid' ),
		array( 'value_id' => $valueId, 'remove_transaction_id' => null ),
		__METHOD__
	);

	return $translatedText;
}

function removeTranslatedTextAttributeValue( $valueId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$translatedTextAttribute = getTranslatedTextAttribute( $valueId );
	$transactionId = getUpdateTransactionId();

	// Dilemma: 
	// Should we also remove the translated texts when removing a
	// translated content attribute? There are pros and cons. For
	// now it is easier to not remove them so they can be rolled
	// back easier.      
//	removeTranslatedTexts($translatedTextAttribute->value_tcid);  

	$dbw->update( "{$dc}_translated_content_attribute_values",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'value_id' => $valueId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function optionAttributeValueExists( $objectId, $optionId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$valueId = $dbr->selectField(
		"{$dc}_option_attribute_values",
		'value_id',
		array(
			'object_id' => $objectId,
			'option_id' => $optionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $valueId ) {
		return true;
	}
	return false;
}

function addOptionAttributeValue( $objectId, $optionId ) {
	if ( !optionAttributeValueExists( $objectId, $optionId ) ) {
		createOptionAttributeValue( $objectId, $optionId );
	}
}

function createOptionAttributeValue( $objectId, $optionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$valueId = newObjectId( "{$dc}_option_attribute_values" );
	$transactionId = getUpdateTransactionId();

	$dbw->insert( "{$dc}_option_attribute_values",
		array(
			'value_id' => $valueId,
			'object_id' => $objectId,
			'option_id' => $optionId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function removeOptionAttributeValue( $valueId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$transactionId = getUpdateTransactionId();

	$dbw->update( "{$dc}_option_attribute_values",
		array( /* SET */
			'remove_transaction_id' => $transactionId
		), array( /* WHERE */
			'value_id' => $valueId,
			'remove_transaction_id' => null
		), __METHOD__
	);
}

function optionAttributeOptionExists( $attributeId, $optionMeaningId, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$optionId = $dbr->selectField(
		"{$dc}_option_attribute_options",
		'option_id',
		array(
			'attribute_id' => $attributeId,
			'option_mid' => $optionMeaningId,
			'language_id' => $languageId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $optionId ) {
		return true;
	}
	return false;
}

function addOptionAttributeOption( $attributeId, $optionMeaningId, $languageId ) {
	if ( !optionAttributeOptionExists( $attributeId, $optionMeaningId, $languageId ) ) {
		createOptionAttributeOption( $attributeId, $optionMeaningId, $languageId );
	}
}

function createOptionAttributeOption( $attributeId, $optionMeaningId, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );
	$optionId = newObjectId( "{$dc}_option_attribute_options" );
	$transactionId = getUpdateTransactionId();

	$dbw->insert( "{$dc}_option_attribute_options",
		array(
			'option_id' => $optionId,
			'attribute_id' => $attributeId,
			'option_mid' => $optionMeaningId,
			'language_id' => $languageId,
			'add_transaction_id' => $transactionId
		), __METHOD__
	);
}

function removeOptionAttributeOption( $optionId ) {
	$dc = wdGetDataSetContext();
	$dbw = wfGetDB( DB_MASTER );

	// first check if the option attribute option is still in use
	$valueId = $dbw->selectField( "{$dc}_option_attribute_values",
		'value_id',
		array(
			'option_id' => $optionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $valueId == false ){
		// option not used, can proceed to delete
		$transactionId = getUpdateTransactionId() ;
		$dbw->update( "{$dc}_option_attribute_options",
			array( /* SET */
				'remove_transaction_id' => $transactionId
			), array( /* WHERE */
				'option_id' => $optionId,
				'remove_transaction_id' => null
			), __METHOD__
		);
	} else {
		echo "\nThe option $optionId cannot be deleted because it is still in use!\n" ;
	}
}

/**
 * Returns the definition of a definedMeaning in a given language
 * @param $definedMeaningId
 * @param $languageId
 * @param $dc
 */
function getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$text = $dbr->selectField(
		array(
			'dm' => "{$dc}_defined_meaning",
			'tc' => "{$dc}_translated_content",
			't' => "{$dc}_text"
		),
		'text_text',
		array(
			'dm.defined_meaning_id' => $definedMeaningId,
			'dm.remove_transaction_id' => null,
			'tc.remove_transaction_id' => null,
			'dm.meaning_text_tcid = tc.translated_content_id',
			'tc.language_id' => $languageId,
			't.text_id = tc.text_id'
		), __METHOD__
	);

	if ( $text ) {
		return $text;
	}
	return "";
}

/**
 * Returns the definition of a definedMeaning in any language
 * according to which definition comes up first in the SQL query
 * @param $definedMeaningId
 */
function getDefinedMeaningDefinitionForAnyLanguage( $definedMeaningId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$text = $dbr->selectField(
		array(
			'dm' => "{$dc}_defined_meaning",
			'tc' => "{$dc}_translated_content",
			't' => "{$dc}_text"
		),
		'text_text',
		array(
			'dm.defined_meaning_id' => $definedMeaningId,
			'dm.remove_transaction_id' => null,
			'tc.remove_transaction_id' => null,
			'dm.meaning_text_tcid = tc.translated_content_id',
			't.text_id = tc.text_id'
		), __METHOD__
	);

	if ( $text ) {
		return $text;
	}
	return "";
}

/**
 * Returns the definition of a definedMeaning in the user language, or in English, or in any other
 * according to what is available
 * @param $definedMeaningId
 */
function getDefinedMeaningDefinition( $definedMeaningId ) {
	global $wgLang;
	$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ;
	
	if ( $userLanguageId > 0 ) {
		$result = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $userLanguageId );
	} else {
		$result = "";
	}
	if ( $result == "" ) {
		$result = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, 85 );
		
		if ( $result == "" ) {
			$result = getDefinedMeaningDefinitionForAnyLanguage( $definedMeaningId );
		}
	}
	return $result;
}


/**
* returns one of the possible translations of 
* a given DefinedMeaning ( $definedMeaningId )
* preferably in a given language ( $languageCode )
* or in English otherwise.
* null if not found
*/
function getSpellingForLanguage( $definedMeaningId, $languageCode, $fallbackLanguageCode = 'en', $dc = null ) {
	if ( is_null ( $dc ) ) {
		$dc = wdGetDataSetContext( $dc );
	}
	$dbr = wfGetDB( DB_SLAVE );

	$userLanguageId = getLanguageIdForCode( $languageCode );

	# wfDebug("User language: $userLanguageId\n");
	$fallbackLanguageId = getLanguageIdForCode( $fallbackLanguageCode );

	if ( $userLanguageId ) {
		$spelling = $dbr->selectField(
			array( "{$dc}_syntrans" , "{$dc}_expression" ),
			'spelling',
			array(
				"{$dc}_syntrans.defined_meaning_id" => $definedMeaningId,
				"{$dc}_expression.expression_id = {$dc}_syntrans.expression_id",
				'language_id' => $userLanguageId,
				"{$dc}_expression.remove_transaction_id" => null
			), __METHOD__
		);

		if ( $spelling ) {
			return $spelling;
		}
	}

	// fallback language
	$spelling = $dbr->selectField(
		array( "{$dc}_syntrans" , "{$dc}_expression" ),
		'spelling',
		array(
			"{$dc}_syntrans.defined_meaning_id" => $definedMeaningId,
			"{$dc}_expression.expression_id = {$dc}_syntrans.expression_id",
			'language_id' => $fallbackLanguageId,
			"{$dc}_expression.remove_transaction_id" => null
		), __METHOD__
	);

	if ( $spelling ) {
		return $spelling;
	}

	// final fallback
	$spelling = $dbr->selectField(
		array( "{$dc}_syntrans" , "{$dc}_expression" ),
		'spelling',
		array(
			"{$dc}_syntrans.defined_meaning_id" => $definedMeaningId,
			"{$dc}_expression.expression_id = {$dc}_syntrans.expression_id",
			"{$dc}_expression.remove_transaction_id" => null
		), __METHOD__
	);

	if ( $spelling ) {
		return $spelling;
	}
	return null;
}

/**
 * Returns true if the concept corresponding to the
 * definedMeaningID $objectId is a class
 */
function isClass( $objectId ) {
	global $wgDefaultClassMids;

	if ( in_array( $objectId, $wgDefaultClassMids ) ) {
		// easy, it is a default class
		return true;
	}

	// if not, search in the db
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$collectionId = $dbr->selectField(
		array( 'cont' => "{$dc}_collection_contents", 'col' => "{$dc}_collection" ),
		'col.collection_id',
		array(
			'cont.member_mid' => $objectId,
			'cont.remove_transaction_id' => null
		), __METHOD__,
		array(),
		array( 'col' => array( 'INNER JOIN', array(
			'col.collection_id = cont.collection_id',
			'col.collection_type' => 'CLAS',
			'col.remove_transaction_id' => null
		)))
	);

	if ( $collectionId ) {
		return true;
	}
	return false;
}

function getCollectionContents( $collectionId ) {
	global $wgWikidataDataSet;
	
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	$queryResult = $dbr->query(
		selectLatest(
			array( $wgWikidataDataSet->collectionMemberships->memberMid, $wgWikidataDataSet->collectionMemberships->internalMemberId ),
			array( $wgWikidataDataSet->collectionMemberships ),
			array( equals( $wgWikidataDataSet->collectionMemberships->collectionId, $collectionId ) )
		)
	);
	
	$collectionContents = array();
	
	while ( $collectionEntry = $dbr->fetchObject( $queryResult ) ) {
		$collectionContents[$collectionEntry->internal_member_id] = $collectionEntry->member_mid;
	}
	
	return $collectionContents;
}

/**
 * Returns an array containing the ids of the defined meanings belonging to the collection
 * with the given id. 
 *
 * @param unknown_type $collectionId
 * @param unknown_type $dc
 */
function getCollectionMembers( $collectionId, $dc = null ) {
	$memberMids = array();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$result = $dbr->select(
		"{$dc}_collection_contents",
		'member_mid',
		array(
			'collection_id' => $collectionId,
			'remove_transaction_id' => null
		), __METHOD__
	);

	foreach ( $result as $row ) {
		$memberMids[] = $row->member_mid;
	}

	return $memberMids;
}

function getCollectionMemberId( $collectionId, $sourceIdentifier ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$memberMid = $dbr->selectField(
		"{$dc}_collection_contents",
		'member_mid',
		array(
			'collection_id' => $collectionId,
			'internal_member_id' => $sourceIdentifier,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $memberMid ) {
		return $memberMid;
	}
	return 0;
}

function getAnyDefinedMeaningWithSourceIdentifier( $sourceIdentifier ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$memberMid = $dbr->selectField(
		"{$dc}_collection_contents",
		'member_mid',
		array(
			'internal_member_id' => $sourceIdentifier,
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $memberMid ) {
		return $memberMid;
	}
	return 0;
}

function getExpressionMeaningIds( $spelling, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$queryResult = $dbr->select(
		array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
		'defined_meaning_id',
		array(
			'spelling' => $spelling,
			'exp.remove_transaction_id' => null,
			'synt.remove_transaction_id' => null,
			'exp.expression_id = synt.expression_id'
		), __METHOD__
	);

	$dmlist = array();
	
	foreach ( $queryResult as $synonymRecord ) {
		$dmlist[] = $synonymRecord->defined_meaning_id;
	}

	return $dmlist;
}

function getExpressionMeaningIdsForLanguages( $spelling, $languageIds, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$queryResult = $dbr->select(
		array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
		array( 'defined_meaning_id', 'language_id' ),
		array(
			'spelling' => $spelling,
			'exp.expression_id = synt.expression_id',
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		), __METHOD__
	);

	$dmlist = array();

	foreach( $queryResult as $synonymRecord ) {
		if ( in_array( $synonymRecord->language_id, $languageIds ) ) {
			$dmlist[] = $synonymRecord->defined_meaning_id;
		}
	}
	return $dmlist;
}


/** Write a concept mapping to db
 * supply mapping as a valid
 * array("dataset_prefix"=>defined_meaning_id,...)
 * @returns: assoc array of uuids used for mapping. (typically you can just
 *           discard this, but it is used in copy.php for objects table support
 *	     array values set to -1 were not mapped.
 */

function createConceptMapping( $concepts, $override_transaction = null ) {
	$uuid_map = getUUID( $concepts );
	foreach ( $concepts as $dc => $dm_id ) {
		$collid = getCollectionIdForDC( $dc );
		if ( $uuid_map[$dc] != - 1 ) {
			writeDmToCollection( $dc, $collid, $uuid_map[$dc], $dm_id, $override_transaction );
		}
	}
	return $uuid_map;
}

function getMapping( $dc, $collid, $dm_id ) {
	$dbr = wfGetDB( DB_SLAVE );

	$internalMemberId = $dbr->selectField(
		"{$dc}_collection_contents",
		'internal_member_id',
		array(
			'collection_id' => $collid,
			'member_mid' => $dm_id
		), __METHOD__
	);

	if ( $internalMemberId ) {
		return $internalMemberId;
	}
	return - 1;
}

/** ask db to provide a universally unique id */

function getUUID( $concepts ) {
	$dbr = wfGetDB( DB_SLAVE );

	$uuid_array = array();
 	$uuid = - 1;
    
	foreach ( $concepts as $dc => $dm_id ) {
		$collid = getCollectionIdForDC( $dc );
		$uuid_array[$dc] = getMapping( $dc, $collid, $dm_id );
		if ( ( $uuid == - 1 ) && ( $uuid_array[$dc] != - 1 ) ) {
			$uuid = $uuid_array[$dc];
		}
	}
	
	if ( $uuid == - 1 ) {
		$query = "SELECT uuid() AS id";
		$queryResult = $dbr->query( $query );
		$row = $dbr->fetchObject( $queryResult );
		$uuid = isset( $row->id ) ? $row->id : - 1;
	}
	
	foreach ( $concepts as $dc => $dm_id ) {
		if ( $uuid_array[$dc] == - 1 ) {
			$uuid_array[$dc] = $uuid;
		}
		else {
			$uuid_array[$dc] = - 1;
		}
	}
	return $uuid_array;
}

/** this funtion assumes that there is only a single mapping collection */

function getCollectionIdForDC( $dc ) {
	$dbr = wfGetDB( DB_SLAVE );

	$collectionId = $dbr->selectField(
		"{$dc}_collection",
		'collection_id',
		array(
			'collection_type' => 'MAPP',
			'remove_transaction_id' => null
		), __METHOD__
	);

	if ( $collectionId ) {
		return $collectionId;
	}
	return null;
}

/** Write the dm to the correct collection for a particular dc */

function writeDmToCollection( $dc, $collid, $uuid, $dm_id, $override_transaction = null ) {
	global $wgUser;
	// if(is_null($dc)) {
	//	$dc=wdGetDataSetContext();
	// } 
	$dbw = wfGetDB( DB_MASTER );

	$add_transaction_id = $override_transaction;
	if ( is_null( $add_transaction_id ) ) {
		startNewTransaction( $wgUser->getId(), wfGetIP(), "inserting collection $collid", $dc );
		$add_transaction_id = getUpdateTransactionId();
	}

	$dbw->insert( "{$dc}_collection_contents",
		array(
			'collection_id' => $collid,
			'internal_member_id' => $uuid,
			'member_mid' => $dm_id,
			'add_transaction_id' => $add_transaction_id
		), __METHOD__
	);
}

/**
 * Read a ConceptMapping from the database.
 * map is in the form;
 * array("dataset_prefix"=>defined_meaning_id,...)
 * (possibly to rename $map or $concepts, to remain consistent)
 * note that we are using collection_contents.internal_member_id
 * as our ConceptMap ID.
 *
 * Later Note: This is somewhat redundant with the objects table.
 *
 * see also: createConceptMapping($concepts)
 */
function &readConceptMapping( $concept_id ) {
	$dbr = wfGetDB( DB_SLAVE );
	$sets = wdGetDataSets();
	$map = array();

	foreach ( $sets as $key => $set ) {
		# wfdebug ("$key => $set");
		$dc = $set->getPrefix();
		$collection_id = getCollectionIdForDC( $dc );

		$memberMid = $dbr->selectField(
			"{$dc}_collection_contents",
			'member_mid',
			array(
				'collection_id' => $collection_id,
				'internal_member_id' => $concept_id
			), __METHOD__
		);
		if ( $memberMid ) {
			$map[$dc] = $memberMid;
		}
	}
	return $map;
}

function getConceptId( $dm, $dc ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$collection_id = getCollectionIdForDC( $dc );
	$dbr = wfGetDB( DB_SLAVE );

	$internalMemberId = $dbr->selectField(
		"{$dc}_collection_contents",
		'internal_member_id',
		array(
			'member_mid' => $dm,
			'collection_id' => $collection_id
		), __METHOD__
	);

	if ( $internalMemberId ) {
		return $internalMemberId;
	}
	return null;
}

function &getAssociatedByConcept( $dm, $dc ) {
	$concept_id = getConceptId( $dm, $dc );
	return readConceptMapping( $concept_id );
}

function &getDataSetsAssociatedByConcept( $dm, $dc ) {
	$map = getAssociatedByConcept( $dm, $dc );
	$sets = wdGetDataSets();
	$newSets = array();
	foreach ( $map as $map_dc => $map_dm ) {
		$dataset = $sets[$map_dc];
	#	$dataset->setDefinedMeaningId($map_dm);
		$newSets[$map_dc] = $dataset;
	}
	return $newSets;
}
function &getDefinedMeaningDataAssociatedByConcept( $dm, $dc ) {
	$meanings = array();
	$map = getDataSetsAssociatedByConcept( $dm, $dc );
	$dm_map = getAssociatedByConcept( $dm, $dc );
	foreach ( $map as $map_dc => $map_dataset ) {
		$dmModel = new DefinedMeaningModel( $dm_map[$map_dc], array( "dataset" => $map_dataset ) );
		$meanings[$map_dc] = $dmModel;
	}
	return $meanings;
}

function definingExpressionRow( $definedMeaningId, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$expression = $dbr->selectRow(
		array( 'dm' => "{$dc}_defined_meaning", 'exp' => "{$dc}_expression" ),
		array( 'exp.expression_id', 'spelling', 'language_id' ),
		array(
			'dm.defined_meaning_id' => $definedMeaningId,
			'exp.expression_id = dm.expression_id',
			'dm.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		), __METHOD__
	);

	if ( $expression ) {
		return array( $expression->expression_id, $expression->spelling, $expression->language_id );
	}
	return null;
}

function definingExpression( $definedMeaningId, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$spelling = $dbr->selectField(
		array( 'dm' => "{$dc}_defined_meaning", 'exp' => "{$dc}_expression" ),
		'spelling',
		array(
			'dm.defined_meaning_id' => $definedMeaningId,
			'exp.expression_id = dm.expression_id',
			'dm.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		), __METHOD__
	);

	if ( $spelling ) {
		return $spelling;
	}
	return null;
}

/**
 * Returns one spelling of an expression corresponding to a given DM in a given language
 * @param $definedMeaningId
 * @param $languageId
 */
function definedMeaningExpressionForLanguage( $definedMeaningId, $languageId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$spelling = $dbr->selectField(
		array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
		'spelling',
		array(
			'defined_meaning_id' => $definedMeaningId,
			'exp.expression_id = synt.expression_id',
			'exp.language_id' => $languageId,
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		), __METHOD__
	);

	if ( $spelling ) {
		return $spelling;
	}
	return "";
}

/**
 * Returns one spelling of an expression corresponding to a given DM in any language
 * @param $definedMeaningId
 */
function definedMeaningExpressionForAnyLanguage( $definedMeaningId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$spelling = $dbr->selectField(
		array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
		'spelling',
		array(
			'defined_meaning_id' => $definedMeaningId,
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		), __METHOD__
	);

	if ( $spelling ) {
		return $spelling;
	}
	return "";
}

/**
 * Returns one spelling of an expression corresponding to a given DM
 * - in a given language if it exists
 * - or else in English
 * - or else in any language
 * @param $definedMeaningId
 */
function definedMeaningExpression( $definedMeaningId ) {
	global $wgLang;

	$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ;
	
	list( $definingExpressionId, $definingExpression, $definingExpressionLanguage ) = definingExpressionRow( $definedMeaningId );
	
	if ( $userLanguageId > 0 ) {
		$result = definedMeaningExpressionForLanguage( $definedMeaningId, $userLanguageId );
	} else {
		$result = "";
	}
	if ( $result == "" ) {
		// if no expression exists for the specified language : look for an expression in English
		$result = definedMeaningExpressionForLanguage( $definedMeaningId, 85 );
		
		if ( $result == "" ) {
			$result = definedMeaningExpressionForAnyLanguage( $definedMeaningId );
			
			if ( $result == "" ) {
				$result = $definingExpression;
			}
		}
	}

	return $result;
}

function getTextValue( $textId ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$text = $dbr->selectField(
		"{$dc}_text",
		'text_text',
		array( 'text_id' => $textId ),
		__METHOD__
	);

	if ( $text ) {
		return $text;
	}
	return "";
}

/**
 * returns an array of "Expression" objects
 * that correspond to the same $spelling (max 1 per language)
 */
function getExpressions( $spelling, $dc = null ) {
	if ( is_null( $dc ) ) {
		$dc = wdGetDataSetContext();
	}
	$dbr = wfGetDB( DB_SLAVE );

	$viewInformation = new ViewInformation();
	$langsubset = $viewInformation->getFilterLanguageList();

	// when the remove_transaction_id will be automatically updated,
	// we can get rid of using the syntrans table
	$cond = array(
		'spelling' => $spelling,
		'exp.expression_id = synt.expression_id',
		'exp.remove_transaction_id' => null,
		'synt.remove_transaction_id' => null
	);
	if ( ! empty( $langsubset ) ) {
		$cond['language_id'] = $langsubset;
	}

	$queryResult = $dbr->select(
		array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
		array( 'exp.expression_id', 'spelling', 'language_id' ),
		$cond,
		__METHOD__
	);

	$rv = array();
	foreach ( $queryResult as $exp ) {
		$rv[] = new Expression( $exp->expression_id, $exp->spelling, $exp->language_id );
	}
	return $rv;
}

class ClassAttribute {
	public $attributeId;
	public $levelName;
	public $type;
}

class ClassAttributes {
	protected $classAttributes;
	
	public function __construct( $definedMeaningId ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		global $wgDefaultClassMids, $wgWikidataDataSet;

		$queryResult = $dbr->select(
			array( 'ca' => "{$dc}_class_attributes", 'bdm' => "{$dc}_bootstrapped_defined_meanings" ),
			array( 'DISTINCT ca.attribute_mid', 'ca.attribute_type', 'bdm.name' ),
			array(
				'ca.level_mid = bdm.defined_meaning_id',
				'ca.remove_transaction_id' => null
			), __METHOD__
		);

		$this->classAttributes = array();
		
		foreach ( $queryResult as $row ) {
			$classAttribute = new ClassAttribute();
			$classAttribute->attributeId = $row->attribute_mid;
			$classAttribute->type = $row->attribute_type;
			$classAttribute->levelName = $row->name;
			
			$this->classAttributes[] = $classAttribute;
		}
	}

	public function filterClassAttributesOnLevelAndType( $levelName, $type ) {
		$result = array();
		
		foreach ( $this->classAttributes as $classAttribute ) {
			if ( $classAttribute->levelName == $levelName && $classAttribute->type == $type ) {
				$result[] = $classAttribute->attributeId;
			}
		}
		
		return $result;
	}

	public function filterClassAttributesOnLevel( $levelName ) {
		$result = array();
		
		foreach ( $this->classAttributes as $classAttribute ) {
			if ( $classAttribute->levelName == $levelName ) {
				$result[] = $classAttribute->attributeId;
			}
		}
		
		return $result;
	}
}

