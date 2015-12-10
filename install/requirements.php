<?php
	$goToNextStep = true;

	// php version
	$currentPhpVersion = phpversion();
	$phpVersionOk = version_compare($currentPhpVersion, $requirements['phpVersion']) >= 0;
	if (!$phpVersionOk) $goToNextStep = false;
	
	// extensions
	$loadedExtensions = get_loaded_extensions();
	foreach ($loadedExtensions as $key => $ext) $loadedExtensions[$key] = strtolower($ext); 
	$showExtensions = array();
	
	foreach ($requirements['extensions'] as $ext)
	{
		$isLoaded = in_array($ext, $loadedExtensions);
		$showExtensions[$ext] =  $isLoaded;
		if (!$isLoaded) $goToNextStep = false;
	}
	
	// show requirements
	foreach ($requirements as $key => $value)
		$$key = $value;
		
	include("templates/requirements.php");
?>