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

	$cache->setCacheKey( array( 'ow_stats_exp' ) );
	$number = $cache->getCachedValue( function () {
		$Expressions = new Expressions;
		return $Expressions->getNumberOfExpressions();
	} );
	$cache->setExpiry( 86400 );
	$cache->saveCache();

	$number = preg_replace( '/\D $/', '', "$number " );
	return htmlspecialchars( $number . $input );
}

function owDefinedMeaningStats( $input ) {
	$cache = new CacheHelper();

	$cache->setCacheKey( array( 'ow_stats_dm' ) );
	$number = $cache->getCachedValue( function () {
		return getNumberOfDefinedMeanings();
	} );
	$cache->setExpiry( 86400 );
	$cache->saveCache();

	$number = preg_replace( '/\D $/', '', "$number " );
	return htmlspecialchars( $number . $input );
}

function wldLanguageStats( $input ) {
	$cache = new CacheHelper();

	$cache->setCacheKey( array( 'wld_stats_lang' ) );
	$number = $cache->getCachedValue( function () {
		return getNumberOfLanguages();
	} );
	$cache->setExpiry( 86400 );
	$cache->saveCache();

	$number = preg_replace( '/\D $/', '', "$number " );
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
