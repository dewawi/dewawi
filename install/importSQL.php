<?php

	include("helper.php");
	$errors = array();
	$goToNextStep = false;

	$host = $_SESSION['db_host'];
	$username = $_SESSION['db_user'];
	$password = $_SESSION['db_pass'];
	$database = $_SESSION['db_name'];

	// connect to db
	$con = mysqli_connect($host, $username, $password);
	mysqli_set_charset($con, "UTF8");
	mysqli_select_db($con, $database);
	
	// read structure sql
	$structure = file_get_contents("config/structure.sql");
	
	$queries = array();
	PMA_splitSqlFile($queries, $structure);
	
	foreach ($queries as $query)
	{
		//print_r($query['query']);
		if (!mysqli_query($con, $query['query']))
		{
			$errors[] = "<b>".mysqli_error()."</b><br>(".substr($query['query'], 0, 200)."...)";
		}
	}
	
	// read data sql
	$data = file_get_contents("config/data.sql");

	$queries = array();
	PMA_splitSqlFile($queries, $data);
	
	foreach ($queries as $query)
	{
		if (!mysqli_query($con, $query['query']))
		{
			$errors[] = "<b>".mysqli_error()."</b><br>(".substr($query['query'], 0, 200)."...)";
		}
	}
   
	// close connection
	mysqli_close($con);
	
	// show error
	include("templates/importSQL.php");
