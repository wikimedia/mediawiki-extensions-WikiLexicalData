<?php

class Attribute {
	public $id = null;
	public $name = "";
	public $type = "";

	/**
	 * @param $id   (String) or null if
	 * @param $name (String)
	 * @param $type (String or Structure)
	 *  If String, can be "language", "spelling", "boolean",
	 *  "defined-meaning", "defining-expression", "relation-type", "attribute",
	 *  "collection", "short-text", "text"
	 *
	 *  If Structure, see below.
	 */
	public function __construct( $id, $name, $type ) {
		$this->id = $id;
		$this->name = $name;
		$this->setAttributeType( $type );
	}

	public function setAttributeType( $type ) {
		# Copy the structure since we might modify it
		if ( $type instanceof Structure ) {
			$this->type = clone $type;
		} else {
			$this->type = $type;
		}

		// Since the attribute is a structure and unnamed, we use
		// the default label associated with it.
		if ( is_null( $this->id ) && ( $this->type instanceof Structure ) ) {
			$this->id = $this->type->getStructureType();
		// Override structure label with a more specific one
		} elseif ( !is_null( $this->id ) && ( $this->type instanceof Structure ) ) {
			$this->type->setStructureType( $this->id );
		}
	}

	public function getId() {
		return $this->id;
	}

	public function __tostring() {
		$id = $this->id;
		$name = $this->name;
		$type = $this->type;
		return "Attribute($id, $name, $type)";
	}
}

class Structure {
	private $attributes;
	private $type;

	public function getAttributes() {
		return $this->attributes;
	}

	public function addAttribute( Attribute $attribute ) {
		$this->attributes[] = $attribute;
	}

	public function getStructureType() {
		return $this->type;
	}

	public function setStructureType( $type ) {
		$this->type = $type;
	}


	/**
	 * Construct named Structure which contains Attribute objects
	 *
	 * @param $type (String)  Identifying string that describes the structure.
	 *                        Optional; if not specified, will be considered
	 *                        'anonymous-structure' unless there is only a
	 *                        a single Attribute object, in which case the structure
	 *                        will inherit its ID. Do not pass null.
	 * @param $structure (Array or Parameter list) One or more Attribute objects.
	 *
	 */
	public function __construct( $argumentList ) {

		# We're trying to be clever.
		$args = func_get_args();
		$this->attributes = null;

		if ( $args[0] instanceof Attribute ) {
			$this->attributes = $args;
		} elseif ( is_array( $args[0] ) ) {
			$this->attributes = $args[0];
		}

		if ( is_array( $this->attributes ) ) {
			# We don't know what to call an unnamed
			# structure with multiple attributes.
			if ( sizeof( $this->attributes ) > 1 ) {
				$this->type = 'anonymous-structure';
			# Meh, just one Attribute. Let's eat it.
			} elseif ( sizeof( $this->attributes ) == 1 ) {
				$this->type = $this->attributes[0]->id;
			} else {
				$this->type = 'empty-structure';
			}

		# First parameter is the structure's name.
		} elseif ( is_string( $args[0] ) && !empty( $args[0] ) ) {
			$this->type = $args[0];
			if ( is_array( $args[1] ) ) {
				$this->attributes = $args[1];
			} else {
				array_shift( $args );
				$this->attributes = $args;
			}
		} else {
			# WTF?
			throw new Exception( "Invalid structure constructor: " . print_r( $args, true ) );
		}
	}

	public function supportsAttributeId( $attributeId ) {
//		$result = false;
//		$i = 0;
//
//		while (!$result && $i < count($this->attributes)) {
//			$result = $this->attributes[$i]->id == $attributeId;
//			$i++;
//		}
//
//		return $result;
		return true;
	}

	public function supportsAttribute( Attribute $attribute ) {
		return $this->supportsAttributeId( $attribute->id );
	}

	public function __tostring() {
		$result = "{";

		if ( count( $this->attributes ) > 0 ) {
			$result .= $this->attributes[0]->id;

			for ( $i = 1; $i < count( $this->attributes ); $i++ )
				$result .= ", " . $this->attributes[$i]->id;
		}

		$result .= "}";

		return $result;
	}
}

class Attributes {

	public function __construct() {
	}

	/**
	 * returns an array of the Text Attributes name and the text itself
	 * for a language
	 *
	 * else returns array()
	 *
	 * Note: $options can be used to introduce new variables
	 */
	public static function getTextAttributes( $objectId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cond = array();
		if ( isset( $options['ORDER BY'] ) ) {
			$cond['ORDER BY']= $options['ORDER BY'];
		} else {
			$cond['ORDER BY']= 'text';
		}

		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT']= $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET']= $options['OFFSET'];
		}

		$languageId = null;
		if ( isset( $options['languageId'] ) ) {
			$languageId = $options['languageId'];
		}

		$queryResult = $dbr->select(
			"{$dc}_text_attribute_values",
			array(
				'text',
				'attribute_mid',
			),
			array(
				'object_id' => $objectId,
				'remove_transaction_id' => null
			),
			__METHOD__,
			$cond
		);

		$textAttributes = array();
		$attributes = new Attributes;
		foreach ( $queryResult as $ta ) {
			$textAttributes[] = array(
				'text' => $ta->text,
				'attribute_name' => $attributes->getAttributeName( $ta->attribute_mid, $languageId )
			);
		}

		if ( $textAttributes ) {
			return $textAttributes;
		}
		return array();
	}

	/**
	 * returns the Attribute Name
	 *
	 * else returns null
	 *
	 */
	public static function getAttributeName( $attributeId, $languageId = null, $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$vars = array(
			'synt.defined_meaning_id' => $attributeId,
			'synt.expression_id = exp.expression_id',
			'synt.remove_transaction_id' => null,
			'exp.remove_transaction_id' => null
		);

		if ( $languageId ) {
			$vars['exp.language_id'] = $languageId;
		}

		$attributeName = $dbr->selectField(
			array(
				'exp' => "{$dc}_expression",
				'synt' => "{$dc}_syntrans",
			),
			'spelling',
			$vars,
			__METHOD__
		);

		if ( $attributeName ) {
			return $attributeName;
		}
		return null;
	}

	/**
	 * returns an array of the Options Attributes Values etc name and the text itself
	 * for a language
	 *
	 * @param $objectId can be either syntransId or definedMeaningId
	 *
	 * else returns array()
	 *
	 * Note: $options can be used to introduce new variables
	 */
	public static function getOptionAttributes( $objectId, $options = array(), $dc = null ) {
		if ( is_null( $dc ) ) {
			$dc = wdGetDataSetContext();
		}
		$dbr = wfGetDB( DB_SLAVE );

		$vars = array(
				'oav.object_id' => $objectId,
				'oav.option_id = oao.option_id',
				'ca.object_id = oao.attribute_id',
				'oav.remove_transaction_id' => null,
				'oao.remove_transaction_id' => null,
				'ca.remove_transaction_id' => null
		);

		$cond = array();
		if ( isset( $options['LIMIT'] ) ) {
			$cond['LIMIT'] = $options['LIMIT'];
		}
		if ( isset( $options['OFFSET'] ) ) {
			$cond['OFFSET'] = $options['OFFSET'];
		}

		$languageId = null;
		if ( isset( $options['languageId'] ) ) {
			$languageId = $options['languageId'];
		}

		$queryResult = $dbr->select(
			array(
				'oav' => "{$dc}_option_attribute_values",
				'oao' => "{$dc}_option_attribute_options",
				'ca' => "{$dc}_class_attributes"
			),
			array(
				'attribute_mid',
				'option_mid'
			),
			$vars,
			__METHOD__,
			$cond
		);

		$optionAttributes = array();
		$attributes = new Attributes;
		foreach ( $queryResult as $oa ) {
			$optionAttributes[] = array(
				'attribute_name' => $attributes->getAttributeName( $oa->attribute_mid, $languageId ),
				'attribute_option_name' => $attributes->getAttributeName( $oa->option_mid, $languageId )
			);
		}

		if ( $optionAttributes ) {
			return $optionAttributes;
		}
		return array();
	}

}
