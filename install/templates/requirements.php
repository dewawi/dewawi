<h3>Server requirements</h3>

<?php if (!$goToNextStep) { ?>
	<div class="error">Contact your webserver support (hosting service) to get the necessary PHP settings fixed and refresh this site!</div>
<?php } ?>

<h4>PHP Version</h4>

<table>
	<thead>
		<tr>
			<th>Name</th>
			<th>Version</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>Required</td>
			<td><?php echo $phpVersion; ?></td>
			<td></td>
		</tr>
		<tr>
			<td>You have</td>
			<td><?php echo $currentPhpVersion; ?></td>
			<td><?php if ($phpVersionOk) { ?> <img src="img/icons/accept.png"> OK <?php } else { ?> <img src="img/icons/cancel.png"> Below requirement! Please update your PHP installation.<?php } ?></td>
		</tr>
	</tbody>
</table>
<hr>

<h4>PHP Extensions</h4>

<table>
	<thead>
		<tr>
			<th>Name</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($showExtensions as $extension => $status): ?>
		<tr>
			<td><?php echo $extension; ?></td>
			<td><?php if ($status) { ?> <img src="img/icons/accept.png"> OK <?php } else { ?> <img src="img/icons/cancel.png"> Not installed!<?php } ?> </td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<hr>


<a href="index.php" class="button negative">
	<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> Cancel
</a>

<?php if ($goToNextStep) { ?>
	<form method="post">
		<input type="hidden" name="nextStep" value="filePermissions">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> Next
		</button>
	</form>
<?php } else { ?>
	<form method="post">
		<input type="hidden" name="nextStep" value="requirements">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> Retry
		</button>
	</form>
<?php } ?>