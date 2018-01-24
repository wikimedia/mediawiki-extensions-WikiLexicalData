<?php

define( 'WLD_ANNOTATION_MEANING_NAME', "Annotation" );
define( 'WLD_DM_MEANING_NAME', "DefinedMeaning" );
define( 'WLD_DEFINITION_MEANING_NAME', "Definition" );
define( 'WLD_RELATION_MEANING_NAME', "Relation" );
define( 'WLD_SYNTRANS_MEANING_NAME', "SynTrans" );

global $wgWldClassAttributeLevels;
$wgWldClassAttributeLevels = [
	WLD_DM_MEANING_NAME,
	WLD_DEFINITION_MEANING_NAME,
	WLD_SYNTRANS_MEANING_NAME,
	WLD_ANNOTATION_MEANING_NAME
];
