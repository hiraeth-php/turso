<?php

namespace Hiraeth\Turso;

use RuntimeException;

class Entity
{
	/**
     * @var array
	 */
	private $_values = array();

	/**
     * @var Database
	 */
	public $_database;

	/**
     *
	 */
	static public function _inspect(): array
	{
		return array_keys(get_class_vars(static::class));
	}


	/**
	 *
	 */
	static public function _create(Database $database, array $values): self
	{
		$entity            = new static();
		$entity->_database = $database;

		foreach ($values as $field => $data) {
			switch ($data['type']) {
				case 'null':
					$entity->$field = NULL; break;

				case 'integer':
					$entity->$field = intval($data['value']); break;

				case 'real':
					$entity->$field = floatval($data['value']); break;

				case 'boolean':
					$entity->$field = boolval($data['value']); break;

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
     *
	 */
	public function __invoke(string $target, string $through = NULL)
	{
		return new class($this->_database, $this, $target, $through) {

			protected $database;

			protected $source;

			protected $target;

			protected $through;

			public function __construct(Database $database, Entity $source, string $target, ?string $through)
			{
				$this->database = $database;
				$this->source   = $source;
				$this->target   = $target;
				$this->through  = $through;
			}

			public function dieOnError(Result $result)
			{
				if ($result->isError()) {
					throw new RuntimeException(sprintf(
						'%s: %s in "%s"',
						$result->getError()->code,
						$result->getError()->message,
						$result->getSQL()
					));
				}

				return $result;
			}

			public function hasSimple(array $map): Result
			{
				$field  = key($map);
				$value  = $this->source->$field;
				$result = $this->database->execute(
					"SELECT * FROM @target WHERE @column = {value}",
					[ 'value' => $value ],
					[ 'target' => $this->target, 'column' => $map[$field] ]
				);

				return $this->dieOnError($result);
			}

			public function hasMany(array $map, string $class): array
			{
				if (!$this->through) {
					return $this->hasSimple($map)->getRecords($class);
				}

				$keys   = array_keys($map);
				$field  = $keys[0];
				$value  = $this->source->$field;
				$result = $this->database->execute(
					"SELECT * FROM @target WHERE @remote IN( SELECT @link FROM @through WHERE @local = {value} )",
					[ 'value' => $value ],
					[
						'target'  => $this->target,
						'remote'  => $map[$keys[1]],
						'link'    => $keys[1],
						'through' => $this->through,
						'local'   => $map[$field],
					]
				);

				return $this->dieOnError($result)->getRecords($class);
			}

			public function hasOne(array $map, string $class): ?Entity
			{
				return $this->hasSimple($map)->getRecord(0, $class);
			}
		};
	}

	/**
	 *
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
	 *
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
