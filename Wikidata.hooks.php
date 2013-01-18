<?php

require_once( "OmegaWiki/WikiDataGlobals.php" );

class WikidataHooks {

	public static function onBeforePageDisplay( $out, $skin ) {
		global $wgLang, $wgScriptPath, $wgRequest, $wgResourceModules;

		$out->addModules( 'ext.Wikidata.css' );
		$out->addModules( 'ext.Wikidata.ajax' );
		
		if ( $wgRequest->getText( 'action' )=='edit' ) {
			$out->addModules( 'ext.Wikidata.edit' );
			$out->addModules( 'ext.Wikidata.suggest' );
		}

		if ( $skin->getTitle()->isSpecialPage() ) {
			$out->addModules( 'ext.Wikidata.suggest' );
		}
		return true;
	}

	private static function isWikidataNs( $title ) {
		global $wdHandlerClasses;
		return array_key_exists( $title->getNamespace(), $wdHandlerClasses );
	}

	/**
	 * FIXME: This does not seem to do anything, need to check whether the
	 *        preferences are still being detected.
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		$datasets = wdGetDatasets();
		foreach ( $datasets as $datasetid => $dataset ) {
			$datasetarray[$dataset->fetchName()] = $datasetid;
		}
		$preferences['ow_uipref_datasets'] = array(
			'type' => 'multiselect',
			'options' => $datasetarray,
			'section' => 'omegawiki',
			'label' => wfMsg( 'ow_shown_datasets' ),
			'prefix' => 'ow_datasets-',
		);
		return true;
	}

	public static function onArticleFromTitle( &$title, &$article ) {
		if ( self::isWikidataNs( $title ) ) {
			$article = new WikidataArticle( $title );
		}
		return true;
	}

	public static function onCustomEditor( $article, $user ) {
		if ( self::isWikidataNs( $article->getTitle() ) ) {
			$editor = new WikidataEditPage( $article );
			$editor->edit();
			return false;
		}
		return true;
	}

	public static function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki ) {
		$action = $request->getVal( 'action' );
		if ( $action === 'history' && self::isWikidataNs( $title ) ) {
			$history = new WikidataPageHistory( $article );
			$history->history();
			return false;
		}
		return true;
	}

	public static function onAbortMove( $oldtitle, $newtitle, $user, &$error, $reason ) {
		if ( self::isWikidataNs( $oldtitle ) ) {
			$error = wfMsg( 'wikidata-handler-namespace-move-error' );
			return false;
		}
		return true;
	}

	/**
	* disable the "move" button for the Expression and DefinedMeaning namespaces
	* and prevent these pages to be moved like standard wiki pages
	* since they work differently
	*/
	public static function onNamespaceIsMovable( $index, $result ) {
		if ( ( $index == NS_EXPRESSION ) || ( $index == NS_DEFINEDMEANING ) ) {
			$result = false;
		}
		return true;
	}

	/**
	* Replaces the proposition to "create new page" by a custom,
	* allowing to create new expression as well
	*/
	public static function onNoGoMatchHook( &$title ) {
		global $wgOut,$wgDisableTextSearch;
		$wgOut->addWikiMsg( 'search-nonefound' );
		$wgOut->addWikiMsg( 'ow_searchnoresult', wfEscapeWikiText( $title ) );
	//	$wgOut->addWikiMsg( 'ow_searchnoresult', $title );

		$wgDisableTextSearch = true ;
		return true;
	}

	/**
	* The Go button should search (first) in the Expression namespace instead of Article namespace
	*/
	public static function onGoClicked( $allSearchTerms, &$title ) {
		$term = $allSearchTerms[0] ;
		$title = Title::newFromText( $term ) ;
		if ( is_null( $title ) ){
			return true;
		}

		# Replace normal namespace with expression namespace
		if ( $title->getNamespace() == NS_MAIN ) {
			$title = Title::newFromText( $term, NS_EXPRESSION ) ; // $expressionNamespaceId ) ;
		}

		if ( $title->exists() ) {
			return false; // match!
		}
		return true; // no match
	}

}
