<?php
/* @file
* Code is outdated and unused
*/

if ( !defined( 'MEDIAWIKI' ) ) die();

/** @brief Imports OmegaWiki data formatted as TSV (Tab Separated Values) files
 */
class SpecialImportTSV extends SpecialPage {

	function SpecialImportTSV() {
		parent::__construct( 'ImportTSV' );
	}

	function execute( $par ) {

		global $wgWldOwScriptPath, $wgRequest, $wgVersion;
		require_once( $wgWldOwScriptPath . "WikiDataAPI.php" );

		$output = $this->getOutput();
		$request = $this->getRequest();

		$output->setPageTitle( wfMessage( 'ow_importtsv_title1' )->text() );
		if ( !$this->getUser()->isAllowed( 'importtsv' ) ) {
			$output->addHTML( wfMessage( 'ow_importtsv_not_allowed' )->text() );
			return false;
		}

		$dbr = wfGetDB( DB_MASTER );
		$dc = wdGetDataSetcontext();
		$output->setPageTitle( wfMessage( 'ow_importtsv_importing' )->text() );
		setlocale( LC_ALL, 'en_US.UTF-8' );
		if ( $request->getFileName( 'tsvfile' ) ) {

			// *****************
			//    process tsv
			// *****************

			$testRun = $request->getCheck( 'testrun' );

			// lets do some tests first. Is this even a tsv file?
			// It is _very_ important that the file is utf-8 encoded.
			// also, this is a good time to determine the max line length for the
			// fgetcsv function.
			$file = fopen( $request->getFileTempname( 'tsvfile' ), 'r' );
			$myLine = "";
			$maxLineLength = 0;
			while ( $myLine = fgets( $file ) ) {
				if ( !preg_match( '/./u', $myLine ) ) {
					$output->setPageTitle( wfMessage( 'ow_importtsv_import_failed' )->text() );
					$output->addHTML( wfMessage( 'ow_importtsv_not_utf8' )->text() );
					return false;
				}
				$maxLineLength = max( $maxLineLength, strlen( $myLine ) + 2 );
			}

			// start from the beginning again. Check if the column names are valid
			rewind( $file );
			$columns = fgetcsv( $file, $maxLineLength, "\t" );
			// somehow testing for $columns[0] fails sometimes. Byte Order Mark?
			if ( !$columns || count( $columns ) <= 2 || $columns[1] != "defining expression" ) {
				$output->setPageTitle( wfMessage( 'ow_importtsv_import_failed' )->text() );
				$output->addHTML( wfMessage( 'ow_importtsv_not_tsv' )->text() );
				return false;
			}
			for ( $i = 2; $i < count( $columns ); $i++ ) {
				$columnName = $columns[$i];
				$baseName = substr( $columnName, 0, strrpos( $columnName, '_' ) );
				if ( $baseName == "definition" || $baseName == "translations" ) {
					$langCode = substr( $columnName, strrpos( $columnName, '_' ) + 1 );
					if ( !getLanguageIdForIso639_3( $langCode ) ) {
						$output->setPageTitle( wfMessage( 'ow_importtsv_import_failed' )->text() );
						$output->addHTML( wfMessage( 'ow_impexptsv_unknown_lang', $langCode )->text() );
						return false;
					}
				}
				else { // column name does not start with definition or translations.
						$output->setPageTitle( wfMessage( 'ow_importtsv_import_failed' )->text() );
						$output->addHTML( wfMessage( 'ow_importtsv_bad_columns', $columnName )->text() );
						return false;
				}
			}

			//
			// All tests passed. lets get started
			//

			if ( $testRun ) {
				$output->setPageTitle( wfMessage( 'ow_importtsv_test_run_title' )->text() );
			}
			else {
				$output->setPageTitle( wfMessage( 'ow_importtsv_importing' )->text() );
			}

			startNewTransaction( $this->getUser()->getID(), $wgRequest->getIP(), "Bulk import via Special:ImportTSV", $dc );	# this string shouldn't be localized because it will be stored in the db

			$row = "";
			$line = 1; // actually 2, 1 was the header, but increased at the start of while
			$definitions = 0; // definitions added
			$translations = 0; // translations added

			while ( $row = fgetcsv( $file, $maxLineLength, "\t" ) ) {
				$line++;

				$dmid = $row[0];
				$exp = $row[1];

				// find the defined meaning record
				$dmResult = $dbr->select(
					array(
					'dm' =>"{$dc}_defined_meaning",
					'exp' => "{$dc}_expression"
					),
					array( 'dm.meaning_text_tcid', 'exp.spelling' ),
					array(
						'dm.defined_meaning_id' => $dmid,
						'dm.remove_transaction_id' => null,
						'exp.remove_transaction_id' => null
					),
					__METHOD__, null,
					array( 'exp' =>
						array( 'INNER JOIN', 'dm.expression_id = exp.expression_id' )
					)
				);

				$dmRecord = null;
				// perform some tests
				if ( $dmRecord = $dbr->fetchRow( $dmResult ) ) {
					if ( $dmRecord['spelling'] != $exp ) {
						$output->addHTML( "Skipped line $line: defined meaning id $dmid does not match defining expression. Should be '{$dmRecord['spelling']}', found '$exp'.<br />" );
						continue;
					}
				}
				else {
					$output->addHTML( "Skipped line $line: unknown defined meaning id $dmid. The id may have been altered in the imported file, or the defined meaning or defining expression was removed from the database.<br />" );
					continue;
				}

				// all is well. Get the translated content id
				$tcid = $dmRecord['meaning_text_tcid'];

				for ( $columnIndex = 2; $columnIndex < count( $columns ); $columnIndex++ ) {

					// Google docs removes empty columns at the end of a row,
					// so if column index is higher than the length of the row, we can break
					// and move on to the next defined meaning.
					if ( $columnIndex >= count( $row ) ) {
						break;
					}

					$columnValue = $row[$columnIndex];
					if ( !$columnValue ) {
						continue;
					}

					$columnName = $columns[$columnIndex];
					$langCode = substr( $columnName, strrpos( $columnName, '_' ) + 1 );
					$langId = getLanguageIdForIso639_3( $langCode );
					if ( strpos( $columnName, 'definition' ) === 0 ) {
						if ( !translatedTextExists( $tcid, $langId ) ) {
							if ( $testRun ) {
								$output->addHTML( "Would add definition for $exp ($dmid) in $langCode: $columnValue.<br />" );
							} else {
								addTranslatedText( $tcid, $langId, $columnValue );
								$output->addHTML( "Added definition for $exp ($dmid) in $langCode: $columnValue.<br />" );
								$definitions++;
							}
						}
					}
					if ( strpos( $columnName, 'translation' ) === 0 ) {
						$spellings = explode( '|', $columnValue );
						foreach ( $spellings as $spelling ) {
							$spelling = trim( $spelling );
							$expression = findExpression( $spelling, $langId );
							if ( !$expression ) { // expression does not exist
								if ( $testRun ) {
									$output->addHTML( "Would add translation for $exp ($dmid) in $langCode: $spelling. Would also add new page.<br />" );
								}
								else {
									$expression = createExpression( $spelling, $langId );
									$expression->bindToDefinedMeaning( $dmid, 'true' );

									// not nescesary to check page exists, createPage does that.

									// compatibility for mw 1.22 or below
									if ( version_compare( $wgVersion, '1.23', '<' ) ) {
										$title = getTitle( $spelling );
									} else {
										$title = getPageTitle( $spelling );
									}
									createPage( 16, $title );

									$output->addHTML( "Added translation for $exp ($dmid) in $langCode: $spelling. Also added new page.<br />" );
									$translations++;
								}
							}
							else { // expression exists, but may not be bound to this defined meaning.
								if ( !$expression->isBoundToDefinedMeaning( $dmid ) ) {
									if ( $testRun ) {
										$output->addHTML( "Would add translation for $exp ($dmid) in $langCode: $spelling.<br />" );
									}
									else {
										$expression->bindToDefinedMeaning( $dmid, 'true' );
										$output->addHTML( "Added translation for $exp ($dmid) in $langCode: $spelling.<br />" );
										$translations++;
									}
								}
							}
						}
					}
				}
			}

			if ( $definitions == 0 && $translations == 0 ) {
				$output->addHTML( "<br />" );
				if ( $testRun ) {
					$output->addHTML( wfMessage( 'ow_importtsv_nothing_added_test' )->text() );
				}
				else {
					$output->addHTML( wfMessage( 'ow_importtsv_nothing_added' )->text() );
				}
				$output->addHTML( "<br />" );
			}
			else {
				$output->addHTML( "<br />" . wfMessage( 'ow_importtsv_results', $definitions, $translations )->text() . "<br />" );
			}

		}
		else {
			// render the page
			$output->setPageTitle( wfMessage( 'ow_importtsv_title2' )->text() );
			$output->addHTML( wfMessage( 'ow_importtsv_header' )->text() );

			$output->addHTML( getOptionPanelForFileUpload(
				array(
					wfMessage( 'ow_importtsv_file' )->text() => getFileField( 'tsvfile' ),
					wfMessage( 'ow_importtsv_test_run' )->text() => getCheckBox( 'testrun', true )
				)
			) );
		}
	}

	protected function getGroupName() {
		return 'omegawiki';	// message 'specialpages-group-omegawiki'
	}
}
