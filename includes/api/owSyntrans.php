<?php

/** O m e g a W i k i   A P I ' s   S y n t r a n s   c l a s s
 *
 * PARAMETERS
 *	@param	req'd	int	dm
 *
 * HISTORY
 * - 2013-06-04: Add basic structure
 *		@param	req'd	int	dm
 * - 2013-06-04: Creation date ~he
 *
 * TODO
 * - Integrate with Define Class
 * - Transfer getSynonymAndTranslation function to WikiDataAPI when ready.
 *
 * QUESTION
 * - none
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );
require_once( 'extensions/WikiLexicalData/OmegaWiki/OmegaWikiRecordSets.php' );

class SynonymTranslation extends ApiBase {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;
		$options = array();

		// Get the parameters
		$params = $this->extractRequestParams();

		// Required parameter
		// Check if dm is valid
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding syntrans is missing', 'param dm is missing' );
		} else {
			$syntrans['dm'] = $params['dm'];
			// check that defined_meaning_id exists
			if ( !verifyDefinedMeaningId( $syntrans['dm'] ) ) {
				$this->dieUsage( 'Non existent dm id (' . $syntrans['dm'] . ').', "dm not found." );
			}
		}

		// Optional parameter

		// get syntrans
		// When only dm is given
		$syntrans = $this->synTrans( $syntrans['dm'] );

		$this->getResult()->addValue( null, $this->getModuleName(), $syntrans );
		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'Get the definition of a defined meaning.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'dm' => array (
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be defined',
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'Get the synonyms and translations of a defined meaning id',
			'api.php?action=ow_syntrans&dm=8218',
		);
	}

	// Additional Functions

	/**
	 * Returns an array of syntrans via defined meaning id
	 * Returns null if empty
	 */
	private function synTrans ( $definedMeaningId ) {
		$stList = getSynonymAndTranslation( $definedMeaningId );

		$ctr = 1;
		foreach ($stList as $row ) {
			$language = getLanguageIdLanguageNameFromIds( $row[1], WLD_ENGLISH_LANG_ID );
			$syntrans[$ctr . '.'] = array(
				'syntrans_sid' => $row[3],
				'e' => $row[0],
				'langid' => $row[1],
				'lang' => $language,
				'im' => $row[2]
			);
			$ctr += 1;
		}

		return $this->returns( $syntrans, null );

	}

	private function returns( $returning , $else ) {
		if ( $returning ) {
			return $returning;
		}
		return $else;
	}
}

/** getSynonymAndTranslation function
 *
 * @param definedMeaningId	int	req'd
 * @param excludeSyntransId	int opt'l
 *
 * returns an array of the following:
 * - spelling
 * - language_id
 * - identical_meaning
 *
 * returns null if not exist
 */
function getSynonymAndTranslation( $definedMeaningId, $excludeSyntransId = null ) {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$result = $dbr->select(
		array(
			'e' => "{$dc}_expression",
			'st' => "{$dc}_syntrans"
		),
		array(
			'spelling',
			'language_id',
			'identical_meaning',
			'syntrans_sid'
		),
		array(
			'defined_meaning_id' => $definedMeaningId,
			'st.expression_id = e.expression_id',
			'st.remove_transaction_id' => null

		), __METHOD__,
		array(
			'ORDER BY' => array (
				'identical_meaning DESC',
				'language_id',
				'spelling'
			)
		)
	);

	foreach ( $result as $row ) {
		$syntrans[] = array(
			0 => $row->spelling,
			1 => $row->language_id,
			2 => $row->identical_meaning,
			3 => $row->syntrans_sid
		);
	}

	if ( $syntrans ) {
		return $syntrans;
	}
	return null;
}
