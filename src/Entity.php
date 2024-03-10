<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;


/**
 * The base Entity class to handle untyped DTOs or to extend
 * @template T
 */
class Entity
{
	/**
	 * The name of the SQL table to which this entity maps
	 * @var string|null
	 */
	const table = NULL;

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
	 * @return array<string, mixed>
	 */
	static final public function _diff(self $entity, bool $reset = FALSE): array
	{
		if ($entity::class == Entity::class) {
			return $entity->_values;
		}

		$values = array();

		foreach ($entity->_database->getReflections($entity::class) as $reflection) {
			if (!$reflection->isInitialized($entity)) {
				continue;
			}

			$field = $reflection->getName();

			if (!isset($entity->_values[$field]) || $entity->$field != $entity->_values[$field]) {
				$values[$field] = $entity->$field;

				if ($reset) {
					$entity->_values[$field] = $entity->$field;
				}
			}
		}

		return $values;
	}


	/**
	 * Do not allow for constructor overload
	 */
	final public function __construct(Database $database, array $values = array(), bool $init = FALSE)
	{
		$this->_database = $database;

		if (static::class == self::class) {
			$fields = array_keys($values);
		} else {
			$fields = array_map(
				fn($reflection) => $reflection->getName(),
				$database->getReflections(static::class))
			;
		}

		foreach ($fields as $field) {
			$this->_values[$field] = NULL;

			if (isset($values[$field])) {
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

				if ($init) {
					$this->_values[$field] = $value;
				}

				if (static::class != self::class) {
					$this->$field = $value;
				}
			}
		}
	}


	/**
	 * Invoke the class to get an association to another entity (optionally through a join)
	 * @template TTarget of Entity
	 * @param class-string<TTarget> $target
	 * @return Association<$this, TTarget>
	 */
	final public function __invoke(string $target, string $through = NULL): Association
	{
		$key = sha1(sprintf('%s:%s', $target, $through ?: 'null'));

		if (!isset($this->_cache[$key])) {
			$this->_cache[$key] = new Association($this->_database, $this, $target, $through);
		}

		return $this->_cache[$key];
	}


	/**
	 * Magic getter to get property values for untyped DTOs
	 */
	public function __get(string $name): mixed
	{
		if (static::class != self::class || !isset($this->_values[$name])) {
			throw new RuntimeException(sprintf(
				'Cannot get field on "%s", undeclared property "%s".',
				static::class,
				$name
			));
		}

		return $this->_values[$name] ?? NULL;
	}


	/**
	 * Magic setter to set property values for untyped DTOs
	 */
	public function __set(string $name, mixed $value): void
	{
		if (static::class != self::class || !isset($this->_values[$name])) {
			throw new RuntimeException(sprintf(
				'Cannot get field on "%s", undeclared property "%s".',
				static::class,
				$name
			));
		}

		$this->_values[$name] = $value;
	}
}
