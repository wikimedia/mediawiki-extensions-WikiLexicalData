<?php
// OmegaWiki Tags
// Created November 18, 2013

require_once $wgWldOwScriptPath . 'WikiDataAPI.php';

function omegaWikiTags( Parser $parser ) {
	$parser->setHook( 'ow_stats', 'owStatsTag' );
	return true;
}

function owStatsTag( $input, array $args, Parser $parser, PPFrame $frame ) {
	$result = '';
	foreach ( $args as $name => $value ) {
		if ( $name == 'exp' ) {
			$result = owExpStats( $input );
		}
		if ( $name == 'dm' ) {
			$result = owDefinedMeaningStats( $input );
		}
		if ( $name == 'lang' ) {
			$result = wldLanguageStats( $input );
		}
	}
	return $result;
}

function owExpStats( $input ) {
	$cache = ObjectCache::getInstance( CACHE_ANYTHING );
	$number = $cache->getWithSetCallback(
		$cache->makeKey( 'ow_stats_exp' ),
		BagOStuff::TTL_DAY,
		function () {
			$Expressions = new Expressions;
			return $Expressions->getNumberOfExpressions();
		}
	);
	$number = preg_replace( '/\D $/', '', "$number " );
	return htmlspecialchars( $number . $input );
}

function owDefinedMeaningStats( $input ) {
	$cache = ObjectCache::getInstance( CACHE_ANYTHING );
	$number = $cache->getWithSetCallback(
		$cache->makeKey( 'ow_stats_dm' ),
		BagOStuff::TTL_DAY,
		function () {
			return getNumberOfDefinedMeanings();
		}
	);

	$number = preg_replace( '/\D $/', '', "$number " );
	return htmlspecialchars( $number . $input );
}

function wldLanguageStats( $input ) {
	$cache = ObjectCache::getInstance( CACHE_ANYTHING );
	$number = $cache->getWithSetCallback(
		$cache->makeKey( 'wld_stats_lang' ),
		BagOStuff::TTL_DAY,
		function () {
			return getNumberOfLanguages();
		}
	);
	$number = preg_replace( '/\D $/', '', "$number " );
	return htmlspecialchars( $number . $input );
}

/**
 * returns the total number of "Defined Meaning Ids"
 *
 */
function getNumberOfDefinedMeanings() {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );

	$nbdm = $dbr->selectField(
		"{$dc}_syntrans",
		'COUNT(DISTINCT defined_meaning_id)',
		[ 'remove_transaction_id' => null ],
		__METHOD__
	);
	return $nbdm;
}

/**
 * returns the total number of "Languages"
 *
 */
function getNumberOfLanguages() {
	$dc = wdGetDataSetContext();
	$dbr = wfGetDB( DB_REPLICA );

	$nbdm = $dbr->selectField(
		"{$dc}_expression",
		'COUNT(DISTINCT language_id)',
		[ 'remove_transaction_id' => null ],
		__METHOD__
	);

	return $nbdm;
}
