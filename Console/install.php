<?php

/**
* Maintenance script to create a WikiLexicalData extension for mediawiki
* it generates the tables in a database (passed as parameter) with a defined prefix (passed as parameter)
*/

$baseDir = dirname( __FILE__ ) . '/../../..' ;
require_once( $baseDir . '/maintenance/Maintenance.php' );
require_once( $baseDir . '/extensions/WikiLexicalData/OmegaWiki/WikiDataGlobals.php' );

echo "start\n";

class InstallWikiLexicalData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Installation by creating the tables and filling them with the minimal necessary data\n"
			. 'Example usage: php install.php --prefix=uw '
			. '--template=wikidataTemplate.sql --datasetname="OmegaWiki community"' ;
		$this->addOption( 'freshInstall', 'Drop all tables before creating new ones' );
		$this->addOption( 'prefix', 'The prefix to use for the relational tables. e.g. --prefix=uw' );
		$this->addOption( 'template', 'A sql template describing the relational tables. e.g. --template=databaseTemplate.sql' );
		$this->addOption( 'datasetname', 'A name for your dataset. e.g. --datasetname="OmegaWiki community"' );
	}

	public function execute() {

		global $wdCurrentContext;

		// checking that the needed parameters are given
		if ( !$this->hasOption( 'prefix' ) ) {
			$this->output( "A prefix is missing. Use for example --prefix=uw\n");
			exit(0);
		}
		if ( !$this->hasOption( 'template' ) ) {
			$this->output( "A template is missing. Use for example --template=databaseTemplate.sql\n");
			exit(0);
		}

		$prefix = $this->getOption( 'prefix' );
		$template = $this->getOption( 'template' );
		$datasetname = $this->getOption( 'datasetname' );
		$wdCurrentContext = $prefix ;

		if ( $this->hasOption( 'freshInstall' ) ) {
			$this->output( "Dropping all tables\n");
			$this->dropTables( $prefix );
		}

		$this->output( "Creating relational tables...\n" );

		$this->ReadTemplateSQLFile( "/*\$wgWDprefix*/", $prefix . "_", dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $template );

		// entering dataset in table wikidata_sets
		$dbw = wfGetDB( DB_MASTER );
		$dbw->query( "DELETE FROM wikidata_sets WHERE set_prefix = '$prefix'" );
		$dbw->query( "INSERT INTO wikidata_sets (set_prefix,set_fallback_name,set_dmid) VALUES ('$prefix','$datasetname',0)" );

		$this->output( "Adding language English...\n" );
		$this->createLanguageEnglish( $prefix );

		$this->output( "Filling table {$prefix}_bootstrapped_defined_meanings...\n" );
		$this->bootStrappedDefinedMeanings( $prefix );

		$this->output( "Adding some more data to enable annotations...\n" );
		$this->enableAnnotations( $prefix );
	}


	protected function createLanguageEnglish( $dc ) {
		$dbw = wfGetDB( DB_MASTER );
		global $wgDBtype;
		$options = array();
		if ( $wgDBtype == 'sqlite' ) {
			$options = array( 'IGNORE' => true );
		}

		$langname = "English";
		$langiso6392 = "en";
		$langiso6393 = "eng";
		$langwmf = "en";
		$langid = WLD_ENGLISH_LANG_ID;

		$dbw->insert(
			'language',
			array(
				'language_id' => $langid,
				'iso639_2' => $langiso6392,
				'iso639_3' => $langiso6393,
				'wikimedia_key' => $langwmf
			), __METHOD__,
			$options
		);

		$dbw->insert(
			'language_names',
			array(
				'language_id' => $langid,
				'name_language_id' => $langid,
				'language_name' => $langname
			), __METHOD__,
			$options
		);
	}

	protected function bootStrappedDefinedMeanings( $dc ) {
		// Admin user
		$userId = 1 ;
		$dbw = wfGetDB( DB_MASTER );

		// check that it is really a fresh install
		$query = "SELECT * FROM  {$dc}_collection" ;
		$queryResult = $dbw->query( $query );
		if ( $dbw->numRows( $queryResult ) > 0 ) {
			echo "Table {$dc}_collection not empty.\n" ;
			echo "\nERROR: It appears that Wikidata is at least already partially installed on your system\n" ;
			echo "\nIf you would like to do a fresh install, drop the following tables, and run the install script again:\n" ;
			$this->printDropTablesCommand( $dc );
			exit(0);
		}

		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $dc );
		$collectionId = bootstrapCollection( "Class attribute levels", WLD_ENGLISH_LANG_ID, "LEVL", $dc );

		$definedMeaningMeaningName = "DefinedMeaning";
		$definitionMeaningName = "Definition";
		$relationMeaningName = "Relation";
		$synTransMeaningName = "SynTrans";
		$annotationMeaningName = "Annotation";

		$meanings = array();
		$meanings[$definedMeaningMeaningName] = $this->bootstrapDefinedMeaning( $definedMeaningMeaningName, WLD_ENGLISH_LANG_ID, "The combination of an expression and definition in one language defining a concept." );
		$meanings[$definitionMeaningName] = $this->bootstrapDefinedMeaning( $definitionMeaningName, WLD_ENGLISH_LANG_ID, "A paraphrase describing a concept." );
		$meanings[$synTransMeaningName] = $this->bootstrapDefinedMeaning( $synTransMeaningName, WLD_ENGLISH_LANG_ID, "A translation or a synonym that is equal or near equal to the concept defined by the defined meaning." );
		$meanings[$relationMeaningName] = $this->bootstrapDefinedMeaning( $relationMeaningName, WLD_ENGLISH_LANG_ID, "The association of two defined meanings through a specific relation type." );
		$meanings[$annotationMeaningName] = $this->bootstrapDefinedMeaning( $annotationMeaningName, WLD_ENGLISH_LANG_ID, "Characteristic information of a concept." );

		foreach ( $meanings as $internalName => $meaningId ) {
			addDefinedMeaningToCollection( $meaningId, $collectionId, $internalName );

			$dbw->query( "INSERT INTO `{$dc}_bootstrapped_defined_meanings` (name, defined_meaning_id) " .
					"VALUES (" . $dbw->addQuotes( $internalName ) . ", " . $meaningId . ")" );
		}

	}

	protected function enableAnnotations( $dc ) {
		// Admin user
		$userId = 1 ;

		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $dc );

		// a collection of classes. A word added to that collection becomes a class
		$classCollectionId = bootstrapCollection( "Community class", WLD_ENGLISH_LANG_ID, "CLAS", $dc );

		// a collection of iso639-3 codes, to enable translation of the interface
		// and language specific annotations
		$iso6393CollectionId = bootstrapCollection( "ISO 639-3 codes", WLD_ENGLISH_LANG_ID, "LANG", $dc );

		// DM lexical item, a class by default for every word
		$lexicalItemDMId = $this->bootstrapDefinedMeaning( "lexical item", WLD_ENGLISH_LANG_ID, "Lexical item is used as a class by default." );
		addDefinedMeaningToCollection( $lexicalItemDMId, $classCollectionId, "" );

		// DM English, a class by default for English words
		$englishDMId = $this->bootstrapDefinedMeaning( "English", WLD_ENGLISH_LANG_ID,
			"A West-Germanic language originating in England but now spoken in all parts of the British Isles,"
			. " the Commonwealth of Nations, the United States of America, and other parts of the world."
		);
		addDefinedMeaningToCollection( $englishDMId, $iso6393CollectionId, "eng" );

		echo "**\n";
		echo "Add to your LocalSettings.php: \n";
		echo '$wgDefaultClassMids = array(' . $lexicalItemDMId . ");\n";
		echo '$wgIso639_3CollectionId = ' . $iso6393CollectionId . ";\n";
		echo "**\n";
	}

	protected function bootstrapDefinedMeaning( $spelling, $languageId, $definition ) {
		$expression = findOrCreateExpression( $spelling, $languageId );
		$definedMeaningId = createNewDefinedMeaning( $expression->id, $languageId, $definition );

		return $definedMeaningId;
	}


	protected function dropTables( $dc ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'page',
			array( 'page_namespace' => NS_EXPRESSION ),
			__METHOD__
		);
		$dbw->delete(
			'page',
			array( 'page_namespace' => NS_DEFINEDMEANING ),
			__METHOD__
		);
		$owTableNames = $this->getWLDtableNamesAddtl();
		foreach ( $owTableNames as $drop ) {
			$dbw->dropTable( $drop, __METHOD__ );
		}
		$owTableNames = $this->getWLDtableNames();
		foreach ( $owTableNames as $drop ) {
			$dbw->dropTable( $dc . $drop, __METHOD__ );
		}
	}

	protected function printDropTablesCommand( $dc ) {
		$dropCommand = "drop table ";

		$owTableNames = $this->getWLDtableNamesAddtl();
		foreach ( $owTableNames as $drop ) {
			$dropCommand .= "$drop, ";
		}

		$owTableNames = $this->getWLDtableNames();
		foreach ( $owTableNames as $drop ) {
			$dropCommand .= $dc . "$drop, ";
		}
		$dropCommand = preg_replace( '/, $/', '', $dropCommand );
		echo "\n\n$dropCommand\n\n";
	}

	protected function ReadTemplateSQLFile( $pattern, $prefix, $filename ) {
		$dbw = wfGetDB( DB_MASTER );
		global $wgDBtype;

		$fp = fopen( $filename, 'r' );
		if ( false === $fp ) {
			return "Could not open \"{$filename}\".\n";
		}

		$cmd = "";
		$done = false;

		while ( ! feof( $fp ) ) {
			$line = trim( fgets( $fp, 1024 ) );
			$sl = strlen( $line ) - 1;

			if ( $sl < 0 ) { continue; }
			if ( '-' == $line { 0 } && '-' == $line { 1 } ) { continue; }

			if ( ';' == $line { $sl } && ( $sl < 2 || ';' != $line { $sl - 1 } ) ) {
				$done = true;
				$line = substr( $line, 0, $sl );
			}

			if ( '' != $cmd ) { $cmd .= ' '; }
			$cmd .= "$line\n";

			if ( $wgDBtype == 'sqlite' ) {
				$cmd = $this->sqliteLineReplace( $cmd );
			}

			if ( $done ) {
				$cmd = str_replace( ';;', ";", $cmd );
				$cmd = trim( str_replace( $pattern, $prefix, $cmd ) );

				$res = $dbw->query( $cmd );

				if ( false === $res ) {
					return "Query \"{$cmd}\" failed with error code \".\n";
				}

				$cmd = '';
				$done = false;
			}
		}
		fclose( $fp );
		return true;
	}

	protected function sqliteLineReplace( $string ) {
		$string = preg_replace( '/ int(eger|) /i', ' INTEGER ', $string );
		$string = preg_replace( '/ int(eger|)\(/i', " INTEGER(", $string );
		$string = str_replace( ' auto_increment', " AUTO_INCREMENT", $string );

		$string = str_replace( 'CREATE INDEX', "CREATE INDEX IF NOT EXISTS", $string );
		if ( preg_match( '/CREATE INDEX /', $string ) ) {
			if ( preg_match( '/`user` ON/', $string ) ) {
				$string = str_replace( "`user`", "`user_id`", $string );
			}
			$string = preg_replace( '/(\(\d+\))/', ' ', $string );
		}
		$string = str_replace( ' unsigned', '', $string );
		$string = preg_replace( '/INTEGER\(\d+\)/', 'INTEGER', $string );
		$string = str_replace( 'AUTO_INCREMENT', "AUTOINCREMENT", $string );
		$string = str_replace( 'collate utf8_bin', "collate binary", $string );
		$string = str_replace( ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci', ")", $string );
		return ( $string );
	}

	protected function getWLDtableNames() {
		return array(
			"_alt_meaningtexts",
			"_bootstrapped_defined_meanings",
			"_class_attributes",
			"_class_membership",
			"_collection",
			"_collection_contents",
			"_collection_language",
			"_defined_meaning",
			"_expression",
			"_meaning_relations",
			"_objects",
			"_option_attribute_options",
			"_option_attribute_values",
			"_script_log",
			"_syntrans",
			"_text",
			"_text_attribute_values",
			"_transactions",
			"_translated_content",
			"_translated_content_attribute_values",
			"_url_attribute_values"
		);
	}

	protected function getWLDtableNamesAddtl() {
		return array( "language", "language_names" );
	}
}

$maintClass = 'InstallWikiLexicalData';
require_once( RUN_MAINTENANCE_IF_MAIN );
