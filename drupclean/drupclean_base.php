<?php

use Drupal\Core\File\FileSystemInterface;

function sayHello()
{
	echo "Hello world\n";
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
	AND cast(table_name as BINARY) RLIKE 'tmp_.*';
	";

	$result = $database->query($query);

	// Iterate through the result set and output the user id and name.
	foreach ($result as $record) {
		$matches = FALSE;
		if(preg_match('/^tmp_[a-zA-Z0-9]{6}group/',$record->table_name)) {
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
		$query = "DROP TABLE " . $database_name . "." . $nukeable;
		$database->query($query);
		$nuked[] = $nukeable;
	}

	return storeDrupcleanReport($nuked);
}