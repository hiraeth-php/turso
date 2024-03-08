<?php

namespace Hiraeth\Turso;

/**
 * Insert queries perform insertions
 */
class InsertQuery extends Query
{
	/**
	 * Create a new instance
	 */
	public function __construct(string $table)
	{
		parent::__construct('INSERT INTO @table @cols VALUES @values');

		$this->raw('table', $table);
	}

	/**
	 *
	 */
	public function values(array $values): static
	{
		$this->raw('cols', array_keys($values));
		$this->raw('values', array_map(fn($value) => $this->esc($value), $values));

		return $this;
	}
}
