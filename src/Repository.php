<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;

/**
 * Repositories are responsible for easily interfacing with common SQL operations using entities
 */
abstract class Repository
{
	/**
	 * The entity class for which this repository operates
	 * @var class-string
	 */
	static protected $entity;

	/**
	 * An array containing the entity's fields which constitute its identity/primary key
	 * @var array<string>
	 */
	static protected $identity = array();

	/**
	 * An array containing entity field => direction ('asc' or 'desc'), for default ordering
	 * @var array<string, string>
	 */
	static protected $order = array();

	/**
	 * The name of the SQL table to which this repository maps
	 * @var string
	 */
	static protected $table;

	/**
	 * The database on which this repository operates
	 * @var Database
	 */
	protected $database;

	/**
	 * A cached map (when repository is constructed) of entity fields to column names
	 * @var array<string, string>
	 */
	protected $mapping = array();


	/**
	 * Create a new repository instance
	 */
	final public function __construct(Database $database)
	{
		$this->database = $database;

		if (!static::$entity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", entity class not defined',
				static::class
			));
		}

		if (!static::$identity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", you must provide at least one identify field',
				static::class
			));
		}

		if (!static::$table) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", table not defined',
				static::class
			));
		}

		$result = $this->database->execute("SELECT * FROM @table LIMIT 1", [], [ 'table' => static::$table ]);

		if ($result->isError()) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository, %s: %s',
				$result->getError()->code,
				$result->getError()->message
			));
		}


		$fields  = static::$entity::_inspect();
		$columns = array_map(
			fn($column) => $column['name'],
			$result->getRaw()['results'][0]['response']['result']['cols']
		);

		foreach ($fields as $i => $field) {
			foreach ($columns as $j => $column) {
				$field  = preg_replace('/[^a-z0-9]/', '', strtolower($field));
				$column = preg_replace('/[^a-z0-9]/', '', strtolower($column));

				if ($field == $column) {
					$this->mapping[$fields[$i]] = $columns[$j];
				}
			}
		}

		if ($missing = array_diff(static::$identity, array_keys($this->mapping))) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", no columns could match identify fields: %s',
				static::class,
				implode(', ', $missing)
			));
		}

		if (!static::$order) {
			static::$order = array_combine(
				static::$identity,
				array_pad([], count(static::$identity), 'asc')
			);
		}
	}


	/**
	 * Find a single entity based on its ID or map of unique field = value criteria.
	 * @param int|string|array<string, mixed> $id
	 */
	public function find(int|string|array $id): Entity
	{
		if (!is_array($id)) {
			if (count(static::$identity) > 1) {
				throw new InvalidArgumentException(sprintf(
					'Cannot find by scalar id on "%s" with "%s", identity has more than one field.',
					static::$entity,
					$id
				));
			}

			$id = array_combine(static::$identity, [$id]);
		}

		$result = $this->findBy($id, static::$order, 2);

		if (count($result) > 1) {
			throw new InvalidArgumentException(sprintf(
				'Cannot find by passed id, argument yielded more than 1 result.'
			));
		}

		return $result->of(static::$entity)->getRecord(0);
	}


	/**
	 * Find all entities with optional ordering
	 * @param array<string, string> $order
	 */
	public function findAll(array $order = array()): Result
	{
		return $this->findBy([], $order);
	}


	/**
	 * Find entities based on a set of simple field = value critier, with optional ordering, limit, and page
	 * @param array<string, mixed> $criteria
	 * @param array<string, string> $order
	 */
	public function findBy(array $criteria, array $order = array(), int $limit = NULL, int $page = NULL): Result
	{
		$sorts      = array();
		$conditions = array();
		$query     = new SelectQuery(static::$table);

		foreach ($criteria as $field => $value) {
			if (!isset($this->mapping[$field])) {
				// throw
			}

			$conditions[] = $query->eq($field, $value);
		}

		if (!$order) {
			$order = static::$order;
		}

		foreach ($order as $field => $value) {
			if (!isset($this->mapping[$field])) {
				// throw
			}

			$sorts[] = $query->sort($field, $value);
		}

		$result = $this->database->execute(
			$query
				->cols('*')
				->where(...$conditions)
				->order(...$sorts)
				->limit($limit)
				->offset(($page - 1) * $limit)
		);

		if ($result->isError()) {
			// throw
		}

		return $result->of(static::$entity);
	}
}



