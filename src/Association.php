<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;

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
	 * Get a many related entities
	 * @param array<string, string> $map
	 * @return Result<T>
	 */
	public function hasMany(array $map, bool $refresh = FALSE): Result
	{
		$key = sha1(sprintf('%s@%s', __FUNCTION__, serialize($map)));

		if (!isset($this->cacheResult[$key]) || $refresh) {
			$this->cacheResult[$key] = $this->getResult($map)
				->throw('Could not fulfill *-to-many association')
			;
		}

		return $this->cacheResult[$key];
	}


	/**
	 * Get a single related entity
	 * @param array<string, string> $map
	 * @return T|null
	 */
	public function hasOne(array $map, bool $refresh = FALSE): ?Entity
	{
		$key = sha1(sprintf('%s@%s', __FUNCTION__, serialize($map)));

		if (!isset($this->cacheEntity[$key]) || $refresh) {
			$this->cacheEntity[$key] = $this->getResult($map)
				->throw('Could not fulfill *-to-one association')
				->getRecord(0)
			;
		}


		return $this->cacheEntity[$key];
	}


	/**
	 * Actually fulfill an association request
	 * @param array<string, string> $map
	 * @return Result<T>
	 */
	protected function getResult(array $map): Result
	{
		if ($this->through) {
			$keys   = array_keys($map);
			$field  = $keys[0];
			$value  = $this->source->$field;
			$result = $this->database->execute(
				"SELECT * FROM @table WHERE @remote IN( SELECT @link FROM @through WHERE @local = {value} )",
				[
					'value' => $value
				],
				[
					'table'   => $this->target::table,
					'remote'  => $map[$keys[1]],
					'link'    => $keys[1],
					'through' => $this->through,
					'local'   => $map[$field],
				]
			);

		} else {
			$field  = key($map);
			$value  = $this->source->$field;
			$result = $this->database->execute(
				"SELECT * FROM @table WHERE @column = {value}",
				[
					'value' => $value
				],
				[
					'table' => $this->target::table,
					'column' => $map[$field]
				]
			);
		}

		return $result->of($this->target);
	}
};
