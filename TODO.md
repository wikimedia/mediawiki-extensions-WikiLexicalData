TODO
====
[TOC]

Features
--------
* save defined meaning
* copy defined meaning
	* complete
	* robust
	* debug
* Conceptmapper will use specialsuggest

General Tidying (refactoring)
-----------------------------
* Start making use of recordhelpers
* Refurbish the attribute system
* Change WikiLexicalData globals prefixed with $wd to $wgWld.
* WikiDataAPI and RecordSetQueries still have query() functions.
  Thought the functions used to generate the SQL is good, it is
  generally not ideal to use the generalized query() function.
* There are mysql_query functions used in Copy.php are not currently
  in use. If you decide to use them, kindly refactor the fuctions to
  use MediaWiki's database function.

Deferred Items (take into account)
----------------------------------
* ArrayRecord has a getItem method that SHOULD use attributes
  but currently cheats and allows one to access any stored item
  by key. This should be revisited when the attribute system
  is refurbished.

Unit Testing
------------
* Extend unit testing framework to also safely test on its own database.
  Since WikiLexicalData is somewhat database based, this should allow testing
  of a majority of the code.

Decide upon unused files Unused
-------------------------------
The following files are unused.
	* Alert.php
	* SpecialTransaction.php
	* copytest.php (broken)
	* GotoSourceTemplate.php
	* Skel.php
	* update_bootstrap.php
	* ApiWikiData (broken)
	* ApiWikiDataFormatBase (broken)
	* ApiWikiDataFormatXml (broken)
	* Search.php ( maybe replaced by the SpecialSearch.php?)

If no one is using them, these are candidates for deletion. In case someone have
plans to use Alert.php, SpecialTransaction.php and copytest, Kindly refactor the
query functions to select functions.
