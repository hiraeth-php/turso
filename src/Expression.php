<?php

namespace Hiraeth\Turso;

class Expression extends Query
{
	/**
	 * Create a new unwrapped query fragment containing only a set of conditions bound by ' AND '
	 */
	public function all(Query ...$conditions): Query
	{
		return $this('@conditions')->bind(' AND ')->raw('conditions', $conditions);
	}


	/**
	 * Create a new unwrapped query fragment containing only a set of conditions bound by ' OR '
	 */
	public function any(Query ...$conditions): Query
	{
		return $this('@conditions')->bind(' OR ')->raw('conditions', $conditions);
	}


	/**
	 * Create a new query fragment in the style: @name @operator {value}
	 */
	public function cmp(string $name, mixed $value, string $operator): Query
	{
		return $this('@name @operator {value}')
			->raw('name',     $name, TRUE)
			->raw('operator', $operator)
			->var('value',    $value)
		;
	}


	/**
	 *
	 */
	public function eq(string $name, mixed $value): Query
	{
		if (is_null($value)) {
			return $this->cmp($name, '', 'IS NULL');
		} else {
			return $this->cmp($name, $value, '=');
		}
	}


	/**
	 *
	 */
	public function gt(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '>');
	}


	/**
	 *
	 */
	public function gte(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '>=');
	}


	/**
	 *
	 */
	public function like(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, 'LIKE');
	}


	/**
	 *
	 */
	public function lt(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '<');
	}


	/**
	 *
	 */
	public function lte(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '<=');
	}


	/**
	 *
	 */
	public function in(string $name, mixed $values): Query
	{
		return $this->cmp($name, (array) $values, 'IN');
	}


	/**
	 *
	 */
	public function neq(string $name, mixed $value): Query
	{
		if (is_null($value)) {
			return $this->cmp($name, '', 'IS NOT NULL');
		} else {
			return $this('(@name <> {value} OR @name IS NULL)')
				->raw('name',  $name, TRUE)
				->var('value', $value)
			;
		}
	}


	/**
	 *
	 */
	public function ngt(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '!>');
	}


	/**
	 *
	 */
	public function nlike(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, 'NOT LIKE');
	}


	/**
	 *
	 */
	public function nlt(string $name, mixed $value): Query
	{
		return $this->cmp($name, $value, '!<');
	}


	/**
	 *
	 */
	public function nin(string $name, mixed $values): Query
	{
		return $this->cmp($name, (array) $values, 'NOT IN');
	}

}
