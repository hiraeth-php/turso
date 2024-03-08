<?php

namespace Hiraeth\Turso;

class Expr extends Query
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
	 * Create a new query fragment in the style: @name = {value}
	 */
	public function eq(string $name, mixed $value): Query
	{
		return $this('@name = {value}')->raw('name', $name)->var('value', $value);
	}
}
