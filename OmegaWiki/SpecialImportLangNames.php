<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

require_once 'Wikidata.php';
require_once 'WikiDataAPI.php';
require_once 'Transaction.php';
require_once 'languages.php';

class SpecialImportLangNames extends SpecialPage {
	function SpecialImportLangNames() {
		parent::__construct( 'ImportLangNames' );
	}

	public function doesWrites() {
		return true;
	}

	function execute( $par ) {
		global $wgIso639_3CollectionId;
		// These operations should always be on the community database.
		$dbr = wfGetDB( DB_REPLICA );
		$dbw = wfGetDB( DB_MASTER );
		$dc = wdGetDataSetContext();
		$output = $this->getOutput();

		$output->setPageTitle( wfMessage( 'importlangnames_title' )->text() );

		if ( ! $this->getUser()->isAllowed( 'languagenames' ) ) {
			$output->addHTML( wfMessage( 'importlangnames_not_allowed' )->text() );
			return false;
		}
		/* Get defined meaning IDs and ISO codes for languages in collection. */
		// wgIso639_3CollectionId is normally defined in LocalSettings.php
		$lang_res = $dbr->select(
			"{$dc}_collection_contents",
			[ 'member_mid' , 'internal_member_id' ],
			[
				'collection_id' => $wgIso639_3CollectionId,
				'remove_transaction_id' => null
			], __METHOD__
		);
		$editable = '';
		$first = true;
		foreach ( $lang_res as $lang_row ) {
			$iso_code = $lang_row->internal_member_id;
			$dm_id = $lang_row->member_mid;
			/*	Get the language ID for the current language. */
			$lang_id = getLanguageIdForIso639_3( $iso_code );

			if ( $lang_id ) {
				if ( !$first ) {
					$output->addHTML( '<br />' . "\n" );
				} else {
					$first = false;
				}
				$output->addHTML( wfMessage( 'importlangnames_added', $iso_code )->text() );

				/* Add current language to list of portals/DMs. */
				// select definingExpression of a DM
				$dm_expr = definingExpression( $dm_id );
				if ( $editable != '' ) {
					$editable .= "\n";
				}
				$editable .= '*[[Portal:' . $iso_code . ']] - [[DefinedMeaning:' . $dm_expr . ' (' . $dm_id . ')]]';

				/*	Delete all language names that match current language ID. */
				$dbw->delete(
					'language_names',
					[ 'language_id' => $lang_id ],
					__METHOD__
				);

				/* Get syntrans expressions for names of language and IDs for the languages the names are in. */
				$syntrans_res = $dbr->select(
					[ 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ],
					[ 'spelling', 'language_id' ],
					[
						'defined_meaning_id' => $dm_id,
						'exp.remove_transaction_id' => null
					], __METHOD__,
					[ 'GROUP BY' => 'language_id' ],
					[ 'synt' => [ 'JOIN', [
						'synt.expression_id = exp.expression_id',
						'synt.remove_transaction_id' => null
					] ] ]
				);

				foreach ( $syntrans_res as $syntrans_row ) {
					$dbw->insert(
						'language_names',
						[
							'language_id' => $lang_id,
							'name_language_id' => $syntrans_row->language_id,
							'language_name' => $syntrans_row->spelling
						], __METHOD__
					);
				}

			} else {
				// no $lang_id found
				if ( !$first ) {
					$output->addHTML( '<br />' . "\n" );
				} else {
					$first = false;
				}
				$output->addHTML( wfMessage( 'importlangnames_not_found', $iso_code )->text() );
			}
		}
		$this->addDMsListToPage( $editable, 'Editable_languages' );
	}

	function addDMsListToPage( $content, $title ) {
		$titleObj = Title::makeTitle( NS_MAIN, $title );
		$wikipage = new WikiPage( $titleObj );
		$wikipage->doEditContent(
			ContentHandler::makeContent( $content, $titleObj ),
			'updated via Special:ImportLangNames'
		);
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
