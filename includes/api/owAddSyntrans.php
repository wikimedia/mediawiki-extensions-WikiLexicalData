<?php

/*
 * Created on March 19, 2013
 *
 * API for WikiData
 *
 * Some portion of this script was taken from Kipcool's maintenance
 * script importMinnan.
 *
 * Copyright (C) 2013
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );
require_once( 'extensions/WikiLexicalData/OmegaWiki/Transaction.php' );

class addSyntrans extends ApiBase {

	public $spelling, $dm, $languageId, $identicalMeaning, $result, $fp;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;

		// limit access to bots
		if ( ! $wgUser->isAllowed( 'bot' ) ) {
			$this->dieUsage( 'you must have a bot flag to use this API function', 'bot_only' );
		}

		// keep blocked bots out
		if ( $wgUser->isBlocked() ) {
			$this->dieUsage( 'your account is blocked.', 'blocked' );
		}

		// Get the parameters
		$params = $this->extractRequestParams();

		// If file, use batch processing
		if ( $params['file'] ) {
			$file = $params['file'];
			$this->getResult()->addValue( null, $this->getModuleName(), array (
				'file' => $file ,
				'process' => 'batch processing'
				)
			);
			if ( ! $fp = openFile( $file) ) {
				$this->getResult()->addValue( null, $this->getModuleName(),
					array ( 'open' => array (
						'note' => "Can not open $file.",
					) )
				);
				return true;
			}
			else {
				$ctr = 0;
				while ( ! feof( $fp ) ) {
					$inputData = fgetcsv( $fp, 1024, ',', '"', '\\' );
					$ctr = $ctr + 1;
					// file is in the form spelling,language_id,defined_meaning_id
					$inputDataCount = count( $inputData );
					if ( $inputDataCount == 1 ) {
						$result = array ( 'note' => "skipped blank line");
						$this->getResult()->addValue( null, $this->getModuleName(),
							array ( 'result' . $ctr => $result )
						);
						continue;
					}
					$spelling = $inputData[0];
					$languageId = $inputData[1];
					$definedMeaningId = $inputData[2];
					if ( $inputDataCount == 4 )
						$identicalMeaning = $inputData[3];
					if ( $inputDataCount == 3 )
						$identicalMeaning = 1;
					if ( !is_numeric($languageId) || !is_numeric($definedMeaningId) ) {
						if($ctr == 1) {
							$result = array ( 'note' => "either $languageId or $definedMeaningId is not an int or probably just the CSV header");
						} else {
							$result = array ( 'note' => "either $languageId or $definedMeaningId is not an int");
						}
					} else {
						$result = array ( 'note' => owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning ) );
					}
					$this->getResult()->addValue( null, $this->getModuleName(),
						array ( 'result' . $ctr => $result )
					);
				}
			}
			return true;
		}
		// if not, add just one syntrans
		$spelling = $params['e'];
		$definedMeaningId = $params['dm'];
		$languageId = $params['lang'];
		$identicalMeaning = $params['im'];
		$this->getResult()->addValue( null, $this->getModuleName(), array (
			'spelling' => $spelling ,
			'dmid' => $definedMeaningId ,
			'lang' => $languageId ,
			'im' => $identicalMeaning
			)
		);
		$result = owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning );
		$this->getResult()->addValue( null, $this->getModuleName(),
			array ( 'result' => array ( 'note' => $result, )
			)
		);
		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'Add expressions, synonyms/translations to Omegawiki.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'e' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
			'dm' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
			'lang' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
			'im' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
			'file' => array (
				ApiBase::PARAM_TYPE => 'string',
			)
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'e' => 'The expression to be added' ,
			'dm' => 'The defined meaning id where the expression will be added' ,
			'lang' => 'The language id of the expression' ,
			'im' => 'The identical meaning value. (boolean)' ,
			'file' => 'The file to process. (csv format)'
		);
	}

	// Get examples
	public function getExamples() {
	return array(
		'Add a synonym/translation to the defined meaning definition',
		'If the expression is already present. Nothing happens',
		'api.php?action=ow_add_syntrans&e=欠席&dm=334562&lang=387&im=1&format=xml',
		'You can also add synonym/translation using a CSV file.  The file must ',
		'contain at least 3 columns (and 1 optional column):',
		' spelling           (string)',
		' language_id        (int)',
		' defined_meaning_id (int)',
		' identical meaning  (boolean 1 or 2, optional)',
		'api.php?action=ow_add_syntrans&file=D:\xampp\data\add_expression_to_dm.csv&format=xml'
		);
	}
}

function openFile( $filename ) {
	$exist = file_exists($filename);
	if ($exist == 1) {
		return fopen( $filename, 'r' );
	} else {
		return null;
	}
}

function owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning ) {
	global $wgUser;
	$dc = wdGetDataSetContext();

	// check that the language_id exists
	if ( ! verifyLanguageId( $languageId ) ) {
		return 'Non existent language id.';
	}

	// check that defined_meaning_id exists
	if ( ! verifyDefinedMeaningId( $definedMeaningId ) ) {
		return 'Non existent dm id.';
	}

	// trim spelling
	$spelling = trim( $spelling );

	if ( $identicalMeaning == 1 ) {
		$identicalMeaning = "true";
	}
	else {
		$identicalMeaning = "false";
	}

	// first check if it exists, then create the transaction and put it in db
	$expression = findExpression( $spelling, $languageId );
	if ( $expression ) {
		// the expression exists, check if it has this syntrans
		$bound = expressionIsBoundToDefinedMeaning ( $definedMeaningId, $expression->id );
		if (  $bound == true ) {
			$synonymId = getSynonymId( $definedMeaningId, $expression->id );
			return "sid=$synonymId - exists: $spelling , lang_id = $languageId , dm_id = $definedMeaningId im = $identicalMeaning";
		}
	}
	// adding the expression
	startNewTransaction( $wgUser->getID(), "0.0.0.0", "", $dc);

	addSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning );

	$expressionId = getExpressionId( $spelling, $languageId );
	$synonymId = getSynonymId( $definedMeaningId, $expressionId );
	return "sid=$synonymId - adding: $spelling , lang_id = $languageId , dm_id = $definedMeaningId, im = $identicalMeaning";

}
