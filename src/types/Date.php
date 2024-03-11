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
		return $date ? new DateTime($date) : NULL;
	}


	/**
	 * Convert a value from an entity to the database
	 */
	static public function to(DateTime|null $date): string|null
	{
		return $date ? $date->format('Y-m-d') : NULL;
	}
}
