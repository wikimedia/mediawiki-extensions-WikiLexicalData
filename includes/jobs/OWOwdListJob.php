<?php
/**
 * Create a list of OmegaWiki Developer data which can be used to generate
 * Dictionaries.
 *
 * TODO
 * Monolingual:
 * Final download file would be compressed as owl_fra_csv.zip
 *
 */

global $wgWldOwScriptPath, $wgWldIncludesScriptPath;
require_once( $wgWldOwScriptPath . 'Attribute.php' );
require_once( $wgWldOwScriptPath . 'DefinedMeaning.php' );
require_once( $wgWldOwScriptPath . 'Expression.php' );
require_once( $wgWldIncludesScriptPath . 'formatCSV.php' );

Class CreateOwdListJob extends Job {

	public function __construct( $title, $params ) {
		parent::__construct( 'CreateOwdList', $title, $params );
	}

	/**
	 * Execute the job
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

		if ( isset( $this->params['start'] ) ) {
			$start = $this->params['start'];
		}

		// Perform your updates

		if ( $type && $languageId && $format && $start ) {
			$this->createList( $type, $languageId, $format, $start );
			return true;
		}
		return false;

	}

	protected function createList( $type, $code, $format, $start ) {
		global $wgWldDownloadScriptPath;
		$csv = new WldFormatCSV();

		// the greater the value of $sqlLimit the faster the download file is
		// finished but the slower each web page loads while the job is being
		// processed.
		$sqlLimit = 125;
		$ctrOver = $sqlLimit + 1;

		$options['OFFSET'] = $start;

		$options['LIMIT'] = $sqlLimit;

		// Why order by defined_meaning_id? To avoid duplication of words
		// and skipping of some. When a language is constantly edited,
		// order by spelling would not accurately get all unique expressions
		// when job queued.
		$options['ORDER BY'] = 'defined_meaning_id';

		// language specifics
		$languageId = getLanguageIdForIso639_3( $code );
		if ( !$languageId ) {
			return false;
		}
		$this->DefinedMeanings = new DefinedMeanings;
		$languageDefinedMeaningIds = DefinedMeanings::getLanguageIdDefinedMeaningId( $languageId, $options );

		// create File name
		$zippedAs = $type . "_$code" . ".$format";
		$synZippedAs = $type . '_syn_' . $code . ".$format";
		$iniZippedAs = $type . "_$code" . '.ini';

		$zipName = $wgWldDownloadScriptPath . $type . "_$code" . "_$format" . ".zip";

		// When someone updates the file while someone is
		// downloading the file, the file may ( in my mind ),
		// be corrupted. So process it first as a temporary file,
		// delete the original file, and rename the temporary file ~he
		$tempFileName = $wgWldDownloadScriptPath;
		$tempFileName .= $format . "_$type" . "_$code.tmp";
		$tempSynFileName = $wgWldDownloadScriptPath;
		$tempSynFileName .= $format . "_$type" . '_syn_' . "$code.tmp";
		$tempIniFileName = $wgWldDownloadScriptPath;
		$tempIniFileName .= $iniZippedAs;

		if ( $start == 1 ) {
			$translations = array();
			$fh = fopen ( $tempFileName, 'w' );
			$fhsyn = fopen ( $tempSynFileName, 'w' );
			$fhini = fopen ( $tempIniFileName, 'w' );
			fwrite( $fhini, "version:1.0\n" );
			fclose( $fhini );
		} else {
			$fh = fopen ( $tempFileName, 'a' );
			$fhsyn = fopen ( $tempSynFileName, 'a' );
		}
		// Add data
		$ctr = 0;
		if ( $start != 0 ) {

			foreach ( $languageDefinedMeaningIds as $definedMeaningId ) {

				if ( $ctr != $ctrOver ) {
					$text = getDefinedMeaningDefinitionForLanguage( $definedMeaningId, $languageId );
					$text = preg_replace( '/\n/', '\\n', $text );
					$text = $csv->formatCSVcolumn( $text );

					$this->Expressions = new Expressions;
					$expressions = Expressions::getDefinedMeaningIdAndLanguageIdExpressions( $languageId, $definedMeaningId );

					$this->Attributes = new Attributes;
					foreach ( $expressions as $spelling ) {
						$IPA = null;
						$pinyin = null;
						$hyphenation = null;
						$example = null;
						$usage = null;

						$expressionId = getExpressionId( $spelling, $languageId );
						$syntransId = getSynonymId( $definedMeaningId, $expressionId );
						$textAttributes = Attributes::getTextAttributes( $syntransId, array( 'languageId' => WLD_ENGLISH_LANG_ID ) );
						foreach ( $textAttributes as $row ) {
							$row['text'] = preg_replace( '/\n/', '\\n', $row['text'] );
							// Convert tabs to space
							$row['text'] = preg_replace( '/\t/', ' ', $row['text'] );
							if ( $row['attribute_name'] == 'International Phonetic Alphabet') {
								$IPA .= $row['text'] . ';';
								$row = array( 'text' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'pinyin') {
								$pinyin .= $row['text'] . ';';
								$row = array( 'text' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'hyphenation') {
								$hyphenation .= $row['text'] . ';';
								$row = array( 'text' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'example sentence') {
								$example .= $row['text'] . ';';
								$row = array( 'text' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'usage') {
								$usage .= $row['text'] . ';';
								$row = array( 'text' => null, 'attribute_name' => null );
							}
							if ( $row['text'] != null ) {
								echo "SYNTRANS TEXT: " . $row['attribute_name'] . " => ";
								echo $row['text'] . "\n";
							}
						}
						$textAttributes = array();
						$IPA = preg_replace( '/;$/', '', $IPA );
						$pinyin = preg_replace( '/;$/', '', $pinyin );
						$hyphenation = preg_replace( '/;$/', '', $hyphenation );
						$example = preg_replace( '/;$/', '', $example );
						$usage = preg_replace( '/;$/', '', $usage );

						$POS = null;
						$oUsage = null;
						$area = null;
						$grammaticalProperty = null;
						$number = null;
						$gender = null;
						$optionAttributes = Attributes::getOptionAttributes( $syntransId, array( 'languageId' => WLD_ENGLISH_LANG_ID ) );
						foreach ( $optionAttributes as $row ) {
							$row['attribute_option_name'] = preg_replace( '/\n/', '\\n', $row['attribute_option_name'] );
							// convert tabs to space
							$row['attribute_option_name'] = preg_replace( '/\t/', ' ', $row['attribute_option_name'] );
							if ( $row['attribute_name'] == 'part of speech' ) {
								$POS .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'usage' ) {
								$oUsage .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'area' ) {
								$area .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'grammatical property' ) {
								$grammaticalProperty .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'number' ) {
								$number .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] == 'gender' ) {
								$gender .= $row['attribute_option_name'] . ';';
								$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
							}
							if ( $row['attribute_name'] != null ) {
								echo "SYNTRANS OPTN: " . $row['attribute_name'] . " => ";
								echo $row['attribute_option_name'] . "\n";
							}
						}
						$optionAttributes = array();
						$POS = preg_replace( '/;$/', '', $POS );
						$oUsage = preg_replace( '/;$/', '', $oUsage );
						$area = preg_replace( '/;$/', '', $area );
						$grammaticalProperty = preg_replace( '/;$/', '', $grammaticalProperty );
						$number = preg_replace( '/;$/', '', $number );
						$gender = preg_replace( '/;$/', '', $gender );

						fwrite( $fhsyn,
							$definedMeaningId .
							',' . $languageId .
							',' . $csv->formatCSVcolumn( $spelling ) .
							',' . $csv->formatCSVcolumn( $IPA ) .
							',' . $csv->formatCSVcolumn( $hyphenation ) .
							',' . $csv->formatCSVcolumn( $example ) .
							',' . $csv->formatCSVcolumn( $usage ) .
							',' . $csv->formatCSVcolumn( $POS ) .
							',' . $csv->formatCSVcolumn( $oUsage ) .
							',' . $csv->formatCSVcolumn( $area ) .
							',' . $csv->formatCSVcolumn( $grammaticalProperty ) .
							',' . $csv->formatCSVcolumn( $number ) .
							',' . $csv->formatCSVcolumn( $pinyin ) .
							',' . $csv->formatCSVcolumn( $gender ) .
							"\n"
						);

					}

					$optionAttributes = Attributes::getOptionAttributes( $definedMeaningId, array( 'languageId' => WLD_ENGLISH_LANG_ID ) );
					$subject = null;
					foreach ( $optionAttributes as $row ) {
						if (
							$row['attribute_name'] == 'subject' OR
							$row['attribute_name'] == 'topic'
						) {
							$subject .= $row['attribute_option_name'] . ';';
							$row = array( 'attribute_option_name' => null, 'attribute_name' => null );
						}
						if ( $row['attribute_name'] != null ) {
							echo "DM OPTN: " . $row['attribute_name'] . " => ";
							echo $row['attribute_option_name'] . "\n";
						}
					}
					$subject = preg_replace( '/;$/', '', $subject );

					fwrite( $fh,
						$definedMeaningId .
						',' . $languageId .
						',' . $text .
						',' . $subject .
						"\n"
					);
				}
				$ctr ++;

			}

		}
		fclose( $fh );
		fclose( $fhsyn );

		// incomplete job
		if ( $ctr == $sqlLimit ) {
			$jobParams = array( 'type' => $type, 'langcode' => $code, 'format' => $format );
			$jobParams['start'] = $start + $sqlLimit;
			$jobName = 'User:JobQuery/' . $type . '_' . $code . '.' . $format;
			$title = Title::newFromText( $jobName );
			$job = new CreateOwdListJob( $title, $jobParams );
			JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
		} else { // complete job
			// Zip file
			if ( file_exists( $zipName ) ) {
				unlink( $zipName );
			}
			$zip = new ZipArchive();
			$zip->open( $zipName, ZipArchive::CREATE );
			$zip->addfile( $tempFileName, $zippedAs );
			$zip->addfile( $tempSynFileName, $synZippedAs );
			$zip->close();
			unlink( $tempFileName );
			unlink( $tempSynFileName );
		}

	}

}