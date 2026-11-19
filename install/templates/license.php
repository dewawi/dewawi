<h3><?= installerText('INSTALL_LICENSE'); ?></h3>

<div class="info"><?= installerText('INSTALL_ACCEPT_LICENSE_REQUIRED'); ?></div>

<textarea style="height: 300px; width: 98%;"><?php echo $license; ?></textarea>
<hr>

<a href="index.php" class="button negative">
	<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> <?= installerText('INSTALL_CANCEL'); ?>
</a>

<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
	<input type="hidden" name="nextStep" value="requirements">
	<button type="submit" class="button positive">
		<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_ACCEPT_LICENSE'); ?>
	</button>
</form>
