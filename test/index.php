<?php

require(__DIR__ . '/../vendor/autoload.php');

$database = new Hiraeth\Turso\Database(
	new GuzzleHttp\Client(),
	'http://localhost:8090',
	'Basic YWRtaW46YWRtaW4='
);

$database->debug = TRUE;

$database->execute("
	CREATE TABLE IF NOT EXISTS users (
		id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
		first_name TEXT,
		last_name TEXT,
		email TEXT NOT NULL UNIQUE,
		age INTEGER
	)
")->throw();
