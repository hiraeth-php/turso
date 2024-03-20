<?php

namespace Hiraeth\Turso\Types;

use stdClass;

/**
 * Handles objects
 */
class Obj {
	/**
	 * Convert a value from the database to the entity
	 */
	static public function from(string|null $object): object|null
	{
		if (!$object) {
			return new stdClass();
		}

		return json_decode($object) ?: new stdClass();
	}


	/**
	 * Convert a value from an entity to the database
	 */
	static public function to(object|null $object): string|null
	{
		if (!$object || !get_object_vars($object)) {
			return NULL;
		}

		return json_encode($object) ?: NULL;
	}
}
