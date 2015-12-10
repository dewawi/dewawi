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

// Installation check, and check on removal of the install directory.
if(!file_exists(BASE_PATH . '/configs/database.ini') || (filesize(BASE_PATH . '/configs/database.ini') < 10)) {
	if(file_exists(BASE_PATH . '/install/index.php')) {
		//header('Location: ' . substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], 'index.php')) . 'install/index.php');
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

/** Zend_Application **/
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
	APPLICATION_ENV,
	array(
		'config' => array(
			BASE_PATH . '/configs/application.ini',
			BASE_PATH . '/configs/database.ini'
		),
	)
);

$application->bootstrap()
		->run();
