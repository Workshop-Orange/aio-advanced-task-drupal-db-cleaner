<?php

include("drupclean_base.php");

$results = locateAndAnalyseTables();

print_r($results);

echo "Noteables found: ". count($results['list']['noteables']) . PHP_EOL;
echo "Nukables found: ". count($results['list']['nukeables']) . PHP_EOL;
echo "Report File: " . $results['destination'] . PHP_EOL;
echo "Report URL: " . $results['url'] . PHP_EOL;
