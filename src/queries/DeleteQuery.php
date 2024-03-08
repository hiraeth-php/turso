<?php

namespace Hiraeth\Turso;

class DeleteQuery extends Query
{
	/**
	 *
	 */
	public function __construct(string $table)
	{
		parent::__construct('DELETE FROM @table @where');

		$this->raw('table', $table);
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
