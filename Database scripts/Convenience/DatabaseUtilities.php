<?php

function dropAllIndicesFromTable( $tableName ) {
	$dbr = wfGetDB( DB_PRIMARY );

	$queryResult = $dbr->query( "SHOW INDEXES FROM " . $tableName );

	$indexNames = [];

	while ( $indexRow = $dbr->fetchObject( $queryResult ) ) {
		$indexName = $indexRow->Key_name;

		if ( !in_array( $indexName, $indexNames ) && $indexName != 'PRIMARY' ) {
			$indexNames[] = $indexName;
		}
	}

	if ( count( $indexNames ) > 0 ) {
		$sql = "ALTER TABLE `" . $tableName . "` DROP INDEX `" . $indexNames[0] . "`";

		for ( $i = 1; $i < count( $indexNames ); $i++ ) {
			$sql .= ", DROP INDEX `" . $indexNames[$i] . "`";
		}

		$sql .= ";";

		$dbr->query( $sql );
	}
}

function addIndexes( $tableName, array $indexes ) {
	if ( count( $indexes ) > 0 ) {
		$dbr = wfGetDB( DB_PRIMARY );
		$indexesSQL = [];

		foreach ( $indexes as $indexName => $columns ) {
			$indexesSQL[] = " ADD INDEX `" . $indexName . "` (" . implode( ", ", $columns ) . ") ";
		}

		$sql = "ALTER TABLE " . $tableName . " " . implode( ", ", $indexesSQL );
		$dbr->query( $sql );
	}
}
