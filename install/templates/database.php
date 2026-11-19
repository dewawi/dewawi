<h3><?= installerText('INSTALL_DATABASE_CONNECTION'); ?></h3>

<p>
	<?= installerText('INSTALL_DATABASE_INTRO'); ?><br><br>
	<?= installerText('INSTALL_DATABASE_DETAILS'); ?>
</p>
<hr>

<?php if ($error) { ?>
	<div class="error">
		<b><?= installerText('INSTALL_DATABASE_ERROR', [$error]); ?></b><br><br>
		<?= installerText('INSTALL_DATABASE_HELP', [$host]); ?><br><br>
		
		<ul>
			<li><?= installerText('INSTALL_CHECK_CREDENTIALS'); ?></li>
			<li><?= installerText('INSTALL_CHECK_HOST'); ?></li>
			<li><?= installerText('INSTALL_CHECK_SERVER'); ?></li>
		</ul>
		
		<?= installerText('INSTALL_CONTACT_HOST'); ?>
	</div>
<?php } ?>

<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
	<p>
		<label><?= installerText('INSTALL_DATABASE_NAME'); ?> </label> <?= installerText('INSTALL_DATABASE_NAME_HINT'); ?><br>
		<input class="title" type="text" name="database" value="<?php echo htmlspecialchars($database, ENT_QUOTES, 'UTF-8'); ?>">
	</p>
	<p>
		<label><?= installerText('INSTALL_USERNAME'); ?></label> <?= installerText('INSTALL_USERNAME_HINT'); ?><br>
		<input class="title" type="text" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
	</p>
	<p>
		<label><?= installerText('INSTALL_PASSWORD'); ?></label> <?= installerText('INSTALL_PASSWORD_HINT'); ?><br>
		<input class="title" type="password" name="password" value="">
	</p>
	<p>
		<label><?= installerText('INSTALL_HOST'); ?></label> <?= installerText('INSTALL_HOST_HINT'); ?><br>
		<input class="title" type="text" name="host" value="<?php echo htmlspecialchars($host, ENT_QUOTES, 'UTF-8'); ?>">
	</p>
	
	<hr>
	
	<?php if ($goToNextStep) { ?>
		<div class="success"><?= installerText('INSTALL_DATABASE_READY'); ?></div>

		<div class="error">
			<big><b><?= installerText('INSTALL_DATABASE_WARNING', [$database]); ?></b></big>
		</div>

		<a href="index.php" class="button negative">
			<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> <?= installerText('INSTALL_CANCEL'); ?>
		</a>		
		
		<input type="hidden" name="nextStep" value="importSQL">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_NEXT'); ?>
		</button>
	<?php } else { ?>
		<a href="index.php" class="button negative">
			<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> <?= installerText('INSTALL_CANCEL'); ?>
		</a>
		
		<input type="hidden" name="nextStep" value="database">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_TEST_CONNECTION'); ?>
		</button>
	<?php } ?>
</form>
