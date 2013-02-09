<?php

/**
* Maintenance script to create a wikidata extension for mediawiki
* it generates the tables in a database (passed as parameter) with a defined prefix (passed as parameter)
*/

$baseDir = dirname( __FILE__ ) . '/../../..' ;
require_once( $baseDir . '/maintenance/Maintenance.php' );

echo "start\n";

class InstallWikidata extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Install Wikidata by creating the tables and filling them with the minimal necessary data\n"
			. 'Example usage: php installWikidata.php --prefix=uw '
			. '--template=wikidataTemplate.sql --datasetname="OmegaWiki community"' ;
		$this->addOption( 'prefix', 'The prefix to use for Wikidata relational tables. e.g. --prefix=uw' );
		$this->addOption( 'template', 'A sql template describing the relational tables. e.g. --template=wikidataTemplate.sql' );
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
			$this->output( "A template is missing. Use for example --template=wikidataTemplate.sql\n");
			exit(0);
		}

		$prefix = $this->getOption( 'prefix' );
		$template = $this->getOption( 'template' );
		$datasetname = $this->getOption( 'datasetname' );
		$wdCurrentContext = $prefix ;

		$this->output( "Creating Wikidata tables...\n" );

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

		$langname = "English";
		$langiso6392 = "en";
		$langiso6393 = "eng";
		$langwmf = "en";
		$sql = 'INSERT IGNORE INTO language(language_id, iso639_2,iso639_3,wikimedia_key) values('
			. WLD_ENGLISH_LANG_ID . ','
			. $dbw->addQuotes( $langiso6392 ) . ','
			. $dbw->addQuotes( $langiso6393 ) . ','
			. $dbw->addQuotes( $langwmf ) . ')';

		$dbw->query( $sql );

		$sql = 'INSERT IGNORE INTO language_names(language_id,name_language_id,language_name) values ('
			. WLD_ENGLISH_LANG_ID . ','
			. WLD_ENGLISH_LANG_ID . ','
			. $dbw->addQuotes( $langname ) . ')';
		$dbw->query( $sql );
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
		$dbw = wfGetDB( DB_MASTER );

		startNewTransaction( $userId, 0, "Script bootstrap class attribute meanings", $dc );

		// a collection of classes. A word added to that collection becomes a class
		$lexicalCollectionId = bootstrapCollection( "lexical functionality", WLD_ENGLISH_LANG_ID, "CLAS", $dc );

		// a collection of iso639-3 codes, to enable translation of the interface
		// and language specific annotations
		$iso6393CollectionId = bootstrapCollection( "ISO 639-3 codes", WLD_ENGLISH_LANG_ID, "", $dc );

		// DM lexical item, a class by default for every word
		$lexicalItemDMId = $this->bootstrapDefinedMeaning( "lexical item", WLD_ENGLISH_LANG_ID, "Lexical item is used as a class by default." );
		addDefinedMeaningToCollection( $lexicalItemDMId, $lexicalCollectionId, "" );

		// DM English, a class by default for English words
		$englishDMId = $this->bootstrapDefinedMeaning( "English", WLD_ENGLISH_LANG_ID,
			"A West-Germanic language originating in England but now spoken in all parts of the British Isles,"
			. " the Commonwealth of Nations, the United States of America, and other parts of the world."
		);
		addDefinedMeaningToCollection( $englishDMId, $lexicalCollectionId, "" );
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


	protected function printDropTablesCommand( $dc ) {
		$dropCommand = "drop table ";
		$dropCommand .= $dc . "_alt_meaningtexts, ";
		$dropCommand .= $dc . "_bootstrapped_defined_meanings, ";
		$dropCommand .= $dc . "_class_attributes, ";
		$dropCommand .= $dc . "_class_membership, ";
		$dropCommand .= $dc . "_collection, ";
		$dropCommand .= $dc . "_collection_contents, ";
		$dropCommand .= $dc . "_collection_language, ";
		$dropCommand .= $dc . "_defined_meaning, ";
		$dropCommand .= $dc . "_expression, ";
		$dropCommand .= $dc . "_meaning_relations, ";
		$dropCommand .= $dc . "_objects, ";
		$dropCommand .= $dc . "_option_attribute_options, ";
		$dropCommand .= $dc . "_option_attribute_values, ";
		$dropCommand .= $dc . "_script_log, ";
		$dropCommand .= $dc . "_syntrans, ";
		$dropCommand .= $dc . "_text, ";
		$dropCommand .= $dc . "_text_attribute_values, ";
		$dropCommand .= $dc . "_transactions, ";
		$dropCommand .= $dc . "_translated_content, ";
		$dropCommand .= $dc . "_translated_content_attribute_values, ";
		$dropCommand .= $dc . "_url_attribute_values ";

		echo "\n\n$dropCommand\n\n";
	}

	protected function ReadTemplateSQLFile( $pattern, $prefix, $filename ) {
		$dbw = wfGetDB( DB_MASTER );

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

}

$maintClass = 'InstallWikidata';
require_once( RUN_MAINTENANCE_IF_MAIN );