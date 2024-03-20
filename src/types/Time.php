<?php

namespace Hiraeth\Turso\Types;

use DateTime;

/**
 * Handles times
 */
class Time {
	/**
	 * Convert a value from the database to the entity
	 */
	static public function from(string|null $time): DateTime|null
	{
		if (!$time) {
			return NULL;
		}

		return new DateTime($time);
	}


	/**
	 * Convert a value from an entity to the database
	 */
	static public function to(DateTime|null $time): string|null
	{
		if (!$time) {
			return NULL;
		}

		return $time->format('H:i:s');
	}
}
