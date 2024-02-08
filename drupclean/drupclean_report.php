<?php

include("drupclean_base.php");


$results = locateAndAnalyseTables();

echo "==================================================================" . PHP_EOL;
echo "* Nukeable tables are the tables which match the _tmp or _old pattern" . PHP_EOL;
echo "* and will be automatically nuked by the Drupclean Nuke job." . PHP_EOL;
echo "==================================================================" . PHP_EOL;
echo "Nukeables found: ". count($results['list']['nukeables']) . PHP_EOL;
echo PHP_EOL;

//print_r($results);
echo "==================================================================" . PHP_EOL;
echo "* Noteable tables are tables which do not match the tmp_ or old_" . PHP_EOL;
echo "* but are tables that the script found which may be a secondary ". PHP_EOL;
echo "* source of extraneous tables which could be investigted. " . PHP_EOL;
echo "* These tables will not be automatically nuked.. " . PHP_EOL;
echo "==================================================================" . PHP_EOL;
echo "Noteables found: ". count($results['list']['noteables']['tables']) . PHP_EOL;
echo PHP_EOL;

echo PHP_EOL;
echo "Noteable Stats:";
print_r($results['list']['noteables']['stats']);
echo PHP_EOL;

echo "Report File in storage: " . $results['destination'] . PHP_EOL;
echo "Report URL: " . $results['url'] . PHP_EOL;
echo PHP_EOL;


/** 
echo "====================================" . PHP_EOL;
echo "Below is the list of Nukeable tables found" . PHP_EOL;
echo "====================================" . PHP_EOL;
foreach($results['list']['nukeable'] as $tbl) {
    echo ' - ' . $tbl . PHP_EOL;
}

echo PHP_EOL;

echo "====================================" . PHP_EOL;
echo "Below is the list of Noteable tables found" . PHP_EOL;
echo "====================================" . PHP_EOL;
foreach($results['list']['noteables']['tables'] as $tbl) {
    echo ' - ' . $tbl . PHP_EOL;
}
*/

echo PHP_EOL;