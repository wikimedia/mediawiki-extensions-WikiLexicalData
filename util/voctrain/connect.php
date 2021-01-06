<?php

function connect() {
	$server = "localhost";
	$user = "kim";
	$password = "";
	$dbname = "trainer";

	$connection = MySQL_connect( $server, $user, $password );
	if ( !$connection ) {
		die( "Cannot connect to SQL server. Try again later." );
	}
	if ( !MySQL_select_db( $dbname ) ) {
		die( "Cannot open database" );
	}
	mysql_query( "SET NAMES 'utf8'" );
}
