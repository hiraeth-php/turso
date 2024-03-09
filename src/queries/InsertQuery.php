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
		parent::__construct('INSERT INTO @table @names VALUES @values');

		$this->raw('table', $table);
	}

	/**
	 * The values to be inserted
	 * @param array<mixed> $values
	 */
	public function values(array $values): static
	{
		$this->raw('names', array_keys($values));
		$this->raw('values', array_map(fn($value) => $this->esc($value), $values));

		return $this;
	}
}
