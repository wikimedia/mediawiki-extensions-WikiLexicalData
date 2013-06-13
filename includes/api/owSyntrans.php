<?php

/** O m e g a W i k i   A P I ' s   S y n t r a n s   c l a s s
 *
 * PARAMETERS
 *	@param	req'd	int	dm	'the defined meaning id'
 *	@param	opt'l	int	lang	'the defined meaning's language id'
 *	@param	opt'l	int	e	'the defined meaning's expression'
 *	@param	opt'l	str	part	'synonym or translation'
 *
 * HISTORY
 * - 2013-06-13: Minimized data output, corrections. Error were
 *		generated last time.
 * - 2013-06-11:
 *		* simplified synTrans function
 *		* renamed part_lang_id to lang
 *		* added option to exclude an expression from synonyms
 * - 2013-06-08: Added
 *		@param	opt'l	str	part	'synonym or translation'
 *		@param	opt'l	int	prtlangid	'the param part's language id'
 * - 2013-06-04: Added basic structure
 *		@param	req'd	int	dm	'the defined meaning id'
 * - 2013-06-04: Creation date ~he
 *
 * TODO
 * - Integrate with Define Class
 * - Transfer getSynonymAndTranslation function to WikiDataAPI when ready
 * - Add parameter
 *		see below.
 * - Add parameters to include sid, langid and im to output.
 *
 * QUESTION
 * - none
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );

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
			// check that defined_meaning_id exists
			if ( !verifyDefinedMeaningId( $params['dm'] ) ) {
				$this->dieUsage( 'Non existent dm id (' . $params['dm'] . ').', "dm not found." );
			}
		}

		// Optional parameter
		$options = array();
		$part = 'all';

		if ( isset( $params['part'] ) ) {
			$part = $params['part'];
		}

		// error if $params['part'] is empty
		if ( $part == '' ) {
			$this->dieUsage( 'parameter part for adding syntrans is empty', 'param part is empty' );
		}

		// get syntrans
		// When returning synonyms or translation only
		if ( $part == 'syn' or $part == 'trans') {
			if ( !isset( $params['lang'] ) ) {
				$this->dieUsage( 'parameter lang for adding syntrans is missing', 'param lang is missing' );
			}
			$options['part'] = $part;
		}

		if ( $params['lang'] ) {
			$trueOrFalse = LanguageIdExist( $params['lang']);
			if ( $trueOrFalse == true ) {
				$options['lang'] = $params['lang'];
			} else {
				if ( $part == 'syn' or $part == 'trans') {
					$this->dieUsage( 'parameter lang for adding syntrans does not exist', 'param lang does not exist' );
				}
			}
		} else {
			if ( $part == 'syn' or $part == 'trans') {
				$this->dieUsage( 'parameter lang for adding syntrans is empty', 'param lang empty' );
			}
		}

		if ( $params['e'] ) {
			$trueOrFalse = getExpressionId( $params['e'], $params['lang']);
			if ( $trueOrFalse == true ) {
				$options['e'] = $params['e'];
			}
		}

		// When only dm is given
		if ( $params['e'] && !isset( $options['e'] ) ) {
			$this->dieUsage( 'parameter e for adding syntrans does not exist', 'param e does not exist' );
		}
		$syntrans = $this->synTrans( $params['dm'], $options );

		$this->getResult()->addValue( null, $this->getModuleName(), $syntrans );
		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'Get a list of synonyms and translations from of a defined meaning.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'dm' => array (
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'lang' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
			'e' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
			'part' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be used to get synonyms and translations',
			'lang' => "The defined meaning's language id to be used to get synonyms or translations",
			'e' => "The defined meaning's expression to be used to get synonyms or translations",
			'part' => 'set whether output are synonyms or translations. requires param langid',
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			' Get the synonyms and translations of a defined meaning id',
			' api.php?action=ow_syntrans&dm=8218',
			' Get the synonyms of a defined meaning id',
			' api.php?action=ow_syntrans&dm=8218&part=syn&lang=120',
			' Get the translations of a defined meaning id',
			' api.php?action=ow_syntrans&dm=8218&part=trans&lang=120',
			'',
			'In case the expression is also given with the language id,',
			'the expression is excluded from the list of syntrans.',
			'',
			' Get the synonyms and translations of a defined meaning id with lang',
			' api.php?action=ow_syntrans&dm=8218&e=字母&lang=107',
			' Get the synonyms of a defined meaning id',
			' api.php?action=ow_syntrans&dm=8218&part=syn&e=aksara&lang=231',
		);
	}

	// Additional Functions

	/**
	 * Returns an array of syntrans via defined meaning id
	 * Returns array() when empty
	 */
	protected function synTrans ( $definedMeaningId, $options = array() ) {
		$syntrans = array();
		$stList = getSynonymAndTranslation( $definedMeaningId );

		$ctr = 1;
		foreach ( $stList as $row ) {
			$language = getLanguageIdLanguageNameFromIds( $row[1], WLD_ENGLISH_LANG_ID );

			$syntransRow = array (
				'sid' => $row[3],
				'langid' => $row[1],
				'lang' => $language,
				'e' => $row[0],
				'im' => $row[2]
			);

			// Minimal output
			if ( !isset( $options['iLangId'] ) ) {
				unset( $syntransRow['langid'] );
			}
			if ( !isset( $options['iSId'] ) ) {
				unset( $syntransRow['sid'] );
			}
			if ( !isset( $options['iIm'] ) ) {
				unset( $syntransRow['im'] );
			}

			if ( isset( $options['part'] ) ) {
				if ( $options['part'] == 'syn' and $options['lang'] == $row[1] ) {
					if ( isset( $options['e'] ) ) {
						// skip the expression for the language id
						if ( $options['lang'] == $row[1] && $options['e'] == $row[0] ) {
						} else {
							$syntrans["$ctr."] = $syntransRow;
							$ctr ++;
						}
					} else {
						$syntrans["$ctr."] = $syntransRow;
						$ctr ++;
					}
				}

				if ( $options['part'] == 'trans' and $options['lang'] != $row[1] ) {
					$syntrans["$ctr."] = $syntransRow;
					$ctr ++;
				}
			} else {
				if ( isset( $options['lang']) && isset( $options['e'] ) ) {
					// skip the expression for the language id
					if ( $options['lang'] == $row[1] && $options['e'] == $row[0] ) {
					} else {
						$syntrans["$ctr."] = $syntransRow;
						$ctr ++;
					}
				} else {
					$syntrans["$ctr."] = $syntransRow;
					$ctr ++;
				}
			}
		}

		return $this->returns( $syntrans, array( 'error' => 'no result' ) );

	}

	protected function returns( $returning , $else ) {
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
