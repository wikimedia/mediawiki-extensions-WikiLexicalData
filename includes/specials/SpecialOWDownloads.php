<?php
/**
 * Special download page. Currently regenerated files are formatted
 * using standard csv with headers and commas as separator and quotation
 * marks, when appropriate as text delimiter (but actually, I guess the
 * all non numeric fields are text delimited, to be remedied in future
 * revisions).
 *
 * QUESTION: Is there a way for the job to know that it is processing a
 * job while in the Special Dowload Page?
 * If so, is there a way to refresh the page to show the updated file with date
 * and at the same time not run another job? ~he
 */
global $wgWldJobsScriptPath;
require_once( $wgWldJobsScriptPath . 'OWExpressionListJob.php');
require_once( $wgWldJobsScriptPath . 'WldJobs.php');

class SpecialOWDownloads extends SpecialPage {

	function __construct() {
		parent::__construct( 'ow_downloads' );
	}

	function execute( $par ) {
		global $wgWldDownloadScriptPath;
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		# Get requests
		$fileName = $request->getText( 'update' );

		$wikitext = array();
		$definitions = array();
		$expressions = array();
		$translations = array();

		$downloadDir = scandir( $wgWldDownloadScriptPath ) ;

		if ( $fileName ) {
			$wldJobs = new WldJobs();
			// update Expression
			if ( preg_match( '/^exp_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				$jobParams = array( 'type' => 'exp', 'langcode' => $match[1], 'format' => $match[2] );
				$title = Title::newFromText('User:JobQuery/exp_' . $match[1] . '.' . $match[2] );
				$job = new CreateExpressionListJob( $title, $jobParams );
				JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
			}
			// update Definition
			if ( preg_match( '/^def_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				$jobName = 'JobQuery/' . $fileName;
				$jobParams = array( 'type' => 'def', 'langcode' => $match[1], 'format' => $match[2], 'start' => '1' );
				$jobExist = $wldJobs->downloadJobExist( $jobName );
				if ( $jobExist == false ) {
					$title = Title::newFromText( 'User:' . $jobName );
					$job = new CreateDefinedExpressionListJob( $title, $jobParams );
					JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
				}
			}
			// update Owd
			if ( preg_match( '/^owd_/', $fileName ) ) {
				preg_match( '/_(.+)\.(.+)/', $fileName, $match );
				$jobName = 'JobQuery/' . $fileName;
				$jobParams = array( 'type' => 'owd', 'langcode' => $match[1], 'format' => $match[2], 'start' => '1' );
				$jobExist = $wldJobs->downloadJobExist( $jobName );
				if ( $jobExist == false ) {
					$title = Title::newFromText( 'User:' . $jobName );
					$job = new CreateOwdListJob( $title, $jobParams );
					JobQueueGroup::singleton()->push( $job ); // mediawiki >= 1.21
				}
			}
		}

		// Segregate by groups
		foreach( $downloadDir as $files ) {
			// definitions
			if ( preg_match( '/^def_/', $files ) ) {
				$definitions[] = "$files";
			}
			// definitions
			if ( preg_match( '/^exp_/', $files ) ) {
				$expressions[] = "$files";
			}
			// translations
			if ( preg_match( '/^trans_/', $files ) ) {
				$translations[] = "$files";
			}
			// OmegaWiki Development
			if ( preg_match( '/^owd_.+zip$/', $files ) ) {
				$development[] = "$files";
			}
		}

		// Process Definitions
		if ( $definitions ) {
			$wikitext[] = '===Definitions===';
			$myLine = $this->processText( $definitions );
			$wikitext[] = $myLine . "\n|}\n";
		}

		// Process Expressions
		if ( $expressions ) {
			$wikitext[] = '====List of Expressions====';
			$myLine = $this->processText( $expressions );
			$wikitext[] = $myLine . "\n|}\n";
		}

		// Process Translations
		if ( $translations ) {
			$wikitext[] = '===Translations===';
			$myLine = $this->processText( $translations );
			$wikitext[] = $myLine . "\n|}\n";
		}

		// Process Development
		if ( $development ) {
			$wikitext[] = '===Development===';
			$myLine = $this->processText( $development );
			$wikitext[] = $myLine . "\n|}\n";
		}

		// output wikitext
		foreach( $wikitext as $wikilines ) {
			$output->addWikiText( $wikilines );
		}
	}

	protected function processText( $text ) {
		global $wgServer, $wgScriptPath, $wgScript, $wgWldDownloadScriptPath;

		$presetMyLine = "{| class=\"wikitable\"\n! Language !! Date !! Size (bytes) !! Status !! Action \n|-\n";
		$myLine = $presetMyLine;
		foreach( $text as $line ) {
			if ( preg_match( '/^exp_(.+)\./', $line, $match ) ) {
				$language = $match[1];
			}
			if ( preg_match( '/^def_(.+)\./', $line, $match ) ) {
				$language = $match[1];
			}
			if ( preg_match( '/^owd_(.+)_csv\./', $line, $match ) ) {
				$language = $match[1];
			}
			$languageId = getLanguageIdForIso639_3( $language );
			// TODO: How to internationalize $nameLanguageId?
			$nameLanguageId = WLD_ENGLISH_LANG_ID;

			$languageName = getLanguageIdLanguageNameFromIds( $languageId, $nameLanguageId );

			$wikiRow = '';
			if( $myLine != $presetMyLine ) {
				$wikiRow = "|-\n";
			}
			$myLink = "[$wgServer$wgScriptPath/downloads/$line $languageName]";
			$myLine .= "$wikiRow| $myLink ";
			$filestats = stat( $wgWldDownloadScriptPath . $line );

			$date = new DateTime();
			$date->setTimestamp( $filestats['mtime'] );
			$dateTime = $date->format('Y-m-d H:i:s') . "\n";

			$myLine .= "|| " . $dateTime . " ";
			$myLine .= "| align=\"right\" | " . $filestats['size'] . " ";

			$jobName = 'JobQuery/' . $line;
			$wldJobs = new WldJobs();
			$jobExist = $wldJobs->downloadJobExist( $jobName );
			if ( $jobExist == false ) {
				$action = "[$wgServer$wgScript?title=Special:Ow_downloads&update=$line Regenerate]";
				$status = "latest";
			} else {
				$action = ' ';
				$status = "update requested";
			}
			// ability to update the file
			$myLine .= "|| $status || $action";
			$myLine .= "\n";
		}
		return $myLine;
	}

}
