<?php

namespace Hiraeth\Turso;

/**
 * Handles creating associations between entities or collections of them
 */
class Association
{
	/**
	 * A cache for associated entities and results
	 * @var array<string, Result|Entity>
	 */
	protected $cache = array();

	/**
	 * The database to use to fulfill the associations
	 * @var Database
	 */
	protected $database;

	/**
	 * The source entity
	 * @var Entity
	 */
	protected $source;

	/**
	 * The target table name with the associated entities
	 * @var string|Repository
	 */
	protected $target;

	/**
	 * The join table to go through to get to the target
	 * @var string
	 */
	protected $through;


	/**
	 * Create a new association
	 */
	public function __construct(Database $database, Entity $source, string|Repository $target, ?string $through = NULL)
	{
		$this->database = $database;
		$this->source   = $source;
		$this->target   = $target;
		$this->through  = $through;
	}


	/**
	 * Get a many related entities
	 * @param array<string, string> $map
	 * @param class-string $class
	 */
	public function hasMany(array $map, bool $refresh = FALSE, string $class = Entity::class): Result
	{
		if ($this->target instanceof Repository && $class == Entity::class) {
			$class = $this->target->getEntity();
		}

		$key = sha1(sprintf('%s@%s', __FUNCTION__, serialize($map)));

		if (!isset($this->cache[$key]) || $refresh) {
			$this->cache[$key] = $this->database
				->dieOnError(
					$this->getResult($map),
					'Could not fulfill *-to-many association'
				)
				->of($class)
			;
		}

		return $this->cache[$key];
	}


	/**
	 * Get a single related entity
	 * @param array<string, string> $map
	 * @param class-string $class
	 */
	public function hasOne(array $map, bool $refresh = FALSE, string $class = Entity::class ): ?Entity
	{
		if ($this->target instanceof Repository && $class == Entity::class) {
			$class = $this->target->getEntity();
		}

		$key = sha1(sprintf('%s@%s', __FUNCTION__, serialize($map)));

		if (!isset($this->cache[$key]) || $refresh) {
			$this->cache[$key] = $this->database
				->dieOnError(
					$this->getResult($map),
					'Could not fulfill *-to-one association'
				)
				->of($class)
				->getRecord(0)
			;
		}

		return $this->cache[$key];
	}


	/**
	 * Actually fulfill an association request
	 * @param array<string, string> $map
	 */
	protected function getResult(array $map): Result
	{
		if ($this->target instanceof Repository) {
			$target = $this->target->getTable();
		} else {
			$target = $this->target;
		}

		if ($this->through) {
			$keys   = array_keys($map);
			$field  = $keys[0];
			$value  = $this->source->$field;
			$result = $this->database->execute(
				"SELECT * FROM @target WHERE @remote IN( SELECT @link FROM @through WHERE @local = {value} )",
				[
					'value' => $value
				],
				[
					'target'  => $target,
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
				"SELECT * FROM @target WHERE @column = {value}",
				[
					'value' => $value
				],
				[
					'target' => $target,
					'column' => $map[$field]
				]
			);

		}

		return $result;
	}

};