<?php

if ( !defined( 'MEDIAWIKI' ) ) die();


class SpecialSuggest extends SpecialPage {

	private $dbr;
	private $dc;
	private $o;
	private $userLangId;

	function __construct() {
		parent::__construct( 'Suggest', 'UnlistedSpecialPage' );
	}

	function execute( $par ) {
		global $wgOut, $wgLang, $wgDBprefix;
		require_once( "Attribute.php" );
		require_once( "WikiDataBootstrappedMeanings.php" );
		require_once( "RecordSet.php" );
		require_once( "Editor.php" );
		require_once( "HTMLtable.php" );
		require_once( "Transaction.php" );
		require_once( "OmegaWikiEditors.php" );
		require_once( "Utilities.php" );
		require_once( "Wikidata.php" );
		require_once( "WikiDataTables.php" );
		require_once( "WikiDataGlobals.php" );

		$this->o = OmegaWikiAttributes::getInstance();
		$this->dc = wdGetDataSetContext();
		$this->dbr = wfGetDB( DB_SLAVE );
		$wgOut->disable();

		$request = $this->getRequest();
		$search = ltrim( $request->getVal( 'search-text' ) );
		$prefix = $request->getVal( 'prefix' );
		$query = $request->getVal( 'query' );
		$definedMeaningId = $request->getVal( 'definedMeaningId' );
		$offset = $request->getVal( 'offset' );
		$attributesLevel = $request->getVal( 'attributesLevel' );
		$annotationAttributeId = $request->getVal( 'annotationAttributeId' );
		$syntransId = $request->getVal( 'syntransId' );
		$langCode = $wgLang->getCode();

		$sql = '';

		$this->userLangId = $this->dbr->selectField(
			'language',
			'language_id',
			array( 'wikimedia_key' => $langCode ),
			__METHOD__
		);
		if ( !$this->userLangId ) {
			// English default
			$this->userLangId = WLD_ENGLISH_LANG_ID ;
		}

		$rowText = 'spelling';
		switch ( $query ) {
			case 'relation-type':
				$sqlActual = $this->getSQLForCollectionOfType( 'RELT' );
				$sqlFallback = $this->getSQLForCollectionOfType( 'RELT', WLD_ENGLISH_LANG_ID );
				$sql = $this->constructSQLWithFallback( $sqlActual, $sqlFallback, array( "member_mid", "spelling", "collection_mid" ) );
				break;
			case 'class':
				$sql = $this->getSQLForClasses( );
				break;
			case WLD_RELATIONS: // 'rel'
				if ( $attributesLevel == "DefinedMeaning" ) {
					$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'DM' );
				} elseif ( $attributesLevel == "SynTrans" ) {
					$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'SYNT' );
				}
				break;
			case 'text-attribute':
				$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'TEXT' );
				break;
			case 'translated-text-attribute':
				$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'TRNS' );
				break;
			case WLD_LINK_ATTRIBUTE:
				$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'URL' );
				break;
			case WLD_OPTION_ATTRIBUTE:
				$sql = $this->getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, 'OPTN' );
				break;
			case 'language':
				require_once( 'languages.php' );
				$sql = getSQLForLanguageNames( $langCode, $this->o->getViewInformation()->getFilterLanguageList() );
				$rowText = 'language_name';
				break;
			case WLD_DEFINED_MEANING:
				$sql = $this->getSQLForDMs();
				break;
			case WLD_SYNONYMS_TRANSLATIONS:
				$sql = $this->getSQLForSyntranses();
				break;
			case 'class-attributes-level':
				$sql = $this->getSQLForLevels();
				break;
			case 'collection':
				$sql = $this->getSQLForCollection();
				break;
			case 'transaction':
				$sql =
					"SELECT transaction_id, user_id, user_ip, " .
					" CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2), ' '," .
					" SUBSTRING(timestamp, 9, 2), ':', SUBSTRING(timestamp, 11, 2), ':', SUBSTRING(timestamp, 13, 2)) AS time, comment" .
					" FROM {$wgDBprefix}{$this->dc}_transactions WHERE 1";

				$rowText = "CONCAT(SUBSTRING(timestamp, 1, 4), '-', SUBSTRING(timestamp, 5, 2), '-', SUBSTRING(timestamp, 7, 2), ' '," .
						" SUBSTRING(timestamp, 9, 2), ':', SUBSTRING(timestamp, 11, 2), ':', SUBSTRING(timestamp, 13, 2))";
				break;
		}

		if ( $search != '' ) {
			if ( $query == 'transaction' ) {
				$searchCondition = " AND $rowText LIKE " . $this->dbr->addQuotes( "%$search%" );
			}
			elseif ( $query == 'class' ) {
				$searchCondition = " AND $rowText LIKE " . $this->dbr->addQuotes( "$search%" );
			}
			elseif ( $query == WLD_RELATIONS or
				$query == WLD_LINK_ATTRIBUTE or
				$query == WLD_OPTION_ATTRIBUTE or
				$query == 'translated-text-attribute' or
				$query == 'text-attribute' )
			{
				$searchCondition = " HAVING $rowText LIKE " . $this->dbr->addQuotes( "$search%" );
			}
			elseif ( $query == 'language' ) {
				$searchCondition = " HAVING $rowText LIKE " . $this->dbr->addQuotes( "%$search%" );
			}
			elseif ( $query == 'relation-type' ) { // not sure in which case 'relation-type' happens...
				$searchCondition = " WHERE $rowText LIKE " . $this->dbr->addQuotes( "$search%" );
			}
			else {
				$searchCondition = " AND $rowText LIKE " . $this->dbr->addQuotes( "$search%" );
			}
		} else {
			$searchCondition = "";
		}

		if ( $query == 'transaction' ) {
			$orderBy = 'transaction_id DESC';
		} else {
			$orderBy = $rowText;
		}

		$sql .= $searchCondition . " ORDER BY $orderBy LIMIT ";

		if ( $offset > 0 ) {
			$sql .= " $offset, ";
		}

		// print only 10 results
		$sql .= "10";

		# == Actual query here
		// wfdebug("]]]".$sql."\n");
		$queryResult = $this->dbr->query( $sql );

		# == Process query
		switch( $query ) {
			case 'relation-type':
				list( $recordSet, $editor ) = $this->getRelationTypeAsRecordSet( $queryResult );
				break;
			case 'class':
				list( $recordSet, $editor ) = $this->getClassAsRecordSet( $queryResult );
				break;
			case WLD_RELATIONS:
				list( $recordSet, $editor ) = $this->getDefinedMeaningAttributeAsRecordSet( $queryResult );
				break;
			case 'text-attribute':
				list( $recordSet, $editor ) = $this->getTextAttributeAsRecordSet( $queryResult );
				break;
			case 'translated-text-attribute':
				list( $recordSet, $editor ) = $this->getTranslatedTextAttributeAsRecordSet( $queryResult );
				break;
			case WLD_LINK_ATTRIBUTE:
				list( $recordSet, $editor ) = $this->getLinkAttributeAsRecordSet( $queryResult );
				break;
			case WLD_OPTION_ATTRIBUTE:
				list( $recordSet, $editor ) = $this->getOptionAttributeAsRecordSet( $queryResult );
				break;
			case WLD_DEFINED_MEANING:
				list( $recordSet, $editor ) = $this->getDefinedMeaningAsRecordSet( $queryResult );
				break;
			case WLD_SYNONYMS_TRANSLATIONS:
				list( $recordSet, $editor ) = $this->getSyntransAsRecordSet( $queryResult );
				break;
			case 'class-attributes-level':
				list( $recordSet, $editor ) = $this->getClassAttributeLevelAsRecordSet( $queryResult );
				break;
			case 'collection':
				list( $recordSet, $editor ) = $this->getCollectionAsRecordSet( $queryResult );
				break;
			case 'language':
				list( $recordSet, $editor ) = $this->getLanguageAsRecordSet( $queryResult );
				break;
			case 'transaction':
				list( $recordSet, $editor ) = $this->getTransactionAsRecordSet( $queryResult );
				break;
		}

		$this->dbr->freeResult( $queryResult );
		$output = $editor->view( new IdStack( $prefix . 'table' ), $recordSet );

		echo $output;
	}

	/** Constructs a new SQL query from 2 other queries such that if a field exists
	 * in the fallback query, but not in the actual query, the field from the
	 * fallback query will be returned. Fields not in the fallback are ignored.
	 * You will need to state which fields in your query need to be returned.
	 * As a (minor) hack, the 0th element of $fields is assumed to be the key field.
	 */
	private function constructSQLWithFallback( $actual_query, $fallback_query, $fields ) {

		# if ($actual_query==$fallback_query)
		#	return $actual_query;

		$sql = "SELECT * FROM (SELECT ";

		$sql_with_comma = $sql;
		foreach ( $fields as $field ) {
			$sql = $sql_with_comma;
			$sql .= "COALESCE(actual.$field, fallback.$field) as $field";
			$sql_with_comma = $sql;
			$sql_with_comma .= ", ";
		}

		$sql .= " FROM ";
		$sql .=	" ( $fallback_query ) AS fallback";
		$sql .=	" LEFT JOIN ";
		$sql .=	" ( $actual_query ) AS actual";

		$field0 = $fields[0]; # slightly presumptuous
		$sql .=  " ON actual.$field0 = fallback.$field0";
		$sql .= ") as coalesced";
		return $sql;
	}

	/**
	 * Returns the list of attributes of a given $attributesType (DM, TEXT, TRNS, URL, OPTN)
	 * in the user language or in English
	 */
	private function getSQLToSelectPossibleAttributes( $definedMeaningId, $attributesLevel, $syntransId, $annotationAttributeId, $attributesType ) {
		global $wgDefaultClassMids, $wgLang, $wgIso639_3CollectionId, $wgDBprefix;

		$classMids = $wgDefaultClassMids ;

		if ( ( !is_null($syntransId) ) && ( !is_null($wgIso639_3CollectionId)) ) {
			// find the language of the syntrans and add attributes of that language by adding the language DM to the list of default classes
			// this first query returns the language_id
			$expressionId = $this->dbr->selectField(
				$this->dc . '_syntrans',
				'expression_id',
				array( 'syntrans_sid' => $syntransId ),
				__METHOD__
			);

			$language_id = $this->dbr->selectField(
				$this->dc . '_expression',
				'language_id',
				array( 'expression_id' => $expressionId ),
				__METHOD__
			);

			// this second query finds the DM number for a given language_id
			$language_dm_id = $this->dbr->selectField(
				array( 'colcont' => $this->dc . '_collection_contents', 'lng' => 'language' ),
				'member_mid',
				array(
					'lng.language_id' => $language_id,
					'colcont.collection_id' => $wgIso639_3CollectionId,
					'lng.iso639_3 = colcont.internal_member_id',
					'colcont.remove_transaction_id' => null
				), __METHOD__
			);

			if ( !$language_dm_id ) {
				// this language does not have an associated dm
				$classMids = $wgDefaultClassMids;
			} else {
				$classMids = array_merge ( $wgDefaultClassMids , array($language_dm_id) ) ;
			}
		}

		$filteredAttributesRestriction = $this->getFilteredAttributesRestriction( $annotationAttributeId );

		// fallback is English, and second fallback is the DM id
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$sql = "SELECT object_id, attribute_mid, COALESCE( exp_lng.spelling, exp_en.spelling, attribute_mid ) AS spelling" ;
		} else {
			$sql = "SELECT object_id, attribute_mid, COALESCE( exp_en.spelling, attribute_mid ) AS spelling" ;
		}
		$sql .= " FROM {$wgDBprefix}{$this->dc}_bootstrapped_defined_meanings bdm, {$wgDBprefix}{$this->dc}_class_attributes clatt" ;
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$sql .= " LEFT JOIN ( {$wgDBprefix}{$this->dc}_syntrans synt_lng, {$wgDBprefix}{$this->dc}_expression exp_lng )" .
				" ON ( clatt.attribute_mid = synt_lng.defined_meaning_id" .
				" AND exp_lng.expression_id = synt_lng.expression_id" .
				" AND exp_lng.language_id = " . $this->userLangId . " )" ;
		}
		$sql .= " LEFT JOIN ( {$wgDBprefix}{$this->dc}_syntrans synt_en, {$wgDBprefix}{$this->dc}_expression exp_en )" .
			" ON ( clatt.attribute_mid = synt_en.defined_meaning_id" .
			" AND exp_en.expression_id = synt_en.expression_id" .
			" AND exp_en.language_id = " . WLD_ENGLISH_LANG_ID . " )" ; // English

		$sql .= " WHERE bdm.name = " . $this->dbr->addQuotes( $attributesLevel ) .
			" AND bdm.defined_meaning_id = clatt.level_mid" .
			" AND clatt.attribute_type = " . $this->dbr->addQuotes( $attributesType ) .
			$filteredAttributesRestriction . " ";

		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$sql .= " AND synt_lng.remove_transaction_id IS NULL" ;
		}
		$sql .= " AND synt_en.remove_transaction_id IS NULL" ;

		$sql .=
			' AND clatt.remove_transaction_id IS NULL ' .
			" AND (clatt.class_mid IN (" .
			' SELECT class_mid ' .
			" FROM  {$wgDBprefix}{$this->dc}_class_membership clmem" .
			" WHERE clmem.class_member_mid = " . $definedMeaningId .
			' AND clmem.remove_transaction_id IS NULL ' .
			' )' ;

		if ( count( $classMids ) > 0 ) {
			$sql .= " OR clatt.class_mid IN (" . join( $classMids, ", " ) . ")";
		}
		$sql .= ')';

		// group by to obtain unicity
		$sql .= ' GROUP BY object_id';

		return $sql;
	}

	private function getPropertyToColumnFilterForAttribute( $annotationAttributeId ) {
		global $wgPropertyToColumnFilters;

		$i = 0;
		$result = null;

		while ( $result == null && $i < count( $wgPropertyToColumnFilters ) ) {
			if ( $wgPropertyToColumnFilters[$i]->getAttribute()->id == $annotationAttributeId ) {
				$result = $wgPropertyToColumnFilters[$i];
			} else {
				$i++;
			}
		}
		return $result;
	}

	private function getFilteredAttributes( $annotationAttributeId ) {
		$propertyToColumnFilter = $this->getPropertyToColumnFilterForAttribute( $annotationAttributeId );

		if ( $propertyToColumnFilter != null ) {
			return $propertyToColumnFilter->attributeIDs;
		} else {
			return array();
		}
	}

	private function getAllFilteredAttributes() {
		global $wgPropertyToColumnFilters;

		$result = array();

		foreach ( $wgPropertyToColumnFilters as $propertyToColumnFilter ) {
			$result = array_merge( $result, $propertyToColumnFilter->attributeIDs );
		}

		return $result;
	}

	private function getFilteredAttributesRestriction( $annotationAttributeId ) {

		$propertyToColumnFilter = $this->getPropertyToColumnFilterForAttribute( $annotationAttributeId );

		if ( $propertyToColumnFilter != null ) {
			$filteredAttributes = $propertyToColumnFilter->attributeIDs;

			if ( count( $filteredAttributes ) > 0 ) {
				$result = " AND clatt.attribute_mid IN (" . join( $filteredAttributes, ", " ) . ")";
			} else {
				$result = " AND 0 ";
			}
		}
		else {
			$allFilteredAttributes = $this->getAllFilteredAttributes();

			if ( count( $allFilteredAttributes ) > 0 ) {
				$result = " AND clatt.attribute_mid NOT IN (" . join( $allFilteredAttributes, ", " ) . ")";
			} else {
				$result = "";
			}
		}

		return $result;
	}

	/**
	 * sql query used to select a DM in a DM-DM relation
	 */
	private function getSQLForDMs() {
		$sql = $this->dbr->selectSQLText(
			array( // tables
				'exp' => "{$this->dc}_expression",
				'synt' => "{$this->dc}_syntrans"
			),
			array( // fields
				'defined_meaning_id' => 'synt.defined_meaning_id',
				'spelling' => 'exp.spelling',
				'language_id' => 'exp.language_id'
			),
			array( // where
				'exp.remove_transaction_id' => null
			), __METHOD__,
			array( 'STRAIGHT_JOIN' ), // options
			array( 'synt' => array( 'JOIN', array(
				'exp.expression_id = synt.expression_id',
				'synt.identical_meaning' => 1,
				'synt.remove_transaction_id' => null
			)))
		);
		return $sql;
	}

	/**
	 * sql query used to select a syntrans in a syntrans-syntrans relation
	 */
	private function getSQLForSyntranses() {
		$sql = $this->dbr->selectSQLText(
			array( // tables
				'exp' => "{$this->dc}_expression",
				'synt' => "{$this->dc}_syntrans"
			),
			array( // fields
				'syntrans_sid' => 'synt.syntrans_sid',
				'defined_meaning_id' => 'synt.defined_meaning_id',
				'spelling' => 'exp.spelling',
				'language_id' => 'exp.language_id'
			),
			array( // where
				'exp.remove_transaction_id' => null
			), __METHOD__,
			array( 'STRAIGHT_JOIN' ), // options
			array( 'synt' => array( 'JOIN', array(
				'exp.expression_id = synt.expression_id',
				'synt.identical_meaning' => 1,
				'synt.remove_transaction_id' => null
			)))
		);
		return $sql;
	}

	/**
	 * Returns the name of all classes and their spelling in the user language or in English
	 */
	private function getSQLForClasses() {
		global $wgDBprefix;

		// exp.spelling, txt.text_text
		$sql = "SELECT member_mid, spelling " .
			" FROM {$wgDBprefix}{$this->dc}_collection_contents col_contents, {$wgDBprefix}{$this->dc}_collection col, {$wgDBprefix}{$this->dc}_syntrans synt," .
			" {$wgDBprefix}{$this->dc}_expression exp, {$wgDBprefix}{$this->dc}_defined_meaning dm" .
			" WHERE col.collection_type='CLAS' " .
			" AND col_contents.collection_id = col.collection_id " .
			" AND synt.defined_meaning_id = col_contents.member_mid " .
//			" AND synt.identical_meaning=1 " .
			" AND exp.expression_id = synt.expression_id " .
			" AND dm.defined_meaning_id = synt.defined_meaning_id " ;

		// fallback is English
		$sql .= " AND ( exp.language_id= " . $this->userLangId ;
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$sql .= ' OR ( ' .
				' language_id= ' . WLD_ENGLISH_LANG_ID .
				" AND NOT EXISTS ( SELECT * FROM {$wgDBprefix}{$this->dc}_syntrans synt2, {$wgDBprefix}{$this->dc}_expression exp2 WHERE synt2.defined_meaning_id = synt.defined_meaning_id AND exp2.expression_id = synt2.expression_id AND exp2.language_id={$userlang} AND synt2.remove_transaction_id IS NULL LIMIT 1 ) ) " ;
		}
		$sql .= ' ) ' ;

		$sql .= " AND " . getLatestTransactionRestriction( "col" ) .
			" AND " . getLatestTransactionRestriction( "col_contents" ) .
			" AND " . getLatestTransactionRestriction( "synt" ) .
			" AND " . getLatestTransactionRestriction( "exp" ) .
			" AND " . getLatestTransactionRestriction( "dm" ) ;

		return $sql;
	}

	private function getSQLForCollectionOfType( $collectionType, $language = "<ANY>" ) {
		global $wgDBprefix;
		$cond = array(
			'colcont.collection_id = col.collection_id',
			'col.collection_type' => $collectionType,
			'synt.defined_meaning_id = colcont.member_mid',
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null,
			'col.remove_transaction_id' => null,
			'colcont.remove_transaction_id' => null
		);

		if ( $language != "<ANY>" ) {
			$cond['language_id'] = $language;
		}

		$sql = $this->dbr->selectSQLText(
			array(
				'colcont' => $this->dc . '_collection_contents',
				'col' => $this->dc . '_collection',
				'synt' => $this->dc . '_syntrans',
				'exp' => $this->dc . '_expression'
			),
			array( 'member_mid', 'spelling', 'collection_mid' ),
			$cond,
			__METHOD__
		);

		return $sql;
	}
	private function getSQLForCollection() {
		global $wgDBprefix;

		$sql = "SELECT collection_id, spelling " .
			" FROM {$wgDBprefix}{$this->dc}_expression exp, {$wgDBprefix}{$this->dc}_collection col, {$wgDBprefix}{$this->dc}_syntrans synt, {$wgDBprefix}{$this->dc}_defined_meaning dm " .
			" WHERE exp.expression_id=synt.expression_id " .
			" AND synt.defined_meaning_id=col.collection_mid " .
			" AND dm.defined_meaning_id = synt.defined_meaning_id " ;
//			" AND synt.identical_meaning=1" .

		// fallback is English
		$sql .= " AND ( exp.language_id= " . $this->userLangId ;
		if ( $this->userLangId != WLD_ENGLISH_LANG_ID ) {
			$notExistsQuery = $this->dbr->selectSQLText(
				array(
					'synt2' => "{$this->dc}_syntrans",
					'exp2' => "{$this->dc}_expression"
				), 'exp2.expression_id', // whatever
				array(
					'synt2.defined_meaning_id = synt.defined_meaning_id',
					'exp2.expression_id = synt2.expression_id',
					'exp2.language_id' => $this->userLangId,
					'synt2.remove_transaction_id' => null
				), __METHOD__,
				array( 'LIMIT' => 1 )
			);

			$sql .= ' OR ( ' .
				' language_id= ' . WLD_ENGLISH_LANG_ID .
				' AND NOT EXISTS ( ' . $notExistsQuery . ' )' ;
			$sql .= ' ) ' ; // or
		}
		$sql .= ' ) ' ; // and

		$sql .= ' AND synt.remove_transaction_id IS NULL ' .
			' AND exp.remove_transaction_id IS NULL ' .
			' AND col.remove_transaction_id IS NULL ' .
			' AND dm.remove_transaction_id IS NULL ';

		return $sql;
	}

	private function getSQLForLevels( ) {
		global $wgWldClassAttributeLevels, $wgWikidataDataSet;

		// TO DO: Add support for multiple languages here
		return
			selectLatest(
				array( $wgWikidataDataSet->bootstrappedDefinedMeanings->definedMeaningId, $wgWikidataDataSet->expression->spelling ),
				array( $wgWikidataDataSet->definedMeaning, $wgWikidataDataSet->expression, $wgWikidataDataSet->bootstrappedDefinedMeanings ),
				array(
					'name IN (' . implodeFixed( $wgWldClassAttributeLevels ) . ')',
					equals( $wgWikidataDataSet->definedMeaning->definedMeaningId, $wgWikidataDataSet->bootstrappedDefinedMeanings->definedMeaningId ),
					equals( $wgWikidataDataSet->definedMeaning->expressionId, $wgWikidataDataSet->expression->expressionId )
				)
			);
	}

	private function getRelationTypeAsRecordSet( $queryResult ) {

		$relationTypeAttribute = new Attribute( "relation-type", wfMessage( 'ow_RelationType' )->text(), "short-text" );
		$collectionAttribute = new Attribute( "collection", wfMessage( 'ow_Collection' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $relationTypeAttribute, $collectionAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->member_mid, $row->spelling, definedMeaningExpression( $row->collection_mid ) ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $relationTypeAttribute ) );
		$editor->addEditor( createShortTextViewer( $collectionAttribute ) );

		return array( $recordSet, $editor );
	}

	/**
	 * Writes an html table from a sql table corresponding to the list of classes, as shown by
	 * http://www.omegawiki.org/index.php?title=Special:Suggest&query=class
	 *
	 * @param $queryResult the result of a SQL query to be made into an html table
	 */
	function getClassAsRecordSet( $queryResult ) {

		// Setting the two column, with titles
		$classAttribute = new Attribute( "class", wfMessage( 'ow_Class' )->text(), "short-text" );
		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $classAttribute, $definitionAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->member_mid, $row->spelling, getDefinedMeaningDefinition( $row->member_mid ) ) );
		}

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $classAttribute ) );
		$editor->addEditor( createShortTextViewer( $definitionAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getDefinedMeaningAttributeAsRecordSet( $queryResult ) {

		$definedMeaningAttributeAttribute = new Attribute( WLD_DM_ATTRIBUTES, wfMessage( 'ow_Relations' )->plain(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $definedMeaningAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->attribute_mid, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $definedMeaningAttributeAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getTextAttributeAsRecordSet( $queryResult ) {

		$textAttributeAttribute = new Attribute( "text-attribute", wfMessage( 'ow_TextAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $textAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->attribute_mid, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $textAttributeAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getLinkAttributeAsRecordSet( $queryResult ) {

		$linkAttributeAttribute = new Attribute( WLD_LINK_ATTRIBUTE, wfMessage( 'ow_LinkAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $linkAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->attribute_mid, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $linkAttributeAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getTranslatedTextAttributeAsRecordSet( $queryResult ) {

		$translatedTextAttributeAttribute = new Attribute( "translated-text-attribute", "Translated text attribute", "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $translatedTextAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->attribute_mid, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $translatedTextAttributeAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getOptionAttributeAsRecordSet( $queryResult ) {

		$optionAttributeAttribute = new Attribute( WLD_OPTION_ATTRIBUTE, wfMessage( 'ow_OptionAttributeHeader' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $optionAttributeAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->object_id, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $optionAttributeAttribute ) );

		return array( $recordSet, $editor );
	}

	/**
	* returns a table with three columns for selecting a DM:
	* spelling / language / definition
	* The three together represent a specific (unique) defined_meaning_id
	*/
	private function getDefinedMeaningAsRecordSet( $queryResult ) {

		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "definition" );

		$spellingLangDefStructure = new Structure( $this->o->id, $this->o->spelling, $this->o->language, $definitionAttribute );
		$recordSet = new ArrayRecordSet( $spellingLangDefStructure, new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$definition = getDefinedMeaningDefinition( $row->defined_meaning_id );

			$recordSet->addRecord( array( $row->defined_meaning_id, $row->spelling, $row->language_id, $definition ) );
		}

		$definitionEditor = new TextEditor( $definitionAttribute, new SimplePermissionController( false ), false, true, 75 );

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->o->spelling ) );
		$editor->addEditor( createLanguageViewer( $this->o->language ) );
		$editor->addEditor( $definitionEditor );

		return array( $recordSet, $editor );
	}

	/**
	* returns a table with three columns for selecting a Syntrans:
	* spelling / language / definition
	* The three together represent a specific (unique) syntrans_sid
	*/
	private function getSyntransAsRecordSet( $queryResult ) {

		$definitionAttribute = new Attribute( "definition", wfMessage( 'ow_Definition' )->text(), "definition" );

		$spellingLangDefStructure = new Structure( $this->o->id, $this->o->spelling, $this->o->language, $definitionAttribute );
		$recordSet = new ArrayRecordSet( $spellingLangDefStructure, new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$definition = getDefinedMeaningDefinition( $row->defined_meaning_id );

			$recordSet->addRecord( array( $row->syntrans_sid, $row->spelling, $row->language_id, $definition ) );
		}

		$definitionEditor = new TextEditor( $definitionAttribute, new SimplePermissionController( false ), false, true, 75 );

		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->o->spelling ) );
		$editor->addEditor( createLanguageViewer( $this->o->language ) );
		$editor->addEditor( $definitionEditor );

		return array( $recordSet, $editor );
	}

	private function getClassAttributeLevelAsRecordSet( $queryResult ) {

		$classAttributeLevelAttribute = new Attribute( "class-attribute-level", wfMessage( 'ow_ClassAttributeLevel' )->text(), "short-text" );
		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $classAttributeLevelAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->defined_meaning_id, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $classAttributeLevelAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getCollectionAsRecordSet( $queryResult ) {

		$collectionAttribute = new Attribute( "collection", wfMessage( 'ow_Collection' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $collectionAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->collection_id, $row->spelling ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $collectionAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getLanguageAsRecordSet( $queryResult ) {

		$languageAttribute = new Attribute( "language", wfMessage( 'ow_Language' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet( new Structure( $this->o->id, $languageAttribute ), new Structure( $this->o->id ) );

		foreach ( $queryResult as $row )  {
			$recordSet->addRecord( array( $row->row_id, $row->language_name ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $languageAttribute ) );

		return array( $recordSet, $editor );
	}

	private function getTransactionAsRecordSet( $queryResult ) {

		$userAttribute = new Attribute( "user", wfMessage( 'ow_User' )->text(), "short-text" );
		$timestampAttribute = new Attribute( "timestamp", wfMessage( 'ow_Time' )->text(), "timestamp" );
		$summaryAttribute = new Attribute( "summary", wfMessage( 'ow_transaction_summary' )->text(), "short-text" );

		$recordSet = new ArrayRecordSet(
			new Structure( $this->o->id, $userAttribute, $timestampAttribute, $summaryAttribute ),
			new Structure( $this->o->id )
		);

		foreach ( $queryResult as $row ) {
			$recordSet->addRecord( array( $row->transaction_id, getUserLabel( $row->user_id, $row->user_ip ), $row->time, $row->comment ) );
		}
		$editor = createSuggestionsTableViewer( null );
		$editor->addEditor( createShortTextViewer( $timestampAttribute ) );
		$editor->addEditor( createShortTextViewer( $this->o->id ) );
		$editor->addEditor( createShortTextViewer( $userAttribute ) );
		$editor->addEditor( createShortTextViewer( $summaryAttribute ) );

		return array( $recordSet, $editor );
	}
}
