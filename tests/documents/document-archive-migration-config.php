<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Including the operator tool must not parse CLI arguments or open a database.
require __DIR__ . '/document-archive-migration.php';
$root = dirname(__DIR__, 2);
$path = tempnam(sys_get_temp_dir(), 'dewawi-archive-config-');
$checks = 0;
$expected = ['host' => 'db.invalid', 'username' => 'audit_user', 'password' => 'fixture_password', 'dbname' => 'audit_fixture', 'port' => 3306];

function migrationConfigCheck(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

function migrationConfigFixture(array $values, string $quote = "'", string $section = "[production]\n"): string
{
	$ini = $section;
	foreach ($values as $key => $value) $ini .= 'resources.db.params.' . $key . ' = ' . $quote . $value . $quote . "\n";
	return $ini;
}

function migrationConfigReject(string $path, string $ini): void
{
	file_put_contents($path, $ini);
	try {
		migrationConfig($path, 'production');
	} catch (RuntimeException $exception) {
		migrationConfigCheck(in_array($exception->getMessage(), ['Database configuration could not be read', 'Database configuration is incomplete', 'Database configuration port is invalid'], true), 'Configuration error exposed unexpected details');
		return;
	}
	throw new RuntimeException('Invalid database configuration was accepted');
}

try {
	foreach (['configs/database.sample.ini', 'install/config/database_template.ini'] as $template) {
		$ini = strtr(file_get_contents($root . '/' . $template), [
			'%%host%%' => 'db.invalid', '%%username%%' => 'audit_user', '%%password%%' => 'fixture_password', '%%database%%' => 'audit_fixture',
			'localhost' => 'db.invalid', '= username' => '= audit_user', '= password' => '= fixture_password', '= dewawi' => '= audit_fixture',
		]);
		file_put_contents($path, $ini);
		migrationConfigCheck(migrationConfig($path, 'production') === $expected, 'Repository database template was not parsed correctly');
	}
	foreach (["'", '"', ''] as $quote) {
		foreach (['off', 'on', 'null', 'true', 'false', 'none', 'yes', 'no', '001234', '1e10', '123456789012345678901234567890', 'PHP_VERSION'] as $literal) {
			$values = array_fill_keys(['host', 'username', 'password', 'dbname'], $literal) + ['port' => '03307'];
			file_put_contents($path, migrationConfigFixture($values, $quote));
			migrationConfigCheck(migrationConfig($path, 'production') === array_replace($values, ['port' => 3307]), 'Literal credentials or quoted port changed');
		}
	}
	foreach ([
		["'", ''], ["'", ' a ; # = b '], ["'", 'a";b'], ["'", '"'],
		["'", 'C:\\fixture\\password'], ["'", '${PATH}'],
		['"', "can't"], ['"', "'quoted'"], ['"', '${PATH}'], ['"', 'C:\\fixture\\password'],
		['', 'fixture-_.:/?@#'],
	] as [$quote, $password]) {
		$values = array_diff_key($expected, ['port' => true]);
		$values['password'] = $password;
		file_put_contents($path, migrationConfigFixture($values, $quote));
		migrationConfigCheck(migrationConfig($path, 'production') === array_replace($expected, ['password' => $password]), 'Quoted credential bytes changed');
	}
	$ini = migrationConfigFixture($expected);
	file_put_contents($path, str_replace("\n", "\r\n", $ini));
	migrationConfigCheck(migrationConfig($path, 'production') === $expected, 'CRLF configuration changed credentials');
	file_put_contents($path, str_replace("'\n", "' ; fixture comment\n", $ini));
	migrationConfigCheck(migrationConfig($path, 'production') === $expected, 'Trailing INI comments changed credentials');
	file_put_contents($path, migrationConfigFixture($expected, "'", ''));
	migrationConfigCheck(migrationConfig($path, 'production') === $expected, 'Flat configuration stopped working');
	file_put_contents($path, $ini . str_replace('[production]', '[audit]', str_replace('db.invalid', 'audit.invalid', $ini)));
	migrationConfigCheck(migrationConfig($path, 'audit')['host'] === 'audit.invalid', 'Explicit environment was ignored');
	file_put_contents($path, migrationConfigFixture($expected, '"'));
	migrationConfigCheck(migrationConfig($path, 'production') === $expected, 'Double-quoted configuration changed');

	foreach (['', "[production\n", "[production]\nresources.db.params.password = 'unterminated", "[production]\nresources.db.params.password = \"unterminated", str_replace('[production]', '[other]', $ini)] as $invalid) migrationConfigReject($path, $invalid);
	foreach (['host', 'username', 'password', 'dbname', 'port'] as $key) {
		migrationConfigReject($path, str_replace('resources.db.params.' . $key . ' =', 'resources.db.params.' . $key . '[] =', $ini));
		if ($key !== 'port') migrationConfigReject($path, migrationConfigFixture(array_diff_key($expected, [$key => true])));
	}
	foreach (['', '0', '-1', '65536', '3306suffix', '3306.0', 'off', 'null', '9999999999999999999999999'] as $port) migrationConfigReject($path, migrationConfigFixture(array_replace($expected, ['port' => $port])));
	file_put_contents($path, migrationConfigFixture(array_replace($expected, ['port' => '65535'])));
	migrationConfigCheck(migrationConfig($path, 'production')['port'] === 65535, 'Valid upper port boundary rejected');
	unlink($path);
	try {
		migrationConfig($path, 'production');
		throw new LogicException('Missing configuration file was accepted');
	} catch (RuntimeException $exception) {
		migrationConfigCheck($exception->getMessage() === 'Database configuration could not be read', 'Unexpected missing-file error');
	}
	echo 'PASS: ' . $checks . " migration configuration checks; synthetic credentials only, no database connection\n";
} finally {
	if (is_file($path)) unlink($path);
}
