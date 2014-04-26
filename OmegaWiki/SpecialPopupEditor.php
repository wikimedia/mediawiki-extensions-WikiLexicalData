<?php

if ( !defined( 'MEDIAWIKI' ) ) die();


/**
* Creates and return the html code corresponding to a Popup editor
* typically for annotations
*/
class SpecialPopUpEditor extends SpecialPage {

	function __construct() {
		parent::__construct( 'PopupEditor', 'UnlistedSpecialPage' );
	}

	function execute( $par ) {

		require_once( 'WikiDataGlobals.php' );
		require_once( 'ViewInformation.php' );
		require_once( 'OmegaWikiAttributes.php' );
		require_once( 'OmegaWikiEditors.php' );
		require_once( 'OmegaWikiRecordSets.php' );
		require_once( 'Transaction.php' );
		require_once( 'WikiDataTables.php' );

		$o = OmegaWikiAttributes::getInstance();

		// disable standard output
		$this->getOutput()->disable();
		$output = '';

		// get the variables from request
		$request = $this->getRequest();

		// view or edit or history, default: view
		$action = $request->getVal( 'action', 'view' );

		// exists if we edit Syntrans attributes
		// for the moment we do Syntrans attributes, so we test its existence
		$syntransId = $request->getVal( 'syntransid', '' );
		if ( $syntransId === '' ) {
			echo "syntransId undefined\n";
			die();
		}

		// always exists
		$definedMeaningId = $request->getVal( 'dmid', '' );
		if ( $definedMeaningId === '' ) {
			echo "definedMeaningId undefined\n";
			die();
		}

		// do we need $idPathFlat or can we put a dummy string?
		// $idPathLocal = new IdStack( '' );
		// for the moment we need it because we use the "old" functions to save
		$idPathFlat = $request->getVal( 'idpathflat', '' );
		if ( $idPathFlat === '' ) {
			echo "idPathFlat undefined\n";
			die();
		}

		// create new basic idStack
		$idPathLocal = $this->getDmSyntIdStack( $definedMeaningId, $syntransId );

		// start building the output
		$id = 'popup-' . $idPathFlat . '-toggleable' ;
		$output .= Html::openElement ('div', array( 'class' => "popupToggleable", 'id' => $id ));
		if ( $action !== 'history' ) {
			// show edit, save, cancel buttons in view and edit mode
			// but not in history mode
			$output .= $this->getHtmlButtons();
		}


		// ViewInformation and attributes
		$viewInformation = new ViewInformation();

		switch ( $action ) {
			case 'view':
				// $viewInformation->viewOrEdit = 'view';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'edit':
				$viewInformation->viewOrEdit = 'edit';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'save':
				$viewInformation->viewOrEdit = 'edit';
				$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();
				break;
			case 'history':
				// $viewInformation->viewOrEdit = 'view';
				$viewInformation->queryTransactionInformation = new QueryHistoryTransactionInformation();
				$viewInformation->showRecordLifeSpan = true;
				break;
		} // switch action


		// Creating the editor
		$syntransAttributesEditor = $this->getSyntransAttributesEditor( $viewInformation, $o );

		// the values (ArrayRecord) to fill the editor
		$recordArray = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

		if ( $action === 'edit' ) {
			// EDIT
			$output .= $syntransAttributesEditor->edit( $idPathLocal, $recordArray );

			// in edit mode, add buttons at the bottom as well
			$output .= $this->getHtmlButtons();

		} elseif ( $action === 'save' ) {
			// SAVE
			// we don't call startNewTransaction here, it is called directly
			// inside the function when something to save is found.
			$syntransAttributesEditor->save( $idPathLocal, $recordArray );

			// Add change to RC log
			$this->updateRecentChange( $syntransId );

			// switch to view mode and refresh the values, according to what was saved
			$viewInformation->viewOrEdit = 'view';
			$syntransAttributesEditor = $this->getSyntransAttributesEditor( $viewInformation, $o );
			$recordArray = getObjectAttributesRecord( $syntransId, $viewInformation, null, "SYNT" );

			// and view the result
			$output .= $syntransAttributesEditor->view( $idPathLocal, $recordArray );

		} else {
			// VIEW (default)
			$output .= $syntransAttributesEditor->view( $idPathLocal, $recordArray );
		}

		$output .= Html::closeElement ('div');

		echo $output;
	}

	/**
	 * create back the idStack
	 */
	protected function getDmSyntIdStack( $definedMeaningId, $syntransId ) {
		$o = OmegaWikiAttributes::getInstance();

		// the result should look like dm-1487027-syntrans-1487033-objAtt

		// level1: DM
		$definedMeaningIdStructure = new Structure( $o->definedMeaningId );
		$definedMeaningIdRecord = new ArrayRecord( $definedMeaningIdStructure );
		$definedMeaningIdRecord->definedMeaningId = $definedMeaningId;
		
		$idStack = new IdStack( WLD_DEFINED_MEANING );
		$idStack->pushKey( $definedMeaningIdRecord );
		
		$idStack->pushDefinedMeaningId( $definedMeaningId );
		$idStack->pushClassAttributes( new ClassAttributes( $definedMeaningId ) );

		// level2: Syntrans
		$syntransIdStructure = new Structure( $o->syntransId );
		$syntransIdRecord = new ArrayRecord( $syntransIdStructure );
		$syntransIdRecord->syntransId = $syntransId;
//		$idStack->pushAttribute( $o->syntransId );
		$idStack->pushAttribute( $o->synonymsAndTranslations );

//		$idStack->pushKey( project( $syntransIdRecord, $o->synonymsTranslationsStructure ) );
		$idStack->pushKey( $syntransIdRecord );
		$idStack->pushAttribute( $o->objectAttributes );

		return $idStack;
	}

	/**
	 * creates the hierarchical structure editor
	 * for view or edit mode according to viewInformation
	 */
	protected function getSyntransAttributesEditor( $viewInformation, $o ) {
		// the editor is of the class ObjectAttributeValuesEditor defined in WrappingEditor.php
		// it wraps a RecordUnorderedListEditor defined in Editor.php
		$syntransAttributesEditor = createObjectAttributesEditor(
			$viewInformation,
			$o->objectAttributes,
			wfMessage( "ow_Property" )->text(),
			wfMessage( "ow_Value" )->text(),
			$o->syntransId,
			WLD_SYNTRANS_MEANING_NAME,
			$viewInformation->getLeftOverAttributeFilter()
		);
		return $syntransAttributesEditor;
	}

	/**
	 * returns the html for the buttons edit, save, cancel
	 */
	protected function getHtmlButtons() {
		$htmlButtons = Html::openElement ('div', array( 'class' => "popupButtons" ));

		$editButton = '[' . wfMessage('edit')->plain() . ']';
		$saveButton = '[' . wfMessage('ow_save')->plain() . ']';
		$cancelButton = '[' . wfMessage('cancel')->plain() . ']';
		$htmlButtons .= Html::rawElement ('span', array( 'class' => "owPopupEdit" ), $editButton );
		$htmlButtons .= Html::rawElement ('span', array( 'class' => "owPopupSave" ), $saveButton );
		$htmlButtons .= Html::rawElement ('span', array( 'class' => "owPopupCancel" ), $cancelButton );

		$htmlButtons .= Html::closeElement ('div');

		return $htmlButtons;
	}

	/**
	 * adds a line in recentchanges to notify that annotations were edited
	 */
	protected function updateRecentChange( $syntransId ) {
		global $wgUser;
		$now = wfTimestampNow();
		$summary = 'Edited annotations via popup';
		
		$expressionId = getExpressionIdFromSyntrans( $syntransId );
		$expression = getExpression( $expressionId );
		$spelling = $expression->spelling;
		$expressionTitle = Title::makeTitle( NS_EXPRESSION , $spelling );

		RecentChange::notifyEdit( $now, $expressionTitle, false, $wgUser, $summary, 0, $now, false );
	}
}
