<h3>Database connection</h3>

<p>
	We need some information on the database. In all likelihood, these items were supplied to you by your Web Host. If you do not have this information, then you will need to contact them before you can continue.<br><br>
	Below you should enter your database connection details.
</p>
<hr>

<?php if ($error) { ?>
	<div class="error">
		<b>Error establishing a database connection: <?php echo $error; ?></b><br><br>
		This either means that the username and password information is incorrect or we can't contact the database server at <?php echo $host; ?>. Maybe your host's database server is down.<br><br>
		
		<ul>
			<li>Are you sure you have the correct username and password?</li>
    		<li>Are you sure that you have typed the correct hostname?</li>
    		<li>Are you sure that the database server is running?</li>
		</ul>
		
		If you're unsure what these terms mean you should probably contact your host. 
	</div>
<?php } ?>

<form method="post">
	<p>
		<label>Database name </label> (The name of the database you want to run this script in)<br>
		<input class="title" type="text" name="database" value="<?php echo $database; ?>">
	</p>
	<p>
		<label>Username</label> (Your MySQL username)<br>
		<input class="title" type="text" name="username" value="<?php echo $username; ?>">
	</p>
	<p>
		<label>Password</label> (...and MySQL password)<br>
		<input class="title" type="password" name="password" value="<?php echo $password; ?>">
	</p>
	<p>
		<label>Host</label> (You should be able to get this info from your web host, if "localhost" does not work.)<br>
		<input class="title" type="text" name="host" value="<?php echo $host; ?>">
	</p>
	
	<hr>
	
	<?php if ($goToNextStep) { ?>
		<div class="success">Everything is ok! Go to next step...</div>

		<div class="error">
			<big><b>WARNING: existing tables in database <?php echo $database; ?> will be deleted!</b></big>
		</div>

		<a href="index.php" class="button negative">
			<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> Cancel
		</a>		
		
		<input type="hidden" name="nextStep" value="importSQL">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> Next
		</button>
	<?php } else { ?>
		<a href="index.php" class="button negative">
			<img src="css/blueprint/plugins/buttons/icons/cross.png" alt=""/> Cancel
		</a>
		
		<input type="hidden" name="nextStep" value="database">
		<button type="submit" class="button positive">
			<img src="css/blueprint/plugins/buttons/icons/tick.png" alt=""/> Test connection
		</button>
	<?php } ?>
</form>
