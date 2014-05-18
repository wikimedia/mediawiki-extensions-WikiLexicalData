<?php
/** @file
 *
 * @brief Contains class OwDatabaseAPI.
 *
 * Future access point for the OmegaWiki PHP API.
 *
 * @todo In the future, WikidataAPI.php functions must be slowly
 * transferred to their respective classes and then accessed here.
 */
require_once( 'WikiDataAPI.php' );
require_once( 'Attribute.php' );
require_once( 'DefinedMeaning.php' );

/** @class OwDatabaseAPI
 *
 * @brief This is the unified PHP API class to access the
 * WikiLexical OmegaWiki database.
 */
class OwDatabaseAPI {

	/** @addtogroup OwDbAPIdmFn OwDatabaseAPI's Defined Meaning functions
	 *	@{
	 */

	/**
	 * @brief Returns the defined_meaning table's DefinedMeaning id via translatedContentId
	 *
	 * @param translatedContentId req'd int The object id
	 * @param options             opt'l arr An optional parameters
	 * @param dc                  opt'l str The WikiLexicalData dataset
	 *
	 * @return array( int meaning1_id, int relationtype_mid, int meaning2_mid )
	 * @return if not exists, array()
	 *
	 * @see DefinedMeanings::getTranslatedContentIdDefinedMeaningId, for a list of options.
	 */
	public static function getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options = array(), $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'definedMeaning', $dc );
		return $api->DefinedMeaning->getTranslatedContentIdDefinedMeaningId( $translatedContentId, $options, $api->dc );
	}

	/*! @} group OwDbAPIdmFn ends here.*/

	/** @addtogroup OwDbAPIlangFn OwDatabaseAPI's language functions
	 *	@{
	 */

	/**
	 * @brief returns the User Language Id
	 *
	 * @return language id
	 * @return if not exist, null
	 *
	 * @todo transfer function to language api class.
	 */
	public static function getUserLanguageId() {
		global $wgLang;
		if ( !$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ) {
			$code = OwDatabaseAPI::getLanguageCodeForIso639_3( $wgLang->getCode() );
			$userLanguageId = getLanguageIdForCode( $code );
		}
		return $userLanguageId;
	}

	/**
	 * @brief returns the User Language Id
	 *
	 * @return language id
	 * @return if not exist, null
	 *
	 * @todo transfer function to language api class.
	 */
	public static function getUserLanguage() {
		global $wgLang;
		if ( !$userLanguageId = getLanguageIdForCode( $wgLang->getCode() ) ) {
			$userLanguage = OwDatabaseAPI::getLanguageCodeForIso639_3( $wgLang->getCode() );
		} else {
			$userLanguage = $wgLang->getCode();
		}

		return $userLanguage;
	}

	/**
	 * @param $purge purge cache
	 * @return array of language names for the user's language preference
	 */
	static function getOwLanguageNames( $purge = false ) {
		static $owLanguageNames = null;
		if ( is_null( $owLanguageNames ) && !$purge ) {
			$userLanguage = owDatabaseAPI::getUserLanguage();
			$owLanguageNames = getLangNames( $userLanguage );
		}
		return $owLanguageNames;
	}

	/* @return Return an array containing all language names translated into the language
	 *	indicated by $code, with fallbacks in English where the language names
	 *	aren't present in that language.
	 * @see WLDLanguage::getNames
	 */
	static function getLangNames( $code ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'language' );
		return $api->Language->getNames( $code );
	}

	static function getLanguageCodeForIso639_3( $iso639_3 ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'language' );
		return $api->Language->getCodeForIso639_3( $iso639_3 );
	}

	/*! @} group OwDbAPIlangFn ends here.*/

	/** @addtogroup OwDbAPIrelAttFn OwDatabaseAPI's relations Attribute functions
	 *	@{
	 */

	/**
	 * @brief Returns the meaning_relations table's details via relation_id
	 *
	 * @param objectId req'd int The object id
	 * @param options  opt'l arr An optional parameters
	 * @param dc       opt'l str The WikiLexicalData dataset
	 *
	 * @return array( int defined_meaning_id )
	 * @return if not exists, array()
	 *
	 * @see Attributes::getRelationIdRelation, for a list of options.
	 */
	public static function getRelationIdRelationAttribute( $relationId, $options = array(), $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'attributes', $dc );
		return $api->Attributes->getRelationIdRelation( $relationId, $options, $api->dc );
	}

	/*! @} group OwDbAPIrelAttFn ends here.*/

	/** @addtogroup OwDbAPIsyntFn OwDatabaseAPI's Syntrans functions
	 *	@{
	 */

	/**
	 * @param syntransId req'd int The syntrans id
	 * @param options    opt'l arr An optional parameters
	 * @param dc         opt'l str The WikiLexicalData dataset
	 *
	 * @return array( str spelling, int defined_meaning_id )
	 * @return if not exists, array()
	 *
	 * @see Syntrans::getSpellingWithDM, for a list of options.
	 */
	public static function getSyntransSpellingWithDM( $syntransId, $options = array(), $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'syntrans', $dc );
		return $api->Syntrans->getSpellingWithDM( $syntransId, $options, $api->dc );
	}

	/*! @} group OwDbAPIsyntFn ends here.*/

	/** @addtogroup OwDbAPItransactFn OwDatabaseAPI's Transactions functions
	 *	@{
	 */

	/**
	 * @param transactionId req'd int The transaction id
	 * @param options       opt'l arr Optional parameters
	 * @param dc            opt'l str The WikiLexicalData dataset
	 *
	 * @return array( int user_id, str user_ip, str timestamp, str comment )
	 * @return if not exists, array()
	 *
	 * @see Transactions::getIdDetails, for a list of options.
	 */
	public static function getTransactionIdDetails( $transactionId, $options = array(), $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'transaction', $dc );
		return $api->Transaction->getIdDetails( $transactionId, $options, $api->dc );
	}

	/**
	 * @param languageId req'd int The language id
	 * @param options    opt'l arr Optional parameters
	 * @param dc         opt'l str The WikiLexicalData dataset
	 *
	 * @return array( int user_id, str user_ip, str timestamp, str comment )
	 * @return if not exists, array()
	 *
	 * @see Transactions::getLanguageIdLatestTransactionId, for a list of options.
	 */
	public static function getLanguageIdLatestTransactionId( $languageId, $options = array(), $dc = null ) {
		$api = new OwDatabaseAPI;
		$api->settings( 'transaction', $dc );
		return $api->Transaction->getLanguageIdLatestTransactionId( $languageId, $options, $api->dc );
	}

	/*! @} group OwDbAPItransactFn ends here.*/

	/**
	 * @brief sets the initial settings for static functions
	 *
	 * @param class req'd str The database class to access
	 * @param dc    opt'l str The WikiLexicalData dataset
	 */
	protected function settings( $class, $dc = null ) {
		$this->getDc();

		if ( $class == 'attributes' ) { $this->Attributes = new Attributes; }
		if ( $class == 'syntrans' ) { $this->Syntrans = new Syntrans; }
		if ( $class == 'definedMeaning' ) { $this->DefinedMeaning = new DefinedMeanings; }
		if ( $class == 'transaction' ) { $this->Transaction = new Transactions; }
		if ( $class == 'language' ) { $this->Language = new WLDLanguage; }
	}

	/**
	 * @brief sets the dc.
	 * @return string $dc
	 */
	protected function getDc( $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$this->dc = $dc;
	}

}

/** @class Syntrans
 *
 * @brief PHP API class for Syntrans
 */
class Syntrans {

	/**
	 * @param syntransId req'd int The syntrans id
	 * @param options    opt'l arr  An optional parameters
	 * * "option['test'] = true" used to test the function
	 * @param dc         opt'l str The WikiLexicalData dataset
	 *
	 * @return if exist, array( str spelling, int defined_meaning_id )
	 * @return if not, array()
	 *
	 * @note options parameter can be used to extend this function.
	 * Though you can access this function, it is highly recommended that you
	 * use the static function OwDatabaseAPI::getSyntransSpellingWithDM instead.
	 * Also note that this function currently includes all data, even removed ones.
	 *
	 */
	public static function getSpellingWithDM( $syntransId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$test = false;
		if ( isset( $options['test'] ) ) {
			$test = true;
		}

		$syntrans = $dbr->selectRow(
			array(
				'synt' => "{$dc}_syntrans",
				'exp' => "{$dc}_expression",
			),
			array(
				'spelling',
				'defined_meaning_id'
			),
			array(
				'syntrans_sid' => $syntransId,
				"synt.expression_id = exp.expression_id"
			), __METHOD__
		);

		if ( $syntrans ) {
			if ( $test ) { var_dump( $syntrans ); die; }
			return $syntrans;
		}
		if ( $test ) { echo 'array()'; die; }
		return array();
	}

}
