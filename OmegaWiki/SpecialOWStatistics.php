<?php
if ( !defined( 'MEDIAWIKI' ) ) die();

require_once( "Wikidata.php" );
require_once( 'languages.php' );

class SpecialOWStatistics extends SpecialPage {
	function __construct() {
		parent::__construct( 'ow_statistics' );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest;

		$wgOut->setPageTitle( wfMsg( 'ow_statistics' ) );

		$showstat = array_key_exists( 'showstat', $_GET ) ? $_GET['showstat']:'';

		$headerText = Html::openElement('div', array( 'class' => 'owstatmainheader' ))
			. $this->linkHeader ( wfMsg('ow_DefinedMeaning'), "dm", $showstat ) . " — "
			. $this->linkHeader ( wfMsg('ow_Definition'), "def", $showstat ) . " — "
			. $this->linkHeader ( wfMsg('ow_Expression'), "exp", $showstat ) . " — "
			. $this->linkHeader ( "Syntrans", "syntrans", $showstat ) . " — "
			. $this->linkHeader ( wfMsg('ow_Annotation'), "annot", $showstat )
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

	function getNumberOfDM ( ) {
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

	function getDefinedMeaningPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		global $wgUploadPath ;
		$output = "";

		$languageNames = getOwLanguageNames();

		// get number of DM with at least one translation for each language
		$queryResult = $dbr->select(
			array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
			array( 'language_id', 'tot' => 'count(DISTINCT synt.defined_meaning_id)' ),
			array( 'exp.expression_id = synt.expression_id', 'synt.remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);

		$nbDMArray = array () ;

		foreach ( $queryResult as $row ) {
			$lang = $languageNames[$row->language_id] ;
			$nbDMArray[$lang] = $row->tot ;
		}
		$nblang = count ( $nbDMArray ) ;
		$nbdm = $this->getNumberOfDM() ;

		$tableLang = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ));
		$tableLang .= Html::openElement( 'tr' );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Language') );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_DefinedMeaning') );
		$tableLang .= Html::closeElement( 'tr' );

		arsort ( $nbDMArray ) ;
		$max = max ( $nbDMArray ) ;

		foreach ($nbDMArray as $lang => $dm) {
			$tableLang .= $this->addTableRowWithBar( $lang, $dm, $max );
		}
		$tableLang .= Html::closeElement( 'table' );

		$output .= Html::openElement( 'table', array('class' => 'owstatbig') );
		$output .= $this->addTableRow( array( wfMsg('ow_DefinedMeaning'), $nbdm ) );
		$output .= $this->addTableRow( array( wfMsg('ow_Language'), $nblang ) );
		$output .= Html::closeElement( 'table' );

		$output .= $tableLang;

		return $output ;
	}


	function getDefinitionPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		global $wgUploadPath ;
		$output = "";

		$languageNames = getOwLanguageNames();

		// get number of definitions for each language (note : a definition is always unique )
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

		$nbDefArray = array () ;

		foreach ( $queryResult as $row ) {
			$lang = $languageNames[$row->language_id] ;
			$nbDefArray[$lang] = $row->tot ;
		}
		$nbDefTot = array_sum ( $nbDefArray ) ;
		$nblang = count ( $nbDefArray ) ;
		$nbdm = $this->getNumberOfDM() ;

		$tableLang = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ));
		$tableLang .= Html::openElement( 'tr' );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Language') );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Definition') );
		$tableLang .= Html::closeElement( 'tr' );

		arsort ( $nbDefArray ) ;
		$max = max ( $nbDefArray ) ;
		foreach ($nbDefArray as $lang => $def) {
			$tableLang .= $this->addTableRowWithBar( $lang, $def, $max );
		}

		$tableLang .= Html::closeElement( 'table' );

		$output .= Html::openElement( 'table', array('class' => 'owstatbig') );
		$output .= $this->addTableRow( array( wfMsg('ow_Definition'), $nbDefTot ) );
		$output .= $this->addTableRow( array( wfMsg('ow_DefinedMeaning'), $nbdm ) );
		$output .= $this->addTableRow( array( wfMsg('ow_Language'), $nblang ) );
		$output .= Html::closeElement( 'table' );

		$output .= $tableLang;

		return $output ;
	}


	function getExpressionPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		global $wgUploadPath ;

		$output = "";

		$queryResult = $dbr->select(
			"{$dc}_expression",
			array( 'language_id', 'tot' => 'count(expression_id)' ),
			array( 'remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);

		$languageNames = getOwLanguageNames();
		$nbexpArray = array () ;

		foreach ( $queryResult as $row ) {
			$lang = $languageNames[$row->language_id] ;
			$nbexpArray[$lang] = $row->tot ;
		}
		$nbexptot = array_sum ( $nbexpArray ) ;
		$nbdm = $this->getNumberOfDM() ;
		$nblang = count ( $nbexpArray ) ;

		$tableLang = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ));
		$tableLang .= Html::openElement( 'tr' );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Language') );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Expression') );
		$tableLang .= Html::closeElement( 'tr' );

		arsort ( $nbexpArray ) ;
		$max = max ( $nbexpArray ) ;
		foreach ($nbexpArray as $lang => $exp) {
			$tableLang .= $this->addTableRowWithBar( $lang, $exp, $max );
		}

		$tableLang .= Html::closeElement( 'table' );

		$output .= Html::openElement( 'table', array('class' => 'owstatbig') );
		$output .= $this->addTableRow( array( wfMsg('ow_Expression'), $nbexptot ) );
		$output .= $this->addTableRow( array( wfMsg('ow_DefinedMeaning'), $nbdm ) );
		$output .= $this->addTableRow( array( wfMsg('ow_Language'), $nblang ) );
		$output .= Html::closeElement( 'table' );

		$output .= $tableLang ;
		return $output ;
	}


	function getSyntransPerLanguage () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		global $wgUploadPath ;
		$output = "";

		$queryResult = $dbr->select(
			array( 'exp' => "{$dc}_expression", 'synt' => "{$dc}_syntrans" ),
			array( 'language_id', 'tot' => 'count(DISTINCT synt.syntrans_sid)' ),
			array( 'exp.expression_id = synt.expression_id', 'synt.remove_transaction_id' => null ),
			__METHOD__,
			array( 'GROUP BY' => 'language_id' )
		);

		$languageNames = getOwLanguageNames();

		$nblang = 0 ;
		$nbexptot = 0 ;
		$nbSyntransArray = array () ;

		foreach ( $queryResult as $row ) {
			$lang = $languageNames[$row->language_id] ;
			$nbSyntransArray[$lang] = $row->tot ;
		}
		$nbSyntransTot = array_sum ( $nbSyntransArray ) ;
		$nbdm = $this->getNumberOfDM() ;
		$nblang = count ( $nbSyntransArray ) ;

		$tableLang = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ));
		$tableLang .= Html::openElement( 'tr' );
		$tableLang .= Html::element( 'th', array(), wfMsg('ow_Language') );
		$tableLang .= Html::element( 'th', array(), 'Syntrans' );
		$tableLang .= Html::closeElement( 'tr' );

		arsort ( $nbSyntransArray ) ;
		$max = max ( $nbSyntransArray ) ;
		foreach ($nbSyntransArray as $lang => $syntrans) {
			$tableLang .= $this->addTableRowWithBar( $lang, $syntrans, $max );
		}
		$tableLang .= Html::closeElement( 'table' );

		$output .= Html::openElement( 'table', array('class' => 'owstatbig') );
		$output .= $this->addTableRow( array( 'Syntrans', $nbSyntransTot ) );
		$output .= $this->addTableRow( array( wfMsg('ow_DefinedMeaning'), $nbdm ) );
		$output .= $this->addTableRow( array( wfMsg('ow_Language'), $nblang ) );
		$output .= Html::closeElement( 'table' );

		$output .= $tableLang ;

		return $output ;
	}

	function getAnnotationStats () {
		$dc = wdGetDataSetContext();
		$dbr = wfGetDB( DB_SLAVE );
		$output = "";

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

		$output .= Html::element( 'h2', array(), 'Link attributes' );
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

		$output .= Html::element( 'h2', array(), 'Text attributes' );
		$output .= $this->createTable( $nbAtt );

		return $output ;
	}

	/**
	 * creates a table from an array
	 * the array key is an element name, the array value is a number
	 */
	function createTable( $nbAtt ) {
		$table = Html::openElement( 'table', array( 'class' => 'sortable owstatmaintable' ) );
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', array(), wfMsg('ow_Annotation') );
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
	 * adds a simple html row in a table
	 * where each value of the input array is a column value
	 */
	function addTableRow ( $data = array() ) {
		$result = Html::openElement( 'tr' );
		foreach( $data as $text ) {
			$result .= Html::element( 'td', array(), $text );
		}
		$result .= Html::closeElement( 'tr' );
		return $result;
	}

	/**
	 * adds a row in a table with three columns
	 * the first column is e.g. the language name
	 * the second column is a value
	 * the third column shows a bar according to value/max
	 */
	function addTableRowWithBar ( $firstcol, $value, $max ) {
		$wi = ceil( ( ( $value / $max ) * 500 ) );
		$per = ceil( ( ( $value / $max ) * 100 ) );

		$row = Html::openElement( 'tr' );
		$row .= Html::element( 'td', array(), $firstcol );
		$row .= Html::element( 'td', array( 'align' => 'right' ), $value );
		$row .= Html::openElement( 'td', array( 'width' => '600px') );

		$row .= Html::element( 'div', array( 'class' => 'owstatbar', 'style' => "width: {$wi}px"));
		$row .= Html::element( 'div', array(), " $per %" );
		$row .= Html::closeElement( 'td' );
		$row .= Html::closeElement( 'tr' );

		return $row;
	}
}
