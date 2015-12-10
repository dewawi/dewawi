<?php

	include("helper.php");
	$errors = array();
	$goToNextStep = false;

	$host = $_SESSION['db_host'];
	$username = $_SESSION['db_user'];
	$password = $_SESSION['db_pass'];
	$database = $_SESSION['db_name'];

	// connect to db
	$con = mysql_connect($host, $username, $password);
	mysql_set_charset("UTF8", $con);
	mysql_select_db($database, $con);
	
	// read structure sql
	$structure = file_get_contents("config/structure.sql");
	
	$queries = array();
	PMA_splitSqlFile($queries, $structure);
	
	foreach ($queries as $query)
	{
		if (!mysql_query($query['query']))
		{
			$errors[] = "<b>".mysql_error()."</b><br>(".substr($query['query'], 0, 200)."...)";
		}
	}
	
	// read data sql
	$data = file_get_contents("config/data.sql");

	$queries = array();
	PMA_splitSqlFile($queries, $data);
	
	foreach ($queries as $query)
	{
		if (!mysql_query($query['query']))
		{
			$errors[] = "<b>".mysql_error()."</b><br>(".substr($query['query'], 0, 200)."...)";
		}
	}
   
	// close connection
	mysql_close($con);
	
	// show error
	include("templates/importSQL.php");
