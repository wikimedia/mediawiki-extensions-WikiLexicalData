@note This is a work in progress

OmegaWiki's software structure.

* Default Application
	* Main Application\n
	The default WikiLexicalData application is the DefaultWikidataApplication class.
	This class is extended by OmegaWiki (Expression namespace), DefinedMeaning
	(DefinedMeaning namespace) and Search.
		* Classes
			* DefaultWikidataApplication Wikidata.php
			* OmegaWiki : OmegaWiki.php
				* OmegaWikiAttributes : OmegaWikiAttributes.php
				* ShowEditFieldForAttributeValuesChecker : OmegaWikiEditors.php
				* DummyViewer : OmegaWikiEditors.php
				* : OmegaWikiRecordSet.php \n currently a set of functions
			* DefinedMeaning : DefinedMeaning.php
			* Search : Search.php
		* Controller
			* UpdateController Interface : Controller.php
			* UpdateAttributeController Interface : Controller.php
			* PermissionController Interface : Controller.php
		* Context Fetcher
			* ContextFetcher Interface : ContextFetcher.php\n
			Interface ContextFetcher is used to look upwards in a keyPath in
			search for a specific attribute value. This attribute value
			establishes a context for an operation that works within a
			hierarchy of Records (like an Editor does).
				* DefaultContextFetcher : ContextFetcher.php
					* DefinitionObjectIdFetcher : ContextFetcher.php
					* ObjectIdFetcher : ContextFetcher.php
		* Converter
			* Converter Interface : converter.php
		* Copiers
			* ObjectCopier : Copy.php
			* Copier : Copy.php\n
			abstract superclass for copiers
			* CopyTools : Copy.php
		* Editors
			* Editor : Editor.php
		* Front End
			* DefinedMeaningModel : DefinedMeaningModel.php
		* Functions
			* : forms.php
			* TableHeaderNode : HTMLtable.php\n
			Functions to create a hierarchical table header
			using rowspan and colspan for \<th\> elements
		* Record Classes
			* Record.php
			* RecordSet :: RecordSet.php
				* ArrayRecordSet : RecordSet.php
				* ConvertingRecordSet : RecordSet.php
			* TableColumnsToAttribute : RecordSetQueries.php
			* TableColumnsToAttributesMapping : RecordSetQueries.php
		* Transaction
			* QueryTransactionInformation : Transaction.php\n
			contains functions
		* ViewInformation
			* ViewInformation : ViewInformation.php\n
			ViewInformation is used to capture various settings
			that influence the way a page will be viewed
			depending on different use case scenarios.
		* Unsorted
			* Attribute : Attribute.php
			* Structure : Attribute.php
			* Expression : Expression.php
			* AttributeIDFilter Interface : PropertyToColumnFilter.php
			* PropertyToColumnFilter : PropertyToColumnFilter.php\n
			@note currently only used by ViewInformation
			* : type.php
			* : Utilities.php\n
			currently contains only one function
		* Unused
			* Alert.php
			* GotoSourceTemplate : GotoSourceTemplate.php
			* Skel.php
			* update_bootstrap.php
	* PHP API
		* Functions
			* ::getExpression : WikiDataAPI.php
			* ::getOwLanguageNames : languages.php
		* Classes
			* Attributes : Attribute.php
			* DefinedMeanings : DefinedMeaning.php
			* Expressions : Expression.php
			* Transactions : Transaction.php
* Special Page
	* Wiki
		* Classes
			* SpecialDatasearch
			* SpecialOWDownloads
			* SpecialOWStatistics
	* Others
		* Classes
			* SpecialAddCollection
			* SpecialConceptMapping
			* SpecialImportLangNames
	* Maintenance
		* Classes
			* SpecialNeedsTranslation
	* Unlisted
		* Classes
			* SpecialCopy
			* SpecialPopUpEditor
			* SpecialSelect
			* SpecialSuggest
	* UnCategorized
		* Classes
			* SpecialExportTSV
			* SpecialImportTSV
			* SpecialLanguages
			* SpecialTransaction
* Web API
	* Obtaining Data
		* Classes
			* Define : owDefine.php
			* Express : owExpress.php
			* SynonymTranslation : owSyntrans.php
	* Modifying Data
		* Classes
			* AddAnnotation : owAddAnnotation.php
			* AddDefinition : owAddDefinition.php
			* AddSyntrans : owAddSyntrans.php
			* AddToCollection : owAddToCollection.php
	* Obsolete
		* \warning old Broken API, replaced by the API above.
			* ApiWikiData
			* ApiWikiDataFormatBase
			* ApiWikiDataFormatXml
