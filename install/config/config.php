<?php
	$config['header'] = "DEWAWI Setup Wizard";
	$config['applicationPath'] = "../";
	$config['database_file'] = "configs/database.ini";
	
	// INTRODUCTION
	$introduction = array();
	$introduction["product"] = "DEWAWI";
	$introduction["productVersion"] = "0.5";
	$introduction["company"] = "Intercom Deec GmbH";

	// SERVER REQUIREMENTS
	$requirements = array();
	$requirements["phpVersion"] = "5";
	$requirements["extensions"] = array("mysql", "pcre", "gd");

	// FILE PERMISSIONS
	// r = readable, w = writable, x = executable
	$filePermissions = array();
	$filePermissions["cache"] = "rw";
	$filePermissions["configs"] = "rw";
	$filePermissions["session"] = "rw";
	$filePermissions["files"] = "rw";
