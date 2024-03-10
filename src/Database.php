<?php

namespace Hiraeth\Turso;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
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
	 * The name of the Turso database to connect to
	 * @var string
	 */
	protected $name;

	/**
	 * The name of the organization it's under
	 * @var string
	 */
	protected $organization;

	/**
	 * The bearer token to authorize Turso API requests
	 * @var string
	 */
	protected $token;

	/**
	 * @var bool
	 */
	public $debug = FALSE;


	/**
	 * Create a new Database instance
	 */
	public function __construct(Client $client, string $name, string $token, string $organization)
	{
		$this->client       = $client;
		$this->name         = $name;
		$this->token        = $token;
		$this->organization = $organization;
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

	/**
	 * Execute a string of SQL (Queries can also be passed as they implement __toString)
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $identifiers
	 * @return Result<Entity>
	 */
	public function execute(string $sql, array $params = array(), array $identifiers = array(), bool $type = TRUE): Result
	{
		$headers = [ 'Authorization' => $this->token, 'Content-Type' => 'application/json' ];
		$handle  = fopen('php://memory', 'w');

		if ($handle) {
			$query   = new Query($sql, $params, $identifiers);
			$stream  = new Stream($handle);
			$uri     = new Uri(sprintf(
				'https://%s-%s.turso.io%s',
				$this->name,
				$this->organization,
				static::PATH_PIPELINE
			));

			$stream->write(json_encode([
				"requests" => [
					[ "type" => "execute", "stmt" => [ "sql" => (string) $query ] ],
					[ "type" => "close" ]
				]
			]) ?: '');

			$response = $this->client->sendRequest(new Request('POST', $uri, $headers, $stream));
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
}
