<?php
/**
 * This file is part of the DEWAWI project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License v3 or later.
 *
 * @website http://www.dewawi.com
 * @license http://www.gnu.org/licenses/gpl.html
 */

// Define path to base directory
defined('BASE_PATH')
	|| define('BASE_PATH', realpath(dirname(__FILE__)));

// Define path to application directory
defined('APPLICATION_PATH')
	|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/application'));

// Define application environment
defined('APPLICATION_ENV')
	|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Apply safe defaults before loading configuration or application classes.
ini_set('display_errors', APPLICATION_ENV === 'development' ? '1' : '0');
ini_set('display_startup_errors', APPLICATION_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');

// Installation check, and check on removal of the install directory.
if(!file_exists(BASE_PATH . '/configs/database.ini') || (filesize(BASE_PATH . '/configs/database.ini') < 10)) {
	if (defined('_CRONJOB_') && _CRONJOB_) {
		error_log('DEWAWI cron: No database configuration found.');
		if (PHP_SAPI === 'cli') fwrite(STDERR, "Application error\n");
		else {
			http_response_code(500);
			header('Content-Type: text/plain; charset=UTF-8');
			echo 'Application error';
		}
		exit(1);
	}
	if(file_exists(BASE_PATH . '/install/index.php')) {
		header('Location: ' . substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'index.php')) . 'install/index.php');
		echo 'No configuration file found. Exiting...';
		exit;
	} else {
		echo 'No configuration file found and no installation code available. Exiting...';
		exit;
	}
}

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(APPLICATION_PATH . '/../library'),
	get_include_path(),
)));

$startupBufferLevel = ob_get_level();
ob_start();
try {
	require_once 'Zend/Application.php';
	require_once 'Zend/Config/Ini.php';

	// Keep existing production-only database configurations usable in development.
	$databaseConfig = new Zend_Config_Ini(BASE_PATH . '/configs/database.ini');
	$application = new Zend_Application(
		APPLICATION_ENV,
		array_merge($databaseConfig->get(APPLICATION_ENV, $databaseConfig->production)->toArray(), array(
			'config' => BASE_PATH . '/configs/application.ini'
		))
	);

	// Cronjobs initialize configuration without dispatching the web application.
	if (!defined('_CRONJOB_') || !_CRONJOB_) $application->bootstrap();
	ob_end_flush();
	if (!defined('_CRONJOB_') || !_CRONJOB_) $application->run();
} catch (Throwable $exception) {
	while (ob_get_level() > $startupBufferLevel) ob_end_clean();
	error_log((string)$exception);
	$message = 'Application error';
	if (PHP_SAPI === 'cli') {
		fwrite(STDERR, (APPLICATION_ENV === 'development' ? (string)$exception : $message) . "\n");
		exit(1);
	}
	header_remove('Content-Disposition');
	header_remove('Content-Length');
	header_remove('Location');
	http_response_code(500);
	header('Cache-Control: no-store');
	if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
		header('Content-Type: application/json; charset=UTF-8');
		$error = ['ok' => false, 'message' => $message];
		if (APPLICATION_ENV === 'development') $error['exception'] = (string)$exception;
		echo json_encode($error, JSON_INVALID_UTF8_SUBSTITUTE);
	} else {
		header('Content-Type: text/html; charset=UTF-8');
		echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>DEWAWI</title></head><body><h1>' . $message . '</h1><p>Please try again later.</p>';
		if (APPLICATION_ENV === 'development') echo '<pre>' . htmlspecialchars((string)$exception, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
		echo '</body></html>';
	}
	exit(1);
}
