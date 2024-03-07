<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use SQLite3;

class Query
{
	protected $bind = ', ';
	protected $vars = array();
	protected $raws = array();
	protected $tmpl = '';
	protected $wrap = TRUE;

	public function __construct(string $sql = '', array $vars = array(), array $raws = array())
	{
		$this->tmpl = $sql;
		$this->vars = $vars;
		$this->raws = $raws;
	}

	public function __invoke(string $sql): Query
	{
		return new self($sql);
	}

	public function __toString(): string
	{
		$sql = $this->tmpl;

		foreach ($this->vars as $name => $value) {
			$value = $this->escape($value);

			if (is_null($value)) {
				throw new InvalidArgumentException(sprintf(
					'Invalid parameter type "%s" for named parameter "%s", not supported.',
					strtolower(gettype($value)),
					$name
				));
			}

			$sql = preg_replace('/\{\s*' . preg_quote($name, '/') . '\s*\}/', $value, $sql);
		}

		foreach ($this->raws as $name => $value) {
			if (is_array($value)) {
				$value = implode($this->bind, $value);

				if ($this->wrap) {
					$value = sprintf('(%s)', $value);
				}
			}

			$sql = preg_replace('/\@' . preg_quote($name, '/') . '\s*/', $value . ' ', $sql);
		}

		return trim($sql);
	}


	public function all(Query ...$conditions): Query
	{
		return $this('@conditions')->bind(' AND ')->raw('conditions', $conditions);
	}

	public function any(Query ...$conditions): Query
	{
		return $this('@conditions')->bind(' OR ')->raw('conditions', $conditions);
	}

	public function bind(string $separator, bool $wrap = TRUE): Query
	{
		$this->bind = $separator;
		$this->wrap = $wrap;

		return $this;
	}

	public function eq(string $name, $value): Query
	{
		return $this('@name = {value}')->raw('name', $name)->var('value', $value);
	}

	public function escape(mixed $value): ?string
	{
		switch (strtolower(gettype($value))) {
			case 'integer':
			case 'double':
				return (string) $value;

			case 'null':
				return 'null';

			case 'boolean':
				return $value ? 'true' : 'false';

			case 'string':
				return "'" . SQLite3::escapeString($value) . "'";

			case 'array':
				return
					"(" . implode(',', array_map(
						function($value) {
							return $this->escape($value);
						},
						$value
					)) . ")";

			default:
				return NULL;
		}
	}

	public function limit(?int $amount): Query
	{
		if (!$amount) {
			return $this('');
		}
		return $this('LIMIT {amount}')->var('amount', $amount);
	}

	public function offset(?int $position): Query
	{
		if (!$position || $position < 0) {
			return $this('');
		}

		return $this('OFFSET {position}')->var('position', $position);
	}

	public function order(Query ...$sorts): Query
	{
		return $this('@sorts')->bind(', ', FALSE)->raw('sorts', $sorts);
	}


	public function raw(string $name, string|array $value): Query
	{
		$this->raws[$name] = $value;

		return $this;
	}


	public function sort(string $field, string $direction = 'ASC'): Query
	{
		$direction = strtoupper($direction);

		if (!in_array($direction, ['ASC', 'DESC'])) {
			// throw
		}
		return $this('@field @direction')->raw('field', $field)->raw('direction', $direction);
	}

	public function var(string $name, $value): Query
	{
		$this->vars[$name] = $value;

		return $this;
	}

	public function where(Query ...$conditions): Query
	{
		if (!$conditions) {
			return $this('');
		}

		return $this('WHERE @conditions')->bind(' AND ', FALSE)->raw('conditions', $conditions);
	}
}
