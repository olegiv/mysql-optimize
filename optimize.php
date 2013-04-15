<?php

// =============================================
// ===	MySQL DB Tables Optimizer
// ===	12.04.2013 --- 15.04.2013
// ===	Version 0.0.0
// =============================================

define ('VERSION', '0.0.0');
define ('DATE', '2013-04-15');

define ('NEWLINE', "\n");

error_reporting (E_ERROR);

if (php_sapi_name () !== 'cli') {
	die ('Error: This script should be executed in CLI mode only.');
}

$options = getopt ('d::h::p::u::', array ('database::', 'help::', 'password::', 'username::'));

if (isset ($options['help']) || isset ($options['h'])) {
	print_syntax ();
}

$database = $options['database'];

if ($options['username']) {
	$username = $options['username'];
} else {
	$username_array = array ();
	exec ('whoami', &$username_array);
	$username = $username_array[0];
}
if ($options['password']) {
	$password = $options['password'];
} else {
	$password = get_password ('Enter password for user ' . $username . ': ');
}

echo 'Using username: ' . $username . NEWLINE;

$DB = new mysqli ('localhost', $username, $password);
if ($DB->connect_errno) {
	die ('Error while connecting to MySQL: ' . $DB->connect_error . NEWLINE);
}

$allDbs = array();
if (! $database) {
	$results = $DB->query ('show databases');
	while ($row = $results->fetch_array(MYSQLI_NUM)) {
		$allDbs[] = $row[0];
	}
	$results->close();
} else {
	$allDbs[0] = $database;
}

$count_free_before = $count_free_after = 0;
foreach ($allDbs as $dbName) {
	if ($dbName != 'information_schema' && $dbName != 'mysql') {
		$DB->select_db ($dbName);
		$results = $DB->query ('SHOW TABLE STATUS');
		if ($results->num_rows > 0) {
			$count_free_before_set = $DB->query ('select sum(data_free) as sm from information_schema.tables where table_schema = "' . $dbName . '"');
			$row = $count_free_before_set->fetch_assoc ();
			$count_free_before += $row['sm'];
			echo 'Optimizing DB: ' . $dbName . NEWLINE;
			while ($row = $results->fetch_assoc ()) {
				$DB->query ('optimize table ' . $row['Name']);
				echo 'Table optimized: ' . $row['Name'] . NEWLINE;
			}
			$count_free_after_set = $DB->query ('select sum(data_free) as sm from information_schema.tables where table_schema = "' . $dbName . '"');
			$row = $count_free_after_set->fetch_assoc ();
			$count_free_after += $row['sm'];
			$results->close ();
		}
	}
}
$DB->close ();

echo 'Optimization done, saved ' . display_filesize ($count_free_before - $count_free_after) . '.' . NEWLINE;

/**
 * 
 */
function print_syntax () {
	
	die ('Syntax: php optimize.php [--database=database_name] [--username=username] [--password=password]' . NEWLINE);
	
}

/**
 * 
 * @param string $prompt
 * @return string
 */
function get_password ($prompt) {
	
	$command = "/usr/bin/env bash -c 'echo OK'";
	if (rtrim(shell_exec($command)) !== 'OK') {
		die ('Can\'t invoke bash' . NEWLINE);
	}
	$command = "/usr/bin/env bash -c 'read -s -p \"" . addslashes ($prompt) . "\" mypassword && echo \$mypassword'";
	$password = rtrim (shell_exec ($command));
  echo NEWLINE;
	return $password;
}

/**
 * Returns human-friendly filesize information.
 * 
 * @param float $filesize
 * @return string 
 */
function display_filesize ($filesize) {

	if (is_numeric ($filesize)) {
		$decr = 1024;
		$step = 0;
		$prefix = array ('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
		while (($filesize / $decr) > 0.9) {
			$filesize = $filesize / $decr;
			$step ++;
		}
		return round ($filesize, 2) . ' ' . $prefix[$step];
	} else {
		return 'NaN';
	}
	
}

?>