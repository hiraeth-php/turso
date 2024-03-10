<?php

namespace Hiraeth\Turso\Types;

use DateTime;

class Date {
	/**
	 *
	 */
	static public function from(string|null $date): DateTime|null
	{
		return $date ? new DateTime($date) : NULL;
	}


	/**
	 *
	 */
	static public function to(DateTime|null $date): string|null
	{
		return $date ? $date->format('Y-m-d') : NULL;
	}
}
