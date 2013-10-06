<?php

require_once( "forms.php" );
require_once( "Transaction.php" );
require_once( "OmegaWikiAttributes.php" );
require_once( "WikiDataAPI.php" );
require_once( "Utilities.php" );

class DefaultWikidataApplication {
	protected $title;

	protected $showRecordLifeSpan;
	protected $transaction;
	protected $queryTransactionInformation;
	protected $showCommunityContribution;

	// The following member variables control some application specific preferences
	protected $showClassicPageTitles = true;	// Show classic page titles instead of prettier page titles

	protected $propertyToColumnFilters = array();
	protected $viewInformation;

	// Show a panel to select expressions from available data-sets
	protected $showDataSetPanel = false;

	public function __construct( $title ) {
		global $wgWldShowClassicPageTitles, $wgPropertyToColumnFilters;

		$this->title = $title;

		if ( isset( $wgWldShowClassicPageTitles ) ) {
			$this->showClassicPageTitles = $wgWldShowClassicPageTitles;
		}
		if ( isset( $wgPropertyToColumnFilters ) ) {
			$this->propertyToColumnFilters = $wgPropertyToColumnFilters;
		}
	}

	public function getTitle() {
		return $this->title;
	}

	protected function outputViewHeader() {
		global $wgOut;

		if ( $this->showDataSetPanel ) {
			$wgOut->addHTML( $this->getDataSetPanel() );
		}
	}

	public function view() {
		wfProfileIn( __METHOD__ );
		global $wgOut, $wgUser;

		$wgOut->enableClientCache( true );

		$myTitle = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles ) {
			$myTitle = $this->title;
		}

		$wgOut->setPageTitle( $myTitle );

		$this->queryTransactionInformation = new QueryLatestTransactionInformation();

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = false;
		$viewInformation->queryTransactionInformation = $this->queryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		// Not clear why this is here. Works well without.
		// initializeOmegaWikiAttributes( $viewInformation );
		// initializeObjectAttributeEditors( $viewInformation );
		wfProfileOut( __METHOD__ );
	}

	protected function getDataSetPanel() {
		global $wgUser;
		$dc = wdGetDataSetContext();
		$ow_datasets = wfMessage( "ow_datasets" )->text();
		$html = "<div class=\"dataset-panel\">"; ;
		$html .= "<table border=\"0\"><tr><th class=\"dataset-panel-heading\">$ow_datasets</th></tr>";
		$dataSets = wdGetDataSets();
		$sk = $wgUser->getSkin();
		foreach ( $dataSets as $dataset ) {
			$active = ( $dataset->getPrefix() == $dc->getPrefix() );
			$name = $dataset->fetchName();
			$prefix = $dataset->getPrefix();

			$class = $active ? 'dataset-panel-active' : 'dataset-panel-inactive';
			$slot = $active ? "$name" : $sk->makeLinkObj( $this->title, $name, "dataset=$prefix" );
			$html .= "<tr><td class=\"$class\">$slot</td></tr>";
		}
		$html .= "</table>";
		$html .= "</div>";
		return $html;
	}

	protected function save( $referenceQueryTransactionInformation ) {
		$viewInformation = new ViewInformation();
		$viewInformation->queryTransactionInformation = $referenceQueryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );
		$viewInformation->viewOrEdit = "edit";

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $this->viewInformation );
	}

	public function saveWithinTransaction() {
		global $wgUser, $wgRequest;

		$summary = $wgRequest->getText( 'summary' );

		// Insert transaction information into the DB
		startNewTransaction( $wgUser->getID(), wfGetIP(), $summary );

		// Perform regular save
		$this->save( new QueryAtTransactionInformation( $wgRequest->getInt( 'transaction' ), false ) );

		// Update page caches
		$this->title->invalidateCache();

		// Add change to RC log
		$now = wfTimestampNow();
		RecentChange::notifyEdit( $now, $this->title, false, $wgUser, $summary, 0, $now, false );
	}

	/**
	 * @return true if permission to edit, false if not
	 */
	public function edit() {
		global $wgOut, $wgRequest, $wgUser;

		$wgOut->enableClientCache( false );

		if ( $wgUser->isBlockedFrom( $this->getTitle(), false ) ) {
			$wgOut->blockedPage() ;
			return false;
		}

		$dc = wdGetDataSetContext();
		if ( !$wgUser->isAllowed( 'editwikidata-' . $dc ) ) {
			$wgOut->addWikiText( wfMessage( "ow_noedit", $dc->fetchName() )->text() );
			$wgOut->setPageTitle( wfMessage( "ow_noedit_title" )->text() );
			return false;
		}

		if ( $wgRequest->getText( 'save' ) != '' )
			$this->saveWithinTransaction();

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = false;
		$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
		$viewInformation->viewOrEdit = "edit";
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $this->viewInformation );

		return true;
	}

	public function history() {
		global $wgOut, $wgRequest;

		$wgOut->enableClientCache( false );

		$title = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles )
			$title = $this->title;

		$wgOut->setPageTitle( wfMessage( "ow_history", $title )->text() );

		# Plain filter for the lifespan info about each record
		if ( isset( $_GET['show'] ) ) {
			$this->showRecordLifeSpan = isset( $_GET["show-record-life-span"] );
			$this->transaction = (int) $_GET["transaction"];
		}
		else {
			$this->showRecordLifeSpan = true;
			$this->transaction = 0;
		}

		# Up to which transaction to view the data
		if ( $this->transaction == 0 )
			$this->queryTransactionInformation = new QueryHistoryTransactionInformation();
		else
			$this->queryTransactionInformation = new QueryAtTransactionInformation( $this->transaction, $this->showRecordLifeSpan );

		$transactionId = $wgRequest->getInt( 'transaction' );

		$wgOut->addHTML( getOptionPanel(
			array(
				wfMessage( 'ow_history_transaction' )->text() => getSuggest( 'transaction', 'transaction', array(), $transactionId, getTransactionLabel( $transactionId ), array( 0, 2, 3 ) ),
				wfMessage( 'ow_history_show_life_span' )->text() => getCheckBox( 'show-record-life-span', $this->showRecordLifeSpan )
			),
			'history'
		) );

		$viewInformation = new ViewInformation();
		$viewInformation->showRecordLifeSpan = $this->showRecordLifeSpan;
		$viewInformation->queryTransactionInformation = $this->queryTransactionInformation;
		$viewInformation->setPropertyToColumnFilters( $this->propertyToColumnFilters );

		$this->viewInformation = $viewInformation;

		initializeOmegaWikiAttributes( $this->viewInformation );
		initializeObjectAttributeEditors( $viewInformation );
	}

	protected function outputEditHeader() {
		global $wgOut, $wgUser;

		$title = $this->title->getPrefixedText();

		if ( !$this->showClassicPageTitles )
			$title = $this->title;

		$wgOut->setPageTitle( $title );
		$wgOut->setPageTitle( wfMessage( "editing", $title )->text() );
		if ( $wgUser->isAnon() ) {
			$wgOut->wrapWikiMsg( "<div id=\"mw-anon-edit-warning\">\n$1</div>", 'anoneditwarning' );
		}

		$wgOut->addHTML(
			'<form method="post" action="">' .
			'<input type="hidden" name="transaction" value="' . getLatestTransactionId() . '"/>'
		);
	}

	protected function outputEditFooter() {
		global $wgOut, $wgUser;

		$wgOut->addHTML(
			'<div class="option-panel">' .
				'<table cellpadding="0" cellspacing="0"><tr>' .
					'<th>' . wfMessage( "summary" )->text() . '</th>' .
					'<td class="option-field">' . getTextBox( "summary" ) . '</td>' .
				'</tr></table>' .
				getSubmitButton( "save", wfMessage( "ow_save" )->text() ) .
			'</div>'
		);

		$wgOut->addHTML( '</form>' );

		if ( $wgUser->isAnon() ) {
			$wgOut->wrapWikiMsg( "<div id=\"mw-anon-edit-warning\">\n$1</div>", 'anoneditwarning' );
		}
	}
}


class DataSet {

	private $dataSetPrefix;
	private $isValidPrefix = false;
	private $fallbackName = '';
	private $dmId = 0; # the dmId of the dataset name

	public function getPrefix() {
		return $this->dataSetPrefix;
	}

	public function isValidPrefix() {
		return $this->isValidPrefix;
	}

	public function setDefinedMeaningId( $dmid ) {
		$this->dmId = $dmid;
	}
	public function getDefinedMeaningId() {
		return $this->dmId;
	}

	public function setValidPrefix( $isValid = true ) {
		$this->isValidPrefix = $isValid;
	}

	public function setPrefix( $cp ) {

		$fname = "DataSet::setPrefix";

		$dbs = wfGetDB( DB_SLAVE );
		$this->dataSetPrefix = $cp;
		$sql = "select * from wikidata_sets where set_prefix=" . $dbs->addQuotes( $cp );
		$res = $dbs->query( $sql );
		$row = $dbs->fetchObject( $res );
		if ( $row->set_prefix ) {
			$this->setValidPrefix();
			$this->setDefinedMeaningId( $row->set_dmid );
			$this->setFallbackName( $row->set_fallback_name );
		} else {
			$this->setValidPrefix( false );
		}
	}

	// Fetch!
	function fetchName() {
		global $wgLang, $wdTermDBDataSet;
		if ( $wdTermDBDataSet ) {
			$userLanguage = $wgLang->getCode() ;
			$spelling = getSpellingForLanguage( $this->dmId, $userLanguage, 'en', $wdTermDBDataSet );
			if ( $spelling ) return $spelling;
		}
		return $this->getFallbackName();
	}

	public function getFallbackName() {
		return $this->fallbackName;
	}

	public function setFallbackName( $name ) {
		$this->fallbackName = $name;
	}

	function __toString() {
		return $this->getPrefix();
	}

}
