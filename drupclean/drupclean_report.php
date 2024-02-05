<?php

include("drupclean_base.php");

$results = locateAndAnalyseTables();

//print_r($results);

echo "Noteables found: ". count($results['list']['noteables']['tables']) . PHP_EOL;
echo "Nukeables found: ". count($results['list']['nukeables']) . PHP_EOL;
echo "Report File: " . $results['destination'] . PHP_EOL;
echo "Report URL: " . $results['url'] . PHP_EOL;

echo PHP_EOL;
echo "Noteable Stats:";
print_r($results['list']['noteables']['stats']);
echo PHP_EOL;