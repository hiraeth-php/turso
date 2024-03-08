<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;

class SelectQuery extends WhereQuery
{
	/**
	 *
	 */
	public function __construct(string $table)
	{
		parent::__construct('SELECT @cols FROM @table @where @order @limit @offset');

		$this->raw('table', $table);
	}


	/**
	 *
	 */
	public function cols(string ...$names): static
	{
		$this->raw('cols', $this('@names')->bind(', ', FALSE)->raw('names', $names));

		return $this;
	}


	/**
	 * Set the "LIMIT" portion of the statement
	 */
	public function limit(?int $amount): static
	{
		if (!$amount) {
			$clause = $this('');
		} else {
			$clause = $this('LIMIT {amount}')->var('amount', $amount);
		}

		$this->raw('limit', $clause);

		return $this;
	}


	/**
	 * Set the "OFFSET" portion of the statement
	 */
	public function offset(?int $position): static
	{
		if (!$position || $position < 0) {
			$clause = $this('');
		} else {
			$clause = $this('OFFSET {position}')->var('position', $position);
		}

		$this->raw('offset', $clause);

		return $this;
	}


	/**
	 * Set the "ORDER BY" portion of the statement
	 */
	public function order(Query ...$sorts): static
	{
		if (empty($sorts)) {
			$clause = $this('');
		} else {
			$clause = $this('ORDER BY @sorts')->bind(', ', FALSE)->raw('sorts', $sorts);
		}

		$this->raw('order', $clause);

		return $this;
	}


	/**
	 * Create a new query fragment in the style: @field @direction
	 * Direction must be variation of 'asc' or 'desc' or an exception will be thrown
	 */
	public function sort(string $field, string $direction = 'ASC'): Query
	{
		$direction = strtoupper($direction);

		if (!in_array($direction, ['ASC', 'DESC'])) {
			throw new InvalidArgumentException(sprintf(
				'Cannot construct sort query with invalid direction "%s", must be ASC or DESC',
				$direction
			));
		}

		return $this('@field @direction')->raw('field', $field)->raw('direction', $direction);
	}
}
