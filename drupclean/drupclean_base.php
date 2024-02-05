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

	$otherTables = [
		'paragraph_r__f6c93ae772',
		'paragraph_r__f6da0dc6f2',
		'paragraph_r__f75114578a',
		'paragraph_r__f759fb301b',
		'paragraph_r__f76947fd67',
		'paragraph_r__f7738da62a',
		'paragraph_r__f7aa26ca12',
		'paragraph_r__f7d95383d5',
		'paragraph_r__f7dd5b529c',
		'paragraph_r__f7e8414e9e',
		'block_content_r__b57c2a1fdf',
		'block_content_r__b57c47c5b7',
		'block_content_r__b5867ded05',
		'block_content_r__b58d705294',
		'block_content_r__b59f7c3a54',
		'block_content_r__b5abd9c1b7',
		'block_content_r__b5bcc67e66',
		'block_content_r__b5bd43332f',
		'block_content_r__b5d0e97e2c',
		'block_content_r__b5d5bab89d',
		'menu_link_content_r__7cbfe1d667',
		'menu_link_content_r__8519f06c44',
		'menu_link_content_r__89f01ba4bf',
		'menu_link_content_r__8ade14ef73',
		'menu_link_content_r__926940d04e',
		'menu_link_content_r__93828ed68e'
	];

	foreach($otherTables as $table) {
		$database->query("create table " . $table . " (id varchar(10))");
	}

	$database->query("insert into menu_link_content_r__7cbfe1d667 (id) values (123)");

	return storeDrupcleanReport($tables, 'devgen');
}

function getDatabaseAnalysis() 
{
	$nukeables = locateNukeableTables();
	$noteables = [];
	
	// Get the default database connection.
	$database = \Drupal::database();

	// Get the connection options.
	$connection_options = $database->getConnectionOptions();

	// Extract the database name (schema).
	$database_name = $connection_options['database'];

	$query = "SELECT table_name 
	FROM information_schema.tables 
	WHERE table_schema = '{$database_name}' 	
	AND (table_name RLIKE '\_r\_\_' or table_name RLIKE '\_revision\_\_');
	";

	$result = $database->query($query);
	$entity_type_manager = \Drupal::entityTypeManager();
	$entity_type_bundle_info = \Drupal::service('entity_type.bundle.info');

	// Iterate through the result set and output the user id and name.
	foreach ($result as $record) {
		$noteable = FALSE;

		if(preg_match('/^tmp_[a-zA-Z0-9]{6}group/',$record->table_name)) {
			continue;
		}
		
		if(preg_match('/^old_[a-zA-Z0-9]{6}group/',$record->table_name)) {
			continue;
		}

		$entity_type = "";
		if(preg_match('/^(.*)_r__[a-zA-Z0-9]{10}$/',$record->table_name, $entity_type) || preg_match('/^(.*)_revision__[a-zA-Z0-9]{10}$/',$record->table_name, $entity_type)) {
			$noteable = "revision";
			$type = "entity";
			$subtype = $entity_type[1];
		}


		if($noteable) {
			$subQueryRows = "select count(*) from " . $record->table_name;
			$subQuerySize = "SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) AS `size` FROM information_schema.tables where TABLE_NAME='".$record->table_name."'";
			
			$noteables[$record->table_name][$noteable] = [
				'type' => $type,
				'subtype' => $subtype,
				//'rows' => $database->query($subQueryRows)->fetchField(),
				//'size_mb' => $database->query($subQuerySize)->fetchField(),
			];

			if($type == 'entity') {
			     if($entity_type_manager->hasDefinition($subtype)) {
				$table_mapping = $entity_type_manager->getStorage($subtype)->getTableMapping();
				$table_names = $table_mapping->getDedicatedTableNames();
			        $noteables[$record->table_name][$noteable]['entity_table_mapped'] = in_array($record->table_name, $table_names);
			     } else {
			        $noteables[$record->table_name][$noteable]['entity_table_mapped'] = '__not_an_entity__';
			     }
			}


		}

	}

	return ['nukeables' => $nukeables, 'noteables' => $noteables];
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

	return storeDrupcleanReport($nukeables, 'nukeable');
}

function locateAndAnalyseTables() 
{
	return storeDrupcleanReport(getDatabaseAnalysis(),'analysis');
}

function storeDrupcleanReport($list, $type='unknown')
{
	// Load Drupal services.
	$filesystem = \Drupal::service('file_system');

	// Determine the public files directory.
	$public_files_directory = $filesystem->realpath('public://');

	// Prepare the file path.
	$filename = 'report_'.$type.'_' . uniqid() . '.json';
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
		echo "Dryrun Mode - Would run: " . $query . PHP_EOL;
		# Unhash this when we are sure everything is working
		# $database->query($query);
		$nuked[] = $nukeable;
	}

	return storeDrupcleanReport($nuked, 'nuked');
}
