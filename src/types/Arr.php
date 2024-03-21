<?php

namespace Hiraeth\Turso\Types;

/**
 * Handles arrays
 */
class Arr {
	/**
	 * Convert a value from the database to the entity
	 * @return array<mixed, mixed>
	 */
	static public function from(string|null $array): array|null
	{
		if (!$array) {
			return array();
		}

		return json_decode($array, TRUE) ?: array();
	}


	/**
	 * Convert a value from an entity to the database
	 * @param array<mixed, mixed> $array
	 */
	static public function to(array|null $array): string|null
	{
		if (!$array) {
			return NULL;
		}

		return json_encode($array) ?: NULL;
	}
}
