<?php

namespace Hiraeth\Turso;

/**
 * Update queries perform updates
 */
class UpdateQuery extends WhereQuery
{
	/**
	 * Create a new instance
	 */
	public function __construct(Database $db, string $table)
	{
		parent::__construct($db, 'UPDATE @table SET @assignments @where');

		$this->raw('table', $table);
	}

	/**
	 * Set the "SET" portion of the query
	 */
	public function set(Query ...$assignments): static
	{
		$assignments = $this('@assignments')
			->bind(', ', FALSE)
			->raw('assignments', $assignments)
		;

		$this->raw('assignments', $assignments);

		return $this;
	}
}
