<?php

	$host = $_SESSION['db_host'];
	$username = $_SESSION['db_user'];
	$password = $_SESSION['db_pass'];
	$database = $_SESSION['db_name'];

	// save settings in database config file
	// load template
	$template = file_get_contents("config/database_template.ini");
	$template = str_replace("%%host%%", $host, $template);
	$template = str_replace("%%username%%", $username, $template);
	$template = str_replace("%%password%%", $password, $template);
	$template = str_replace("%%database%%", $database, $template);
	
	// write config file
	$dbFile = dirname(getenv('SCRIPT_FILENAME'))."/".$config['applicationPath'].$config['database_file'];
	file_put_contents($dbFile, $template);

	$furtherInstructions = file_get_contents("config/done.html");
	
	include("templates/done.php");
