<?php

use Drupal\Core\File\FileSystemInterface;

function sayHello()
{
	echo "Hello world\n";
}

function createDummyTablesForDev($max = 1000)
{
	// Get the default database connection.
	$database = \Drupal::database();

	$tables = [];
	for($i = 1; $i <= $max; $i++) {
		$tableName = "tmp_" . substr(uniqid(), 0, 6) . "group_" . uniqid() . uniqid();
		$tables[] = $tableName;
		$query ="create table " . $tableName . " (".uniqid() . " varchar(10))";
		$database->query($query);
	}

	return storeDrupcleanReport($tables);
}

function locateNukeableTables() 
{
	$nukeables = [];
	
	// Get the default database connection.
	$database = \Drupal::database();

	// Get the connection options.
	$connection_options = $database->getConnectionOptions();

	// Extract the database name (schema).
	$database_name = $connection_options['database'];

	$query = "SELECT table_name 
	FROM information_schema.tables 
	WHERE table_schema = '{$database_name}' 	
	AND (table_name RLIKE '^tmp_.*' or table_name RLIKE '^old_.*');
	";

	$result = $database->query($query);

	// Iterate through the result set and output the user id and name.
	foreach ($result as $record) {
		$matches = FALSE;

		if(preg_match('/^tmp_[a-zA-Z0-9]{6}group/',$record->table_name)) {
			$matches = TRUE;
		}
		
		if(preg_match('/^old_[a-zA-Z0-9]{6}group/',$record->table_name)) {
			$matches = TRUE;
		}

		// TODO: Add other matching cases here

		if($matches) {
			$nukeables[] = $record->table_name;
		}

	}

	return $nukeables;
}

function locateAndReportNukeableTables()
{
	$nukeables = locateNukeableTables();

	return storeDrupcleanReport($nukeables);
}

function storeDrupcleanReport($list)
{
	// Load Drupal services.
	$filesystem = \Drupal::service('file_system');

	// Determine the public files directory.
	$public_files_directory = $filesystem->realpath('public://');

	// Prepare the file path.
	$filename = 'report_nukeable_' . uniqid() . '.json';
	$directory = $public_files_directory . '/drupclean/';
	$destination = $directory . $filename;
	$uri = "public://drupclean/" . $filename;
	$url = file_create_url($uri);


	// Ensure the directory exists.
	$filesystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

	// Write the file.
	file_put_contents($destination, json_encode($list));

	return [
		'list' => $list,
		'filename' => $filename,
		'destination' => $destination,
		'directory' => $directory,
		'uri' => $uri,
		'url' => $url
	];
}

function locateAndNukeNukeableTables() 
{
	$nukeables = locateNukeableTables();

	// Get the default database connection.
	$database = \Drupal::database();

	// Get the connection options.
	$connection_options = $database->getConnectionOptions();

	// Extract the database name (schema).
	$database_name = $connection_options['database'];
	
	$nuked = [];
	foreach($nukeables as $nukeable) {
		$query = "DROP TABLE `" . $database_name . "`." . $nukeable;
		echo "Would run: " . $query . PHP_EOL;
		# Unhash this when we are sure everything is working
		$database->query($query);
		$nuked[] = $nukeable;
	}

	return storeDrupcleanReport($nuked);
}
