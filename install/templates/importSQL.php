<h3><?= installerText('INSTALL_IMPORTING_SQL'); ?></h3>

<p><?= installerText('INSTALL_IMPORT_RUNNING'); ?></p>
<hr>

<?php if (count($errors) > 0) { ?>
	<div class="error"><?= installerText('INSTALL_IMPORT_FAILED'); ?></div>
	
	<ul>
		<?php foreach ($errors as $error): ?>
			<li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
		<?php endforeach; ?>
	</ul>
<?php } else { ?>
	<div class="success"><?= installerText('INSTALL_IMPORT_SUCCESS'); ?></div>
<?php } ?>

<hr>

<a href="index.php" class="button negative">
	<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> <?= installerText('INSTALL_CANCEL'); ?>
</a>

<?php if (count($errors) == 0) { ?>
	<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="nextStep" value="done">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_NEXT'); ?>
		</button>
	</form>
<?php } else { ?>
	<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="nextStep" value="importSQL">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_RETRY'); ?>
		</button>
	</form>
<?php } ?>