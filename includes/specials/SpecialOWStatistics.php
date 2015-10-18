<?php
if ( !defined( 'MEDIAWIKI' ) ) die();

global $wgWldOwScriptPath;
require_once( $wgWldOwScriptPath . 'languages.php' );

class SpecialOWStatistics extends SpecialPage {
	function __construct() {
		parent::__construct( 'ow_statistics' );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest;

		$wgOut->setPageTitle( wfMessage( 'ow-stat-header' )->text() );

		$showstat = array_key_exists( 'showstat', $_GET ) ? $_GET['showstat'] : $par ;

		$headerText = Html::openElement('div', array( 'class' => 'owstatmainheader' ))
			. $this->linkHeader ( wfMessage('ow-stat-overview-link')->text(), "", $showstat ) . " — "
			. $this->linkHeader ( wfMessage('ow-stat-definedmeanings-link')->text(), "dm", $showstat ) . " — "
			. $this->linkHeader ( wfMessage('ow-stat-meanings-link')->text(), "def", $showstat ) . " — "
			. $this->linkHeader ( wfMessage('ow-stat-expressions-link')->text(), "exp", $showstat ) . " — "
			. $this->linkHeader ( wfMessage('ow-stat-syntranses-link')->text(), "syntrans", $showstat ) . " — "
			. $this->linkHeader ( wfMessage('ow-stat-annotations-link')->text(), "annot", $showstat )
			. Html::closeElement('div')
			. Html::element('br');

		$wgOut->addHTML( $headerText ) ;

		if ( $showstat == 'dm' ) {
			$wgOut->addHTML( $this->getDefinedMeaningPerLanguage () );
		} elseif ( $showstat == 'def' ) {
			$wgOut->addHTML( $this->getDefinitionPerLanguage () );
		} elseif ( $showstat == 'syntrans' ) {
			$wgOut->addHTML( $this->getSyntransPerLanguage () );
		} elseif ( $showstat == 'exp' ) {
			$wgOut->addHTML ( $this->getExpressionPerLanguage () ) ;
		} elseif ( $showstat == 'annot' ) {
			$wgOut->addHTML ( $this->getAnnotationStats () ) ;
		} else {
			$wgOut->addHTML ( $this->getOverview () ) ;
		}
	}

	function linkHeader ( $text, $val , $showstat ) {
		global $wgArticlePath;
		if ( $showstat != $val ) {
			$url = str_replace( "$1", 'Special:Ow_statistics' , $wgArticlePath );
			$url .= strpos($url , "?") ? "&showstat=$val":"?showstat=$val";
			return Html::element( 'a', array( 'href' => $url ), $text );
		} else {
			return "<b>$text</b>" ;
		}
	}

	private function getNumberOfDM ( ) {
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

	public function getOverview ( $statspageformat=False ) {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		$output = "";

		$nbsyntrans = $dbr->selectField(
			array( "{$dc}_syntrans" ),
			'COUNT(DISTINCT syntrans_sid)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbdef = $dbr->selectField(
			array( "{$dc}_defined_meaning" ),
			'COUNT(DISTINCT defined_meaning_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbexp = $dbr->selectField(
			"{$dc}_expression",
			'COUNT(DISTINCT expression_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nblang = $dbr->selectField(
			array( "{$dc}_expression" ),
			'COUNT(DISTINCT language_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbcoll = $dbr->selectField(
			array( "{$dc}_collection" ),
			'COUNT(DISTINCT collection_mid)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbclass = $dbr->selectField(
			array( "{$dc}_class_membership" ),
			'COUNT(DISTINCT class_mid)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbdm = $this->getNumberOfDM() ;
		$nbanot = 0;
/*
		$nbclassanot = $dbr->selectField(
			"{$dc}_class_attributes",
			'COUNT(DISTINCT class_mid)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbanot += $nbclassanot;
/* */
		$nburlanot = $dbr->selectField(
			"{$dc}_url_attribute_values",
			'COUNT(DISTINCT value_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbanot += $nburlanot;
		$nbtextanot = $dbr->selectField(
			"{$dc}_text_attribute_values",
			'COUNT(DISTINCT value_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbanot += $nbtextanot;
/*
		$nboptionanot = $dbr->selectField(
			"{$dc}_option_attribute_values",
			'COUNT(DISTINCT value_id)',
			array( 'remove_transaction_id' => null ),
			__METHOD__
		);
		$nbanot += $nboptionanot;
/* */
		if ( $statspageformat )
		{
			$nblangavail = $dbr->selectField(
				array( "language" ),
				'COUNT(DISTINCT language_id)',
				array( ),
				__METHOD__
			);
			$index = 'ow-special-stat-head' ;
			$output[ $index ][ 'ow-special-stat-languages-avail' ] = $nblangavail ;
			$output[ $index ][ 'ow-special-stat-languages-used' ] = $nblang ;
			$output[ $index ][ 'ow-special-stat-syntranses' ] = $nbsyntrans ;
			$output[ $index ][ 'ow-special-stat-expressions' ] = $nbexp ;
			$output[ $index ][ 'ow-special-stat-definedmeanings' ] = $nbdm ;
			$output[ $index ][ 'ow-special-stat-meanings']  = $nbdef ;
			$output[ $index ][ 'ow-special-stat-collections' ] = $nbcoll ;
			$output[ $index ][ 'ow-special-stat-classes' ] = $nbclass ;
#			$output[ $index ][ 'ow-special-stat-annotations' ] = $nbanot ;
		}
		else
		{
			$output .= Html::openElement( 'table', array('class' => 'owstatbig') );
			$output .= $this->addTableRow( 'ow-stat-syntranses', $nbsyntrans );
			$output .= $this->addTableRow( 'ow-stat-expressions', $nbexp );
			$output .= $this->addTableRow( 'ow-stat-meanings', $nbdef );
			$output .= $this->addTableRow( 'ow-stat-definedmeanings', $nbdm );
			$output .= $this->addTableRow( 'ow-stat-languages', $nblang );
			$output .= $this->addTableRow( 'ow-stat-collections', $nbcoll );
			$output .= $this->addTableRow( 'ow-stat-classes', $nbclass );
#			$output .= $this->addTableRow( 'ow-stat-class-annotations', $nbclassanot );
			$output .= $this->addTableRow( 'ow-stat-url-annotations', $nburlanot );
			$output .= $this->addTableRow( 'ow-stat-text-annotations', $nbtextanot );
#			$output .= $this->addTableRow( 'ow-stat-option-annotations', $nboptionanot );
#			$output .= $this->addTableRow( 'ow-stat-annotations', $nbanot );
			$output .= Html::closeElement( 'table' );
		}
		return $output ;
	}

	private function getDefinedMeaningPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		// get the number of DMs with at least one translation for each language
		$queryResult = $dbr->select(
			array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
			array( 'language_id', 'tot' => 'count(DISTINCT synt.defined_meaning_id)' ),
			array( 'exp.expression_id = synt.expression_id', 'synt.remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);
		return( $this->getPerLanguageTable( $queryResult, 'ow-stat-definedmeanings' , False ) ) ;
	}

	private function getDefinitionPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		// get the number of definitions for each language (note : a definition is always unique )
		$queryResult = $dbr->select(
			array( 'tc' => "{$dc}_translated_content", 'dm' => "{$dc}_defined_meaning" ),
			array( 'language_id', 'tot' => 'count(DISTINCT tc.text_id)' ),
			array(
				'tc.translated_content_id = dm.meaning_text_tcid',
				'tc.remove_transaction_id' => null,
				'dm.remove_transaction_id' => null
			), __METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);
		return( $this->getPerLanguageTable( $queryResult, 'ow-stat-meanings' ) ) ;
	}

	private function getExpressionPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		// get the number of expressions for each language
		$queryResult = $dbr->select(
			"{$dc}_expression",
			array( 'language_id', 'tot' => 'count(expression_id)' ),
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);
		return( $this->getPerLanguageTable( $queryResult, 'ow-stat-expressions' ) ) ;
	}

	private function getSyntransPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		// get the number of syntrans' for each language
		$queryResult = $dbr->select(
			array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
			array( 'language_id', 'tot' => 'count(DISTINCT synt.syntrans_sid)' ),
			array( 'exp.expression_id = synt.expression_id', 'synt.remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);
		return( $this->getPerLanguageTable( $queryResult, 'ow-stat-syntranses' ) ) ;
	}

	private function getPerLanguageTable ( $queryResult, $headMessageKey, $doCount=True ) {
		$languageNames = getOwLanguageNames();
		$nblang = 0 ;
		$nbDataArray = array () ;
		foreach ( $queryResult as $row ) {
			$lang = $languageNames[$row->language_id] ;
			$nbDataArray[$lang] = $row->tot ;
		}
		$nbdm = $this->getNumberOfDM() ;
		$nblang = count ( $nbDataArray ) ;
		arsort ( $nbDataArray ) ;
		$max = max ( $nbDataArray ) ;
		if ( $doCount ) {
			$nbDataTot = array_sum ( $nbDataArray ) ;
		} else {
			$nbDataTot = $nbdm ;
		}
		$output = Html::openElement( 'table', array('class' => 'owstatbig') );
		if ( $doCount ) {
			$output .= $this->addTableRow( $headMessageKey, $nbDataTot );
		}
		$output .= $this->addTableRow( 'ow-stat-definedmeanings', $nbdm );
		$output .= $this->addTableRow( 'ow-stat-languages', $nblang );
		$output .= Html::closeElement( 'table' );
		$output .= Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ));
		$output .= Html::openElement( 'tr' );
		$output .= Html::element( 'th', array(), wfMessage('ow-stat-languages')->numparams( $nblang )->text() );
		$output .= Html::element( 'th', array(), wfMessage( $headMessageKey )->numparams( $nbDataTot )->text() );
		$output .= Html::openElement( 'td', array( 'width' => '600px') );
		$output .= Html::element( 'div', array( 'class' => 'owstatbar', 'style' => "width: 500px"));
		$output .= Html::element( 'div', array( 'class' => 'owstatpercent' ),
			wfMessage( 'ow-stat-all-is')->params( wfMessage( 'ow-stat-percent' )->numparams( 100 ) )->text() );
		$output .= Html::closeElement( 'td' );
		$output .= Html::closeElement( 'tr' );
		foreach ($nbDataArray as $lang => $dataitem) {
			$output .= $this->addTableRowWithBar( $lang, $dataitem, $max, $nbDataTot );
		}
		$output .= Html::closeElement( 'table' );
		return $output ;
	}

	private function getAnnotationStats () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		$output = "";

		// option attributes
		$nbAtt = array();
/*
		$queryResult = $dbr->select(
			"{$dc}_class_attributes",
			array( 'attribute_mid', 'tot' => 'count(DISTINCT class_mid)' ),
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'class_mid' )
		);

		foreach ( $queryResult as $row ) {
			$att = $row->class_mid ;
			$nbAtt[$att] = $row->tot ;
		}
		arsort ( $nbAtt ) ;

		$output .= Html::element( 'h2', array(), wfMessage('ow-class-annotation's)->text() );
		$output .= $this->createTable( $nbAtt );

/* */
		// LINK ATTRIBUTES
		$nbAtt = array();
		$queryResult = $dbr->select(
			"{$dc}_url_attribute_values",
			array( 'attribute_mid', 'tot' => 'count(DISTINCT value_id)' ),
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'attribute_mid' )
		);

		foreach ( $queryResult as $row ) {
			$att = $row->attribute_mid ;
			$nbAtt[$att] = $row->tot ;
		}
		arsort ( $nbAtt ) ;

		$output .= Html::element( 'h2', array(), wfMessage('ow-stat-url-annotations')->numparams( count( $nbAtt ) )->text() );
		$output .= $this->createTable( $nbAtt );

		// TEXT ATTRIBUTES
		$nbAtt = array();
		$queryResult = $dbr->select(
			"{$dc}_text_attribute_values",
			array( 'attribute_mid', 'tot' => 'count(DISTINCT value_id)' ),
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'attribute_mid' )
		);

		foreach ( $queryResult as $row ) {
			$att = $row->attribute_mid ;
			$nbAtt[$att] = $row->tot ;
		}
		arsort ( $nbAtt ) ;

		$output .= Html::element( 'h2', array(), wfMessage('ow-stat-text-annotations')->numparams( count( $nbAtt ) )->text() );
		$output .= $this->createTable( $nbAtt );
/*

		// option attributes
		$nbAtt = array();
		$queryResult = $dbr->select(
			"{$dc}_option_attribute_values",
			array( 'attribute_mid', 'tot' => 'count(DISTINCT value_id)' ),
B
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'option_mid' )
		);

		foreach ( $queryResult as $row ) {
			$att = $row->attribute_mid ;
			$nbAtt[$att] = $row->tot ;
		}
		arsort ( $nbAtt ) ;
A

		$output .= Html::element( 'h2', array(), wfMessage('ow-option-annotations')->text() );
		$output .= $this->createTable( $nbAtt );
/* */

		return $output ;
	}

	/**
	 * creates a table from an array
	 * the array key is an element name, the array value is a number
	 */
	private function createTable( $nbAtt ) {
		$table = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ) );
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', array(), wfMessage('ow_Annotation')->text() );
		$table .= Html::element( 'th', array(), '#' );
		$table .= Html::closeElement( 'tr' );

		foreach ($nbAtt as $att => $nb) {
			$attname = definedMeaningExpression ( $att ) ;
			if ( $attname == "" ) $attname = $att ;
			$table .= Html::openElement( 'tr' );
			$table .= Html::element( 'td', array(), $attname );
			$table .= Html::element( 'td', array('text-align' => 'right'), $nb );
			$table .= Html::closeElement( 'tr' );
		}
		$table .= Html::closeElement( 'table' );
		return $table;
	}

	/**
	 * return a table row with a text in row 1 and a numeric value in row 2
	 * msgkey is a message key, the message is output in the 1st row
	 * value is a number to be output in the 2nd row
	 */
	private function addTableRow ( $msgkey , $value ) {
		$result = Html::openElement( 'tr' );
		$result .= Html::element( 'td', array(), wfMessage( $msgkey )->numparams( $value )->text() );
		$result .= Html::element( 'td', array( 'align' => 'right' ), $value );
		$result .= Html::closeElement( 'tr' );
		return $result;
	}

	/**
	 * adds a row in a table with three columns
	 * the first column is e.g. the language name
	 * the second column is a value
	 * the third column shows a bar according to value/max
	 * followed by a percent figure based on value/totel
	 */
	private function addTableRowWithBar ( $firstcol, $value, $max, $total ) {
		$wi = ceil( ( ( $value / $total ) * 500 ) );
		$per = round( ( ( $value / $total ) * 100 ) );

		$row = Html::openElement( 'tr' );
		$row .= Html::element( 'td', array(), $firstcol );
		$row .= Html::element( 'td', array( 'align' => 'right' ), $value );
		$row .= Html::openElement( 'td', array( 'width' => '600px') );
		$row .= Html::element( 'div', array( 'class' => 'owstatbar', 'style' => "width: {$wi}px"));
		$row .= Html::element( 'div', array( 'class' => 'owstatpercent' ),
							wfMessage( 'ow-stat-percent' )->numparams( $per )->text() );
		$row .= Html::closeElement( 'td' );
		$row .= Html::closeElement( 'tr' );

		return $row;
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
