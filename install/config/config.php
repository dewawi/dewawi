<?php
	$config['header'] = "DEWAWI Setup Wizard";
	$config['applicationPath'] = "../";
	$config['database_file'] = "configs/database.ini";
	
	// INTRODUCTION
	$introduction = array();
	$introduction["product"] = "DEWAWI";
	$introduction["productVersion"] = "0.6";
	$introduction["company"] = "DEWAWI Open Source";

	// SERVER REQUIREMENTS
	$requirements = array();
	$requirements["phpVersion"] = "5";
	$requirements["extensions"] = array("mysqli", "pcre", "gd");

	// FILE PERMISSIONS
	// r = readable, w = writable, x = executable
	$filePermissions = array();
	$filePermissions["cache"] = "rw";
	$filePermissions["configs"] = "rw";
	$filePermissions["session"] = "rw";
	$filePermissions["files"] = "rw";
