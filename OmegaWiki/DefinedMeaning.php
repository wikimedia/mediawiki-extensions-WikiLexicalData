<?php
/** @file
 */
require_once( 'IdStack.php' );
require_once( 'Wikidata.php' );
require_once( 'OmegaWikiRecordSets.php' );
require_once( 'OmegaWikiEditors.php' );
require_once( 'DefinedMeaningModel.php' );

/**
 * @brief The Defined Meaning Namespace class
 */
class DefinedMeaning extends DefaultWikidataApplication {
	protected $definedMeaningModel;

	public function view() {
		global
			$wgOut, $wgTitle, $wgRequest;

		// Split title into defining expression and ID
		$titleText = $wgTitle->getText();
		$dmInfo = DefinedMeaningModel::splitTitleText( $titleText );

		// WikiData compatibility. Using title with dmid only
		if ( is_null( $dmInfo ) && is_numeric( $titleText ) ) {
			$dmInfo = array(
				"expression" => null,
				"id" => $titleText
			);
		}

		$dmNumber = $dmInfo["id"];

		// Title doesn't have an ID in it (or ID 0)
		if ( is_null( $dmInfo ) || !$dmNumber ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}
		parent::view();
		$this->definedMeaningModel = new DefinedMeaningModel( $dmNumber, array( "viewinformation" => $this->viewInformation ) );

		if ( !empty( $dmInfo["expression"] ) ) {
			$this->definedMeaningModel->setDefiningExpression( $dmInfo["expression"] );
		}


		// check that the constructed DM actually exists in the database
		$match = $this->definedMeaningModel->checkExistence( true, true );

		// The defining expression is likely incorrect for some reason. Let's just
		// try looking up the number.
		if ( is_null( $match ) && !empty( $dmInfo["expression"] ) ) {
			$this->definedMeaningModel->setDefiningExpression( null );
			$dmInfo["expression"] = null;
			$match = $this->definedMeaningModel->checkExistence( true, true );
		}

		// The defining expression is either bad or missing. Let's redirect
		// to the correct URL.
		if ( empty( $dmInfo["expression"] ) && !is_null( $match ) ) {
			$this->definedMeaningModel->loadRecord();
			$title = Title::newFromText( $this->definedMeaningModel->getWikiTitle() );
			$url = $title->getFullURL();
			$wgOut->disable();
			header( "Location: $url" );
			return false;
		}

		// Bad defining expression AND bad ID! :-(
		if ( is_null( $match ) ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		$this->definedMeaningModel->loadRecord();
		$this->showDataSetPanel = false;

		# Raw mode
		$view_as = $wgRequest->getText( 'view_as' );
		if ( $view_as == "raw" ) {
			$wgOut->addHTML( "<pre>" . $this->definedMeaningModel->getRecord() . "</pre>" );
			# $wgOut->disable();
			return;
		}

		$this->outputViewHeader();
// concept panel is annoying and useless
//		$wgOut->addHTML( $this->getConceptPanel() );
		$expressionTranslated = definedMeaningExpression( $this->definedMeaningModel->getId() ) ;

		// @note Since the defined meaning expression can be misleading and
		// the software is now able to redirect using the dm id only, it seems logical
		// to replace the full dm title and replace it with the id. ~he
		// @todo Maybe in the future, $expressionTranslated will default to the user
		// language or preferred language. ~he
		$wgOut->setPageTitle( "DefinedMeaning:{$dmInfo['id']} - $expressionTranslated" ) ;

		$editor = getDefinedMeaningEditor( $this->viewInformation );
		$idStack = $this->getIdStack( $this->definedMeaningModel->getId() );
		$html = $editor->view( $idStack, $this->definedMeaningModel->getRecord() );
		$wgOut->addHTML( $html );
	}

	public function edit() {
		global
			$wgOut, $wgTitle;

		if ( !parent::edit() ) return false;

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );

		// Title doesn't have an ID in it (or ID 0)
		if ( !$definedMeaningId ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		$this->outputEditHeader();
		$dmModel = new DefinedMeaningModel( $definedMeaningId, array( "viewinformation" => $this->viewInformation ) );

		// check that the constructed DM actually exists in the database
		$match = $dmModel->checkExistence( true, true );
		if ( is_null( $match ) ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		if ( is_null( $dmModel->getRecord() ) ) {
			$wgOut->addHTML( wfMessage( "ow_db_consistency__not_found" )->text() . " ID:$definedMeaningId" );
			return;
		}

		$wgOut->addHTML(
			getDefinedMeaningEditor( $this->viewInformation )->edit(
				$this->getIdStack( $dmModel->getId() ),
				$dmModel->getRecord()
			)
		);
		$this->outputEditFooter();
	}

	public function history() {
		global $wgOut, $wgTitle ;

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
		// Title doesn't have an ID in it (or ID 0)
		if ( !$definedMeaningId ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		parent::history();

		$dmModel = new DefinedMeaningModel( $definedMeaningId, array ( "viewinformation" => $this->viewInformation ) );

		// check that the constructed DM actually exists in the database
		$match = $dmModel->checkExistence( true, true );
		if ( is_null( $match ) ) {
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_missing' );
			return false;
		}

		$dmIdStack = $this->getIdStack( $definedMeaningId );
		$dmRecord = $dmModel->getRecord();
		$dmEditor = getDefinedMeaningEditor( $this->viewInformation );
		$wgOut->addHTML( $dmEditor->view( $dmIdStack, $dmRecord ) );
	}

	protected function save( $referenceQueryTransactionInformation ) {
		global
			$wgTitle;

		parent::save( $referenceQueryTransactionInformation );

		$definedMeaningId = $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
		if ( !$definedMeaningId ) {
			// Title doesn't have an ID in it (or ID 0)
			$wgOut->showErrorPage( 'errorpagetitle', 'ow_dm_badtitle' );
			return false;
		}

		$dmModel = new DefinedMeaningModel( $definedMeaningId, array ( "viewinformation" => $this->viewInformation ) );

		getDefinedMeaningEditor( $this->viewInformation )->save(
			$this->getIdStack( $definedMeaningId ),
			$dmModel->getRecord()
		);

	}

	public function getTitle() {
		global $wgTitle;
		return $wgTitle->getFullText() ;
	}

	protected function getIdStack( $definedMeaningId ) {

		$o = OmegaWikiAttributes::getInstance();

		$definedMeaningIdStructure = new Structure( $o->definedMeaningId );
		$definedMeaningIdRecord = new ArrayRecord( $definedMeaningIdStructure, $definedMeaningIdStructure );
		$definedMeaningIdRecord->definedMeaningId = $definedMeaningId;

		$idStack = new IdStack( WLD_DEFINED_MEANING );
		$idStack->pushKey( $definedMeaningIdRecord );

		return $idStack;
	}

	protected function getDefinedMeaningIdFromTitle( $title ) {
		// get id from title: DefinedMeaning:expression (id)
		$bracketPosition = strrpos( $title, "(" );
		$definedMeaningId = substr( $title, $bracketPosition + 1, strlen( $title ) - $bracketPosition - 2 );
		return $definedMeaningId;
	}

	public function getDefinedMeaningId() {
		global
			$wgTitle;
		return $this->getDefinedMeaningIdFromTitle( $wgTitle->getText() );
	}

	/**
	 * Creates sidebar HTML for indicating concepts which exist
	 * in multiple datasets, and providing a link to add new
	 * mappings.
	 *
	 * Potential refactor candidate!
	 */
	protected function getConceptPanel() {
		global $wgTitle, $wdShowCopyPanel;
		$active = true; # wrong place, but hey
		$dmId = $this->getDefinedMeaningId();
		$dc = wdGetDataSetContext();
		$ow_conceptpanel = wfMessage( "ow_concept_panel" )->text();

		$html = "<div class=\"dataset-panel\">"; ;
		$html .= "<table border=\"0\"><tr><th class=\"dataset-panel-heading\">$ow_conceptpanel</th></tr>";
		$meanings = getDefinedMeaningDataAssociatedByConcept( $dmId, $dc );
		if ( $meanings ) {
			foreach ( $meanings as $dm ) {
				$dataset = $dm->getDataset();
				$active = ( $dataset->getPrefix() == $dc->getPrefix() );
				$name = $dataset->fetchName();
				$prefix = $dataset->getPrefix();

				$class = $active ? 'dataset-panel-active' : 'dataset-panel-inactive';
				$slot = $active ? "$name" : Linker::makeLinkObj( $dm->getTitleObject(), $name, "dataset=$prefix" );
				$html .= "<tr><td class=\"$class\">$slot</td></tr>";
			}
		} else {
				$name = $dc->fetchName();
				$html .= "<tr><td class=\"dataset-panel-active\">$name</td></tr>";
		}
		$cmtitle = Title::newFromText( "Special:ConceptMapping" );
		$titleText = $wgTitle->getPrefixedURL();
		$cmlink = Linker::makeLinkObj( $cmtitle, "<small>" . wfMessage( "ow_add_concept_link" )->text() . "</small>", "set_$dc=$dmId&suppressWarnings=true" );
		$html .= "<tr><td>$cmlink</td></tr>\n";
		if ( $wdShowCopyPanel ) {
			$html .= "<tr><td>" . $this->getCopyPanel() . "<td><tr>";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		return $html;
	}

	/** @returns user interface html for copying Defined Meanings
	 * between datasets. returns an empty string if the user
	 * actually doesn't have permission to edit.
	 */
	protected function getCopyPanel() {

		# mostly same code as in SpecialAddCollection... possibly might
		# make a nice separate function

		global
			$wgUser;
		if ( !$wgUser->isAllowed( 'wikidata-copy' ) ) {
			return "";
		}

		$datasets = wdGetDatasets();
		$datasetarray[''] = wfMessage( 'ow_none_selected' )->text();
		foreach ( $datasets as $datasetid => $dataset ) {
			$datasetarray[$datasetid] = $dataset->fetchName();
		}

		/* Deprecated for now

		$html= getOptionPanel( array (
			'Copy to' => getSelect('CopyTo', $datasetarray)
		));
		*/
		$html = $this->getCopyPanel2();
		return $html;
	}

	/** links to futt bugly alternate copy mechanism, the
	 * latter being something that actually is somewhat
	 * understandable (though not yet refactored into
	 * something purdy and maintainable)
	 */
	protected function getCopyPanel2() {
		global
			$wgScriptPath, $wgCommunity_dc;

		$html = "Copy to:<br />\n";
		$datasets = wdGetDatasets();
		$dataset = $datasets[$wgCommunity_dc];
		$dmid = $this->definedMeaningModel->getId();
		$dc1 = $this->definedMeaningModel->getDataSet();
		$name = $dataset->fetchName();
		$dc2 = $wgCommunity_dc;
		$html .= "<a href='index.php?title=Special:Copy&action=copy&dmid=$dmid&dc1=$dc1&dc2=$dc2'>$name</a><br />\n";

		return $html;
	}

}

/** @brief PHP API class for Defined Meaning
 */
class DefinedMeanings {

	/**
	 * @brief Returns the defined_meaning table's DefinedMeaning id via translatedContentId
	 *
	 * @param translatedContentId req'd int The object id
	 * @param $options            opt'l arr An optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param $dc                 opt'l str The WikiLexicalData dataset
	 *
	 * @return array( meaning1_id, relationtype_mid, meaning2_mid)
	 * @return if not exists, array()
	 *
	 * @note options parameter can be used to extend this function.
	 * Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getTranslatedContentIdDefinedMeaningId instead.
	 * Also note that this function currently includes all data, even removed ones.
	 *
	 */
	public static function getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$test = false;
		if ( isset( $options['test'] ) ) {
			$test = true;
		}

		$definedMeaningId = $dbr->selectRow(
			"{$dc}_defined_meaning",
			'defined_meaning_id',
			array(
				'meaning_text_tcid' => $translatedContentId
			), __METHOD__
		);

		if ( $definedMeaningId ) {
			if ( $test ) { var_dump( $definedMeaningId ); die; }
			return $definedMeaningId;
		}
		if ( $test ) { echo 'array()'; die; }
		return array();
	}

	/**
	 * @param languageId req'd int The language id
	 * @param options    opt'l arr An optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param dc         opt'l str The WikiLexicalData dataset
	 *
	 * @return an array of "Defined Meaning Id" objects for a language
	 * @returns if not exists, null
	 */
	public static function getLanguageIdDefinedMeaningId( $languageId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'defined_meaning_id';
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
			'defined_meaning_id',
			array(
				'synt.expression_id = exp.expression_id',
				'language_id' => $languageId,
				'synt.remove_transaction_id' => null,
				'exp.remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$definedMeaningId = array();
		foreach ( $queryResult as $dm ) {
			$definedMeaningId[] = $dm->defined_meaning_id;
		}
		$queryResult = array();

		if ( $definedMeaningId ) {
			return $definedMeaningId;
		}
		return null;
	}

}
