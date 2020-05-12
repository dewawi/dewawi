<?php
	$config['header'] = "DEWAWI Setup Wizard";
	$config['applicationPath'] = "../";
	$config['database_file'] = "configs/database.ini";
	
	// INTRODUCTION
	$introduction = array();
	$introduction["product"] = "DEWAWI";
	$introduction["productVersion"] = "0.8";
	$introduction["company"] = "DEWAWI Open Source";

	// SERVER REQUIREMENTS
	$requirements = array();
	$requirements["phpVersion"] = "5.3";
	$requirements["extensions"] = array("curl", "mysqli", "pcre", "gd", "openssl");

	// FILE PERMISSIONS
	// r = readable, w = writable, x = executable
	$filePermissions = array();
	$filePermissions["cache"] = "rw";
	$filePermissions["configs"] = "rw";
	$filePermissions["session"] = "rw";
	$filePermissions["files"] = "rw";
