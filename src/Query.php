<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;
use SQLite3;

/**
 * The Query class is responsible for basic SQL query construction by templating
 */
class Query
{
	/**
	 * The string by which multiple raw suby-Query elements are bound
	 * @var string
	 */
	protected $bind = ', ';

	/**
	 * The raw values for the query template (preceded by @ in template)
	 * @var array<string, string|array<string>>
	 */
	protected $raws = array();

	/**
	 * The template, uses @value and {variable} to indicate raw/escapable pieces
	 * @var string
	 */
	protected $tmpl = '';

	/**
	 * The escapable variables for the query template (appear in {} in template)
	 * @var array<string, mixed>
	 */
	protected $vars = array();

	/**
	 * Whether or not bound multiple raw sub-Query elements are wrapped in ()
	 * @var bool
	 */
	protected $wrap = TRUE;


	/**
	 * Create a new query
	 * @param array<string, mixed> $vars
	 * @param array<string, string> $raws
	 */
	public function __construct(string $sql = '', array $vars = array(), array $raws = array())
	{
		$this->tmpl = $sql;
		$this->vars = $vars;
		$this->raws = $raws;
	}


	/**
	 * Create a new query for chainable API
	 */
	public function __invoke(string $sql): self
	{
		return new self($sql);
	}


	/**
	 * Convert the query to a string by replacing raws and vars
	 */
	public function __toString(): string
	{
		$tmpl = $this->tmpl;

		foreach ($this->raws as $name => $value) {
			if (is_array($value)) {
				$value = implode($this->bind, $value);

				if ($this->wrap) {
					$value = sprintf('(%s)', $value);
				}
			}

			$tmpl = preg_replace('/\@' . preg_quote($name, '/') . '\s*/', $value . ' ', $tmpl);
		}

		$tmpl = preg_replace('/\s+\@.+\s+/', '', $tmpl);

		if (preg_match_all('/\{\s*[^}]+\s*\}/', $tmpl, $matches)) {
			$matches = array_unique($matches[0]);
			$symbols = array_map(fn($match) => trim($match, ' {}'), $matches);
			$invalid = array_diff(array_keys($this->vars), $symbols);

			if (count($invalid)) {
				throw new RuntimeException(sprintf(
					'Cannot compile query, the following variables were set but not used: %s',
					implode(', ', $invalid)
				));
			}

			foreach ($matches as $i => $token) {
				if (!isset($this->vars[$symbols[$i]])) {
					throw new RuntimeException(sprintf(
						'Cannot compile query, %s used in template, but no matching variable set',
						$token
					));
				}

				$value = $this->esc($this->vars[$symbols[$i]]);

				if (is_null($value)) {
					throw new InvalidArgumentException(sprintf(
						'Invalid type "%s" for variable named "%s", not supported.',
						strtolower(gettype($value)),
						$symbols[$i]
					));
				}

				$tmpl = str_replace($token, $value, $tmpl);
			}
		}

		return trim($tmpl);
	}


	/**
	 * Create a new unwrapped query containing only a set of conditions bound by ' AND '
	 */
	public function all(Query ...$conditions): self
	{
		return $this('@conditions')->bind(' AND ')->raw('conditions', $conditions);
	}


	/**
	 * Create a new unwrapped query containing only a set of conditions bound by ' OR '
	 */
	public function any(Query ...$conditions): self
	{
		return $this('@conditions')->bind(' OR ')->raw('conditions', $conditions);
	}


	/**
	 * Bind the query and set wrapping
	 */
	public function bind(string $separator, bool $wrap = TRUE): self
	{
		$this->bind = $separator;
		$this->wrap = $wrap;

		return $this;
	}


	/**
	 * Create a new query in the style: @name = {value}
	 */
	public function eq(string $name, mixed $value): self
	{
		return $this('@name = {value}')->raw('name', $name)->var('value', $value);
	}


	/**
	 * Escape a value for the database (the type will be inferred using gettype())
	 */
	public function esc(mixed $value): ?string
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
							return $this->esc($value);
						},
						$value
					)) . ")";

			default:
				return NULL;
		}
	}

	/**
	 * Create a new query in the style: LIMIT {amount}
	 * If the amount is falsey the query will be an empty string (no LIMIT keyword)
	 */
	public function limit(?int $amount): self
	{
		if (!$amount) {
			return $this('');
		}
		return $this('LIMIT {amount}')->var('amount', $amount);
	}


	/**
	 * Create a new query in the style: OFFSET {position}
	 * If the position is falsey the query will be an empty string (no OFFSET keyword)
	 */
	public function offset(?int $position): self
	{
		if (!$position || $position < 0) {
			return $this('');
		}

		return $this('OFFSET {position}')->var('position', $position);
	}


	/**
	 * Create a new query in the style: ORDER BY ...@sorts
	 * If the sorts is empty the query will be an empty string (no ORDER BY keyword)
	 */
	public function order(Query ...$sorts): self
	{
		if (empty($sorts)) {
			return $this('');
		}

		return $this('ORDER BY @sorts')->bind(', ', FALSE)->raw('sorts', $sorts);
	}


	/**
	 * Add a raw value to the query
	 * If the value is an array they will be bound/wrapped by default
	 * @param string|array<string> $value
	 */
	public function raw(string $name, string|array $value): self
	{
		$this->raws[$name] = $value;

		return $this;
	}


	/**
	 * Create a new query in the style: @field @direction
	 * Direction must be variation of 'asc' or 'desc' or an exception will be thrown
	 */
	public function sort(string $field, string $direction = 'ASC'): self
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


	/**
	 * Add an escapable variable to the query
	 */
	public function var(string $name, mixed $value): self
	{
		$this->vars[$name] = $value;

		return $this;
	}


	/**
	 * Create a new query in the style: WHERE @conditions
	 * If the conditions is empty the query will be an empty string (no WHERE keyword)
	 */
	public function where(Query ...$conditions): self
	{
		if (empty($conditions)) {
			return $this('');
		}

		return $this('WHERE @conditions')->bind(' AND ', FALSE)->raw('conditions', $conditions);
	}
}
