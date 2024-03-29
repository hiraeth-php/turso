<?php

use Hiraeth\Turso\Entity;

require(__DIR__ . '/../vendor/autoload.php');

$database = new Hiraeth\Turso\Database(
	new GuzzleHttp\Client(),
	'http://localhost:8090',
	'Basic YWRtaW46YWRtaW4='
);

$database->debug = TRUE;

$database
	->execute("DROP TABLE IF EXISTS users")
	->throw()
;

//
// Create our users table if it doesn't exist
//

$database
	->execute("
		CREATE TABLE IF NOT EXISTS users (
			id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
			parent INTEGER REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
			first_name TEXT,
			last_name TEXT,
			email TEXT NOT NULL UNIQUE,
			age INTEGER,
			died TEXT
		)
	")->throw()
;

//
// Insert a couple records using the repository
//

$users = $database->getRepository(Users::class);

$users
	->insert(
		$jwick = $users->create([
			'firstName' => 'John',
			'lastName'  => 'Wick',
			'email'     => 'babayaga@hotmail.com',
			'age'       => 44,
			'died'      => new DateTime('2023-03-24')
		])
	)
;

$users
	->insert(
		$mouse = $users->create([
			'firstName' => 'Mickey',
			'lastName'  => 'Mouse',
			'email'     => 'mm@disney.com',
			'age'       => 94
		])
	)->throw()
;

//
// Ensure IDs get assigned
//

echo PHP_EOL;
echo 'JWick Insert ID: ' . $jwick->id . PHP_EOL;
echo 'Mouse Insert ID: ' . $mouse->id . PHP_EOL;

//
// Update Wick's age (should onlys send difference) and parent
//
//
$jwick->age    = 45;
$jwick->parent = $mouse;

$users->update($jwick)->throw();

echo PHP_EOL;
echo 'Jwick Parent ID: ' . $jwick->parent->id . PHP_EOL;

//
// Null Mouse's name
//
$mouse->firstName = NULL;

$users->update($mouse)->throw();

//
// Delete Mouse
//

$users->delete($mouse)->throw();

//
// Test findAll(), should only be Wick
//
foreach ($users->findAll() as $user) {
	echo PHP_EOL . 'User ID: ' . $user->id . ', Died: ' . $user->died->format('m/d/y') . PHP_EOL;

	$user->age = 46;

	$users->update($user)->throw();
}

//
// Update ID
//

try {
	$jwick->id = 0;

	throw new Exception('Failed trying to set entity id');
} catch (RuntimeException $e) {

}

//
// Should be an empty update
//

$users->update($jwick)->throw();

//
// Raw execute with casting (partial fields)
//

$records = $database->execute("SELECT first_name, last_name FROM users")->of(User::class);

foreach ($records as $user) {
	echo PHP_EOL . get_class($user) . ': ' . $user->fullName . PHP_EOL;

	$user->age  = 47;
	$user->died = NULL;

	try {
		// This will not work because no ID is set.
		$users->update($user)->throw();

		// If an exception was not thrown, we actually error
		throw new Exception('Failed trying to update entity without id');

	} catch (InvalidArgumentException $e) {
		// We want to test that this exception occured, so we catch it
		// and continue;
	}
}

//
// Raw execute with no-casting (partial fields)
//

$records = $database->execute("SELECT first_name, last_name FROM users");

foreach ($records as $record) {
	echo PHP_EOL . $record->first_name . ' ' . $record->last_name . PHP_EOL;

	try {
		$users->insert($record)->throw();

		throw new Exception('Failed trying to handle foreign entity');
	} catch (InvalidArgumentException $e) {

	}
}

//
// Select with count
//
$result = $users->select(function() {}, $total)->throw();

echo PHP_EOL . $total . PHP_EOL;

echo $result->getRecord(0)->parent->id;
