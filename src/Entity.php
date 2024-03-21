<?php
declare(strict_types=1);

namespace Hiraeth\Turso;

use RuntimeException;


/**
 * The base Entity class to handle untyped DTOs or to extend
 * @phpstan-consistent-constructor
 */
class Entity
{
	/**
	 * The identifying fields for this entity
	 * @var array<string>
	 */
	const ident = [];

	/**
	 * The name of the SQL table to which this entity maps
	 * @var string|null
	 */
	const table = NULL;

	/**
	 * A mapping of field to data type converters
	 * @var array<string, class-string>
	 */
	const types = [];

	/**
	 * The value store for untyped DTOs
	 * @var array<string, mixed>
	 */
	protected $_values = array();

	/**
	 * A cache for associations
	 * @var array<string, mixed>
	 */
	private $_cache = array();

	/**
	 * The Database which populated this entity (for hasMany/hasOne queries)
	 * @var Database
	 */
	private $_database;


	/**
	 * Return the difference between original values and current property values
	 * @param static $entity
	 * @return array<string, mixed>
	 */
	static final public function __diff(Entity $entity, bool $reset = FALSE): array
	{
		if (static::class == Entity::class) {
			return $entity->_values;
		}

		$new_values = array();
		$old_values = static::__dump($entity);

		foreach ($entity->_database->getReflections(static::class) as $reflection) {
			if (!$reflection->isInitialized($entity)) {
				continue;
			}

			$field     = $reflection->getName();
			$new_value = isset(static::types[$field])
				? static::types[$field]::to($entity->$field)
				: $entity->$field;

			if (!array_key_exists($field, $old_values) || $new_value != $old_values[$field]) {
				$new_values[$field] = $new_value;

				if ($reset) {
					$entity->_values[$field] = $entity->$field;
				}
			}
		}

		return $new_values;
	}


	/**
	 * Dump an entities values for database transactions
	 * @param static $entity
	 * @param array<string> $fields
	 * @return array<string, mixed>
	 */
	static final public function __dump(Entity $entity, array $fields = array()): array
	{
		if (static::class == self::class) {
			return count($fields)
				? array_intersect_key($entity->_values, array_flip($fields))
				: $entity->_values;
		}

		$values = array();

		foreach ($entity->_database->getReflections(static::class) as $reflection) {
			$field = $reflection->getName();

			if (count($fields) && !in_array($field, $fields)) {
				continue;
			}

			if (!array_key_exists($field, $entity->_values)) {
				continue;
			}

			$values[$field] = isset(static::types[$field])
				? static::types[$field]::to($entity->_values[$field])
				: $entity->_values[$field];
		}

		return $values;
	}


	/**
	 * Has the entity according to its current $_values
	 * @param static $entity
	 */
	final static public function __hash(Entity $entity): string|null
	{
		$values = static::__dump($entity, static::ident);

		if (count($values) == count($entity::ident)) {
			return sha1(serialize($values));
		}

		return NULL;
	}


	/**
	 * Initialize a new entity with data
	 * @param array<string, array<string, string>> $values
	 */
	final static public function __init(Database $database, array $values = array(), bool $existing = FALSE): static
	{
		$entity = new static();

		$entity->_database = $database;

		if (static::class == self::class) {
			$fields = array_keys($values);
		} else {
			$fields = array_map(
				fn($reflection) => $reflection->getName(),
				$database->getReflections(static::class))
			;
		}

		foreach ($fields as $field) {
			if (!array_key_exists($field, $values)) {
				continue;
			}

			$data = $values[$field];

			switch (strtolower($data['type'])) {
				case 'null':
					$value = NULL; break;

				case 'integer':
					$value = intval($data['value']); break;

				case 'double':
				case 'real':
					$value = floatval($data['value']); break;

				case 'boolean':
					$value = boolval($data['value']); break;

				case 'string':
				case 'text':
				case 'blob':
					$value = $data['value']; break;

				default:
					throw new RuntimeException(sprintf(
						'Cannot assign type "%s" for field "%s", unknown type.',
						$data['type'],
						$field
					));
			}

			if (static::class != self::class) {
				if (isset(static::types[$field])) {
					$type  = static::types[$field];
					$value = $type::from($value);
				}

				$entity->$field = $value;
			}

			if ($existing) {
				$entity->_values[$field] = $value;
			}
		}

		return $entity;
	}


	/**
	 * Empty constructor
	 */
	public function __construct()
	{

	}


	/**
	 * Invoke the class to get an association to another entity (optionally through a join)
	 * @template TTarget of Entity
	 * @param class-string<TTarget> $target
	 * @return Association<static, TTarget>
	 */
	public function __invoke(string $target, string $through = NULL): Association
	{
		$key = sha1(sprintf('%s:%s', $target, $through ?: 'null'));

		if (!isset($this->_cache[$key])) {
			$this->_cache[$key] = new Association($this->_database, $this, $target, $through);
		}

		return $this->_cache[$key];
	}


	/**
	 * Magic getter to get property values for DTOs
	 */
	public function __get(string $name): mixed
	{
		if (static::class != self::class) {
			$method = '_' . $name;

			if (is_callable([$this, $method])) {
				return $this->$method();
			}

			if (array_key_exists($name, $this->_values)) {
				return $this->$name;
			}
		} else {
			if (array_key_exists($name, $this->_values)) {
				return $this->_values[$name];
			}
		}

		throw new RuntimeException(sprintf(
			'Cannot get field on "%s", inaccessible property "%s".',
			static::class,
			$name
		));
	}


	/**
	 *
	 */
	public function __isset(string $name): bool
	{
		if (static::class != self::class) {
			return is_callable([$this, '_' . $name]) || property_exists($this, $name);
		}

		return isset($this->_values[$name]);
	}


	/**
	 * Magic setter to set property values for untyped DTOs
	 */
	public function __set(string $name, mixed $value): void
	{
		if (static::class != self::class) {
			$method = '_' . $name;

			if (is_callable([$this, $method])) {
				$output = $this->$method($value);

				if ($output === $this) {
					return;
				}
			}
		} else {
			if (array_key_exists($name, $this->_values)) {
				$this->_values[$name] = $value;

				return;
			}
		}

		throw new RuntimeException(sprintf(
			'Cannot set field on "%s", inaccessible property "%s".',
			static::class,
			$name
		));
	}
}
