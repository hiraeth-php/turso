<?php

namespace Hiraeth\Turso;

use Iterator;
use Countable;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template T of Entity
 * @implements Iterator<int, T>
 */
class Result implements Countable, Iterator
{
	/**
	 * Cache of entity objects, will be erased if entity is re-cast using as()
	 * @var array<T>
	 */
	protected $cache = array();

	/**
	 * The raw response returned from Turso (deserialized as associative array)
	 * @var array<mixed>
	 */
	protected $content;

	/**
	 * The internal cursor (when used as an iterator)
	 * @var int
	 */
	protected $cursor;

	/**
     * The database which generated this result
	 * @var Database
	 */
	protected $database;

	/**
	 * The entity class to which results should be mapped
	 * @var class-string<T>|class-string
	 */
	protected $entity;

	/**
	 * The cached mapping of columns to entity fields
	 * @var array<string, string>
	 */
	protected $mapping = array();

	/**
	 * The sql which generated this result
	 * @var string
	 */
	protected $sql;


	/**
	 * Create a new Result instance
	 * @param array<mixed> $content
	 * @param bool|class-string<T> $class
	 */
	public function __construct(string $sql, Database $database, array $content, bool|string $class = Entity::class)
	{
		$this->sql      = $sql;
		$this->database = $database;
		$this->content  = $content;

		if ($class == Entity::class) {
			$this->entity = Entity::class;
		}
	}


	/**
	 * {@inheritDoc}
	 */
	public function count(): int
	{
		if ($this->isError()) {
			return 0;
		}

		return count(
			$this->content['results'][0]['response']['result']['rows']
			?? []
		);
	}


	/**
	 * {@inheritDoc}
	 * @return T|null
	 */
	public function current(): ?Entity
	{
		return $this->getRecord($this->cursor);
	}


	/**
     * Get the number of affected rows (for insert, delete, update)
	 */
	public function getAffectedRows(): int
	{
		if ($this->isError()) {
			return 0;
		}

		return $this->content['results'][0]['response']['result']['affected_row_count']
			?? 0;
	}


	/**
	 * Get the column names returned with the result
	 * @return array<string>
	 */
	public function getColumns(): array
	{
		return array_map(
			fn($column) => $column['name'],
			$this->content['results'][0]['response']['result']['cols']
			?? []
		);
	}


	/**
     * Get the error from the result
	 *
     * @return \stdClass|null
     */
	public function getError(): ?object
	{
		// @var array{'code': string, 'message': string}
		$error = $this->content['results'][0]['error']
			?? NULL;

		if ($error) {
			return (object) $error;
		}

		return NULL;
	}


	/**
	 * Get the insert ID generated for auto increment column (for insert)
	 */
	public function getInsertId(): ?int
	{
		if ($this->isError()) {
			return NULL;
		}

		return $this->content['results'][0]['response']['result']['last_insert_rowid']
			?? NULL;
	}


	/**
	 * Get a record at a given position/index
	 * @return T|null
	 */
	public function getRecord(int $index): ?Entity
	{
		$data = $this->content['results'][0]['response']['result']['rows'][$index]
			?? NULL;

		if ($data) {
			if (!isset($this->cache[$index])) {
				$this->cache[$index] = $this->database->getEntity(
					$this->entity,
					$this->getColumns(),
					$data
				);
			}

			return $this->cache[$index];
		}

		return NULL;
	}


	/**
	 * Get all the records as an array
	 * @return array<T>
	 */
	public function getRecords(): array
	{
		if (count($this->cache) == count($this)) {
			return $this->cache;
		}

		return array_map(
			fn($index) => $this->getRecord($index),
			range(0, count($this) - 1)
		);
	}


	/**
	 * Get the raw result back from Turso
	 * @return array<mixed>
	 */
	public function getRaw(): array
	{
		return $this->content;
	}


	/**
	 * Get the SQL which generated this result
	 */
	public function getSQL(): string
	{
		return $this->sql;
	}


	/**
	 * Determine whether or not an error occurred
	 */
	public function isError(): bool
	{
		$type = $this->content['results'][0]['type']
			?? 'ok';

		return $type != 'ok';
	}


	/**
	 *
	 */
	public function key(): int
	{
		return $this->cursor;
	}


	/**
	 *
	 */
	public function next(): void
	{
		$this->cursor++;
	}


	/**
	 * Cast the result as a typed DTO
	 * @template C of Entity
	 * @param class-string<C> $class
	 * @return Result<C>
	 */
	public function of(string $class): self
	{
		if ($this->entity == $class) {
			return $this;
		}

		if (!is_subclass_of($class, Entity::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot instantiate results, "%s" is not an entity.',
				$class
			));
		}

		$reflections = $this->database->getReflections($class);

		$this->cache   = array();
		$this->entity  = $class;
		$this->mapping = array_combine(
			array_keys($reflections),
			array_map(fn($reflection) => $reflection->getName(), $reflections)
		);

		return $this;
	}


	/**
	 *
	 */
	public function rewind(): void
	{
		$this->cursor = 0;
	}


	/**
	 * Dies with a RuntimeException if the result is an error with an optional message
	 * @return self<T>
	 */
	public function throw(string $message = NULL): self
	{
		if ($this->isError()) {
			$error = sprintf(
				'%s: %s in "%s"',
				$this->getError()->code,
				$this->getError()->message,
				$this->getSQL()
			);

			if ($message) {
				$error = $message . '(' . $error . ')';
			}

			throw new RuntimeException($error);
		}

		return $this;
	}


	/**
	 *
	 */
	public function valid(): bool
	{
		return $this->cursor < count($this);
	}
}
