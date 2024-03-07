<?php

namespace Hiraeth\Turso;

use InvalidArgumentException;
use RuntimeException;

abstract class Repository
{
	static protected $entity = NULL;

	static protected $identity = array();

	static protected $table = NULL;

	static protected $order = array();

	/**
	 * @var Database
	 */
	protected $database;

	protected $map = array();

	/**
	 *
	 */
	final public function __construct(Database $database)
	{
		$this->database = $database;

		if (!static::$entity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", entity class not defined',
				static::class
			));
		}

		if (!static::$identity) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", you must provide at least one identify field',
				static::class
			));
		}

		if (!static::$table) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", table not defined',
				static::class
			));
		}

		$result = $this->database->execute("SELECT * FROM @table LIMIT 1", [], [ 'table' => static::$table ]);

		if ($result->isError()) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository, %s: %s',
				$result->getError()->code,
				$result->getError()->message
			));
		}

		$fields  = static::$entity::_inspect();
		$columns = array_map(
			fn($column) => $column['name'],
			$result->getRaw()['results'][0]['response']['result']['cols']
		);

		foreach ($fields as $i => $field) {
			foreach ($columns as $j => $column) {
				$field  = preg_replace('/[^a-z0-9]/', '', strtolower($field));
				$column = preg_replace('/[^a-z0-9]/', '', strtolower($column));

				if ($field == $column) {
					$this->map[$fields[$i]] = $columns[$j];
				}
			}
		}

		if ($missing = array_diff(static::$identity, array_keys($this->map))) {
			throw new RuntimeException(sprintf(
				'Cannot initialize repository "%s", no columns could match identify fields: %s',
				static::class,
				implode(', ', $missing)
			));
		}

		if (!static::$order) {
			static::$order = array_combine(
				static::$identity,
				array_pad([], count(static::$identity), 'asc')
			);
		}
	}


	/**
     *
	 */
	public function find(array|int|string $id): Entity
	{
		if (!is_array($id)) {
			if (count(static::$identity) > 1) {
				throw new InvalidArgumentException(sprintf(
					'Cannot find by scalar id on "%s" with "%s", identity has more than one field.',
					static::$entity,
					$id
				));
			}

			$id = array_combine(static::$identity, [$id]);
		}

		$result = $this->findBy($id, static::$order, 2);

		if (count($result) > 1) {
			throw new InvalidArgumentException(sprintf(
				'Cannot find by passed id, argument yielded more than 1 result.'
			));
		}

		return $result->setEntity(static::$entity)->getRecord(0);
	}


	/**
     *
	 */
	public function findAll(array $order = array()): Result
	{
		return $this->findBy([], $order);
	}


	/**
	 *
	 */
	public function findBy(array $criteria, array $order = array(), int $limit = NULL, int $page = NULL): Result
	{
		$query      = new Query();
		$conditions = array();
		$order_bys  = array();

		foreach ($criteria as $field => $value) {
			if (!isset($this->map[$field])) {
				// throw
			}

			$conditions[] = $query->eq($field, $value);
		}

		if (!$order) {
			$order = static::$order;
		}

		foreach ($order as $field => $value) {
			if (!isset($this->map[$field])) {
				// throw
			}

			$order_bys[] = $query->sort($field, $value);
		}

		$result = $this->database->execute(
			$query("SELECT * FROM @table @where ORDER BY @order @limit @start")
				->raw('table', static::$table)
				->raw('where', $query->where(...$conditions))
				->raw('order', $query->order(...$order_bys))
				->raw('limit', $query->limit($limit))
				->raw('start', $query->offset(($page - 1) * $limit))
		);

		if ($result->isError()) {
			// throw
		}

		return $result->setEntity(static::$entity);
	}
}



