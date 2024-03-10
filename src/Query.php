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
	 * A list of raws which have been identified as name values for possible translation
	 * @var array<string>
	 */
	protected $names = array();

	/**
	 * The raw values for the query template (preceded by @ in template)
	 * @var array<string, string|Query|array<string|Query>>
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
		$raws = array();

		if (count($this->raws) && preg_match_all('/\@[^\s\@]+(\s*)/', $tmpl, $raws)) {
			$swaps = array();

			foreach ($raws[0] as $i => $raw) {
				$ref = trim($raw, '@ ');

				if (!isset($this->raws[$ref])) {
					$swaps[$raw] = '';
				} else {
					$value = $this->raws[$ref];

					if (is_array($value)) {
						$value = implode($this->bind, $value);

						if ($this->wrap) {
							$value = sprintf('(%s)', $value);
						}
					}

					$swaps[$raw] = $value . $raws[1][$i];
				}
			}

			$tmpl = str_replace(array_keys($swaps), $swaps, $tmpl);
		}

		if (count($this->vars) && preg_match_all('/\{\s*[^}]+\s*\}/', $tmpl, $matches)) {
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
				if (!array_key_exists($symbols[$i], $this->vars)) {
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
	 * Bind the query and set wrapping
	 */
	public function bind(string $separator, bool $wrap = TRUE): static
	{
		$this->bind = $separator;
		$this->wrap = $wrap;

		return $this;
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
	 * Map any values signified as names to new names (usually just columns)
	 * @param array<string, string> $mapping
	 */
	public function map(array $mapping): self
	{
		$map = function($name) use ($mapping) {
			if (!isset($mapping[$name])) {
				// throw
			}

			return $mapping[$name];
		};

		foreach ($this->raws as $ref => $raw) {
			if (is_array($raw)) {
				foreach ($raw as $sub_raw) {
					if ($sub_raw instanceof self) {
						$sub_raw->map($mapping);
					}
				}
			}

			if ($raw instanceof self) {
				$raw->map($mapping);
			}
		}

		foreach ($this->names as $i => $ref) {
			if (is_array($this->raws[$ref])) {
				$this->raws[$ref] = array_map($map, $this->raws[$ref]);
			} else {
				$this->raws[$ref] = $map($this->raws[$ref]);
			}

			unset($this->names[$i]);
		}

		return $this;
	}


	/**
	 * Add a raw value to the query
	 * If the value is an array they will be bound/wrapped by default
	 * @param self|string|array<self|string> $value
	 */
	public function raw(string $ref, self|string|array $value, bool $name = FALSE): static
	{
		$this->raws[$ref] = $value;

		if ($name) {
			$this->names[] = $ref;
		}

		return $this;
	}


	/**
	 * Add an escapable variable to the query
	 */
	public function var(string $ref, mixed $value): static
	{
		$this->vars[$ref] = $value;

		return $this;
	}
}
