<?php

namespace Hiraeth\Turso;

class UpdateQuery extends Query
{
	/**
	 *
	 */
	public function __construct(string $table)
	{
		parent::__construct('UPDATE @table SET @assign @where');

		$this->raw('table', $table);
	}

	/**
	 *
	 */
	public function set(Query ...$assignments): self
	{
		$assignments = $this('@assignments')->bind(', ', FALSE)->raw('assignments', $assignments);

		$this->raw('assign', $assignments);

		return $this;
	}


	/**
	 *
	 */
	public function where(Query ...$conditions): self
	{
		$this->raw('where', parent::where(...$conditions));

		return $this;
	}
}
