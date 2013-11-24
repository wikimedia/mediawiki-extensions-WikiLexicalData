<?php
// OmegaWiki Tags
// Created November 18, 2013

require_once( $wgWldOwScriptPath . 'WikiDataAPI.php' );

function omegaWikiTags( Parser $parser ) {
	$parser->setHook( 'ow_stats', 'owStatsTag' );
	return true;
}

function owStatsTag( $input, array $args, Parser $parser, PPFrame $frame ) {
	$result = '';
	foreach ( $args as $name => $value ) {
		if ( $name == 'exp' ) $result = owExpStats( $input );
		if ( $name == 'dm' ) $result = owDefinedMeaningStats( $input );
		if ( $name == 'lang' ) $result = wldLanguageStats( $input );
	}
	return $result;
}

function owExpStats( $input ) {
	$cache = new CacheHelper();

	$number = $cache->setCacheKey( array( 'ow_stats_exp' ) );
	$number = $cache->getCachedValue( function () {
		$Expressions = new Expressions;
		$number = $Expressions->getNumberOfExpressions();

		// This line is for checking. Delete if certified ok!
		echo "cached value for ow_stats_exp not found.<br/>";

		return $number;
	} );

	// line set to 35 seconds for testing, please set to 86400.
	$cache->setExpiry( 35 );

	$cache->saveCache();

	$number = "$number ";
	$number = preg_replace( '/\D $/', '', $number );
	return htmlspecialchars( $number . $input );
}

function owDefinedMeaningStats( $input ) {
	$cache = new CacheHelper();

	$number = $cache->setCacheKey( array( 'ow_stats_dm' ) );
	$number = $cache->getCachedValue( function () {
		$number = getNumberOfDefinedMeanings();

		// This line is for checking. Delete if certified ok!
		echo "cached value for ow_stats_dm not found.<br/>";

		return $number;
	} );

	// line set to 35 seconds for testing, please set to 86400.
	$cache->setExpiry( 35 );

	$cache->saveCache();

	$number = "$number ";
	$number = preg_replace( '/\D $/', '', $number );
	return htmlspecialchars( $number . $input );
}

function wldLanguageStats( $input ) {
	$cache = new CacheHelper();

	$number = $cache->setCacheKey( array( 'wld_stats_lang' ) );
	$number = $cache->getCachedValue( function () {
		$number = getNumberOfLanguages();

		// This line is for checking. Delete if certified ok!
		echo "cached value for wld_stats_lang not found.<br/>";

		return $number;
	} );


	// line set to 35 seconds for testing, please set to 86400.
	$cache->setExpiry( 35 );

	$cache->saveCache();

	$number = "$number ";
	$number = preg_replace( '/\D $/', '', $number );
	return htmlspecialchars( $number . $input );
}

/**
 * returns the total number of "Defined Meaning Ids"
 *
 */
function getNumberOfDefinedMeanings () {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$nbdm = $dbr->selectField(
		"{$dc}_syntrans",
		'COUNT(DISTINCT defined_meaning_id)',
		array( 'remove_transaction_id' => null ),
		__METHOD__
	);
	return $nbdm;
}

/**
 * returns the total number of "Languages"
 *
 */
function getNumberOfLanguages () {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_SLAVE );

	$nbdm = $dbr->selectField(
		"{$dc}_expression",
		'COUNT(DISTINCT language_id)',
		array( 'remove_transaction_id' => null ),
		__METHOD__
	);

	return $nbdm;
}
