<?php

namespace Hiraeth\Turso;

use RuntimeException;

/**
 * The base Entity class to handle untyped DTOs or to extend
 */
class Entity
{
	/**
	 * The value store for untyped DTOs
     * @var array<string, mixed>
	 */
	private $_values = array();

	/**
	 * The Database which populated this entity (for hasMany/hasOne queries)
     * @var Database
	 */
	public $_database;

	/**
	 * Inspect the entity to obtain a list of properties
	 * @return array<string>
	 */
	static public function _inspect(): array
	{
		return array_filter(
			array_keys(get_class_vars(static::class)),
			fn($name) => $name[0] != '_'
		);
	}


	/**
	 * Create a new instance of the entity and populate it with its values
	 * @param array<string, array{'type': string, 'value': mixed}> $values
	 */
	static public function _create(Database $database, array $values): self
	{
		$entity            = new static();
		$entity->_database = $database;

		foreach ($values as $field => $data) {
			switch (strtolower($data['type'])) {
				case 'null':
					$entity->$field = NULL; break;

				case 'integer':
					$entity->$field = intval($data['value']); break;

				case 'double':
				case 'real':
					$entity->$field = floatval($data['value']); break;

				case 'boolean':
					$entity->$field = boolval($data['value']); break;

				case 'string':
				case 'text':
				case 'blob':
					$entity->$field = $data['value']; break;

				default:
					throw new RuntimeException(sprintf(
						'Cannot assign type "%s" for field "%s", unknown type.',
						$data['type'],
						$field
					));
			}
		}

		return $entity;
	}


	/**
	 * Do not allow overloading constructor
	 */
	final public function __construct()
	{
	}


	/**
     * Invoke the class to get an association to another entity (optionally through a join)
	 */
	final public function __invoke(string $target, string $through = NULL): Association
	{
		return new Association($this->_database, $this, $target, $through);
	}


	/**
	 * Magic getter to get property values for untyped DTOs
	 */
	public function __get(string $name): mixed
	{
		if (static::class != self::class) {
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
		if (static::class != self::class) {
			throw new RuntimeException(sprintf(
				'Cannot get field on "%s", undeclared property "%s".',
				static::class,
				$name
			));
		}

		$this->_values[$name] = $value;
	}
}
