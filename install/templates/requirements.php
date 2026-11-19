<h3><?= installerText('INSTALL_REQUIREMENTS'); ?></h3>

<?php if (!$goToNextStep) { ?>
	<div class="error"><?= installerText('INSTALL_PHP_SETTINGS'); ?></div>
<?php } ?>

<h4><?= installerText('INSTALL_PHP_VERSION'); ?></h4>

<table>
	<thead>
		<tr>
			<th><?= installerText('INSTALL_NAME'); ?></th>
			<th><?= installerText('INSTALL_VERSION'); ?></th>
			<th><?= installerText('INSTALL_STATUS'); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><?= installerText('INSTALL_REQUIRED'); ?></td>
			<td><?php echo $phpVersion; ?></td>
			<td></td>
		</tr>
		<tr>
			<td><?= installerText('INSTALL_INSTALLED_VERSION'); ?></td>
			<td><?php echo $currentPhpVersion; ?></td>
			<td><?php if ($phpVersionOk) { ?> <img src="img/icons/accept.png"> OK <?php } else { ?> <img src="img/icons/cancel.png"> <?= installerText('INSTALL_PHP_OLD'); ?><?php } ?></td>
		</tr>
	</tbody>
</table>
<hr>

<h4><?= installerText('INSTALL_PHP_EXTENSIONS'); ?></h4>

<table>
	<thead>
		<tr>
			<th><?= installerText('INSTALL_NAME'); ?></th>
			<th><?= installerText('INSTALL_STATUS'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($showExtensions as $extension => $status): ?>
		<tr>
			<td><?php echo $extension; ?></td>
			<td><?php if ($status) { ?> <img src="img/icons/accept.png"> OK <?php } else { ?> <img src="img/icons/cancel.png"> <?= installerText('INSTALL_NOT_INSTALLED'); ?><?php } ?> </td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<hr>


<a href="index.php" class="button negative">
	<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> <?= installerText('INSTALL_CANCEL'); ?>
</a>

<?php if ($goToNextStep) { ?>
	<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="nextStep" value="filePermissions">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_NEXT'); ?>
		</button>
	</form>
<?php } else { ?>
	<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="nextStep" value="requirements">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_RETRY'); ?>
		</button>
	</form>
<?php } ?>