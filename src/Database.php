<?php

namespace Hiraeth\Turso;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use RuntimeException;
use ReflectionClass;
use ReflectionProperty;
use SQLite3;

/**
 * The Database handles connection, and request/response to Turso server
 */
class Database
{
	/**
	 * The API path to the pipeline
	 * @var string
	 */
	const PATH_PIPELINE = '/v2/pipeline';

	/**
	 * The HTTP Client (Guzzle) for sending requests
	 * @var Client
	 */
	protected $client;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected $entities = array();

	/**
	 * @var array<class-string, array<\ReflectionProperty>>
	 */
	protected $reflections = array();

	/**
	 * A cache of repositories already instantiated
	 * @var array<class-string, mixed>
	 */
	protected $repositories = array();

	/**
	 * The auth token to use (must includear Bearer/Basic/etc)
	 * @var string
	 */
	protected $token;

	/**
	 * The url of the LibSQL/Turso DB to connect to
	 * @var string
	 */
	protected $url;

	/**
	 * @var bool
	 */
	public $debug = FALSE;

	/**
	 * Create a new Database instance
	 */
	public function __construct(Client $client, string $url, string $token = NULL)
	{
		$this->client = $client;
		$this->url    = rtrim($url, '/');
		$this->token  = $token;
	}


	/**
	 * Escape a value for the database (the type will be inferred using gettype())
	 */
	public function escape(mixed $value): ?string
	{
		switch (strtolower(gettype($value))) {
			case 'integer':
			case 'double':
				return (string) $value;

			case 'null':
				return 'NULL';

			case 'boolean':
				return $value ? 'TRUE' : 'FALSE';

			case 'string':
				return "'" . SQLite3::escapeString($value) . "'";

			case 'array':
				return
					"(" . implode(',', array_map(
						function($value) {
							return $this->escape($value);
						},
						$value
					)) . ")";

			default:
				return NULL;
		}
	}


	/**
	 * Execute a string of SQL (Queries can also be passed as they implement __toString)
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $identifiers
	 * @return Result<Entity>
	 */
	public function execute(string $sql, array $params = array(), array $identifiers = array()): Result
	{
		$headers = [ 'Content-Type' => 'application/json' ];
		$handle  = fopen('php://memory', 'w');

		if ($this->token) {
			$headers['Authorization'] = $this->token;
		}

		if ($handle) {
			$query   = new Query($this, $sql, $params, $identifiers);
			$stream  = new Stream($handle);
			$url     = new Uri($this->url . static::PATH_PIPELINE);

			$stream->write(json_encode([
				"requests" => [
					[ "type" => "execute", "stmt" => [ "sql" => (string) $query ] ],
					[ "type" => "close" ]
				]
			]) ?: '');

			$response = $this->client->sendRequest(new Request('POST', $url, $headers, $stream));
			$content  = json_decode($response->getBody()->getContents(), TRUE);
			$result   = new Result($query, $this, $content);

			fclose($handle);

			if ($this->debug) {
				echo PHP_EOL . $result->getSQL() . PHP_EOL;
			}

			return $result;
		}

		throw new RuntimeException(sprintf(
			'Unable to execute query, failed to open memory stream fo writing.'
		));
	}


	/**
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @param array<string> $columns
	 * @param array<mixed> $data
	 * @return T
	 */
	public function getEntity(string $class, array $columns, array $data): Entity
	{
		if (is_subclass_of($class, Entity::class, TRUE)) {
			$reflections = $this->getReflections($class);
			$fields      = array_map(fn($column) => $reflections[$column]->getName(), $columns);
			$entity      = $class::__init($this, array_combine($fields, $data), TRUE);

			$this->mapEntity($entity);

		} else {
			$entity = $class::__init($this, array_combine($columns, $data), TRUE);

		}

		return $entity;
	}


	/**
	 * @template T of Entity
	 * @param class-string<T>|T $class
	 */
	public function getReflection(string|Entity $class, string $column): ReflectionProperty
	{
		if ($class instanceof Entity) {
			$class = get_class($class);
		}

		$reflections = $this->getReflections($class);

		if (!isset($reflections[$column])) {
			throw new RuntimeException(sprintf(
				'Cannot get property for column "%s" on "%s"',
				$column,
				$class
			));
		}

		return $reflections[$column];
	}

	/**
	 * Get entity property reflections
	 * @template T of Entity
	 * @param class-string<T>|T $class
	 * @return array<ReflectionProperty>
	 */
	public function getReflections(string|Entity $class): array
	{
		if ($class instanceof Entity) {
			$class = get_class($class);
		}

		if (!isset($this->reflections[$class])) {
			if (!$class::table) {
				throw new RuntimeException(sprintf(
					'Cannot initialize entity of type "%s", no table defined',
					$class
				));
			}

			$columns    = array();
		    $properties = array();
			$reflection = new ReflectionClass($class);
			$inspection = $this
				->execute("SELECT * FROM @table LIMIT 0", [], ['table' => $class::table])
			    ->throw();

			foreach($inspection->getColumns() as $column) {
				$columns[]  = $column;
				$column_cmp = preg_replace('/[^a-z0-9]/', '', strtolower($column));

				foreach ($reflection->getProperties() as $field) {
					if ($field->isPrivate()) {
						continue;
					}

					$field_cmp = preg_replace('/[^a-z0-9]/', '', strtolower($field->getName()));

					if ($field_cmp == $column_cmp) {
						$properties[$column] = $field;
					}
				}
			}

			if ($missing = array_diff($columns, array_keys($properties))) {
				throw new RuntimeException(sprintf(
					'Missing properties %s, when initializing entity "%s"',
					implode(', ', $missing),
					$class
				));
			}

			$this->reflections[$class] = $properties;
		}

		if (!isset($this->entities[$class])) {
			$this->entities[$class] = array();
		}

		return $this->reflections[$class];
	}


	/**
	 * Get a repository: by its class name
	 * @template T of Repository
	 * @param class-string<T> $class
	 * @return T
	 */
	public function getRepository(string $class): Repository
	{
		if (isset($this->repositories[$class])) {
			return $this->repositories[$class];
		}

		if (!is_a($class, Repository::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot instantiate repository, "%s" is not a repository.',
				$class
			));
		}

		return $this->repositories[$class] = new $class($this);
	}


	/**
	 *
	 */
	public function mapEntity(Entity &$entity): void
	{
		$hash = $entity::__hash($entity);

		if ($hash) {
			if (!isset($this->entities[$entity::class][$hash])) {
				$this->entities[$entity::class][$hash] = $entity;
			} else {
				$entity = $this->entities[$entity::class][$hash];
			}
		}
	}

	/**
	 *
	 */
	public function remapEntity(Entity $entity, string $old_hash): void
	{
		$hash = $entity::__hash($entity);

		if ($old_hash != $hash) {
			if (!$old_hash) {
				if ($hash) {
					$this->entities[$entity::class][$hash] = $entity;
				}

			} else {
				if (isset($this->entities[$entity::class][$old_hash])) {
					unset($this->entities[$entity::class][$old_hash]);
				}

				if ($hash) {
					$this->entities[$entity::class][$hash] = $entity;
				}
			}
		}
	}

	/**
	 *
	 */
	public function unmapEntity(Entity $entity): void
	{
		$hash = $entity::__hash($entity);

		if ($hash) {
			unset($this->entities[$entity::class][$hash]);
		}
	}
}
