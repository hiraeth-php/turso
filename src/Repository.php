<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use ReflectionClass;
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
		if (!static::entity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", entity class not defined',
				static::class
			));
		}

		if (!static::entity::ident) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", no identity fields specified',
				static::class
			));
		}

		$reflections    = $database->getReflections(static::entity);
		$this->database = $database;
		$this->mapping  = array_combine(
			array_map(fn($reflection) => $reflection->getName(), $reflections),
			array_keys($reflections)
		);
	}


	/**
	 * Create a new entity instance
	 * @param array<string, mixed> $values
	 * @return T
	 */
	public function create(array $values = array()): Entity
	{
		$class   = static::entity;
		$invalid = array_diff(
			array_keys($values),
			array_keys($this->mapping)
		);

		if ($invalid) {
			throw new InvalidArgumentException(sprintf(
				'Unsupported fields %s when creating entity of type "%s"',
				implode(', ', $invalid),
				$class
			));
		}

		$entity = $class::__init($this->database);

		foreach ($values as $field => $value) {
			$entity->$field = $value;
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
		$values = static::entity::__dump($entity);
		$query  = new DeleteQuery($this->database, static::entity::table);
		$ident  = array();

		$this->handle($entity, __FUNCTION__);

		foreach (static::entity::ident as $field) {
			if (!array_key_exists($field, $values)) {
				continue;
			}

			$column  = $this->mapping[$field];
			$ident[] = $query->expression()->eq($column, $values[$field]);
		}

		if (count($ident) != count(static::entity::ident)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot delete entity "%s", insufficient identy fields',
				static::entity
			));
		}

		$result = $this->database
			->execute($query->where(...$ident))
			->throw('Failed deleting entity')
		;

		$this->database->unmapEntity($entity);

		return $result->of(static::entity);
	}


	/**
	 * Find a single entity based on its ID or map of unique field = value criteria.
	 * @param int|string|array<string, mixed> $id
	 * @return T
	 */
	public function find(int|string|array $id): ?Entity
	{
		if (!is_array($id)) {
			if (count(static::entity::ident) > 1) {
				throw new InvalidArgumentException(sprintf(
					'Cannot find by scalar id on "%s" with "%s", identity has more than one field.',
					static::entity,
					$id
				));
			}

			$id = array_combine(static::entity::ident, [$id]);
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
		$query      = new SelectQuery($this->database, static::entity::table);

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
	 * @return Result<T>
	 */
	public function insert(Entity $entity): Result
	{
		$query  = new InsertQuery($this->database, static::entity::table);
		$values = array();

		$this->handle($entity, __FUNCTION__);

		foreach (static::entity::__diff($entity, TRUE) as $field => $value) {
			if (isset(static::entity::types[$field])) {
				$type  = static::entity::types[$field];
				$value = $type::to($value);
			}

			$values[$this->mapping[$field]] = $value;
		}

		$result = $this->database
			->execute($query->values($values))
			->throw('Failed inserting entity')
		;

		if (count(static::entity::ident) == 1 && empty($values[static::entity::ident[0]])) {
			$identity    = static::entity::ident[0];
			$reflections = $this->database->getReflections(static::entity);

			$reflections[$identity]->setValue($entity, $result->getInsertId());

			static::entity::__diff($entity, TRUE);
		}

		$this->database->mapEntity($entity);

		return $result->of(static::entity);
	}


	/**
	 * Select entities from the database
	 * @return Result<T>
	 */
	public function select(callable $builder, int &$total = NULL): Result
	{
		$query = new SelectQuery($this->database, static::entity::table);

		$builder($query->fetch('*'), $query->expression());

		$result = $this->database
			->execute($query->map($this->mapping))
			->throw('Failed selecting entities')
			->of(static::entity)
		;

		if (func_num_args() == 2) {
			$count = $this->database
				->execute($query->fetch('COUNT(*) as total')->limit(NULL)->offset(NULL))
				->throw('Failed counting entities')
			;

			if (isset($count->getRecord(0)->total)) {
				$total = $count->getRecord(0)->total;
			}
		}

		return $result;
	}


	/**
	 * Update an entity in the database
	 * @return Result<T>
	 */
	public function update(Entity $entity): Result
	{
		$sets     = array();
		$ident    = array();
		$original = static::entity::__dump($entity);
		$old_hash = static::entity::__hash($entity);
		$values   = static::entity::__diff($entity, TRUE);
		$query    = new UpdateQuery($this->database, static::entity::table);

		$this->handle($entity, __FUNCTION__);

		if (!$values) {
			return (new Result(sprintf('NULL'), $this->database))->of(static::entity);
		}

		foreach (static::entity::ident as $field) {
			$column     = $this->mapping[$field];
			$reflection = $this->database->getReflections(static::entity)[$column];

			if (!$reflection->isInitialized($entity)) {
				continue;
			}

			if (!array_key_exists($field, $original)) {
				$value = $reflection->getValue($entity);
				unset($values[$field]);
			} else {
				$value = $original[$field];
			}

			if (isset(static::entity::types[$field])) {
				$type  = static::entity::types[$field];
				$value = $type::to($value);
			}

			$ident[$field] = $query->expression()->eq($column, $value);
		}

		if (array_diff(static::entity::ident, array_keys($ident))) {
			$reflection = new ReflectionClass(static::entity);

			$reflection->getProperty('_values')->setValue($entity, $original);

			throw new InvalidArgumentException(sprintf(
				'Cannot update entity of type "%s", insufficient identify',
				static::entity
			));
		}

		foreach ($values as $field => $value) {
			$column     = $this->mapping[$field];
			$reflection = $this->database->getReflections(static::entity)[$column];

			if (!$reflection->isInitialized($entity)) {
				continue;
			}

			if (array_key_exists($field, $values)) {
				if (isset(static::entity::types[$field])) {
					$type = static::entity::types[$field];
					$value = $type::to($value);
				}

				$sets[] = $query('@column = {value}')
					->raw('column', $column, TRUE)
					->var('value', $value)
				;
			}
		}

		$result = $this->database
			->execute($query->set(...$sets)->where(...$ident))
			->throw('Failed updating entity')
		;

		$this->database->remapEntity($entity, $old_hash);

		return $result->of(static::entity);
	}

	/**
	 * A simple call to ensure we can handle an entity (used by other methods)
	 */
	protected function handle(Entity $entity, string $function): void
	{
		if ($entity::class != static::entity) {
			throw new InvalidArgumentException(sprintf(
				'Entity of type "%s" cannot be handled by "%s::%s"',
				$entity::class,
				static::class,
				$function
			));
		}
	}
}



