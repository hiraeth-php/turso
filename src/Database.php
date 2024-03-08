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
	 * @var array<Repository>
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
	 * Dies with a RuntimeException if the result is an error with an optional message
	 */
	public function dieOnError(Result $result, string $message = NULL): Result
	{
		if ($result->isError()) {
			$error = sprintf(
				'%s: %s in "%s"',
				$result->getError()->code,
				$result->getError()->message,
				$result->getSQL()
			);

			if ($message) {
				$error = $message . '(' . $error . ')';
			}

			throw new RuntimeException($error);
		}

		return $result;
	}

	/**
	 * Get a repository by its class name
	 * @param class-string $class
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
	 */
	public function execute(string $sql, array $params = array(), array $identifiers = array()): Result
	{
		$headers = [ 'Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json' ];
		$handle  = fopen('php://memory', 'w');

		if ($handle) {
			$stream  = new Stream($handle);
			$uri     = new Uri(sprintf(
				'https://%s-%s.turso.io%s',
				$this->name,
				$this->organization,
				static::PATH_PIPELINE
			));

			$stream->write(json_encode([
				"requests" => [
					[ "type" => "execute", "stmt" => [ "sql" => (string) new Query($sql, $params, $identifiers) ] ],
					[ "type" => "close" ]
				]
			]) ?: '');

			$response = $this->client->sendRequest(new Request('POST', $uri, $headers, $stream));

			fclose($handle);

			return new Result($sql, $this, json_decode($response->getBody()->getContents(), TRUE));
		}

		throw new RuntimeException(sprintf(
			'Unable to execute query, failed to open memory stream fo writing.'
		));
	}
}
