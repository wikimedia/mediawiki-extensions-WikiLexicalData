<?php

# Empty skeleton for omegawiki test cases.
# Replace occurrences of RecordHelperTest with the name of your own test case.

define( 'MEDIAWIKI', true );

if ( !defined( "PHPUnit_MAIN_METHOD" ) ) {
	define( "PHPUnit_MAIN_METHOD", "RecordHelperTest::main" );
}

# do we seriously need ALL of these?
require_once "../../../StartProfiler.php";
require_once "../../../LocalSettings.php";
require_once "../php-tools/ProgressBar.php";
require_once "Setup.php";
require_once "DefinedMeaningModel.php";
require_once "Transaction.php";

require_once "PHPUnit/Framework/TestCase.php";
require_once "PHPUnit/Framework/TestSuite.php";
require_once "RecordHelper.php";

class RecordHelperTest extends PHPUnit\Framework\TestCase {

	public static function main() {
		require_once "PHPUnit/TextUI/TestRunner.php";

		$suite  = new PHPUnit\Framework\TestSuite( "somethingTest" );
		$result = PHPUnit\TextUI\TestRunner::run( $suite );
	}

	function setUp() : void {
		global $wgCommandLineMode;
		$wgCommandLineMode = true;
		$dc = "uw";
		$definedMeaningId = 663665; # education
		$viewInformation = new ViewInformation();
		$viewInformation->queryTransactionInformation = new QueryLatestTransactionInformation();

		$model = new DefinedMeaningModel( $definedMeaningId, [ "viewinformation" => $viewInformation ] );
	}

}

if ( PHPUnit_MAIN_METHOD == "RecordHelperTest::main" ) {
	RecordHelperTest::main();
}
