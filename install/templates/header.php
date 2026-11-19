<!doctype html>
<html lang="<?= ($_SESSION['installer_locale'] ?? 'en_US') === 'de_DE' ? 'de' : 'en'; ?>">
  <head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($product, ENT_QUOTES, 'UTF-8'); ?> - <?= installerText('INSTALL_TITLE'); ?></title>

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
        <?php if ($step === 'introduction'): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="nextStep" value="introduction">
            <label for="installer-locale"><?= installerText('INSTALL_LANGUAGE'); ?></label>
            <select name="installer_locale" id="installer-locale">
                <?php foreach (['de_DE' => 'Deutsch', 'en_US' => 'English'] as $code => $name): ?>
                <option value="<?= $code; ?>"<?= ($_SESSION['installer_locale'] ?? 'en_US') === $code ? ' selected' : ''; ?>><?= $name; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><?= installerText('INSTALL_APPLY_LANGUAGE'); ?></button>
        </form>
        <?php endif; ?>
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
	        <h3><?= installerText('INSTALL_STEPS'); ?></h3>
	        <ol>
				<li <?php if ($step == "introduction") echo "class='current'"; ?>><?= installerText('INSTALL_INTRODUCTION'); ?></li>
				<li <?php if ($step == "license") echo "class='current'"; ?>><?= installerText('INSTALL_LICENSE'); ?></li>
				<li <?php if ($step == "requirements") echo "class='current'"; ?>><?= installerText('INSTALL_REQUIREMENTS'); ?></li>
				<li <?php if ($step == "filePermissions") echo "class='current'"; ?>><?= installerText('INSTALL_FILE_PERMISSIONS'); ?></li>
				<li <?php if ($step == "database") echo "class='current'"; ?>><?= installerText('INSTALL_DATABASE_CONNECTION'); ?></li>
				<li <?php if ($step == "importSQL") echo "class='current'"; ?>><?= installerText('INSTALL_IMPORT_SQL'); ?></li>
				<li <?php if ($step == "done") echo "class='current'"; ?>><?= installerText('INSTALL_DONE'); ?></li>
	        </ol>
		</div>
		<div class="span-18 last">
