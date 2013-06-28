<?php
/**
 * Create a list of Expressions
 *
 */
global $wgWldOwScriptPath, $wgWldIncludesScriptPath;
require_once( $wgWldOwScriptPath . 'languages.php' );
require_once( $wgWldIncludesScriptPath . 'formatCSV.php' );

Class CreateExpressionListJob extends Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'CreateExpressionList', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		// Load data from $this->params and $this->title
		if ( isset( $this->params['langcode'] ) ) {
			$languageId = $this->params['langcode'];
		}

		if ( isset( $this->params['type'] ) ) {
			$type = $this->params['type'];
		}

		if ( isset( $this->params['format'] ) ) {
			$format = $this->params['format'];
		}

		if ( $type && $languageId && $format ) {
			$this->createList( $type, $languageId, $format );
			return true;
		}

		// Perform your updates

		return false;
	}

	protected function createList( $type, $code, $format ) {
		global $wgWldDownloadScriptPath;
		$csv = new WldFormatCSV();

		// language specifics
		$languageId = getLanguageIdForIso639_3( $code );
		$languageExpressions = $this->getLanguageIdExpressions( $languageId );

		// create File name
		$fileName = $wgWldDownloadScriptPath;
		$fileName .= $type . '_' . $code . ".$format";

		// When someone updates the file while someone is
		// downloading the file, the file may ( in my mind ),
		// be corrupted. So process it first as a temporary file,
		// delete the original file, and rename the temporary file ~he
		$tempFileName = $wgWldDownloadScriptPath;
		$tempFileName .= "tmp_$type" . "_$code.tmp";

		$fh = fopen( $tempFileName, 'w' );
		fwrite( $fh, '"Expression"' . "\n" );
		foreach( $languageExpressions as $row ) {
			$spelling = $csv->formatCSVcolumn( $row->spelling );
			fwrite( $fh, $spelling . "\n" );
		}
		fclose( $fh );

		if ( file_exists( $fileName ) ) {
			unlink( $fileName );
		}
		rename( $tempFileName, $fileName );
	}

	/**
	 * returns an array of "Expression" objects
	 * for a language
	 *
	 * else returns null
	 */
	function getLanguageIdExpressions( $languageId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'spelling';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT']= $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET']= $options['OFFSET'];
		}

		$queryResult = $dbr->select(
			"{$dc}_expression",
			'spelling',
			array(
				'language_id' => $languageId,
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$expression = array();
		foreach ( $queryResult as $exp ) {
			$expression[] = $exp;
		}

		if ( $expression ) {
			return $expression;
		}
		return null;
	}
}
