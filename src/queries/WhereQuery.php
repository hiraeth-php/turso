<?php

namespace Hiraeth\Turso;

/**
 * A Query with features common to all queries with a WHERE clause
 */
abstract class WhereQuery extends Query
{
	/**
	 * Get a new expression query for assignments and conditions
	 */
	public function expr(): Expr
	{
		return new Expr();
	}


	/**
	 * Set the "WHERE" portion of the statement
	 */
	public function where(Query ...$conditions): static
	{
		if (empty($conditions)) {
			$clause = $this('');
		} else {
			$clause = $this('WHERE @conditions')
				->bind(' AND ', FALSE)
				->raw('conditions', $conditions)
			;
		}

		$this->raw('where', $clause);

		return $this;
	}
}
