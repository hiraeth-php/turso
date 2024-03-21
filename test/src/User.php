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
	protected int|null $parent;
	public string|null $firstName;
	public string|null $lastName;
	public string $email;
	public int|null $age;
	public DateTime|null $died;

	protected function _fullName() {
		return trim(sprintf('%s %s', $this->firstName, $this->lastName));
	}

	protected function _parent(bool|self $refresh = FALSE): self|null
	{
		if ($refresh instanceof self) {
			return $this(self::class)->changeOne($refresh, ['id' => 'parent']);
		}

		return $this(self::class)->hasOne(['parent' => 'id'], $refresh);
	}
}
