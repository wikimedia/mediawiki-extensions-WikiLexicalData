<?php

if ( !defined( 'MEDIAWIKI' ) ) die();

require_once( "Wikidata.php" );
require_once( "WikiDataGlobals.php" );
require_once( "WikiDataAPI.php" );
require_once( "forms.php" );
require_once( "type.php" );
require_once( "ViewInformation.php" );
require_once( "OmegaWikiAttributes.php" );
require_once( "OmegaWikiRecordSets.php" );
require_once( "OmegaWikiEditors.php" );

class SpecialDatasearch extends SpecialPage {
	protected $externalIdentifierAttribute;
	protected $collectionAttribute;
	protected $collectionMemberAttribute;
	protected $externalIdentifierMatchStructure;

	protected $spellingAttribute;
	protected $languageAttribute;

	protected $expressionStructure;
	protected $expressionAttribute;

	protected $definedMeaningAttribute;
	protected $definitionAttribute;

	protected $meaningStructure;
	protected $meaningAttribute;

	private $withinWords;
	private $collectionId;
	private $languageId;
	private $withinExternalIdentifiers;
	private $languageName;
	private $searchText;
	private $show;

	function SpecialDatasearch() {
		parent::__construct( 'DataSearch' );

		$request = $this->getRequest();
		$this->collectionId = $request->getInt( "collection" ); // default is 0
		$this->languageId = $request->getInt( "language" );
		$this->withinWords = $request->getBool( "within-words" ); // default is false
		$this->withinExternalIdentifiers = $request->getBool( "within-external-identifiers" );
		$this->languageName = languageIdAsText( $this->languageId );
		$this->searchText = $request->getText( 'search-text', null );
		$this->show = $request->getBool( 'show' );
	}

	function execute( $parameter ) {
		global $definedMeaningReferenceType ;

		$output = $this->getOutput();
		$output->setPageTitle( wfMsg( 'search' ) );

		$this->spellingAttribute = new Attribute( "found-word", wfMsg( 'datasearch_found_word' ), "short-text" );
		$this->languageAttribute = new Attribute( "language", wfMsg( 'ow_Language' ), "language" );

		$this->expressionStructure = new Structure( $this->spellingAttribute, $this->languageAttribute );
		$this->expressionAttribute = new Attribute( "expression", wfMsg( 'ow_Expression' ), $this->expressionStructure );

		$this->definedMeaningAttribute = new Attribute( WLD_DEFINED_MEANING, wfMsg( 'ow_DefinedMeaning' ), $definedMeaningReferenceType );
		$this->definitionAttribute = new Attribute( "definition", wfMsg( 'ow_Definition' ), "definition" );

		$this->meaningStructure = new Structure( $this->definedMeaningAttribute, $this->definitionAttribute );
		$this->meaningAttribute = new Attribute( "meaning", wfMsg( 'datasearch_meaning' ), $this->meaningStructure );

		$this->externalIdentifierAttribute = new Attribute( "external-identifier", wfMsg( 'datasearch_ext_identifier' ), "short-text" );
		$this->collectionAttribute = new Attribute( "collection", wfMsg( 'ow_Collection' ), $definedMeaningReferenceType );
		$this->collectionMemberAttribute = new Attribute( "collection-member", wfMsg( 'ow_CollectionMember' ), $definedMeaningReferenceType );

		$this->externalIdentifierMatchStructure = new Structure(
			$this->externalIdentifierAttribute,
			$this->collectionAttribute,
			$this->collectionMemberAttribute
		);


		$this->displayForm();

		if ( $this->show ) {
			$this->search();
		}
	}

	function displayForm() {
		global $wgSearchWithinWordsDefaultValue, $wgSearchWithinExternalIdentifiersDefaultValue,
			$wgShowSearchWithinExternalIdentifiersOption, $wgShowSearchWithinWordsOption;

		$output = $this->getOutput();

		if ( ! $this->withinWords && ! $this->withinExternalIdentifiers ) {
			$this->withinWords = $wgSearchWithinWordsDefaultValue;
			$this->withinExternalIdentifiers = $wgSearchWithinExternalIdentifiersDefaultValue;
		}

		$options = array();
		$options[wfMsg( 'datasearch_search_text' )] = getTextBox( 'search-text', $this->searchText );

		$options[wfMsg( 'datasearch_language' )]
			= getSuggest( 'language', "language", array(), $this->languageId, $this->languageName );

		$options[wfMsg( 'ow_Collection_colon' )]
			= getSuggest( 'collection', 'collection', array(), $this->collectionId, collectionIdAsText( $this->collectionId ) );

		if ( $wgShowSearchWithinWordsOption ) {
			$options[wfMsg( 'datasearch_within_words' )] = getCheckBox( 'within-words', $this->withinWords );
		} else {
			$this->withinWords = $wgSearchWithinWordsDefaultValue;
		}

		if ( $wgShowSearchWithinExternalIdentifiersOption ) {
			$options[wfMsg( 'datasearch_within_ext_ids' )]
				= getCheckBox( 'within-external-identifiers', $this->withinExternalIdentifiers );
		} else {
			$this->withinExternalIdentifiers = $wgSearchWithinExternalIdentifiersDefaultValue;
		}
		$output->addHTML( getOptionPanel( $options ) );
	}

	function search() {
		$output = $this->getOutput();
		if ( $this->withinWords ) {
			if ( $this->languageId != 0 && $this->languageName != "" ) {
				$output->addHTML( '<h1>' . wfMsg( 'datasearch_match_words_lang', $this->languageName, $this->searchText ) . '</h1>' );
			} else {
				$output->addHTML( '<h1>' . wfMsg( 'datasearch_match_words', $this->searchText ) . '</h1>' );
			}
			$resultCount = $this->searchWordsCount() ;
			$output->addHTML( '<p>' . wfMsgExt( 'datasearch_showing_only', 'parsemag', 100 , $resultCount ) . '</p>' );

			$output->addHTML( $this->searchWords() );
		}

		if ( $this->withinExternalIdentifiers ) {
			$output->addHTML( '<h1>' . wfMsg( 'datasearch_match_ext_ids', $this->searchText ) . '</i></h1>' );

			$resultCount = $this->searchExternalIdentifiersCount();
			$output->addHTML( '<p>' . wfMsgExt( 'datasearch_showing_only', 'parsemag', 100, $resultCount) . '</p>' );

			$output->addHTML( $this->searchExternalIdentifiers() );
		}
	}

	function getSpellingRestriction( $spelling, $tableColumn ) {
		$dbr = wfGetDB( DB_SLAVE );

		if ( trim( $spelling ) != '' )
			return " AND " . $tableColumn . " LIKE " . $dbr->addQuotes( "%$spelling%" );
		else
			return "";
	}

	function getSpellingOrderBy( $spelling ) {
		if ( trim( $spelling ) != '' )
			return "position ASC, ";
		else
			return "";
	}

	function getPositionSelectColumn( $spelling, $tableColumn ) {
		$dbr = wfGetDB( DB_SLAVE );

		if ( trim( $spelling ) != '' )
			return "INSTR(LCASE(" . $tableColumn . "), LCASE(" . $dbr->addQuotes( "$spelling" ) . ")) as position, ";
		else
			return "";
	}

	function searchWords() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		$sql =
			"SELECT " . $this->getPositionSelectColumn( $this->searchText, "{$dc}_expression.spelling" ) . " {$dc}_syntrans.defined_meaning_id AS defined_meaning_id, {$dc}_expression.spelling AS spelling, {$dc}_expression.language_id AS language_id " .
			"FROM {$dc}_expression, {$dc}_syntrans ";

		if ( $this->collectionId > 0 )
			$sql .= ", {$dc}_collection_contents ";

		$sql .=
			"WHERE {$dc}_expression.expression_id={$dc}_syntrans.expression_id AND {$dc}_syntrans.identical_meaning=1 " .
			" AND " . getLatestTransactionRestriction( "{$dc}_syntrans" ) .
			" AND " . getLatestTransactionRestriction( "{$dc}_expression" ) .
			$this->getSpellingRestriction( $this->searchText, 'spelling' );

		if ( $this->collectionId > 0 )
			$sql .=
				" AND {$dc}_collection_contents.member_mid={$dc}_syntrans.defined_meaning_id " .
				" AND {$dc}_collection_contents.collection_id=" . $this->collectionId .
				" AND " . getLatestTransactionRestriction( "{$dc}_collection_contents" );

		if ( $this->languageId > 0 )
			$sql .=
				" AND {$dc}_expression.language_id={$this->languageId}";

		$sql .=
			" ORDER BY " . $this->getSpellingOrderBy( $this->searchText ) . "{$dc}_expression.spelling ASC limit 100";

		$queryResult = $dbr->query( $sql );
		$recordSet = $this->getWordsSearchResultAsRecordSet( $queryResult );
		$editor = $this->getWordsSearchResultEditor();

		return $editor->view( new IdStack( "words" ), $recordSet );
	}

	/**
	 * Gives the exact number of results (not limited to 100)
	 */
	function searchWordsCount() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		$table = array(
			'exp' => "{$dc}_expression",
			'synt' => "{$dc}_syntrans"
		);

		$wherecond = array(
			'exp.expression_id = synt.expression_id',
			'synt.identical_meaning' => 1,
			'exp.remove_transaction_id' => null,
			'synt.remove_transaction_id' => null
		);

		if ( $this->searchText ) {
			$wherecond[] = 'exp.spelling ' . $dbr->buildLike( $this->searchText, $dbr->anyString() );
		}

		if ( $this->languageId > 0 ) {
			$wherecond['exp.language_id'] = $this->languageId;
		}

		if ( $this->collectionId > 0 ) {
			$table['colcont'] = "{$dc}_collection_contents";
			$wherecond[] = 'colcont.member_mid = synt.defined_meaning_id';
			$wherecond['colcont.collection_id'] = $this->collectionId;
			$wherecond['colcont.remove_transaction_id'] = null;
		}

		$queryResultCount = $dbr->selectField(
			$table,
			'COUNT(*)',
			$wherecond,
			__METHOD__
		);

		return $queryResultCount ;
	}




	function getWordsSearchResultAsRecordSet( $queryResult ) {

		$o = OmegaWikiAttributes::getInstance();

		$dbr = wfGetDB( DB_SLAVE );
		$recordSet = new ArrayRecordSet( new Structure( $o->definedMeaningId, $this->expressionAttribute, $this->meaningAttribute ), new Structure( $o->definedMeaningId ) );

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$expressionRecord = new ArrayRecord( $this->expressionStructure );
			$expressionRecord->setAttributeValue( $this->spellingAttribute, $row->spelling );
			$expressionRecord->setAttributeValue( $this->languageAttribute, $row->language_id );

			$meaningRecord = new ArrayRecord( $this->meaningStructure );
			$meaningRecord->setAttributeValue( $this->definedMeaningAttribute, getDefinedMeaningReferenceRecord( $row->defined_meaning_id ) );
			$meaningRecord->setAttributeValue( $this->definitionAttribute, getDefinedMeaningDefinition( $row->defined_meaning_id ) );

			$recordSet->addRecord( array( $row->defined_meaning_id, $expressionRecord, $meaningRecord ) );
		}

		return $recordSet;
	}

	function getWordsSearchResultEditor() {

		$expressionEditor = new RecordTableCellEditor( $this->expressionAttribute );
		$expressionEditor->addEditor( new SpellingEditor( $this->spellingAttribute, new SimplePermissionController( false ), false ) );
		$expressionEditor->addEditor( new LanguageEditor( $this->languageAttribute, new SimplePermissionController( false ), false ) );

		$meaningEditor = new RecordTableCellEditor( $this->meaningAttribute );
		$meaningEditor->addEditor( new DefinedMeaningReferenceEditor( $this->definedMeaningAttribute, new SimplePermissionController( false ), false ) );
		$meaningEditor->addEditor( new TextEditor( $this->definitionAttribute, new SimplePermissionController( false ), false, true, 75 ) );

		$editor = createTableViewer( null );
		$editor->addEditor( $expressionEditor );
		$editor->addEditor( $meaningEditor );

		return $editor;
	}

	function searchExternalIdentifiers() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		$sql =
			"SELECT " . $this->getPositionSelectColumn( $this->searchText, "{$dc}_collection_contents.internal_member_id" ) . " {$dc}_collection_contents.member_mid AS member_mid, {$dc}_collection_contents.internal_member_id AS external_identifier, {$dc}_collection.collection_mid AS collection_mid " .
			"FROM {$dc}_collection_contents, {$dc}_collection ";

			$sql .=
			"WHERE {$dc}_collection.collection_id={$dc}_collection_contents.collection_id " .
			" AND " . getLatestTransactionRestriction( "{$dc}_collection" ) .
			" AND " . getLatestTransactionRestriction( "{$dc}_collection_contents" ) .
			$this->getSpellingRestriction( $this->searchText, "{$dc}_collection_contents.internal_member_id" );

		if ( $this->collectionId > 0 )
			$sql .=
				" AND {$dc}_collection.collection_id={$this->collectionId} ";

		$sql .=
			" ORDER BY " . $this->getSpellingOrderBy( $this->searchText ) . "{$dc}_collection_contents.internal_member_id ASC limit 100";

		$queryResult = $dbr->query( $sql );
		$recordSet = $this->getExternalIdentifiersSearchResultAsRecordSet( $queryResult );
		$editor = $this->getExternalIdentifiersSearchResultEditor();

		return $editor->view( new IdStack( "external-identifiers" ), $recordSet );
	}

	function searchExternalIdentifiersCount() {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );

		$table = array(
			'colcont' => "{$dc}_collection_contents",
			'col' => "{$dc}_collection"
		);

		$wherecond = array(
			'col.collection_id = colcont.collection_id',
			'col.remove_transaction_id' => null,
			'colcont.remove_transaction_id' => null
		);

		if ( $this->searchText ) {
			$wherecond[] = 'colcont.internal_member_id ' . $dbr->buildLike( $this->searchText, $dbr->anyString() );
		}

		if ( $this->collectionId > 0 ) {
			$wherecond['colcont.collection_id'] = $this->collectionId;
		}

		$queryResultCount = $dbr->selectField(
			$table,
			'COUNT(*)',
			$wherecond,
			__METHOD__
		);

		return $queryResultCount ;

	}

	function getExternalIdentifiersSearchResultAsRecordSet( $queryResult ) {
		$dbr = wfGetDB( DB_SLAVE );

		$externalIdentifierMatchStructure = new Structure( $this->externalIdentifierAttribute, $this->collectionAttribute, $this->collectionMemberAttribute );
		$recordSet = new ArrayRecordSet( $externalIdentifierMatchStructure, new Structure( $this->externalIdentifierAttribute ) );

		while ( $row = $dbr->fetchObject( $queryResult ) ) {
			$record = new ArrayRecord( $this->externalIdentifierMatchStructure );
			$record->setAttributeValue( $this->externalIdentifierAttribute, $row->external_identifier );
			$record->setAttributeValue( $this->collectionAttribute, $row->collection_mid );
			$record->setAttributeValue( $this->collectionMemberAttribute, $row->member_mid );

			$recordSet->add( $record );
		}

		expandDefinedMeaningReferencesInRecordSet( $recordSet, array( $this->collectionAttribute, $this->collectionMemberAttribute ) );

		return $recordSet;
	}

	function getExternalIdentifiersSearchResultEditor() {
		$editor = createTableViewer( null );
		$editor->addEditor( createShortTextViewer( $this->externalIdentifierAttribute ) );
		$editor->addEditor( createDefinedMeaningReferenceViewer( $this->collectionMemberAttribute ) );
		$editor->addEditor( createDefinedMeaningReferenceViewer( $this->collectionAttribute ) );

		return $editor;
	}
}
