<?php

namespace Hiraeth\Turso;

use RuntimeException;

/**
 * The base Entity class to handle untyped DTOs or to extend
 */
class Entity
{
	/**
	 * A cache for associations
	 * @var array<string, Association>
	 */
	protected $_cache = array();

	/**
	 * The Database which populated this entity (for hasMany/hasOne queries)
	 * @var Database
	 */
	protected $_database;

	/**
	 * The value store for untyped DTOs
     * @var array<string, mixed>
	 */
	protected $_values = array();


	/**
	 * Create a new instance of the entity and populate it with its values
	 * @param array<string, array{'type': string, 'value': mixed}> $values
	 */
	final static public function _create(Database $database, array $values): self
	{
		$entity            = new static();
		$entity->_database = $database;

		foreach ($values as $field => $data) {
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
				$entity->$field = $value;
			}

			$entity->_values[$field] = $value;
		}

		return $entity;
	}


	/**
	 *
	 */
	final static public function _diff(Entity $entity, bool $reset = FALSE): array
	{
		if ($entity::class == Entity::class) {
			return $entity->_values;
		}

		$values = array();

		foreach ($entity::_inspect() as $field) {
			if ($entity->$field != $entity->_values[$field]) {
				$values[$field] = $entity->$field;

				if ($reset) {
					$entity->_values[$field] = $entity->$field;
				}
			}
		}

		return $values;
	}


	/**
	 * Inspect the entity to obtain a list of properties
	 * @return array<string>
	 */
	final static public function _inspect(): array
	{
		return array_filter(
			array_keys(get_class_vars(static::class)),
			fn($name) => $name[0] != '_'
		);
	}


	/**
     * Invoke the class to get an association to another entity (optionally through a join)
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
	final public function __get(string $name): mixed
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
	final public function __set(string $name, mixed $value): void
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
