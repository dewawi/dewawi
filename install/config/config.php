<?php
	require_once dirname(__DIR__, 2) . '/library/DEEC/Version.php';
	$config['header'] = "DEWAWI Setup Wizard";
	$config['applicationPath'] = "../";
	$config['database_file'] = "configs/database.ini";

	// INTRODUCTION
	$introduction = array();
	$introduction["product"] = "DEWAWI";
	$introduction["productVersion"] = dewawiVersion() ?? installerText('INSTALL_VERSION_UNKNOWN');
	$introduction["company"] = "DEWAWI Open Source";

	// SERVER REQUIREMENTS
	$requirements = array();
	$requirements["phpVersion"] = "5.3";
	$requirements["extensions"] = array("gd", "mbstring", "mysqli", "openssl", "pcre", "iconv", "intl", "zip",  "xml");

	// FILE PERMISSIONS
	// r = readable, w = writable, x = executable
	$filePermissions = array();
	$filePermissions["cache"] = "rw";
	$filePermissions["configs"] = "rw";
	$filePermissions["files"] = "rw";
	$filePermissions["media"] = "rw";
	$filePermissions["session"] = "rw";
