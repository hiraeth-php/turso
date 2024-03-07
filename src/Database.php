<?php

namespace Hiraeth\Turso;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use SQLite3;

class Database
{
	const PATH_PIPELINE = '/v2/pipeline';

	/**
	 * @var array<Repository>
	 */
	protected $cache = array();

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $organization;

	/**
	 * @var string
	 */
	protected $token;


	/**
	 *
	 */
	public function __construct(Client $client, string $name, string $token, string $organization)
	{
		$this->client       = $client;
		$this->name         = $name;
		$this->token        = $token;
		$this->organization = $organization;
	}


	/**
	 *
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
	 *
	 */
	public function execute(string $sql, array $params = array(), array $identifiers = array()): Result
	{
		$headers = [ 'Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json' ];
		$stream  = new Stream(fopen('php://memory', 'w'));
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
		]));

		$response = $this->client->sendRequest(new Request('POST', $uri, $headers, $stream));

		return new Result($sql, $this, json_decode($response->getBody()->getContents(), TRUE));
	}
}
