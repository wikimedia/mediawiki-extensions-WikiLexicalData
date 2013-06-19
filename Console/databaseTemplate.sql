--
-- Add the wikidata specific namespaces
--

CREATE TABLE IF NOT EXISTS language (
	language_id int(10) NOT NULL PRIMARY KEY auto_increment,
	dialect_of_lid int(10) NOT NULL default '0',
	iso639_2 varchar(10) collate utf8_bin NOT NULL default '',
	iso639_3 varchar(10) collate utf8_bin NOT NULL default '',
	wikimedia_key varchar(10) collate utf8_bin NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS language_names (
	language_id int(10) NOT NULL default '0',
	name_language_id int(10) NOT NULL default '0',
	language_name varchar(255) NOT NULL default '',
	PRIMARY KEY (language_id,name_language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX language_id ON language_names (language_id);

CREATE TABLE IF NOT EXISTS wikidata_sets (
	set_prefix varchar(20) default NULL,
	set_fallback_name varchar(255) default NULL,
	set_dmid int(10) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/alt_meaningtexts (
	`meaning_mid` int(10) default NULL,
	`meaning_text_tcid` int(10) default NULL,
	`source_id` int(11) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_meaning ON /*$wgWDprefix*/alt_meaningtexts (`remove_transaction_id`,`meaning_mid`,`meaning_text_tcid`,`source_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_text ON /*$wgWDprefix*/alt_meaningtexts (`remove_transaction_id`,`meaning_text_tcid`,`meaning_mid`,`source_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_source ON /*$wgWDprefix*/alt_meaningtexts (`remove_transaction_id`,`source_id`,`meaning_mid`,`meaning_text_tcid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_meaning ON /*$wgWDprefix*/alt_meaningtexts (`add_transaction_id`,`meaning_mid`,`meaning_text_tcid`,`source_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_text ON /*$wgWDprefix*/alt_meaningtexts (`add_transaction_id`,`meaning_text_tcid`,`meaning_mid`,`source_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_source ON /*$wgWDprefix*/alt_meaningtexts (`add_transaction_id`,`source_id`,`meaning_mid`,`meaning_text_tcid`);


CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/bootstrapped_defined_meanings (
	`name` varchar(255) NOT NULL,
	`defined_meaning_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/unversioned_meaning ON /*$wgWDprefix*/bootstrapped_defined_meanings (`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/unversioned_name ON /*$wgWDprefix*/bootstrapped_defined_meanings (`name` (255),`defined_meaning_id`);

-- object_id - key for the attribute, used elsewhere as a foreign key
-- class_mid - which class (identified by DMID) has this attribute?
-- level_mid - on which level can we annotate: Annotation, DefinedMeaning, Definition, Relation, SynTrans; these are also cached in *_bootstrapped_defined_meanings
-- attribute_mid - which attribute are we describing?
-- attribute_type - what kind of information are we talking about? can be 'DM', 'TRNS' (translatable text), 'TEXT', 'URL', 'OPTN' (multiple DMs to choose from)a
-- attribute_id - refers to the object_id from xx_class_attributes
CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/class_attributes (
	`object_id` int(11) NOT NULL,
	`class_mid` int(11) NOT NULL default '0',
	`level_mid` int(11) NOT NULL,
	`attribute_mid` int(11) NOT NULL default '0',
	`attribute_type` char(4) collate utf8_bin NOT NULL default 'TEXT',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_class ON /*$wgWDprefix*/class_attributes (`remove_transaction_id`,`class_mid`,`attribute_mid`,`object_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_attribute ON /*$wgWDprefix*/class_attributes (`remove_transaction_id`,`attribute_mid`,`class_mid`,`object_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_object ON /*$wgWDprefix*/class_attributes (`remove_transaction_id`,`object_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_class ON /*$wgWDprefix*/class_attributes (`add_transaction_id`,`class_mid`,`attribute_mid`,`object_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_attribute ON /*$wgWDprefix*/class_attributes (`add_transaction_id`,`attribute_mid`,`class_mid`,`object_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_object ON /*$wgWDprefix*/class_attributes (`add_transaction_id`,`object_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/class_membership (
	`class_membership_id` int(11) NOT NULL,
	`class_mid` int(11) NOT NULL default '0',
	`class_member_mid` int(11) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_class ON /*$wgWDprefix*/class_membership (`remove_transaction_id`,`class_mid`,`class_member_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_class_member ON /*$wgWDprefix*/class_membership (`remove_transaction_id`,`class_member_mid`,`class_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_class_membership ON /*$wgWDprefix*/class_membership (`remove_transaction_id`,`class_membership_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_class ON /*$wgWDprefix*/class_membership (`add_transaction_id`,`class_mid`,`class_member_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_class_member ON /*$wgWDprefix*/class_membership (`add_transaction_id`,`class_member_mid`,`class_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_class_membership ON /*$wgWDprefix*/class_membership (`add_transaction_id`,`class_membership_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/collection_contents (
	`object_id` int(11) default NULL,
	`collection_id` int(10) NOT NULL default '0',
	`member_mid` int(10) NOT NULL default '0',
	`internal_member_id` varchar(255) default NULL,
	`applicable_language_id` int(10) default NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_collection ON /*$wgWDprefix*/collection_contents (`remove_transaction_id`,`collection_id`,`member_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_collection_member ON /*$wgWDprefix*/collection_contents (`remove_transaction_id`,`member_mid`,`collection_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_internal_id ON /*$wgWDprefix*/collection_contents (`remove_transaction_id`,`internal_member_id`,`collection_id`,`member_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_collection ON /*$wgWDprefix*/collection_contents (`add_transaction_id`,`collection_id`,`member_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_collection_member ON /*$wgWDprefix*/collection_contents (`add_transaction_id`,`member_mid`,`collection_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_internal_id ON /*$wgWDprefix*/collection_contents (`add_transaction_id`,`internal_member_id`,`collection_id`,`member_mid`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/collection_language (
	`collection_id` int(10) NOT NULL default '0',
	`language_id` int(10) NOT NULL default '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/collection (
	`collection_id` int(10) unsigned NOT NULL,
	`collection_mid` int(10) NOT NULL default '0',
	`collection_type` char(4) default NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_collection ON /*$wgWDprefix*/collection (`remove_transaction_id`,`collection_id`,`collection_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_collection_meaning ON /*$wgWDprefix*/collection (`remove_transaction_id`,`collection_mid`,`collection_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_collection_type ON /*$wgWDprefix*/collection (`remove_transaction_id`,`collection_type` (4),`collection_id`,`collection_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_collection ON /*$wgWDprefix*/collection (`add_transaction_id`,`collection_id`,`collection_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_collection_meaning ON /*$wgWDprefix*/collection (`add_transaction_id`,`collection_mid`,`collection_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_collection_type ON /*$wgWDprefix*/collection (`add_transaction_id`,`collection_type` (4),`collection_id`,`collection_mid`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/defined_meaning (
	`defined_meaning_id` int(8) unsigned NOT NULL,
	`expression_id` int(10) NOT NULL,
	`meaning_text_tcid` int(10) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_meaning ON /*$wgWDprefix*/defined_meaning (`remove_transaction_id`,`defined_meaning_id`,`expression_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_expression ON /*$wgWDprefix*/defined_meaning (`remove_transaction_id`,`expression_id`,`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_meaning_text ON /*$wgWDprefix*/defined_meaning (`remove_transaction_id`,`meaning_text_tcid`,`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_meaning ON /*$wgWDprefix*/defined_meaning (`add_transaction_id`,`defined_meaning_id`,`expression_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_expression ON /*$wgWDprefix*/defined_meaning (`add_transaction_id`,`expression_id`,`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_meaning_text ON /*$wgWDprefix*/defined_meaning (`add_transaction_id`,`meaning_text_tcid`,`defined_meaning_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/expression (
	`expression_id` int(10) unsigned NOT NULL,
	`spelling` varbinary(255) NOT NULL default '',
	`language_id` int(10) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_expression ON /*$wgWDprefix*/expression (`remove_transaction_id`,`expression_id`,`language_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_language ON /*$wgWDprefix*/expression (`remove_transaction_id`,`language_id`,`expression_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_spelling ON /*$wgWDprefix*/expression (`remove_transaction_id`,`spelling`(255),`expression_id`,`language_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_expression ON /*$wgWDprefix*/expression (`add_transaction_id`,`expression_id`,`language_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_language ON /*$wgWDprefix*/expression (`add_transaction_id`,`language_id`,`expression_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_spelling ON /*$wgWDprefix*/expression (`add_transaction_id`,`spelling`,`expression_id`,`language_id`);
CREATE INDEX /*$wgWDprefix*/spelling_idx ON /*$wgWDprefix*/expression (`spelling`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/meaning_relations (
	`relation_id` int(11) NOT NULL,
	`meaning1_mid` int(10) NOT NULL default '0',
	`meaning2_mid` int(10) NOT NULL default '0',
	`relationtype_mid` int(10) default NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_outgoing ON /*$wgWDprefix*/meaning_relations (`remove_transaction_id`,`meaning1_mid`,`relationtype_mid`,`meaning2_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_incoming ON /*$wgWDprefix*/meaning_relations (`remove_transaction_id`,`meaning2_mid`,`relationtype_mid`,`meaning1_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_relation ON /*$wgWDprefix*/meaning_relations (`remove_transaction_id`,`relation_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_outgoing ON /*$wgWDprefix*/meaning_relations (`add_transaction_id`,`meaning1_mid`,`relationtype_mid`,`meaning2_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_incoming ON /*$wgWDprefix*/meaning_relations (`add_transaction_id`,`meaning2_mid`,`relationtype_mid`,`meaning1_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_relation ON /*$wgWDprefix*/meaning_relations (`add_transaction_id`,`relation_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/objects (
	`object_id` int(11) NOT NULL PRIMARY KEY auto_increment,
	`table` varchar(100) collate utf8_bin NOT NULL,
	`original_id` int(11) default NULL,
	`UUID` varchar(36) collate utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX `table` ON /*$wgWDprefix*/objects (`table`);
CREATE INDEX `original_id` ON /*$wgWDprefix*/objects (`original_id`);

-- attribute_id - refers to the object_id from xx_class_attributes
CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/option_attribute_options (
	`option_id` int(11) NOT NULL default '0',
	`attribute_id` int(11) NOT NULL default '0',
	`option_mid` int(11) NOT NULL default '0',
	`language_id` int(11) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL default '0',
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_option ON /*$wgWDprefix*/option_attribute_options (`remove_transaction_id`,`option_mid`,`attribute_id`,`option_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_attribute ON /*$wgWDprefix*/option_attribute_options (`remove_transaction_id`,`attribute_id`,`option_id`,`option_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_id ON /*$wgWDprefix*/option_attribute_options (`remove_transaction_id`,`option_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_option ON /*$wgWDprefix*/option_attribute_options (`add_transaction_id`,`option_mid`,`attribute_id`,`option_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_attribute ON /*$wgWDprefix*/option_attribute_options (`add_transaction_id`,`attribute_id`,`option_id`,`option_mid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_id ON /*$wgWDprefix*/option_attribute_options (`add_transaction_id`,`option_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/option_attribute_values (
	`value_id` int(11) NOT NULL default '0',
	`object_id` int(11) NOT NULL default '0',
	`option_id` int(11) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL default '0',
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_object ON /*$wgWDprefix*/option_attribute_values (`remove_transaction_id`,`object_id`,`option_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_option ON /*$wgWDprefix*/option_attribute_values (`remove_transaction_id`,`option_id`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_value ON /*$wgWDprefix*/option_attribute_values (`remove_transaction_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_object ON /*$wgWDprefix*/option_attribute_values (`add_transaction_id`,`object_id`,`option_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_option ON /*$wgWDprefix*/option_attribute_values (`add_transaction_id`,`option_id`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_value ON /*$wgWDprefix*/option_attribute_values (`add_transaction_id`,`value_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/script_log (
	`script_id` int(11) NOT NULL default '0',
	`time` datetime NOT NULL default '0000-00-00 00:00:00',
	`script_name` varchar(128) collate utf8_bin NOT NULL default '',
	`comment` varchar(128) collate utf8_bin NOT NULL default ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/syntrans (
	`syntrans_sid` int(10) NOT NULL default '0',
	`defined_meaning_id` int(10) NOT NULL default '0',
	`expression_id` int(10) NOT NULL,
	`identical_meaning` tinyint(1) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_syntrans ON /*$wgWDprefix*/syntrans (`remove_transaction_id`,`syntrans_sid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_expression ON /*$wgWDprefix*/syntrans (`remove_transaction_id`,`expression_id`,`identical_meaning`,`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_defined_meaning ON /*$wgWDprefix*/syntrans (`remove_transaction_id`,`defined_meaning_id`,`identical_meaning`,`expression_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_syntrans ON /*$wgWDprefix*/syntrans (`add_transaction_id`,`syntrans_sid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_expression ON /*$wgWDprefix*/syntrans (`add_transaction_id`,`expression_id`,`identical_meaning`,`defined_meaning_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_defined_meaning ON /*$wgWDprefix*/syntrans (`add_transaction_id`,`defined_meaning_id`,`identical_meaning`,`expression_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/text (
	`text_id` int(8) unsigned NOT NULL PRIMARY KEY auto_increment,
	`text_text` mediumblob NOT NULL,
	`text_flags` tinyblob default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/text_attribute_values (
	`value_id` int(11) NOT NULL,
	`object_id` int(11) NOT NULL,
	`attribute_mid` int(11) NOT NULL,
	`text` mediumblob NOT NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_object ON /*$wgWDprefix*/text_attribute_values (`remove_transaction_id`,`object_id`,`attribute_mid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_attribute ON /*$wgWDprefix*/text_attribute_values (`remove_transaction_id`,`attribute_mid`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_value ON /*$wgWDprefix*/text_attribute_values (`remove_transaction_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_object ON /*$wgWDprefix*/text_attribute_values (`add_transaction_id`,`object_id`,`attribute_mid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_attribute ON /*$wgWDprefix*/text_attribute_values (`add_transaction_id`,`attribute_mid`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_value ON /*$wgWDprefix*/text_attribute_values (`add_transaction_id`,`value_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/transactions (
	`transaction_id` int(11) NOT NULL PRIMARY KEY auto_increment,
	`user_id` int(5) NOT NULL,
	`user_ip` varchar(15) NOT NULL,
	`timestamp` varchar(14) NOT NULL,
	`comment` tinyblob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX `user` ON /*$wgWDprefix*/transactions (`user_id`,`transaction_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/translated_content (
	`translated_content_id` int(11) NOT NULL default '0',
	`language_id` int(10) NOT NULL default '0',
	`text_id` int(10) NOT NULL default '0',
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_translated_content ON /*$wgWDprefix*/translated_content (`remove_transaction_id`,`translated_content_id`,`language_id`,`text_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_text  ON /*$wgWDprefix*/translated_content (`remove_transaction_id`,`text_id`,`translated_content_id`,`language_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_translated_content  ON /*$wgWDprefix*/translated_content (`add_transaction_id`,`translated_content_id`,`language_id`,`text_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_text  ON /*$wgWDprefix*/translated_content (`add_transaction_id`,`text_id`,`translated_content_id`,`language_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/translated_content_attribute_values (
	`value_id` int(11) NOT NULL default '0',
	`object_id` int(11) NOT NULL,
	`attribute_mid` int(11) NOT NULL,
	`value_tcid` int(11) NOT NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_object ON /*$wgWDprefix*/translated_content_attribute_values (`remove_transaction_id`,`object_id`,`attribute_mid`,`value_tcid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_attribute ON /*$wgWDprefix*/translated_content_attribute_values (`remove_transaction_id`,`attribute_mid`,`object_id`,`value_tcid`);
CREATE INDEX /*$wgWDprefix*/versioned_end_translated_content ON /*$wgWDprefix*/translated_content_attribute_values (`remove_transaction_id`,`value_tcid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_value ON /*$wgWDprefix*/translated_content_attribute_values (`remove_transaction_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_object ON /*$wgWDprefix*/translated_content_attribute_values (`add_transaction_id`,`object_id`,`attribute_mid`,`value_tcid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_attribute ON /*$wgWDprefix*/translated_content_attribute_values (`add_transaction_id`,`attribute_mid`,`object_id`,`value_tcid`);
CREATE INDEX /*$wgWDprefix*/versioned_start_translated_content ON /*$wgWDprefix*/translated_content_attribute_values (`add_transaction_id`,`value_tcid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_value ON /*$wgWDprefix*/translated_content_attribute_values (`add_transaction_id`,`value_id`);

CREATE TABLE IF NOT EXISTS /*$wgWDprefix*/url_attribute_values (
	`value_id` int(11) NOT NULL,
	`object_id` int(11) NOT NULL,
	`attribute_mid` int(11) NOT NULL,
	`url` varchar(255) collate utf8_bin NOT NULL,
	`label` varchar(255) collate utf8_bin NOT NULL,
	`add_transaction_id` int(11) NOT NULL,
	`remove_transaction_id` int(11) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE INDEX /*$wgWDprefix*/versioned_end_object ON /*$wgWDprefix*/url_attribute_values (`remove_transaction_id`,`object_id`,`attribute_mid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_attribute ON /*$wgWDprefix*/url_attribute_values (`remove_transaction_id`,`attribute_mid`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_end_value ON /*$wgWDprefix*/url_attribute_values (`remove_transaction_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_object ON /*$wgWDprefix*/url_attribute_values (`add_transaction_id`,`object_id`,`attribute_mid`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_attribute ON /*$wgWDprefix*/url_attribute_values (`add_transaction_id`,`attribute_mid`,`object_id`,`value_id`);
CREATE INDEX /*$wgWDprefix*/versioned_start_value ON /*$wgWDprefix*/url_attribute_values (`add_transaction_id`,`value_id`);
