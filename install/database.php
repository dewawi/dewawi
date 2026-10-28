
<?php

require_once __DIR__ . '/helper.php';

$error = false;
$goToNextStep = false;

$database = isset($_SESSION['db_name']) ? $_SESSION['db_name'] : '';
$username = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';
$password = '';
$host = isset($_SESSION['db_host']) ? $_SESSION['db_host'] : 'localhost';

if (isset($_POST['database'])) {
	$database = trim((string) $_POST['database']);
	$username = trim((string) ($_POST['username'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$host = trim((string) ($_POST['host'] ?? ''));

	if ($database === '' || $username === '' || $host === '' || $password === '') {
		$error = 'Please fill in all database connection fields.';
	} else {
		try {
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

			$connection = new mysqli(
				$host,
				$username,
				$password,
				$database
			);

			$connection->close();

			// Save valid connection details
			$_SESSION['db_host'] = $host;
			$_SESSION['db_user'] = $username;
			$_SESSION['db_pass'] = $password;
			$_SESSION['db_name'] = $database;

			$goToNextStep = true;

		} catch (mysqli_sql_exception $e) {
			error_log('DEWAWI installer: ' . (string)$e);

			switch ((int) $e->getCode()) {
				case 1044:
					$error = 'Database access denied. Please check the user permissions.';
					break;

				case 1045:
					$error = 'Invalid database username or password.';
					break;

				case 1049:
					$error = 'The specified database does not exist.';
					break;

				case 2002:
				case 2003:
					$error = 'Database server not reachable. Please check the hostname and server status.';
					break;

				default:
					$error = 'Database connection failed. Please check your settings.';
					break;
			}
		}
	}
}

include __DIR__ . '/templates/database.php';
