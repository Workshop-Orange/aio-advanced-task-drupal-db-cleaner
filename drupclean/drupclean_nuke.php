<?php

include("drupclean_base.php");

$results = locateAndNukeNukeableTables();

echo "==================================================================" . PHP_EOL;
echo "* Nukeable tables are the tables which match the _tmp or _old pattern" . PHP_EOL;
echo "* and have be automatically nuked by this Drupclean Nuke job." . PHP_EOL;
echo "==================================================================" . PHP_EOL;
echo "Nukeables nuked: ". count($results['list'] ?? []) . PHP_EOL;
echo PHP_EOL;


echo "Report File: " . $results['destination'] . PHP_EOL;
echo "Report URL: " . $results['url'] . PHP_EOL;
