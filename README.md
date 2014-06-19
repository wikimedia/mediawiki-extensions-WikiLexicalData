WikiLexicalData	{#mainpage}
===============
\warning This is a work in progress...

The WikiLexicalData extension currently consist of the WikiLexicalData framework
and OmegaWiki, the current main (only) application using the WikiLexical framework.

Besides these it also contains some tools and applications ( most of which are outdated ).

Subdirectories
--------------
- Database scripts/
	- /Convenience - useful scripts for developers
	- /Incremental - necessary update scripts to keep your extension installation in sync with the trunk
- Images/ - images used in the Wikidata UI
- OmegaWiki/ - the current main (only) application of the Wikidata framework
- includes/ - contains WikiLexicalData's WikiMedia extensions of Special Pages, Tags and API
- maintenance/ - currently contains our own update.php
- perl-tools/ - import/export tools written in Perl ( outdated )
- php-tools/ - import/export tools written in PHP ( outdated )
- util/ - ( outdated )

Schema
------
[Schema]: md__s_c_h_e_m_a.html
@see [Schema][Schema]

Policy
------
[POLICY]: md__p_o_l_i_c_y.html
@see [Policy][POLICY]

Testing
-------
[TESTING]: md__t_e_s_t_i_n_g.html
@see [Testing][TESTING]

TODO
----
[TODO]: md__t_o_d_o.html
@see [TODO][TODO]

WikiLexicalData
---------------
[WikiLexicalData]: md__wiki_lexical_data.html
@see [WikiLexicalData][WikiLexicalData]

OmegaWiki
---------
[OmegaWiki]: md__omega_wiki.html
@see [OmegaWiki][OmegaWiki]

Updating the database
---------------------
Go to the maintenance folder of WikiLexicalData extension.

run: php update.php

This will install the base schema, if it wasn't installed yet. Call
MediaWiki's update.php, which will update both MediaWiki and WikiLexical's updates,
then give instruction on globals one needs to add so that the WikiLexicalData's
software runs smoothly (again, if freshly installed ).
