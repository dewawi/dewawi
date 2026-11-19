<h3><?= installerText('INSTALL_INTRODUCTION'); ?></h3>

<p><?= installerText('INSTALL_INSTALL_PRODUCT'); ?> <b><?php  echo $product; ?></b> (<?= installerText('INSTALL_VERSION'); ?>: <?php echo $productVersion; ?>) <?= installerText('INSTALL_DEVELOPED_BY'); ?> <b><?php echo $company; ?></b>.</p>

<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
	<input type="hidden" name="nextStep" value="license">
	<button type="submit" class="button positive">
		<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_START'); ?>
	</button>
</form>
