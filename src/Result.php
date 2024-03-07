<?php

namespace Hiraeth\Turso;

use Countable;
use InvalidArgumentException;

class Result implements Countable
{
	/**
	 * @var array
	 */
	protected $content;


	/**
	 * @var Database
	 */
	protected $database;

	/**
	 *
	 */
	protected $entity = Entity::class;


	/**
	 * @var string
	 */
	protected $sql;


	/**
	 *
	 */
	public function __construct(string $sql, Database $database, array $content)
	{
		$this->sql      = $sql;
		$this->database = $database;
		$this->content  = $content;
	}


	/**
     *
	 */
	public function __invoke(string $class = NULL)
	{
		if ($class) {
			$this->setEntity($class);
		}

		if (!$this->isError() && count($this)) {
			$columns = $this->translate($this->entity);

			foreach ($this->content['results'][0]['response']['result']['rows'] as $row_data) {
				yield $this->entity::_create($this->database, array_combine($columns, $row_data));
			}
		}
	}


	/**
	 *
	 */
	public function count(): int
	{
		if ($this->isError()) {
			return 0;
		}

		return count($this->content['results'][0]['response']['result']['rows']) ?? 0;
	}


	/**
     *
	 */
	public function getAffectedRows(): int
	{
		if ($this->isError()) {
			return NULL;
		}

		return $this->content['results'][0]['response']['result']['affected_row_count'] ?? NULL;
	}


	/**
     * Get the error from the result
	 *
     * @return object{'code': string, 'message': string}|null
     */
	public function getError(): ?object
	{
		$error = $this->content['results'][0]['error'] ?? null;

		if ($error) {
			return (object) $error;
		}

		return NULL;
	}


	/**
	 *
	 */
	public function getInsertId(): ?int
	{
		if ($this->isError()) {
			return NULL;
		}

		return $this->content['results'][0]['response']['result']['last_insert_rowid'] ?? NULL;
	}


	/**
     *
	 */
	public function getRecord(int $index, string $class = NULL): ?Entity
	{
		if ($class) {
			$this->setEntity($class);
		}

		$columns  = $this->translate($this->entity);
		$row_data = $this->content['results'][0]['response']['result']['rows'][$index] ?? NULL;

		if ($row_data) {
			return $this->entity::_create($this->database, array_combine($columns, $row_data));
		}

		return NULL;
	}


	/**
	 *
	 */
	public function getRecords(string $class = NULL): array
	{
		if ($class) {
			$this->setEntity($class);
		}

		$columns = $this->translate($this->entity);

		return array_map(
			function($row_data) use ($class, $columns) {
				return $this->entity::_create($this->database, array_combine($columns, $row_data));
			},
			$this->content['results'][0]['response']['result']['rows'] ?? []
		);
	}


	/**
	 *
	 */
	public function getRaw(): array
	{
		return $this->content;
	}


	/**
	 *
	 */
	public function getSQL(): string
	{
		return $this->sql;
	}


	/**
	 *
	 */
	public function isError(): bool
	{
		$type = $this->content['results'][0]['type'] ?? null;

		return $type && $type != 'ok';
	}


	/**
	 *
	 */
	public function setEntity(string $class): self
	{
		if (!is_a($class, Entity::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot instantiate results, "%s" is not an entity.',
				$class
			));
		}

		$this->entity = $class;

		return $this;
	}


	/**
	 *
	 */
	protected function translate(string $class): array
	{
		$fields  = $class::_inspect();
		$columns = array_map(
			fn($col) => $col['name'],
			$this->content['results'][0]['response']['result']['cols'] ?? []
		);

		foreach ($fields as $i => $field) {
			foreach ($columns as $j => $column) {
				$column = preg_replace('/[^a-z0-9]/', '', strtolower($column));
				$field  = preg_replace('/[^a-z0-9]/', '', strtolower($field));

				if ($column == $field) {
					$columns[$j] = $fields[$i];
				}
			}
		}

		return $columns;
	}
}
