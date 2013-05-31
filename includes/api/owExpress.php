<?php

/** OmegaWiki API's Express class
 *
 * Returns an array of datas similar to the one given by the Expression Namespace.
 *
 * PURPOSE
 * To provide a way for Developers and/or contributors of Wiktionary
 * to access OmegaWiki's data that can be easily parsed.
 *
 * HISTORY
 * - 2013-05-21 Creation Date ~ he
 *
 * TODO
 * - Improve definition by improving owDefine.php
 *		Need to make Class Define return output similar to what
 *		OmegaWiki outputs in the DefinedMeaning Namespace.
 * - Add param lang (optional). To set the primary language.
 * - Add param limit_lang (optional). To limit search to just a language or more.
 * - Add param options (optional). To limit the display to only
 *		data one is interested in. Example:
 *		& o p t i o n s = p i n y i n | I P A | h y p h e n a t i o n
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );
require_once( 'owDefine.php' );

class Express extends ApiBase {

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {

		global $wgScriptPath;

		// Get the parameters
		$params = $this->extractRequestParams();

		// Parameter checks
		if ( !isset($params['search']) ) {
			$this->dieUsage( 'parameter search is missing', 'param search is missing' );
		}

		$spelling = $params['search'];

		// Check if spelling exist
		if ( existSpelling( $spelling ) ) {
			$dmlist = getExpressionMeaningIds( $spelling );
			$options['e'] = $spelling;
			// There are duplicates using getExpressionMeaningIds !!!
			$dmlist = array_unique ( $dmlist );
			$express['expression'] = $spelling;
			$dmlistCtr = 1;
			foreach ( $dmlist as $dmrow ) {
				$defining = definingForAnyLanguage( $dmrow, $options );
				foreach ( $defining as $definingRow) {
					$express['ow_define_' . $dmlistCtr] = $definingRow;
				}
				$dmlistCtr += 1;
			}
			$this->getResult()->addValue( null, $this->getModuleName(), $express );

		} else {
			$this->dieUsage( 'the search word does not exist.', 'non-existent spelling' );
		}

		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'View Omegawiki Expression.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'search' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'search' => 'The expression to view'
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'View Expression',
			'api.php?action=ow_express&search=acusar&format=xml',
			'api.php?action=ow_express&search=pig&format=xml',
			'api.php?action=ow_express&search=咱&format=xml'
		);
	}

}
