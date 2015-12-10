<h3>License</h3>

<div class="info">You must accept the license to continue!</div>

<textarea style="height: 300px; width: 98%;"><?php echo $license; ?></textarea>
<hr>

<a href="index.php" class="button negative">
	<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> Cancel
</a>

<form method="post">
	<input type="hidden" name="nextStep" value="requirements">
	<button type="submit" class="button positive">
		<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> I accept the license
	</button>
</form>
