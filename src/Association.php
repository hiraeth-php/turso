<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;

/**
 * Handles creating associations between entities or collections of them
 * @template S of Entity
 * @template T of Entity
 */
class Association
{
	/**
	 * A cache for associated entities and results
	 * @var array<string, Result<T>>
	 */
	protected $cacheResult = array();

	/**
	 * A cache for associated entities and results
	 * @var array<string, T|null>
	 */
	protected $cacheEntity = array();

	/**
	 * The database to use to fulfill the associations
	 * @var Database
	 */
	protected $database;

	/**
	 * The source entity
	 * @var S $source
	 */
	protected $source;

	/**
	 * The target entity type
	 * @var class-string<T>
	 */
	protected $target;

	/**
	 * The join table to go through to get to the target
	 * @var string
	 */
	protected $through;


	/**
	 * Create a new association
	 * @param S $source
	 * @param class-string<T> $target
	 */
	public function __construct(Database $database, Entity $source, string $target, ?string $through = NULL)
	{
		if (!is_subclass_of($target, Entity::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Must be a subclass of entity'
			));
		}

		$this->database = $database;
		$this->source   = $source;
		$this->target   = $target;
		$this->through  = $through;
	}


	/**
	 *
	 */
	public function changeMany(array|Result $entities, array $map)
	{
		return $this->source;
	}


	/**
	 * Enables changing an associate to-one entity
	 */
	public function changeOne(Entity $entity, array $map)
	{
		$ident             = array();
		$source_column     = reset($map);
		$target_column     = key($map);
		$cache_key         = sha1(serialize(array_flip($map)));
		$query             = new Query($this->database);
		$source_reflection = $this->database->getReflection($this->source, $source_column);
		$target_reflection = $this->database->getReflection($this->target, $target_column);

		if (in_array($source_column, $this->source::ident)) {
			$update = $entity;
			$column = $target_column;
			$value  = $source_reflection->getValue($this->source);

			$target_reflection->setValue($update, $value);

		} else {
			$update = $this->source;
			$column = $source_column;
			$value  = $target_reflection->getValue($entity);

			$source_reflection->setValue($update, $value);
		}

		foreach ($this->database->getReflections($update::class) as $column => $reflection) {
			if (!in_array($reflection->getName(), $update::ident)) {
				continue;
			}

			if (!$reflection->isInitialized($update)) {
				continue;
			}

			$ident[] = $query('@column = {value}')
				->raw('column', $column)
				->var('value', $reflection->getValue($update))
			;
		}

		if (count($ident) == count($update::ident)) {
			$this->database->execute(
				"UPDATE @table SET @column = {value} @ident",
				[
					'value' => $value,
				],
				[
					'table'  => $update::table,
					'column' => $column,
					'ident'  => $query('WHERE @ident')
						->bind(' AND ', FALSE)
						->raw('ident', $ident),
				]
			)->throw();
		}

	//	$this->cacheEntity[$cache_key] = $entity;

		return $this->source;
	}


	/**
	 * Get a many related entities
	 * @param array<string, string> $map
	 * @return Result<T>
	 */
	public function hasMany(array $map, bool $refresh = FALSE): Result
	{
		$cache_key = sprintf('%s@%s[%s]', key($map), $this->target, $this->through);

		if (!isset($this->cacheResult[$cache_key]) || $refresh) {
			$this->cacheResult[$cache_key] = $this->getResult($map)
				->throw('Could not fulfill *-to-many association')
			;
		}

		return $this->cacheResult[$cache_key];
	}


	/**
	 * Get a single related entity
	 * @param array<string, string> $map
	 * @return T|null
	 */
	public function hasOne(array $map, bool $refresh = FALSE): ?Entity
	{
		$cache_key = sha1(serialize($map));

		if (!isset($this->cacheEntity[$cache_key]) || $refresh) {
			$this->cacheEntity[$cache_key] = $this->getResult($map)
				->throw('Could not fulfill *-to-one association')
				->getRecord(0)
			;
		}

		return $this->cacheEntity[$cache_key];
	}


	/**
	 * Actually fulfill an association request
	 * @param array<string, string> $map
	 * @return Result<T>
	 */
	protected function getResult(array $map): Result
	{
		$target_table = constant($this->target . '::table');
		$reflection   = $this->database->getReflection($this->source, key($map));

		if (!$reflection->isInitialized($this->source)) {
			throw new RuntimeException(sprintf(
				'Cannot obtain associated entity/entities, field "%s" is not initialized',
				$reflection->getName()
			));
		}

		$value = $reflection->getValue($this->source);

		if ($this->through) {
			$keys   = array_keys($map);
			$result = $this->database->execute(
				"SELECT * FROM @table WHERE @remote IN( SELECT @link FROM @through WHERE @local = {value} )",
				[
					'value' => $value
				],
				[
					'table'   => $target_table,
					'remote'  => $map[$keys[1]],
					'link'    => $keys[1],
					'through' => $this->through,
					'local'   => reset($map),
				],
				FALSE
			);
		} else {
			$result = $this->database->execute(
				"SELECT * FROM @table WHERE @column = {value}",
				[
					'value' => $value
				],
				[
					'table'  => $target_table,
					'column' => reset($map)
				],
				FALSE
			);
		}

		return $result->of($this->target);
	}
};
