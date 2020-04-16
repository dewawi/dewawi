<?php
	// Define path to base directory
	defined('BASE_PATH')
		|| define('BASE_PATH', realpath(dirname(__FILE__)));

	// Check if a configuration file already exists.
	if(file_exists(BASE_PATH . '/../configs/database.ini') && (filesize(BASE_PATH . '/../configs/database.ini') > 10)) {
		//header('Location: ../');
		echo 'A configuration file is already exists. Exiting...';
        header("Location: ../");
		exit;
	}

	session_start();
	require("config/config.php");
	
	// show current step
	$nextStep = "introduction";
	if (isset($_POST['nextStep']))
		$nextStep = $_POST['nextStep'];
	
	
	// define vars
	$step = $nextStep;
	$header = $config['header'];
	$product = $introduction["product"];
	
	include("templates/header.php");
	include($nextStep.".php");
	include("templates/footer.php");
	
