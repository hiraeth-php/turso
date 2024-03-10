<?php

class Users extends Hiraeth\Turso\Repository
{
	const entity = User::class;

	const identity = [
		'id'
	];

	const order = [
		'lastName' => 'asc',
		'firstName' => 'asc'
	];
}
