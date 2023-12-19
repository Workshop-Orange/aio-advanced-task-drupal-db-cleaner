#!/bin/bash

connectuser=drupal9
connectpass=drupal9
connecthost=database
connectdb=drupal9

echo "****************************************************************"
echo "Small Script to clean up old _tmp tables on Drupal Databases"
echo "****************************************************************"
echo "================================================================"
echo ""
echo ""
echo "Enter operation to perform?"
echo "[1] Run Report"
echo "[2] Delete Tables"
read op

case $op in

1) echo "RUNNING REPORT.."
mysql -u $connectuser -p$connectpass -h $connecthost -e "SELECT count(table_name) FROM information_schema.tables WHERE table_type = 'base table' AND table_schema='$connectdb' AND table_name LIKE 'tmp\_%'"
echo "This is the number of Drupal database tables that start with _tmp"
echo "Do you want to export a summary to a text file? (Y/N)?"
read savefile

case $savefile in

Y) timestamp=$(date +%Y%m%d%H%M%S)
echo "saving file report_$timestamp.txt"
mysql -u $connectuser -p$connectpass -h "$connecthost" -D $connectdb -e "SHOW TABLES LIKE 'tmp\_%';" >report_$timestamp.txt
echo "file was saved.";;

N) echo "report was not exported.";;

*) echo "Invalid Answer.";;

esac

;;

2) echo "RUNNING REPORT.."
mysql -u $connectuser -p$connectpass -h "$connecthost" -e "SELECT count(table_name) FROM information_schema.tables WHERE table_type = 'base table' AND table_schema='$connectdb' AND table_name LIKE 'tmp\_%'"
echo "This is the number of Drupal database tables that start with _tmp, that will be dropped"
echo "Are you sure that you want to delete them? (Y/N)?"
read deletetables

case $deletetables in

Y) 
timestamp=$(date +%Y%m%d%H%M%S)
mysql -u $connectuser -p$connectpass -h "$connecthost" -D $connectdb -e "SHOW TABLES LIKE 'tmp\_%';" >report_deleted_tables_$timestamp.txt
mysql -u $connectuser -p$connectpass -h "$connecthost" -e "set group_concat_max_len = 999999999999999; set @schema = '$connectdb'; set @string = 'tmp%'; SELECT CONCAT ('DROP TABLE ',GROUP_CONCAT(CONCAT(@schema,'.',table_name)),';') INTO @droptool FROM information_schema.tables WHERE TABLE_SCHEMA = '$connectdb' AND table_name LIKE @string; SELECT @droptool; PREPARE stmt FROM @droptool; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
echo "All 'tmp_' tables were deleted"
mysql -u $connectuser -p$connectpass -h "$connecthost" -e "SELECT count(table_name) FROM information_schema.tables WHERE table_type = 'base table' AND table_schema='$connectdb' AND table_name LIKE 'tmp\_%'"
;;

N) echo "Tables were NOT deleted";;

*) echo "Invalid Answer. Tables were NOT deleted";;

esac

;;

*) echo "Invalid Operation.";;

esac




