<?php

	$error = false;
	$goToNextStep = false;
	
	if (isset($_POST['database']))
	{
		$database = $_POST['database'];
		$username = $_POST['username'];
		$password = $_POST['password'];
		$host = $_POST['host'];
		
		// check connection
		$connection = @mysql_connect($host, $username, $password);
		if ($connection)
		{
			$error = !mysql_select_db($database, $connection);
			//@mysql_close($connection);
			
			if (!$error)
			{
				// save login in session for further use
				$_SESSION['db_host'] = $host;
				$_SESSION['db_user'] = $username;
				$_SESSION['db_pass'] = $password;
				$_SESSION['db_name'] = $database;

				// allow user to proceed
				$goToNextStep = true;
			}
			else $error = mysql_error();
		}
		else
			$error = mysql_error();
	}
	else
	{
		if (isset($_SESSION['db_host']))
		{
			$host = $_SESSION['db_host'];
			$username = $_SESSION['db_user'];
			$password = $_SESSION['db_pass'];
			$database = $_SESSION['db_name'];
		}
		else
		{
			$database = "";
			$username = "";
			$password = "";
			$host = "localhost";
		}
	}

		
	include("templates/database.php");
