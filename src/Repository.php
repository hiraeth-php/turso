<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;

/**
 * Repositories are responsible for easily interfacing with common SQL operations using entities
 * @template T of Entity
 */
abstract class Repository
{
	/**
	 * The entity class for which this repository operates
	 * @var class-string<T>|null
	 */
	const entity = NULL;

	/**
	 * An array containing the entity's fields which constitute its identity/primary key
	 * @var array<string>
	 */
	const identity = array();

	/**
	 * An array containing entity field => direction ('asc' or 'desc'), for default ordering
	 * @var array<string, string>
	 */
	const order = array();

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

		if (!static::entity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", entity class not defined',
				static::class
			));
		}

		if (!static::identity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", you must provide at least one identify field',
				static::class
			));
		}

		if (!static::entity::table) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", table not defined',
				static::class
			));
		}

		$result = $this->database
			->execute("SELECT * FROM @table LIMIT 1", [], ['table' => static::entity::table])
		;

		if ($result->isError()) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository, %s: %s',
				$result->getError()->code,
				$result->getError()->message
			));
		}

		$fields  = static::entity::_inspect();
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

		if ($missing = array_diff(static::identity, array_keys($this->mapping))) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", no columns could match identify fields: %s',
				static::class,
				implode(', ', $missing)
			));
		}
	}


	/**
	 * Create a new entity instance
	 * @param array<string, mixed> $values
	 * @return T
	 */
	public function create(array $values = array()): Entity
	{
		$class   = static::entity;
		$entity  = new $class();
		$fields  = $entity::_inspect();
		$invalid = array_diff(array_keys($values), $fields);

		if ($invalid) {
			throw new InvalidArgumentException(sprintf(
				'Unsupported fields %s when creating entity of type "%s"',
				implode(', ', $invalid),
				static::entity
			));
		}

		foreach ($fields as $field) {
			if (!isset($values[$field])) {
				continue;
			}

			$entity->$field = $values[$field];
		}

		return $entity;
	}


	/**
	 * Delete an entity from the database
	 * @param T $entity
	 * @return Result<T>
	 */
	public function delete(Entity $entity): Result
	{
		$query = new DeleteQuery(static::entity::table);
		$ident = array();

		foreach (static::identity as $field) {
			$column  = $this->mapping[$field];
			$ident[] = $query->expression()->eq($column, $entity->$field);
		}

		return $this->database
			->execute($query->where(...$ident), [], [], FALSE)
			->throw('Failed deleting entity')
			->of(static::entity)
		;
	}


	/**
	 * Find a single entity based on its ID or map of unique field = value criteria.
	 * @param int|string|array<string, mixed> $id
	 * @return T
	 */
	public function find(int|string|array $id): ?Entity
	{
		if (!is_array($id)) {
			if (count(static::identity) > 1) {
				throw new InvalidArgumentException(sprintf(
					'Cannot find by scalar id on "%s" with "%s", identity has more than one field.',
					static::entity,
					$id
				));
			}

			$id = array_combine(static::identity, [$id]);
		}

		$result = $this->findBy($id, array(), 2);

		if (count($result) > 1) {
			throw new InvalidArgumentException(sprintf(
				'Cannot find by passed id, argument yielded more than 1 result.'
			));
		}

		return $result->of(static::entity)->getRecord(0);
	}


	/**
	 * Find all entities with optional ordering
	 * @param array<string, string> $order
	 * @return Result<T>
	 */
	public function findAll(array $order = array()): Result
	{
		return $this->findBy([], $order);
	}


	/**
	 * Find entities based on simple field = value criteria + optional ordering, limit, and page
	 * @param array<string, mixed> $criteria
	 * @param array<string, string> $order
	 * @return Result<T>
	 */
	public function findBy(array $criteria, array $order = array(), int $limit = NULL, int $page = NULL): Result
	{
		$sorts      = array();
		$conditions = array();
		$query      = new SelectQuery(static::entity::table);

		if (!$order) {
			$order = static::order;
		}

		foreach ($criteria as $field => $value) {
			$conditions[] = $query->expression()->eq($field, $value);
		}

		foreach ($order as $field => $value) {
			$sorts[] = $query->sort($field, $value);
		}

		return $this->select(
			function(SelectQuery $query) use ($conditions, $sorts, $limit, $page) {
				$query
					->fetch('*')
					->where(...$conditions)
					->order(...$sorts)
					->limit($limit)
					->offset(($page - 1) * $limit)
				;
			}
		);
	}


	/**
	 * Insert an entity into the database
	 * @param Entity<T> $entity
	 * @return Result<T>
	 */
	public function insert(Entity $entity): Result
	{
		$query  = new InsertQuery(static::entity::table);
		$values = array();

		foreach ($entity->_diff(TRUE) as $field => $value) {
			$values[$this->mapping[$field]] = $value;
		}

		$result = $this->database
			->execute($query->values($values), [], [], FALSE)
			->throw('Failed inserting entity')
		;

		if (count(static::identity) == 1 && empty($values[static::identity[0]])) {
			$field          = static::identity[0];
			$entity->$field = $result->getInsertId();

			$entity->_diff(TRUE);
		}

		return $result->of(static::entity);
	}


	/**
	 * Select entities from the database
	 * @return Result<T>
	 */
	public function select(callable $builder): Result
	{
		$query = new SelectQuery(static::entity::table);

		$builder($query->fetch('*'), $query->expression());

		return $this->database
			->execute($query->map(['*' => '*'] + $this->mapping), [], [], FALSE)
			->throw('Failed selecting entities')
			->of(static::entity)
		;
	}


	/**
	 * Update an entity in the database
	 * @param Entity<T> $entity
	 * @return Result<T>
	 */
	public function update(Entity $entity): Result
	{
		$query  = new UpdateQuery(static::entity::table);
		$values = $entity->_diff(TRUE);
		$ident  = array();
		$sets   = array();

		if (!$values) {
			return new Result('NULL', $this->database, [], static::entity);
		}

		foreach (static::identity as $field) {
			$column  = $this->mapping[$field];
			$ident[] = $query->expression()->eq($column, $entity->$field);
		}

		foreach ($values as $field => $value) {
			$column = $this->mapping[$field];
			$sets[] = $query->expression()->eq($column, $value);
		}

		return $this->database
			->execute($query->set(...$sets)->where(...$ident), [], [], FALSE)
			->throw('Failed updating entity')
			->of(static::entity)
		;
	}
}



