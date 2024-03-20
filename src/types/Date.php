<?php

namespace Hiraeth\Turso\Types;

use DateTime;

/**
 * Handles dates
 */
class Date {
	/**
	 * Convert a value from the database to the entity
	 */
	static public function from(string|null $date): DateTime|null
	{
		if (!$date) {
			return NULL;
		}

		return new DateTime($date);
	}


	/**
	 * Convert a value from an entity to the database
	 */
	static public function to(DateTime|null $date): string|null
	{
		if (!$date) {
			return NULL;
		}

		return $date->format('Y-m-d');
	}
}
