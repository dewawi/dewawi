<?php
	include("helper.php");

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
					case "r": if (!is_readable($file)) $error = "Not readable"; break;
					case "w": if (!is_writable($file)) $error = "Not writeable"; break;
					// funzt bei manchen servern nicht richtig...
					// case "x": if (!is_executable($file)) $error = "Not executeable"; break;
				}
			}
		}
		else
			$error = "File doesnt exist!";
		
		// combine string for user easy reading
		$showRequired = array();
		foreach ($values as $char)
		{
			switch ($char)
			{
				case "r": $showRequired[] = "Read"; break;
				case "w": $showRequired[] = "Write"; break;
				case "x": $showRequired[] = "Execute"; break; 
			}
		}
		
		$showPermissions[$key] = array("required" => $value, "error" => $error, "showRequired" => implode(", ", $showRequired), "realpath" => $file);	
		
		if ($error != "") $goToNextStep = false;
	}	
		
	include("templates/filePermissions.php");
