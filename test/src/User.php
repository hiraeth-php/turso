<?php

use Hiraeth\Turso\Types;

/**
 *
 */
class User extends Hiraeth\Turso\Entity
{
	const table = 'users';

	const ident = [
		'id'
	];

	const types = [
		'died' => Types\Date::class
	];

	protected int $id;
	protected string|null $firstName;
	protected string|null $lastName;
	protected string $email;
	protected int|null $age;
	protected DateTime|null $died;

	/**
	 * Make properties publicly readable.
	 */
	public function __get(string $name): mixed
	{
		if (array_key_exists($name, $this->_values)) {
			return $this->$name;
		}

		throw new RuntimeException(sprintf(
			'Class "%s" does not allow public access to property named %s',
			static::class,
			$name
		));
	}

	/**
	 * You should not do this, if you want your properties to be publicly writable, just make
	 * them public.  This is only for testing purposes.
	 */
	public function __set(string $name, mixed $value): void
	{
		if (array_key_exists($name, $this->_values)) {
			$this->$name = $value;

			return;
		}

		throw new RuntimeException(sprintf(
			'Class "%s" does not allow public access to property named %s',
			static::class,
			$name
		));
	}
}
