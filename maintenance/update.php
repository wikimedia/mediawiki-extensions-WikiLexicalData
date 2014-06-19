<?php
/**
 * WikiLexicalData's updater.
 *
 * @file
 * @todo document
 * @ingroup Maintenance
 */

if ( !function_exists( 'version_compare' ) || ( version_compare( phpversion(), '5.3.2' ) < 0 ) ) {
	require dirname( __FILE__ ) . '/../includes/PHPVersionError.php';
	wfPHPVersionError( 'cli' );
}

$wgUseMasterForMaintenance = true;
//		require_once( __DIR__ . '/../../../maintenance/update.php' );

require_once( __DIR__ . '/../../../maintenance/maintenance.php' );
//require_once( __DIR__ . '/../App.php' );
global $processed, $lexicalItemDMId, $iso6393CollectionId;

/**
 * Maintenance script to run database schema updates.
 *
 * @ingroup Maintenance
 */
class UpdateWikiLexicalData extends Maintenance {

	function execute() {
		global $wgDBprefix, $wgWldIncludesScriptPath, $wgWldDbScripts, $install;
		global $processed, $lexicalItemDMId, $iso6393CollectionId;

		require_once( $wgWldIncludesScriptPath . '/Installer.php' );
		require_once( $wgWldIncludesScriptPath . '/WikiLexicalDataInstaller.php' );
//		require_once( __DIR__ . '/../includes/Installer.php' );
//		require_once( __DIR__ . '/../includes/WikiLexicalDataInstaller.php' );

		$install = new WikiLexicalDataDatabaseUpdater();

		echo "\n" . 'Installing WikiLexicalData schema...' . "\n";

		if ( !$this->hasOption( 'quick' ) ) {
			$this->output( "Abort with control-c in the next five seconds (skip this countdown with --quick) ... " );
			wfCountDown( 5 );
		}

		// process the base database
		$processed = $install->addExtensionSCHEMA(
			array( // pattern
				0 => '/*wgDBprefix*/',
				1 => '/*wgWDprefix*/'
			),
			array( // prefix
				0 => $wgDBprefix,
				1 => 'uw_' // he: this is the ultimate dataset concept!
			), $wgWldDbScripts . 'baseWld.sql' // script path
		);

		// If core was freshly installed, add these settings
		if ( $processed ) {
			$install->setWikiLexicalDataSettings(
				'uw',
				'Community',
				'Placeholder for the Community database',
				WLD_ENGLISH_LANG_ID
			);
			$install->log( // record
				'baseWld.sql', // sql name
				'Installed the base SCHEMA of WikiLexicalData/OmegaWiki as June 2014.' // comment
			);
		}

		$this->output( "\n" . '...done.' . "\n" );

		require_once( __DIR__ . '/../../../maintenance/update.php' );

		$lexicalItemDMId = $install->lexicalItemDMId;
		$iso6393CollectionId = $install->iso6393CollectionId;

		$update = new UpdateMediaWiki;
		$update->mOptions = $this->mOptions;

		$update->execute();

	}

}


$maintClass = 'UpdateWikiLexicalData';
require_once RUN_MAINTENANCE_IF_MAIN;

// add message
if ( $processed ) {
	echo "\n\n**\n";
	echo "Add to your LocalSettings.php: \n";
	echo '$wgDefaultClassMids = array(' . $lexicalItemDMId . ");\n";
	echo '$wgIso639_3CollectionId = ' . $iso6393CollectionId . ";\n";
	echo "**\n\n";
}
