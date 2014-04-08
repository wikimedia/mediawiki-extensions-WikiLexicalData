WikiLexicalData builds on top of MediaWiki's database. Most WikiLexicalData tables
have what is called a "dataset prefix" in their name, as WikiLexicalData can
handle multiple "instances" of itself inside a single database.
The default prefix for the community DB at omegawiki.org is 'uw_'.

WikiLexicalData Schema
----------------------
The WikiLexicalData schema is in the process of being documented in the SQL scripts
that are used to create it, mainly:

* Database scripts/Convenience/wikidataSourceTables.sql
* Database scripts/Convenience/wikidataCoreTables.sql

Currently, MySQL compatible.  Though recently made more compatible with SQLite 3,
there seems to still be some incompatibilities with it.

Database Sequence
-----------------
The core you want to understand is:

When you look up an expression, it goes through

1. PAGE table (user accesses it by its wiki link title)
2. *_expression table (Wikidata looks in all datasets for an expression with that spelling)
3. *_syntrans table (the expressions are associated with one or more meanings)
4. *_defined_meaning tables (the DM consists of a "defining expression", and
	a pointer to a definition)
5. *_translated_content table (points to a _set_ of definition blobs in
	different languages)

The more complex stuff are the attributes (annotations) that can be
associated with DefinedMeanings and some of their components. An essential
table here is the *_objects table, which is used by all Wikidata tables as
an "ID generator" (instead of the typical auto-increments). This allows us
to

- associate attributes directly with entities in different tables, without
  having countless association tables
- generate a globally unique identifier for each record, which can be used
to retain an object identity even if data is transported to other tables,
databases, or completely different environments.

It also makes for slightly odd ID behavior, e.g. you will often skip a lot
of IDs in a single table, because another table has meanwhile increased the
global ID counter.

All annotations beyond the basic syntrans/definition are regulated by
classes. Like in OOP, a class is a blueprint for an entity in Wikidata. So
it can, for example, define that an entity of the class "virus" can have a
relationship of the type "causes" with other DMs, or an entity of the class
"country" can have a string property "telephone code".

MediaWiki
---------
MediaWiki tables we use:

### PAGE ###
- entry point for looking up an expression or a defined meaning.
The "page title" is essentially a wiki link title that we can use
to benefit from some of MediaWiki's features for
	- link existence colorization (red/blue)
	- "What links here" tracking
	- Recent changes log
	- All pages index
	- etc.

However, it is redundant to the information stored in Wikidata
itself (e.g. Expression:foo has a corresponding spelling 'foo'
in the *_expression table).

### NAMESPACE, NAMESPACE_NAMES ###
- we hook our extension into the Expression: and DefinedMeaning: namespaces,
which  are  defined here. Note that the namespace management functionality
and tables are  not part of the  standard MediaWiki branch.

### JOB ###

### OBJECT CACHE ###
