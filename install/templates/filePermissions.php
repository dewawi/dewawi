<h3><?= installerText('INSTALL_FILE_PERMISSIONS'); ?></h3>

<?php if (!$goToNextStep) { ?>
	<div class="error"><?= installerText('INSTALL_PERMISSIONS_ERROR'); ?></div>
<?php } ?>

<table>
	<thead>
		<tr>
			<th><?= installerText('INSTALL_NAME'); ?></th>
			<th><?= installerText('INSTALL_REAL_PATH'); ?></th>
			<th><?= installerText('INSTALL_REQUIRED'); ?></th>
			<th><?= installerText('INSTALL_STATUS'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($showPermissions as $filename => $permissions): ?>
		<tr>
			<td><?php echo $filename; ?></td>
			<td><?php echo $permissions['realpath']; ?></td>
			<td><?php echo $permissions['showRequired']; ?></td>
			<td><?php if ($permissions['error'] == "") { ?><img src="img/icons/accept.png"> OK <?php } else { ?><img src="img/icons/cancel.png"><?php echo $permissions['error']; ?> <?php } ?></td>
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
	<input type="hidden" name="nextStep" value="database">
	<button type="submit" class="button positive">
		<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_NEXT'); ?>
	</button>
</form>
<?php } else { ?>
	<form method="post">
	<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
		<input type="hidden" name="nextStep" value="filePermissions">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> <?= installerText('INSTALL_RETRY'); ?>
		</button>
	</form>
<?php } ?>