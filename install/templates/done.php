<h3><?= installerText('INSTALL_DONE'); ?></h3>

<p><?= installerText('INSTALL_FINISHED'); ?> <b><?= installerText('INSTALL_DELETE_INSTALL'); ?></b></p>

<hr>

<?php echo $furtherInstructions; ?>

<hr>
<p><a href="<?php echo $config['applicationPath']; ?>"><b><big><?= installerText('INSTALL_LOGIN'); ?></big></b></a></p>
