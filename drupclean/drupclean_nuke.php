<?php

include("drupclean_base.php");

$results = locateAndNukeNukeableTables();

echo "Nukables nuked: ". count($results['list']) . PHP_EOL;
echo "Report File: " . $results['destination'] . PHP_EOL;
echo "Report URL: " . $results['url'] . PHP_EOL;
