<?php

require(__DIR__ . '/../vendor/autoload.php');

$database = new Hiraeth\Turso\Database(
	new GuzzleHttp\Client(),
	'http://localhost:8090',
	'Basic YWRtaW46YWRtaW4='
);

$database->debug = TRUE;

//
// Create our users table if it doesn't exist
//

$database->execute("
	CREATE TABLE IF NOT EXISTS users (
		id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
		first_name TEXT,
		last_name TEXT,
		email TEXT NOT NULL UNIQUE,
		age INTEGER
	)
")->throw();

//
// Clear it out for testing
//

$database->execute("DELETE FROM users")->throw();

//
// Insert a couple records using the repository
//

$users = $database->getRepository(Users::class);
$users->insert(
	$jwick = $users->create([
		'firstName' => 'John',
		'lastName'  => 'Wick',
		'email'     => 'babayaga@hotmail.com',
		'age'       => 44
	])
);

$users->insert(
	$mouse = $users->create([
		'firstName' => 'Mickey',
		'lastName'  => 'Mouse',
		'email'     => 'mm@disney.com',
		'age'       => 94
	])
);

//
// Ensure IDs get assigned
//

echo $jwick->id . PHP_EOL;
echo $mouse->id . PHP_EOL;

//
// Update Wick's age (should onlys send difference)
//

$jwick->age = 45;

$users->update($jwick);

//
// Null Mouse's name
//
$mouse->firstName = NULL;

$users->update($mouse);

//
// Delete Mouse
//

$users->delete($mouse);

//
// Test findAll(), should only be Wick
//
foreach ($users->findAll() as $user) {
	$user->age = 46;

	echo PHP_EOL . get_class($user) . PHP_EOL;

	$users->update($user);
}

//
// Update ID
//

$jwick->id = 0;

$users->update($jwick);

//
// Raw execute with casting (partial fields)
//

$records = $database->execute("SELECT first_name, last_name FROM users")->of(User::class);

foreach ($records as $user) {
	echo PHP_EOL . get_class($user) . PHP_EOL;
	echo PHP_EOL . $user->firstName . PHP_EOL;

	$user->age = 47;

	try {
		// This will not work because no ID is set.
		$users->update($user);

		// If an exception was not thrown, we actually error
		throw new Exception('Failed trying to update entity without id');

	} catch (InvalidArgumentException $e) {
		// We want to test that this exception occured, so we catch it
		// and continue;
	}

	$user->id = $jwick->id;  // Now that we've set the ID we should be able to update

	$users->update($user);
}

//
// Raw execute with no-casting (partial fields)
//

$records = $database->execute("SELECT first_name, last_name FROM users");

foreach ($records as $record) {
	echo PHP_EOL . $record->first_name . PHP_EOL;

	try {
		$users->insert($record);

		throw new Exception('Failed trying to handle foreign entity');
	} catch (InvalidArgumentException $e) {

	}
}

