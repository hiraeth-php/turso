<?php

namespace Hiraeth\Turso\Types;

use DateTime;

/**
 * Handles timestamps
 */
class Timestamp {
	/**
	 * Convert a value from the database to the entity
	 */
	static public function from(string|null $timestamp): DateTime|null
	{
		if (!$timestamp) {
			return NULL;
		}

		return new DateTime($timestamp);
	}


	/**
	 * Convert a value from an entity to the database
	 */
	static public function to(DateTime|null $timestamp): string|null
	{
		if (!$timestamp) {
			return NULL;
		}

		return $timestamp->format('c');
	}
}
