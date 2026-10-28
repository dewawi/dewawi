<?php
	require_once __DIR__ . '/helper.php';
	ob_start();
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
	if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
		http_response_code(405);
		header('Allow: GET, HEAD, POST');
		exit;
	}
	if (empty($_SESSION['installer_csrf'])) $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['installer_csrf'], $_POST['csrf_token']))) {
		http_response_code(403);
		echo 'Invalid installation request.';
		exit;
	}
	require("config/config.php");
	
	// show current step
	$nextStep = "introduction";
	if (isset($_POST['nextStep']))
		$nextStep = $_POST['nextStep'];
	if (!is_string($nextStep) || !in_array($nextStep, ['introduction', 'license', 'requirements', 'filePermissions', 'database', 'importSQL', 'done'], true)) {
		http_response_code(400);
		echo 'Invalid installation step.';
		exit;
	}
	
	
	// define vars
	$step = $nextStep;
	$header = $config['header'];
	$product = $introduction["product"];
	
	include("templates/header.php");
	include($nextStep.".php");
	include("templates/footer.php");
	
