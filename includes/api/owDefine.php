<?php

/*
 * Created on March 14, 2013
 *
 * API for WikiData
 *
 * Copyright (C) 2013
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
require_once( 'extensions/WikiLexicalData/OmegaWiki/WikiDataAPI.php' );

class define extends ApiBase {

	public $languageId, $text, $spelling, $spellingLanguageId;

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action, null);
	}

	public function execute() {
		global $wgUser, $wgOut;

		// Get the parameters
		$params = $this->extractRequestParams();

		if ($params['lang']) {
			$languageId = $params['lang'];
			$text = getDefinedMeaningDefinitionForLanguage( $params['dm'], $languageId );
			$spelling = getDefinedMeaningSpellingForLanguage( $params['dm'], $languageId );
			$spellingLanguageId = $languageId ;
			if (!$text) {
				$languageId = 85;
				$text = getDefinedMeaningDefinitionForLanguage( $params['dm'], $languageId );
			}
		} else {
			$languageId = getDefinedMeaningDefinitionLanguageForAnyLanguage( $params['dm'] );
			$text = getDefinedMeaningDefinitionForAnyLanguage( $params['dm'] );
			$spelling = getDefinedMeaningSpellingForAnyLanguage( $params['dm'] );
			// spellingLanguageId is wrong
			$spellingLanguageId = getDefinedMeaningSpellingLanguageId( $params['dm'] );
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(), array ( 'dmid' => $params['dm'] ) );
		$this->getResult()->addValue( null, $this->getModuleName(), array ( 'spelling' => $spelling ) );
		$this->getResult()->addValue( null, $this->getModuleName(), array ( 'spelllang' => $spellingLanguageId ) );

		// definition
		$this->getResult()->addValue( null, $this->getModuleName()
			, array ( 'definition' => array (
				'lang' => $languageId ,
				'text' => $text
			) ) );
		return true;
	}

	// Version
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	// Description
	public function getDescription() {
		return 'Get the definition of a defined meaning.' ;
	}

	// Parameters.
	public function getAllowedParams() {
		return array(
			'dm' => array (
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'lang' => array (
				ApiBase::PARAM_TYPE => 'integer',
			),
		);
	}

	// Describe the parameter
	public function getParamDescription() {
		return array(
			'dm' => 'The defined meaning id to be defined' ,
			'lang' => 'The language id to be defined'
		);
	}

	// Get examples
	public function getExamples() {
		return array(
			'Get a definition from a defined meaning id',
			'api.php?action=ow_define&dm=8218&format=xml',
			'Get a definition from a defined meaning id and a language id.',
			'When a definition is not available for a language id, ',
			'the definition will default to English',
			'api.php?action=ow_define&dm=8218&lang=87&format=xml' ,
			'api.php?action=ow_define&dm=8218&lang=107&format=xml'
		);
	}
}
