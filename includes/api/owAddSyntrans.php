<?php

/** OmegaWiki API's add syntrans class
 * Created on March 19, 2013
 */

require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );
require_once( 'extensions/WikiLexicalData/OmegaWiki/Transaction.php' );

class AddSyntrans extends ApiBase {

	public $spelling, $dm, $languageId, $identicalMeaning, $result, $fp;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;

		// limit access to bots
		if ( !$wgUser->isAllowed( 'bot' ) ) {
			$this->dieUsage( 'you must have a bot flag to use this API function', 'bot_only' );
		}

		// keep blocked bots out
		if ( $wgUser->isBlocked() ) {
			$this->dieUsage( 'your account is blocked.', 'blocked' );
		}

		// Get the parameters
		$params = $this->extractRequestParams();

		// set test status
		$this->test = false;

		if ( isset( $params['test'] ) ) {
			if ( $params['test'] == '1' OR $params['test'] == null ) {
				$this->test = true;
			}
		}

		// If wikipage, use batch processing
		if ( $params['wikipage'] ) {
			$text = $this->processBatch( $params['wikipage'] );
			return true;
		}

		// if not, add just one syntrans

		// Parameter checks
		if ( !isset( $params['e'] ) ) {
			$this->dieUsage( 'parameter e for adding syntrans is missing', 'param e is missing' );
		}
		if ( !isset( $params['dm'] ) ) {
			$this->dieUsage( 'parameter dm for adding syntrans is missing', 'param dm is missing' );
		}
		if ( !isset( $params['lang'] ) ) {
			$this->dieUsage( 'parameter lang for adding syntrans is missing', 'param lang is missing' );
		}
		if ( !isset( $params['im'] ) ) {
			$this->dieUsage( 'parameter im for adding syntrans is missing', 'param im is missing' );
		}

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
		$result = $this->owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning );
		$this->getResult()->addValue( null, $this->getModuleName(),
			array ( 'result' => $result )
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
			),
			'wikipage' => array (
				ApiBase::PARAM_TYPE => 'string',
			),
			'test' => array (
				ApiBase::PARAM_TYPE => 'string'
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'e' => 'The expression to be added' ,
			'dm' => 'The defined meaning id where the expression will be added' ,
			'lang' => 'The language id of the expression' ,
			'im' => 'The identical meaning value. (boolean)' ,
			'file' => 'The file to process. (csv format)' ,
			'wikipage' => 'The wikipage to process. (csv format, using wiki page)',
			'test' => 'test mode. No changes are made.'
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
		' identical meaning  (boolean 0 or 1, optional)',
		'api.php?action=ow_add_syntrans&wikipage=User:MinnanBot/addSyntrans130124.csv&format=xml',
		'or to test it',
		'api.php?action=ow_add_syntrans&wikipage=User:MinnanBot/addSyntrans130124.csv&format=xml&test'
		);
	}

	public function processBatch( $wikiPage ) {
		global $params;

		$csvWikiPageTitle = Title::newFromText( $wikiPage );
		$csvWikiPage = new WikiPage ( $csvWikiPageTitle );

		if ( !$wikiText = $csvWikiPage->getContent( Revision::RAW ) )
			return $this->getResult()->addValue( null, $this->getModuleName(),
				array ( 'result' => array (
					'error' => "WikiPage ( $csvWikiPageTitle ) does not exist"
				) )
			);

		$text = $wikiText->mText;

		// Check if the page is redirected,
		// then adjust accordingly.
		preg_match( "/REDIRECT \[\[(.+)\]\]/", $text, $match2 );
		if ( isset($match2[1]) ) {
			$redirectedText = $match2[1];
			$csvWikiPageTitle = Title::newFromText( $redirectedText );
			$csvWikiPage = new WikiPage ( $csvWikiPageTitle );
			$wikiText = $csvWikiPage->getContent( Revision::RAW );
			$text = $wikiText->mText;
		}

		$this->getResult()->addValue( null, $this->getModuleName(),
			array ( 'process' => array (
			'text' =>  'wikipage',
			'type' => 'batch processing'
			) )
		);

		$inputLine = explode("\n", $text);
		$ctr = 0;
		while ( $inputData = array_shift( $inputLine ) ) {
			$ctr = $ctr + 1;
			$inputData = trim( $inputData );
			if ( $inputData == "" ) {
				$result = array ( 'note' => "skipped blank line");
				$this->getResult()->addValue( null, $this->getModuleName(),
					array ( 'result' . $ctr => $result )
				);
				continue;
			}

			$inputMatch = preg_match("/^\"(.+)/", $inputData, $match);
			if ($inputMatch == 1) {
				$inputData = $match[1];
				preg_match("/(.+)\",(.+)/", $inputData, $match2);
				$spelling = $match2[1];
				$inputData = $match2[2];
				$inputData = explode(',',$inputData);
				$inputDataCount = count( $inputData );
				$languageId = $inputData[0];
				$definedMeaningId = $inputData[1];
				if ( $inputDataCount == 3 )
					$identicalMeaning = $inputData[2];
				if ( $inputDataCount == 2 )
					$identicalMeaning = 1;
			} else {
				$inputData = explode(',',$inputData);
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
					$identicalMeaning = 1 ;
			}

			if ( !is_numeric($languageId) || !is_numeric($definedMeaningId) ) {
				if($ctr == 1) {
					$result = array ( 'note' => "either $languageId or $definedMeaningId is not an int or probably just the CSV header");
				} else {
					$result = array ( 'note' => "either $languageId or $definedMeaningId is not an int");
				}
			} else {
				$result = $this->owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning );
			}

			$this->getResult()->addValue( null, $this->getModuleName(),
				array ( 'result' . $ctr => $result )
			);
		}
		return true;
	}

	public function owAddSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning ) {
		global $wgUser;
		$dc = wdGetDataSetContext();

		// check that the language_id exists
		if ( !verifyLanguageId( $languageId ) )
			return array(
				'WARNING' => 'Non existent language id(' . $languageId . ').'
			);

		// check that defined_meaning_id exists
		if ( !verifyDefinedMeaningId( $definedMeaningId ) )
			return array(
				'WARNING' => 'Non existent dm id (' . $definedMeaningId . ').'
			);

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
		$concept = getDefinedMeaningSpellingForLanguage( $definedMeaningId, WLD_ENGLISH_LANG_ID );
		if ( $expression ) {
			// the expression exists, check if it has this syntrans
			$bound = expressionIsBoundToDefinedMeaning ( $definedMeaningId, $expression->id );
			if (  $bound == true ) {
				$synonymId = getSynonymId( $definedMeaningId, $expression->id );
				$note = array (
					'status' => 'exists',
					'in' => "$concept DM($definedMeaningId)",
					'sid' => $synonymId,
					'e' => $spelling,
					'langid' => $languageId,
					'dm' => $definedMeaningId,
					'im' => $identicalMeaning
				);
				if ( $this->test ) {
					$note['note'] = 'test run only';
				}

				return $note;
			}
		}
		// adding the expression
		$expressionId = getExpressionId( $spelling, $languageId );
		$synonymId = getSynonymId( $definedMeaningId, $expressionId );
		$note = array (
			'status' => 'added',
			'to' => "$concept DM($definedMeaningId)",
			'sid' => $synonymId,
			'e' => $spelling,
			'langid' => $languageId,
			'dm' => $definedMeaningId,
			'im' => $identicalMeaning
		);

		if ( !$this->test ) {
			startNewTransaction( $this->getUser()->getID(), "0.0.0.0", "Added using API function add_syntrans", $dc);
			addSynonymOrTranslation( $spelling, $languageId, $definedMeaningId, $identicalMeaning );
		} else {
			$note['note'] = 'test run only';
		}

		return $note;
	}
}

