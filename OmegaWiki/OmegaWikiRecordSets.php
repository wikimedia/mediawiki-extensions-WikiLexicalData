<?php

require_once( 'OmegaWikiAttributes.php' );
require_once( 'Record.php' );
require_once( 'RecordSet.php' );
require_once( 'RecordSetQueries.php' );
require_once( 'ViewInformation.php' );
require_once( 'Wikidata.php' );
require_once( 'WikiDataGlobals.php' );


function getSynonymSQLForLanguage( $languageId, array &$definedMeaningIds ) {
	$dc = wdGetDataSetContext();
	
	# Query building
	$frontQuery = "SELECT {$dc}_defined_meaning.defined_meaning_id AS defined_meaning_id, {$dc}_expression.spelling AS label " .
		" FROM {$dc}_defined_meaning, {$dc}_syntrans, {$dc}_expression " .
		" WHERE {$dc}_syntrans.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.remove_transaction_id IS NULL " .
		" AND {$dc}_defined_meaning.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.language_id=" . $languageId .
		" AND {$dc}_expression.expression_id={$dc}_syntrans.expression_id " .
		" AND {$dc}_defined_meaning.defined_meaning_id={$dc}_syntrans.defined_meaning_id " .
		" AND {$dc}_syntrans.identical_meaning=1 " .
		" AND {$dc}_defined_meaning.defined_meaning_id = ";

	# Build atomic queries
	$definedMeaningIdsCopy = $definedMeaningIds;
	foreach ( $definedMeaningIdsCopy as &$value ) {
		$value = $frontQuery . $value;
	}
	unset( $value );
	# Union of the atoms
	return implode( ' UNION ', $definedMeaningIdsCopy );
}

function getSynonymSQLForAnyLanguage( array &$definedMeaningIds ) {
	$dc = wdGetDataSetContext();

	# Query building
	$frontQuery = "SELECT {$dc}_defined_meaning.defined_meaning_id AS defined_meaning_id, {$dc}_expression.spelling AS label " .
		" FROM {$dc}_defined_meaning, {$dc}_syntrans, {$dc}_expression " .
		" WHERE {$dc}_syntrans.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.remove_transaction_id IS NULL " .
		" AND {$dc}_defined_meaning.remove_transaction_id IS NULL" .
		" AND {$dc}_expression.expression_id={$dc}_syntrans.expression_id " .
		" AND {$dc}_defined_meaning.defined_meaning_id={$dc}_syntrans.defined_meaning_id " .
		" AND {$dc}_syntrans.identical_meaning=1 " .
		" AND {$dc}_defined_meaning.defined_meaning_id = ";

	# Build atomic queries
	$definedMeaningIdsCopy = $definedMeaningIds;
	foreach ( $definedMeaningIdsCopy as &$value ) {
		$value = $frontQuery . $value;
	}
	unset( $value );
	# Union of the atoms
	return implode( ' UNION ', $definedMeaningIdsCopy );
}

function getDefiningSQLForLanguage( $languageId, array &$definedMeaningIds ) {
	$dc = wdGetDataSetContext();

	# Query building
	$frontQuery = "SELECT {$dc}_defined_meaning.defined_meaning_id AS defined_meaning_id, {$dc}_expression.spelling AS label " .
		" FROM {$dc}_defined_meaning, {$dc}_syntrans, {$dc}_expression " .
		" WHERE {$dc}_syntrans.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.remove_transaction_id IS NULL " .
		" AND {$dc}_defined_meaning.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.expression_id={$dc}_syntrans.expression_id " .
		" AND {$dc}_defined_meaning.defined_meaning_id={$dc}_syntrans.defined_meaning_id " .
		" AND {$dc}_syntrans.identical_meaning=1 " .
		" AND {$dc}_defined_meaning.expression_id={$dc}_expression.expression_id " .
		" AND {$dc}_expression.language_id=" . $languageId .
		" AND {$dc}_defined_meaning.defined_meaning_id = ";

	# Build atomic queries
	$definedMeaningIdsCopy = $definedMeaningIds;
	foreach ( $definedMeaningIdsCopy as &$value ) {
		$value = $frontQuery . $value;
	}
	unset( $value );
	# Union of the atoms
	return implode( ' UNION ', $definedMeaningIdsCopy );
}


function fetchDefinedMeaningReferenceRecords( $sql, array &$definedMeaningIds, array &$definedMeaningReferenceRecords, $usedAs = '' ) {
	if ( $usedAs == '' ) $usedAs = WLD_DEFINED_MEANING ;
	$dc = wdGetDataSetContext();
	$o = OmegaWikiAttributes::getInstance();

	$foundDefinedMeaningIds = array();

	$dbr = wfGetDB( DB_SLAVE );
	$queryResult = $dbr->query( $sql );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$definedMeaningId = $row->defined_meaning_id;

		$specificStructure = clone $o->definedMeaningReferenceStructure;
		$specificStructure->setStructureType( $usedAs );
		$record = new ArrayRecord( $specificStructure );
		$record->definedMeaningId = $definedMeaningId;
		$record->definedMeaningLabel = $row->label;

		$definedMeaningReferenceRecords[$definedMeaningId] = $record;
		$foundDefinedMeaningIds[] = $definedMeaningId;
	}
	
	$definedMeaningIds = array_diff( $definedMeaningIds, $foundDefinedMeaningIds );
}


function fetchDefinedMeaningDefiningExpressions( array &$definedMeaningIds, array &$definedMeaningReferenceRecords ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );
	
	# Query building
	$frontQuery = "SELECT {$dc}_defined_meaning.defined_meaning_id AS defined_meaning_id, {$dc}_expression.spelling" .
		" FROM {$dc}_defined_meaning, {$dc}_expression " .
		" WHERE {$dc}_defined_meaning.expression_id={$dc}_expression.expression_id " .
		" AND {$dc}_defined_meaning.remove_transaction_id IS NULL " .
		" AND {$dc}_expression.remove_transaction_id IS NULL " .
		" AND {$dc}_defined_meaning.defined_meaning_id = ";

	// copy the definedMeaningIds array to create one query for each DM id
	$definedMeaningQueries = $definedMeaningIds;
	unset( $value );
	foreach ( $definedMeaningQueries as &$value ) {
		$value = $frontQuery . $value;
	}
	unset( $value );
	# Union of the atoms
	$finalQuery = implode( ' UNION ', $definedMeaningQueries );
	
	$queryResult = $dbr->query( $finalQuery );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		if ( isset( $definedMeaningReferenceRecords[$row->defined_meaning_id] ) ) {
			$definedMeaningReferenceRecord = $definedMeaningReferenceRecords[$row->defined_meaning_id];
		} else {
			$definedMeaningReferenceRecord = null;
		}
		if ( $definedMeaningReferenceRecord == null ) {
			$definedMeaningReferenceRecord = new ArrayRecord( $o->definedMeaningReferenceStructure );
			$definedMeaningReferenceRecord->definedMeaningId = $row->defined_meaning_id;
			$definedMeaningReferenceRecord->definedMeaningLabel = $row->spelling;
			$definedMeaningReferenceRecords[$row->defined_meaning_id] = $definedMeaningReferenceRecord;
		}
		
		$definedMeaningReferenceRecord->definedMeaningDefiningExpression = $row->spelling;
	}
}

function getNullDefinedMeaningReferenceRecord() {

	$o = OmegaWikiAttributes::getInstance();
	
	$record = new ArrayRecord( $o->definedMeaningReferenceStructure );
	$record->definedMeaningId = 0;
	$record->definedMeaningLabel = "";
	$record->definedMeaningDefiningExpression = "";
	
	return $record;
}

function getDefinedMeaningReferenceRecords( array $definedMeaningIds, $usedAs ) {
	global $wgLang ;
	$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ;

//	$startTime = microtime(true);

	$result = array();
	$definedMeaningIdsForExpressions = $definedMeaningIds;

	if ( count( $definedMeaningIds ) > 0 ) {
		if ( $userLanguageId > 0 ) {
			fetchDefinedMeaningReferenceRecords(
				getSynonymSQLForLanguage( $userLanguageId, $definedMeaningIds ),
				$definedMeaningIds,
				$result,
				$usedAs
			);
		}

		if ( count( $definedMeaningIds ) > 0 ) {
			fetchDefinedMeaningReferenceRecords(
				getSynonymSQLForLanguage( WLD_ENGLISH_LANG_ID, $definedMeaningIds ),
				$definedMeaningIds,
				$result,
				$usedAs
			);
	
			if ( count( $definedMeaningIds ) > 0 ) {
				fetchDefinedMeaningReferenceRecords(
					getSynonymSQLForAnyLanguage( $definedMeaningIds ),
					$definedMeaningIds,
					$result,
					$usedAs
				);
			}
		}
		
		fetchDefinedMeaningDefiningExpressions( $definedMeaningIdsForExpressions, $result );
		$result[0] = getNullDefinedMeaningReferenceRecord();

	} // if ( count( $definedMeaningIds ) > 0 )

//	$queriesTime = microtime(true) - $startTime;
//	echo "<!-- Defined meaning reference queries: " . $queriesTime . " -->\n";

	return $result;
}

function expandDefinedMeaningReferencesInRecordSet( RecordSet $recordSet, array $definedMeaningAttributes ) {
	$definedMeaningReferenceRecords = array();

	foreach ( $definedMeaningAttributes as $dmatt ) {
		$tmpArray = getDefinedMeaningReferenceRecords( getUniqueIdsInRecordSet( $recordSet, array( $dmatt ) ), $dmatt->id );
		$definedMeaningReferenceRecords += $tmpArray;
	}

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		foreach ( $definedMeaningAttributes as $att ) {
			$record->setAttributeValue(
				$att,
				$definedMeaningReferenceRecords[$record->getAttributeValue( $att )]
			);
		}
	}
}

function getSyntransReferenceRecords( array $syntransIds, $usedAs ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	// an array of records
	$result = array();

	// find the spelling of a syntrans (of all syntrans from array syntransIds)
	$sql = "SELECT /* getSyntransReferenceRecords */ syntrans_sid, spelling"
		. " FROM {$dc}_syntrans, {$dc}_expression "
		. " WHERE syntrans_sid IN (" . implode( ", ", $syntransIds ) . ")"
		. " AND {$dc}_expression.expression_id={$dc}_syntrans.expression_id ";

	$queryResult = $dbr->query( $sql );
	$structure = new Structure( WLD_SYNONYMS_TRANSLATIONS, $o->syntransId, $o->spelling );
	$structure->setStructureType( $usedAs );

	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $structure );
		$syntransId = $row->syntrans_sid;
		$record->syntransId = $syntransId;
		$record->spelling = $row->spelling;
		$result[$syntransId] = $record;
	}
	return $result;
}

function expandSyntransReferencesInRecordSet( RecordSet $recordSet, array $syntransAttributes ) {
	$syntransReferenceRecords = array();

	foreach ( $syntransAttributes as $att ) {
		$listIds = getUniqueIdsInRecordSet( $recordSet, array( $att ) );
		if ( $listIds ) {
			// can be empty... why?
			$syntransReferenceRecords += getSyntransReferenceRecords( $listIds, $att->id );
		}
	}

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		foreach ( $syntransAttributes as $att ) {
			$record->setAttributeValue(
				$att,
				$syntransReferenceRecords[$record->getAttributeValue( $att )]
			);
		}
	}
}

function expandTranslatedContentInRecord( Record $record, Attribute $idAttribute, Attribute $translatedContentAttribute, ViewInformation $viewInformation ) {
	$record->setAttributeValue(
		$translatedContentAttribute,
		getTranslatedContentRecordSet( $record->getAttributeValue( $idAttribute ), $viewInformation )
	);
}

function expandTranslatedContentsInRecordSet( RecordSet $recordSet, Attribute $idAttribute, Attribute $translatedContentAttribute, ViewInformation $viewInformation ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		expandTranslatedContentInRecord( $recordSet->getRecord( $i ), $idAttribute, $translatedContentAttribute, $viewInformation );
	}
}

function getExpressionSpellings( array $expressionIds ) {
	$dc = wdGetDataSetContext();

	if ( count( $expressionIds ) > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		# Prepare steady components
		$frontQuery = "SELECT expression_id, spelling FROM {$dc}_expression WHERE expression_id =";
		$queueQuery	= " AND {$dc}_expression.remove_transaction_id IS NULL ";
		# Build atomic queries
		foreach ( $expressionIds as &$value ) {
			$value = $frontQuery . $value . $queueQuery;
		}
		unset( $value );
		# Union of the atoms
		$finalQuery = implode( ' UNION ', $expressionIds );
		
		$queryResult = $dbr->query( $finalQuery );
		
		$result = array();

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$result[$row->expression_id] = $row->spelling;
		}
		return $result;
	} else {
		return array();
	}
}

function expandExpressionSpellingsInRecordSet( RecordSet $recordSet, array $expressionAttributes ) {
	$expressionSpellings = getExpressionSpellings( getUniqueIdsInRecordSet( $recordSet, $expressionAttributes ) );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		
		foreach ( $expressionAttributes as $expressionAttribute ) {
			$record->setAttributeValue(
				$expressionAttribute,
				$expressionSpellings[$record->getAttributeValue( $expressionAttribute )]
			);
		}
	}
}

function getTextReferences( array $textIds ) {
	$dc = wdGetDataSetContext();
	if ( count( $textIds ) > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		# Query building
		$frontQuery = "SELECT text_id, text_text" .
			" FROM {$dc}_text" .
			" WHERE text_id = ";

		# Build atomic queries
		foreach ( $textIds as &$value ) {
			$value = $frontQuery . $value;
		}
		unset( $value );
		# Union of the atoms
		$finalQuery = implode( ' UNION ', $textIds );
		
		$queryResult = $dbr->query( $finalQuery );
		
		$result = array();
	
		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$result[$row->text_id] = $row->text_text;
		}
		return $result;
	} else {
		return array();
	}
}

function expandTextReferencesInRecordSet( RecordSet $recordSet, array $textAttributes ) {
	$textReferences = getTextReferences( getUniqueIdsInRecordSet( $recordSet, $textAttributes ) );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		foreach ( $textAttributes as $textAttribute ) {
			$textId = $record->getAttributeValue( $textAttribute );
			
			if ( isset( $textReferences[$textId] ) ) {
				$textValue = $textReferences[$textId];
			} else {
				$textValue = "";
			}
			$record->setAttributeValue( $textAttribute, $textValue );
		}
	}
}

/**
* The corresponding Editor function is getExpressionMeaningsEditor
* $exactMeaning is a boolean
*/
function getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();

	$dc = wdGetDataSetContext();
	$identicalMeaning = $exactMeaning ? 1 : 0;

	$recordSet = new ArrayRecordSet( $o->expressionMeaningStructure, new Structure( $o->definedMeaningId ) );

	$dbr = wfGetDB( DB_SLAVE );
	$queryResult = $dbr->query(
		"SELECT defined_meaning_id, syntrans_sid FROM {$dc}_syntrans" .
		" WHERE expression_id=$expressionId AND identical_meaning=" . $identicalMeaning .
		" AND {$dc}_syntrans.remove_transaction_id IS NULL "
	);

	while ( $syntrans = $dbr->fetchObject( $queryResult ) ) {
		$definedMeaningId = $syntrans->defined_meaning_id;
		$syntransId = $syntrans->syntrans_sid;
		$dmModelParams = array( "viewinformation" => $viewInformation, "syntransid" => $syntransId );
		$dmModel = new DefinedMeaningModel( $definedMeaningId, $dmModelParams );
		$recordSet->addRecord(
			array(
				$definedMeaningId,
				getDefinedMeaningDefinition( $definedMeaningId ),
				$dmModel->getRecord()
			)
		);
	}

	return $recordSet;
}

/**
* The corresponding Editor function is getExpressionsEditor
*/
function getExpressionMeaningsRecord( $expressionId, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();
		
	$record = new ArrayRecord( $o->expressionMeaningsStructure );
	$exactMeaning = true;
	$record->expressionExactMeanings = getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, $viewInformation );
	$exactMeaning = false;
	$record->expressionApproximateMeanings = getExpressionMeaningsRecordSet( $expressionId, $exactMeaning, $viewInformation );
	
	return $record;
}

function getExpressionsRecordSet( $spelling, ViewInformation $viewInformation, $dc = null ) {
	global $wgLang;
	$dc = wdGetDataSetContext( $dc );
	$o = OmegaWikiAttributes::getInstance();

	$dbr = wfGetDB( DB_SLAVE );
	$sqlbase =
		"SELECT expression_id, language_id " .
		" FROM {$dc}_expression" .
		" WHERE spelling=BINARY " . $dbr->addQuotes( $spelling ) .
		" AND {$dc}_expression.remove_transaction_id IS NULL " ;

	// needed because expression.remove_transaction_id is not updated automatically
	$sqlbase .= " AND EXISTS (" .
		"SELECT * " .
		" FROM {$dc}_syntrans " .
		" WHERE {$dc}_syntrans.expression_id={$dc}_expression.expression_id" .
		" AND {$dc}_syntrans.remove_transaction_id IS NULL " .
		")";

	$queryResult = null;
	if ( $viewInformation->expressionLanguageId != 0 ) {
		// display the expression in that language
		$sql = $sqlbase . " AND language_id=" . $viewInformation->expressionLanguageId ;
		$queryResult = $dbr->query( $sql );
	} else {
		// default: is there an expression in the user language?
		$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ;
		if ( $userLanguageId ) {
			$sql = $sqlbase . " AND language_id=" . $userLanguageId ;
		} else {
			// no $userLanguageId, try English
			$sql = $sqlbase . " AND language_id=" . WLD_ENGLISH_LANG_ID ;
		}
		$queryResult = $dbr->query( $sql );

		if ( $dbr->numRows( $queryResult ) == 0 ) {
			// nothing in the user language, any language will do
			$sql = $sqlbase . " LIMIT 1";
			$queryResult = $dbr->query( $sql );
		}
	}

	$result = new ArrayRecordSet( $o->expressionsStructure, new Structure( "expression-id", $o->expressionId ) );
	$languageStructure = new Structure( "language", $o->language );

	foreach ( $queryResult as $expression ) {
		$expressionRecord = new ArrayRecord( $languageStructure );
		$expressionRecord->language = $expression->language_id;

		$result->addRecord( array(
			$expression->expression_id,
			$expressionRecord,
			getExpressionMeaningsRecord( $expression->expression_id, $viewInformation )
		) );
	}

	return $result;
}

function getExpressionIdThatHasSynonyms( $spelling, $languageId ) {
	$dc = wdGetDataSetContext();

	$dbr = wfGetDB( DB_SLAVE );
	$queryResult = $dbr->query(
		"SELECT expression_id, language_id " .
		" FROM {$dc}_expression" .
		" WHERE spelling=BINARY " . $dbr->addQuotes( $spelling ) .
		" AND {$dc}_expression.remove_transaction_id IS NULL " .
		" AND language_id=$languageId" .
		" AND EXISTS (" .
			"SELECT expression_id " .
			" FROM {$dc}_syntrans " .
			" WHERE {$dc}_syntrans.expression_id={$dc}_expression.expression_id" .
			" AND {$dc}_syntrans.remove_transaction_id IS NULL "
		. ")"
	);
	
	if ( $expression = $dbr->fetchObject( $queryResult ) ) {
		return $expression->expression_id;
	} else {
		return 0;
	}
}
 

function getClassAttributesRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->classAttributesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->classAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'object_id' ), $o->classAttributeId ),
			new TableColumnsToAttribute( array( 'level_mid' ), $o->classAttributeLevel ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->classAttributeAttribute ),
			new TableColumnsToAttribute( array( 'attribute_type' ), $o->classAttributeType )
		),
		$wgWikidataDataSet->classAttributes,
		array( "class_mid=$definedMeaningId" )
	);
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->classAttributeLevel , $o->classAttributeAttribute ) );
	expandOptionAttributeOptionsInRecordSet( $recordSet, $o->classAttributeId, $viewInformation );

	return $recordSet;
}

function expandOptionAttributeOptionsInRecordSet( RecordSet $recordSet, Attribute $attributeIdAttribute, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();
	$recordSet->getStructure()->addAttribute( $o->optionAttributeOptions );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		$record->optionAttributeOptions = getOptionAttributeOptionsRecordSet( $record->getAttributeValue( $attributeIdAttribute ), $viewInformation );
	}
}

function getAlternativeDefinitionsRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->alternativeDefinitionsStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->definitionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'meaning_text_tcid' ), $o->definitionId ),
			new TableColumnsToAttribute( array( 'source_id' ), $o->source )
		),
		$wgWikidataDataSet->alternativeDefinitions,
		array( "meaning_mid=$definedMeaningId" )
	);

	$recordSet->getStructure()->addAttribute( $o->alternativeDefinition );
	
	expandTranslatedContentsInRecordSet( $recordSet, $o->definitionId, $o->alternativeDefinition, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->source ) );

	return $recordSet;
}

function getDefinedMeaningDefinitionRecord( $definedMeaningId, ViewInformation $viewInformation ) {
	$o = OmegaWikiAttributes::getInstance();
		
	$definitionId = getDefinedMeaningDefinitionId( $definedMeaningId );
	$record = new ArrayRecord( $o->definition->type );
	$record->translatedText = getTranslatedContentRecordSet( $definitionId, $viewInformation );
	
	// (Kip) What is this? There is no attributes to a definition => commented
	// $objectAttributesRecord = getObjectAttributesRecord( $definitionId, $viewInformation, $o->objectAttributes->id );
	// $record->objectAttributes = $objectAttributesRecord;
	
	// applyPropertyToColumnFiltersToRecord( $record, $objectAttributesRecord, $viewInformation );

	return $record;
}

function applyPropertyToColumnFiltersToRecord( Record $destinationRecord, Record $sourceRecord, ViewInformation $viewInformation ) {
	foreach ( $viewInformation->getPropertyToColumnFilters() as $propertyToColumnFilter ) {
		$destinationRecord->setAttributeValue(
			$propertyToColumnFilter->getAttribute(),
			filterObjectAttributesRecord( $sourceRecord, $propertyToColumnFilter->attributeIDs )
		);
	}
}

function applyPropertyToColumnFiltersToRecordSet( RecordSet $recordSet, Attribute $sourceAttribute, ViewInformation $viewInformation ) {
	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$attributeValuesRecord = $recordSet->getAttributeValue( $sourceAttribute );
		
		applyPropertyToColumnFiltersToRecord( $record, $attributeValuesRecord, $viewInformation );
	}
}

function getObjectAttributesRecord( $objectId, ViewInformation $viewInformation, $structuralOverride = null, $level = "" ) {
	$o = OmegaWikiAttributes::getInstance();

	if ( $structuralOverride ) {
		$record = new ArrayRecord( new Structure( $structuralOverride, $o->definedMeaningAttributes->type->getAttributes() ) );
	} else {
		$record = new ArrayRecord( $o->definedMeaningAttributes->type );
	}

	$record->objectId = $objectId;
	$record->relations = getRelationsRecordSet( array( $objectId ), $viewInformation, $level );
	$record->textAttributeValues = getTextAttributesValuesRecordSet( array( $objectId ), $viewInformation );
	$record->translatedTextAttributeValues = getTranslatedTextAttributeValuesRecordSet( array( $objectId ), $viewInformation );
	$record->linkAttributeValues = getLinkAttributeValuesRecordSet( array( $objectId ), $viewInformation );
	$record->optionAttributeValues = getOptionAttributeValuesRecordSet( array( $objectId ), $viewInformation );

	return $record;
}

function filterAttributeValues( RecordSet $sourceRecordSet, Attribute $attributeAttribute, array &$attributeIds ) {
	$result = new ArrayRecordSet( $sourceRecordSet->getStructure(), $sourceRecordSet->getKey() );
	$i = 0;
	
	while ( $i < $sourceRecordSet->getRecordCount() ) {
		$record = $sourceRecordSet->getRecord( $i );
		
		if ( in_array( $record->getAttributeValue( $attributeAttribute )->definedMeaningId, $attributeIds ) ) {
			$result->add( $record );
			$sourceRecordSet->remove( $i );
		}
		else
			$i++;
	}
	
	return $result;
}

function filterObjectAttributesRecord( Record $sourceRecord, array &$attributeIds ) {
	$o = OmegaWikiAttributes::getInstance();
	
	$result = new ArrayRecord( $sourceRecord->getStructure() );
	$result->objectId = $sourceRecord->objectId;
	
	$result->setAttributeValue( $o->relations, filterAttributeValues(
		$sourceRecord->relations,
		$o->relationType,
		$attributeIds
	) );
	
	$result->setAttributeValue( $o->textAttributeValues, filterAttributeValues(
		$sourceRecord->textAttributeValues,
		$o->textAttribute,
		$attributeIds
	) );
	
	$result->setAttributeValue( $o->translatedTextAttributeValues, filterAttributeValues(
		$sourceRecord->translatedTextAttributeValues,
		$o->translatedTextAttribute,
		$attributeIds
	) );
	
	$result->setAttributeValue( $o->linkAttributeValues, filterAttributeValues(
		$sourceRecord->linkAttributeValues,
		$o->linkAttribute,
		$attributeIds
	) );
	
	$result->setAttributeValue( $o->optionAttributeValues, filterAttributeValues(
		$sourceRecord->optionAttributeValues,
		$o->optionAttribute,
		$attributeIds
	) );
	
	return $result;
}

function getTranslatedContentRecordSet( $translatedContentId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	if ( ! $viewInformation->showRecordLifeSpan ) {
		// standard view
		$getTranslatedContentSQL = "SELECT language_id, text_id "
			. " FROM {$dc}_translated_content "
			. " WHERE translated_content_id = $translatedContentId "
			. " AND remove_transaction_id IS NULL " ;
	} else {
		// history view
		$getTranslatedContentSQL = "SELECT language_id, text_id "
			. ", add_transaction_id, remove_transaction_id "
			. " FROM {$dc}_translated_content "
			. " WHERE translated_content_id = $translatedContentId ";
	}

	// filter on languages, if activated by the user
	$filterLanguageSQL = $viewInformation->getFilterLanguageSQL() ;
	if ( $filterLanguageSQL != "" ) {
		$getTranslatedContentSQL .= " AND language_id IN $filterLanguageSQL " ;
	}

	$structure = $o->translatedTextStructure ;
	if ( $viewInformation->showRecordLifeSpan ) {
		// additional attributes for history view
		$structure->addAttribute( $o->recordLifeSpan );
	}
	// keyAttribute is stored in the $keyPath and used by the controller
	$keyAttribute = $o->language ;
	$recordSet = new ArrayRecordSet( $structure, new Structure( $keyAttribute ) );

	$queryResult = $dbr->query( $getTranslatedContentSQL );
	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$record = new ArrayRecord( $structure );
		$record->language = $row->language_id;
		$record->text = $row->text_id; // expanded below

		// adds transaction details for history view
		if ( $viewInformation->showRecordLifeSpan ) {
			$record->recordLifeSpan = getRecordLifeSpanTuple ( $row->add_transaction_id, $row->remove_transaction_id ) ;
		}
		$recordSet->add( $record );
	}

	expandTextReferencesInRecordSet( $recordSet, array( $o->text ) );
	
	return $recordSet;
}

/**
* the corresponding Editor is getSynonymsAndTranslationsEditor
*/
function getSynonymAndTranslationRecordSet( $definedMeaningId, ViewInformation $viewInformation, $excludeSyntransId = null ) {
	$o = OmegaWikiAttributes::getInstance();
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	if ( ! $viewInformation->showRecordLifeSpan ) {
		// standard view
		$getSynTransSQL = "SELECT syntrans_sid, {$dc}_syntrans.expression_id AS expression_id, identical_meaning, language_id, spelling "
			. " FROM {$dc}_syntrans, {$dc}_expression "
			. " WHERE defined_meaning_id = $definedMeaningId "
			. " AND {$dc}_expression.expression_id = {$dc}_syntrans.expression_id"
			. " AND {$dc}_syntrans.remove_transaction_id IS NULL" ;
	} else {
		// history view
		$getSynTransSQL = "SELECT syntrans_sid, {$dc}_syntrans.expression_id AS expression_id, "
			. " identical_meaning, language_id, spelling, "
			. " {$dc}_syntrans.remove_transaction_id AS remove_transaction_id,  "
			. " {$dc}_syntrans.add_transaction_id AS add_transaction_id "
			. " FROM {$dc}_syntrans, {$dc}_expression "
			. " WHERE defined_meaning_id = $definedMeaningId "
			. " AND {$dc}_expression.expression_id = {$dc}_syntrans.expression_id" ;
	}

	// filter on languages, if activated by the user
	$filterLanguageSQL = $viewInformation->getFilterLanguageSQL() ;
	if ( $filterLanguageSQL != "" ) {
		$getSynTransSQL .= " AND language_id IN $filterLanguageSQL " ;
	}

	// have identical translations on top
	$getSynTransSQL .= " ORDER BY identical_meaning DESC" ;

	// TODO; try with synTransExpressionStructure instead of synonymsTranslationsStructure
	// so that expression is not a sublevel of the hierarchy, but on the same level
	//	$structure = $o->synTransExpressionStructure ;
	$structure = $o->synonymsTranslationsStructure ;
	$structure->addAttribute( $o->objectAttributes );

	// adds additional attributes for history view if needed
	if ( $viewInformation->showRecordLifeSpan ) {
		$structure->addAttribute( $o->recordLifeSpan );
	}

	$keyAttribute = $o->syntransId ;
	$recordSet = new ArrayRecordSet( $structure, new Structure( $keyAttribute ) );

	$queryResult = $dbr->query( $getSynTransSQL );
	while ( $row = $dbr->fetchObject( $queryResult ) ) {
		$syntransId = $row->syntrans_sid;
		if ( $syntransId == $excludeSyntransId ) {
			continue;
		}

		$record = new ArrayRecord( $structure );
		$record->syntransId = $syntransId;
		$record->identicalMeaning = $row->identical_meaning;

		// adds the expression structure 
		$expressionRecord = new ArrayRecord( $o->expressionStructure );
		$expressionRecord->language = $row->language_id;
		$expressionRecord->spelling = $row->spelling;
		$record->expression = $expressionRecord;

		// adds the annotations (if any)
		$record->objectAttributes = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

		// adds transaction details for history view
		if ( $viewInformation->showRecordLifeSpan ) {
			$record->recordLifeSpan = getRecordLifeSpanTuple ( $row->add_transaction_id, $row->remove_transaction_id ) ;
		}

		$recordSet->add( $record );
	}

	return $recordSet;
}

function getDefinedMeaningReferenceRecord( $definedMeaningId ) {
	$o = OmegaWikiAttributes::getInstance();
	
	$record = new ArrayRecord( $o->definedMeaningReferenceStructure );
	$record->definedMeaningId = $definedMeaningId;
	$record->definedMeaningLabel = definedMeaningExpression( $definedMeaningId );
	$record->definedMeaningDefiningExpression = definingExpression( $definedMeaningId );
	
	return $record;
}

function getRelationsRecordSet( array $objectIds, ViewInformation $viewInformation, $level = "" ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->relationStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->relationId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'relation_id' ), $o->relationId ),
			new TableColumnsToAttribute( array( 'relationtype_mid' ), $o->relationType ),
			new TableColumnsToAttribute( array( 'meaning2_mid' ), $o->otherObject )
		),
		$wgWikidataDataSet->meaningRelations,
		array( "meaning1_mid IN (" . implode( ", ", $objectIds ) . ")" ),
		array( 'relationtype_mid' )
	);

	if ( $level == "SYNT" ) {
		expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType ) );
		expandSyntransReferencesInRecordSet( $recordSet, array( $o->otherObject ) );
	} else {
		// assuming DM relations
		expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType, $o->otherObject ) );
	}

	return $recordSet;
}

function getDefinedMeaningReciprocalRelationsRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->reciprocalRelations->id,
		$viewInformation->queryTransactionInformation,
		$o->relationId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'relation_id' ), $o->relationId ),
			new TableColumnsToAttribute( array( 'relationtype_mid' ), $o->relationType ),
			new TableColumnsToAttribute( array( 'meaning1_mid' ), $o->otherObject )
		),
		$wgWikidataDataSet->meaningRelations,
		array( "meaning2_mid=$definedMeaningId" ),
		array( 'relationtype_mid' )
	);
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->relationType, $o->otherObject ) );
	
	return $recordSet;
}

function getGotoSourceRecord( $record ) {
	$o = OmegaWikiAttributes::getInstance();
		
	$result = new ArrayRecord( $o->gotoSourceStructure );
	$result->collectionId = $record->collectionId;
	$result->sourceIdentifier = $record->sourceIdentifier;
	
	return $result;
}

function getDefinedMeaningCollectionMembershipRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->collectionMembershipStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->collectionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'collection_id' ), $o->collectionId ),
			new TableColumnsToAttribute( array( 'internal_member_id' ), $o->sourceIdentifier )
		),
		$wgWikidataDataSet->collectionMemberships,
		array( "member_mid=$definedMeaningId" )
	);

	$structure = $recordSet->getStructure();
	$structure->addAttribute( $o->collectionMeaning );
	$structure->addAttribute( $o->gotoSource );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );
		$record->collectionMeaning = getCollectionMeaningId( $record->collectionId );
		$record->gotoSource = getGotoSourceRecord( $record );
	}
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->collectionMeaning ) );
	
	return $recordSet;
}

function getTextAttributesValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->textAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->textAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->textAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->textAttributeObject ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->textAttribute ),
			new TableColumnsToAttribute( array( 'text' ), $o->text )
		),
		$wgWikidataDataSet->textAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->textAttribute ) );
	
	return $recordSet;
}

function getLinkAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->linkAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->linkAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->linkAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->linkAttributeObject ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->linkAttribute ),
			new TableColumnsToAttribute( array( 'label', 'url' ), $o->link )
		),
		$wgWikidataDataSet->linkAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->linkAttribute ) );
	
	return $recordSet;
}

function getTranslatedTextAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		 $wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->translatedTextAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->translatedTextAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->translatedTextAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->attributeObjectId ),
			new TableColumnsToAttribute( array( 'attribute_mid' ), $o->translatedTextAttribute ),
			new TableColumnsToAttribute( array( 'value_tcid' ), $o->translatedTextValueId )
		),
		$wgWikidataDataSet->translatedContentAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);
	
	$recordSet->getStructure()->addAttribute( $o->translatedTextValue );
	
	expandTranslatedContentsInRecordSet( $recordSet, $o->translatedTextValueId, $o->translatedTextValue, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->translatedTextAttribute ) );

	return $recordSet;
}

function getOptionAttributeOptionsRecordSet( $attributeId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		null,
		$viewInformation->queryTransactionInformation,
		$o->optionAttributeOptionId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'option_id' ), $o->optionAttributeOptionId ),
			new TableColumnsToAttribute( array( 'attribute_id' ), $o->optionAttribute ),
			new TableColumnsToAttribute( array( 'option_mid' ), $o->optionAttributeOption ),
			new TableColumnsToAttribute( array( 'language_id' ), $o->language )
		),
		$wgWikidataDataSet->optionAttributeOptions,
		array( 'attribute_id = ' . $attributeId )
	);

	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->optionAttributeOption ) );

	return $recordSet;
}

function getOptionAttributeValuesRecordSet( array $objectIds, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();
	$recordSet = queryRecordSet(
		$o->optionAttributeValuesStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->optionAttributeId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'value_id' ), $o->optionAttributeId ),
			new TableColumnsToAttribute( array( 'object_id' ), $o->optionAttributeObject ),
			new TableColumnsToAttribute( array( 'option_id' ), $o->optionAttributeOptionId )
		),
		$wgWikidataDataSet->optionAttributeValues,
		array( "object_id IN (" . implode( ", ", $objectIds ) . ")" )
	);

	expandOptionsInRecordSet( $recordSet, $viewInformation );
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->optionAttribute, $o->optionAttributeOption ) );

	return $recordSet;
}

/* XXX: This can probably be combined with other functions. In fact, it probably should be. Do it. */
function expandOptionsInRecordSet( RecordSet $recordSet, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet->getStructure()->addAttribute( $o->optionAttributeOption );
	$recordSet->getStructure()->addAttribute( $o->optionAttribute );

	for ( $i = 0; $i < $recordSet->getRecordCount(); $i++ ) {
		$record = $recordSet->getRecord( $i );

		$optionRecordSet = queryRecordSet(
			null,
			$viewInformation->queryTransactionInformation,
			$o->optionAttributeOptionId,
			new TableColumnsToAttributesMapping(
				new TableColumnsToAttribute( array( 'attribute_id' ), $o->optionAttributeId ),
				new TableColumnsToAttribute( array( 'option_mid' ), $o->optionAttributeOption )
			),
			$wgWikidataDataSet->optionAttributeOptions,
			array( 'option_id = ' . $record->optionAttributeOptionId )
		);

		$optionRecord = $optionRecordSet->getRecord( 0 );
		$record->optionAttributeOption = $optionRecord->optionAttributeOption;

		$optionRecordSet = queryRecordSet(
			null,
			$viewInformation->queryTransactionInformation,
			$o->optionAttributeId,
			new TableColumnsToAttributesMapping( new TableColumnsToAttribute( array( 'attribute_mid' ), $o->optionAttribute ) ),
			$wgWikidataDataSet->classAttributes,
			array( 'object_id = ' . $optionRecord->optionAttributeId )
		);
	
		$optionRecord = $optionRecordSet->getRecord( 0 );
		$record->optionAttribute = $optionRecord->optionAttribute;
	}
}

function getDefinedMeaningClassMembershipRecordSet( $definedMeaningId, ViewInformation $viewInformation ) {
	global
		$wgWikidataDataSet;

	$o = OmegaWikiAttributes::getInstance();

	$recordSet = queryRecordSet(
		$o->classMembershipStructure->getStructureType(),
		$viewInformation->queryTransactionInformation,
		$o->classMembershipId,
		new TableColumnsToAttributesMapping(
			new TableColumnsToAttribute( array( 'class_membership_id' ), $o->classMembershipId ),
			new TableColumnsToAttribute( array( 'class_mid' ), $o->class )
		),
		$wgWikidataDataSet->classMemberships,
		array( "class_member_mid=$definedMeaningId" )
	);
	
	expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $o->class ) );
	
	return $recordSet;
}

function getDefiningExpressionRecord( $definedMeaningId ) {

		$o = OmegaWikiAttributes::getInstance();

		$definingExpression = definingExpressionRow( $definedMeaningId );
		$definingExpressionRecord = new ArrayRecord( $o->definedMeaningCompleteDefiningExpression->type );
		$definingExpressionRecord->expressionId = $definingExpression[0];
		$definingExpressionRecord->definedMeaningDefiningExpression = $definingExpression[1];
		$definingExpressionRecord->language = $definingExpression[2];
		return $definingExpressionRecord;
}
