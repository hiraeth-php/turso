<?php

namespace Hiraeth\Turso;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

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
	 * A cache of repositories already instantiated
	 * @var array<class-string, mixed>
	 */
	protected $cache = array();

	/**
	 * The HTTP Client (Guzzle) for sending requests
	 * @var Client
	 */
	protected $client;


	/**
	 * @var array<class-string, array<\ReflectionProperty>>
	 */
	protected $reflections = array();


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
		$this->url    = $url;
		$this->token  = $token;
	}


	/**
	 * Execute a string of SQL (Queries can also be passed as they implement __toString)
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $identifiers
	 * @return Result<Entity>
	 */
	public function execute(string $sql, array $params = array(), array $identifiers = array(), bool $type = TRUE): Result
	{
		$headers = [ 'Content-Type' => 'application/json' ];
		$handle  = fopen('php://memory', 'w');

		if ($this->token) {
			$headers['Authorization'] = $this->token;
		}

		if ($handle) {
			$query   = new Query($sql, $params, $identifiers);
			$stream  = new Stream($handle);
			$url     = new Uri($this->url . static::PATH_PIPELINE);

			$stream->write(json_encode([
				"requests" => [
					[ "type" => "execute", "stmt" => [ "sql" => (string) $query ] ],
					[ "type" => "close" ]
				]
			]) ?: '');

			$response = $this->client->sendRequest(new Request('POST', $url, $headers, $stream));
			$result   = new Result($query, $this, json_decode($response->getBody()->getContents(), TRUE), $type);

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
	 * Get entity property reflections
	 * @param class-string $class
	 * @return array<ReflectionProperty>
	 */
	public function getReflections(string $class): array
	{
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

		return $this->reflections[$class];
	}


	/**
	 * Get a repository by its class name
	 * @param class-string $class
	 * @return Repository
	 */
	public function getRepository(string $class): Repository
	{
		if (isset($this->cache[$class])) {
			return $this->cache[$class];
		}

		if (!is_a($class, Repository::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot instantiate repository, "%s" is not a repository.',
				$class
			));
		}

		return $this->cache[$class] = new $class($this);
	}
}
