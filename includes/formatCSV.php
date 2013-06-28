<?php
/**
 * Some CSV functions for WLD
 */

/**
 * Simple formatting
 */

class WldFormatCSV {
	function formatCSVcolumn( $column ) {

		if( is_numeric( $column ) ) {
			return $column;
		}
		if( preg_match( '/\"/', $column ) ) {
			$column = str_replace( '"', '""', $column );
		}
		return '"' . $column . '"';
	}
}
