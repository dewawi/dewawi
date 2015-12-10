<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo $product; ?> - PHP Installer - <?php echo $step; ?></title>

    <!-- Framework CSS -->
    <link rel="stylesheet" href="css/blueprint/screen.css" type="text/css" media="screen, projection">
    <link rel="stylesheet" href="css/blueprint/print.css" type="text/css" media="print">
    <!--[if lt IE 8]><link rel="stylesheet" href="css/blueprint/ie.css" type="text/css" media="screen, projection"><![endif]-->
    <!-- Import fancy-type plugin for the sample page. -->
    <link rel="stylesheet" href="css/blueprint/plugins/fancy-type/screen.css" type="text/css" media="screen, projection">
	<link rel="stylesheet" href="css/blueprint/plugins/buttons/screen.css" type="text/css" media="screen, projection">
	
	<link rel="stylesheet" href="css/styles.css" type="text/css" media="screen, projection">
  </head>
  <body>
    <div class="container">
    	<div class="span-24">
    		<div class="span-17">
				<h1><?php echo $header; ?></h1>
			</div>
			<div class="span-7 last" style="text-align: right;">
				<img src="img/logo.png">
			</div>
		</div>
		<hr>

		<div class="span-5 colborder">
	        <h3>Installation steps</h3>
	        <ol>
	        	<li <?php if ($step == "introduction") echo "class='current'"; ?>>Introduction</li>
				<li <?php if ($step == "license") echo "class='current'"; ?>>License</li>
				<li <?php if ($step == "requirements") echo "class='current'"; ?>>Server requirements</li>
				<li <?php if ($step == "filePermissions") echo "class='current'"; ?>>File permissions</li>
				<li <?php if ($step == "database") echo "class='current'"; ?>>Database connection</li>
				<li <?php if ($step == "importSQL") echo "class='current'"; ?>>Import SQL</li>
				<li <?php if ($step == "done") echo "class='current'"; ?>>Done</li>
	        </ol>
		</div>
		<div class="span-18 last">
