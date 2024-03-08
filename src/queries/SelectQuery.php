<?php

namespace Hiraeth\Turso;

class SelectQuery extends Query
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
	public function cols(string ...$names): self
	{
		$this->raw('cols', $this('@names')->bind(', ', FALSE)->raw('names', $names));

		return $this;
	}


	/**
	 *
	 */
	public function limit(?int $limit): self
	{
		$this->raw('limit', parent::limit($limit));

		return $this;
	}


	/**
	 *
	 */
	public function offset(?int $offset): self
	{
		$this->raw('offset', parent::offset($offset));

		return $this;
	}


	/**
	 *
	 */
	public function order(Query ...$sorts): self
	{
		$this->raw('order', parent::order(...$sorts));

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
