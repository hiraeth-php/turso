<?php

namespace Hiraeth\Turso;

use GuzzleHttp\Client;
use Hiraeth\Application;
use Hiraeth\Delegate;

class DatabaseDelegate implements Delegate
{
	/**
	 * {@inheritDoc}
	 */
	static public function getClass(): string
	{
		return Database::class;
	}


	/**
	 * {@inheritDoc}
	 */
	public function __invoke(Application $app): object
	{
		return new Database(
			$app->get(Client::class),
			$app->getEnvironment('TURSO_NAME', 'default'),
			$app->getEnvironment('TURSO_TOKEN'),
			$app->getEnvironment('TURSO_ORGANIZATION')
		);
	}
}
