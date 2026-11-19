<?php
	require_once __DIR__ . '/helper.php';

	$goToNextStep = true;
	
	clearstatcache();
	
	$showPermissions = array();
	foreach ($filePermissions as $key => $value)
	{
		$error = "";
		$values = str_split($value);
		$file = getRealpath(dirname(getenv('SCRIPT_FILENAME'))."/".$config['applicationPath'].$key);
		
		if (file_exists($file))
		{
			foreach ($values as $char)
			{
				switch ($char)
				{
					case "r": if (!is_readable($file)) $error = installerTranslate('INSTALL_NOT_READABLE'); break;
					case "w": if (!is_writable($file)) $error = installerTranslate('INSTALL_NOT_WRITABLE'); break;
					// funzt bei manchen servern nicht richtig...
					// case "x": if (!is_executable($file)) $error = "Not executeable"; break;
				}
			}
		}
		else
			$error = installerTranslate('INSTALL_FILE_MISSING');
		
		// combine string for user easy reading
		$showRequired = array();
		foreach ($values as $char)
		{
			switch ($char)
			{
				case "r": $showRequired[] = installerTranslate('INSTALL_READ'); break;
				case "w": $showRequired[] = installerTranslate('INSTALL_WRITE'); break;
				case "x": $showRequired[] = installerTranslate('INSTALL_EXECUTE'); break;
			}
		}
		
		$showPermissions[$key] = array("required" => $value, "error" => $error, "showRequired" => implode(", ", $showRequired), "realpath" => $file);	
		
		if ($error != "") $goToNextStep = false;
	}	
		
	include("templates/filePermissions.php");
