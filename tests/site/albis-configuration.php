<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Reuse the ALBIS HTTP mocks; never read a developer's populated configuration file.
$configurationDirectory = sys_get_temp_dir() . '/dewawi-albis-config-' . bin2hex(random_bytes(8));
mkdir($configurationDirectory . '/configs', 0700, true);
define('BASE_PATH', $configurationDirectory);
$configurationPath = BASE_PATH . '/configs/albis.env';
$configurationLog = BASE_PATH . '/errors.log';
$previousLog = ini_get('error_log');
$environmentKeys = [];
try {
	require __DIR__ . '/albis-leasing.php';
	$checks = 0;
	ini_set('error_log', $configurationLog);
	ini_set('log_errors', '1');
	ini_set('display_errors', '1');
	$fields = ['client_id', 'client_secret', 'cache_key'];
	$prefix = static fn(int $tenant = 7, int $shop = 41, string $environment = 'test'): string => 'DEWAWI_ALBIS_' . $tenant . '_' . $shop . '_' . strtoupper($environment) . '_';
	$environment = static function(array $values, string $scope) use ($fields, &$environmentKeys): void {
		foreach ($fields as $field) {
			$key = $scope . strtoupper($field);
			$environmentKeys[$key] = true;
			putenv($key . (isset($values[$field]) ? '=' . $values[$field] : ''));
		}
	};
	$write = static function(array $scopes) use ($configurationPath): string {
		$contents = "# Isolated ALBIS fixtures, never real credentials\n; INI comment\n\n";
		foreach ($scopes as $scope => $values) {
			foreach ($values as $field => $value) $contents .= $scope . strtoupper($field) . '="' . $value . '"' . "\n";
		}
		file_put_contents($configurationPath, $contents);
		chmod($configurationPath, 0600);
		clearstatcache(true, $configurationPath);
		return $contents;
	};
	$disabled = static function(array $changes = []) use ($context, $item, $setup): void {
		$ctx = $context($changes);
		$product = array_replace($item, ['clientid' => $ctx->getClientId(), 'shopid' => $ctx->getSiteId()]);
		$http = $setup();
		check((new DEEC_Albis($ctx, new AlbisTestCache(), $http))->quote($product) === null && !$http->requests, 'Invalid or incomplete configuration contacted ALBIS');
	};
	$selected = static function(array $expected, array $changes = [], ?AlbisTestCache $cache = null, int $requests = 5) use ($context, $item, $setup): AlbisTestHttp {
		$ctx = $context($changes);
		$product = array_replace($item, ['clientid' => $ctx->getClientId(), 'shopid' => $ctx->getSiteId()]);
		$http = $setup();
		check((new DEEC_Albis($ctx, $cache ?? new AlbisTestCache(), $http))->quote($product) !== null && count($http->requests) === $requests, 'Valid configuration or scoped cache failed');
		if ($requests) {
			check(json_decode($http->requests[0]['body'], true) === array_intersect_key($expected, array_flip(['client_id', 'client_secret'])), 'Credentials mixed or wrong configuration source selected');
			$host = $ctx->get('albisenvironment') === 'live' ? 'api.albis-leasing.de' : 'test-api.albis-leasing.de';
			check(parse_url($http->requests[0]['url'], PHP_URL_HOST) === $host, 'TEST/LIVE endpoint mismatch');
		}
		return $http;
	};
	$fileCredentials = ['client_id' => 'file-fixture-client', 'client_secret' => 'file-fixture;#=secret-${DEWAWI_ALBIS_LITERAL}', 'cache_key' => base64_encode(str_repeat('f', 32))];
	$envCredentials = ['client_id' => 'env-fixture-client', 'client_secret' => 'env-fixture-secret', 'cache_key' => base64_encode(str_repeat('e', 32))];
	$scopes = [$prefix(), $prefix(8), $prefix(7, 42), $prefix(7, 41, 'live')];
	foreach ($scopes as $scope) $environment([], $scope);
	$disabled();
	$environment($envCredentials, $prefix());
	$selected($envCredentials); // A missing file retains getenv() compatibility.
	$environment([], $prefix());
	putenv('DEWAWI_ALBIS_LITERAL=must-not-expand');
	$write([$prefix() => $fileCredentials]);
	$selected($fileCredentials);
	$plain = '';
	foreach ($envCredentials as $field => $value) $plain .= '  ' . $prefix() . strtoupper($field) . ' = ' . $value . "\r\n";
	file_put_contents($configurationPath, $plain);
	$selected($envCredentials); // Plain assignments, whitespace, CRLF and base64 padding are preserved.
	$write([$prefix() => $fileCredentials]);
	$environment($envCredentials, $prefix());
	$selected($fileCredentials); // A complete file set wins over a complete environment set.

	foreach ($fields as $missing) {
		$partial = $fileCredentials;
		unset($partial[$missing]);
		$write([$prefix() => $partial]);
		$environment([], $prefix());
		$disabled();
		$environment($envCredentials, $prefix());
		$selected($envCredentials); // Fall back as a whole, never fill only the missing field.
		$onlyMissing = [$missing => $envCredentials[$missing]];
		$environment($onlyMissing, $prefix());
		$disabled();
	}
	foreach (['client_id' => '', 'client_secret' => '   ', 'cache_key' => 'invalid-base64'] as $field => $invalid) {
		$write([$prefix() => array_replace($fileCredentials, [$field => $invalid])]);
		$environment([], $prefix());
		$disabled();
		$environment($envCredentials, $prefix());
		$selected($envCredentials);
	}
	$write([]);
	foreach ($fields as $missing) {
		$partial = $envCredentials;
		unset($partial[$missing]);
		$environment($partial, $prefix());
		$disabled();
	}
	foreach (['bad', base64_encode(str_repeat('x', 31)), base64_encode(str_repeat('x', 33))] as $invalid) {
		$environment(array_replace($envCredentials, ['cache_key' => $invalid]), $prefix());
		$disabled();
	}

	// Parser warnings must not reach output, the application error handler or PHP's error log.
	$warnings = [];
	set_error_handler(static function($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
	ob_start();
	try {
		$valid = $write([$prefix() => $fileCredentials]);
		foreach ([
			$valid . "true=parser-fixture-secret\n",
			$valid . "bare-fixture-secret-line\n",
			$valid . "[fixture-secret-section]\n",
			$valid . $prefix() . "CLIENT_ID=duplicate-fixture-secret\n",
			$prefix() . "CLIENT_SECRET[]=array-fixture-secret\n",
			$prefix() . "CLIENT_SECRET=\"unterminated-fixture-secret\n",
			$prefix() . "CLIENT_SECRET=\"fixture-secret\" trailing\n",
			$valid . "INVALID[fixture-secret]=value\n",
			$valid . "<?php file_put_contents('" . BASE_PATH . "/executed', 'fixture-secret'); ?>\n",
		] as $malformed) {
			file_put_contents($configurationPath, $malformed);
			$environment([], $prefix());
			$disabled();
			$environment($envCredentials, $prefix());
			$selected($envCredentials);
		}
		trigger_error('fixture-handler-restored', E_USER_WARNING);
	} finally {
		$output = ob_get_clean();
		restore_error_handler();
	}
	check($output === '' && $warnings === ['fixture-handler-restored'] && (!file_exists($configurationLog) || file_get_contents($configurationLog) === ''), 'Secret-bearing parser warning escaped suppression');
	check(!file_exists(BASE_PATH . '/executed'), 'Configuration was executed as PHP');
	file_put_contents($configurationPath, file_get_contents($root . '/configs/albis.sample.env'));
	$environment([], $prefix());
	$disabled();
	$environment($envCredentials, $prefix());
	$selected($envCredentials); // Empty sample entries never authorize or mask valid getenv() values.

	$environment([], $prefix());
	$write([$prefix() => $fileCredentials]);
	chmod($configurationPath, 0000);
	clearstatcache(true, $configurationPath);
	if (is_readable($configurationPath)) throw new RuntimeException('Run unreadable-file test as an unprivileged user');
	$disabled();
	$environment($envCredentials, $prefix());
	$selected($envCredentials);
	chmod($configurationPath, 0600);
	unlink($configurationPath);
	mkdir($configurationPath, 0700);
	$selected($envCredentials); // A directory is not a configuration file.
	rmdir($configurationPath);

	foreach ($scopes as $scope) $environment([], $scope);
	$all = [];
	foreach ($scopes as $index => $scope) $all[$scope] = ['client_id' => 'scoped-fixture-' . $index, 'client_secret' => 'scoped-fixture-secret-' . $index, 'cache_key' => base64_encode(str_repeat((string)$index, 32))];
	$write($all);
	$sharedCache = new AlbisTestCache();
	foreach ([[], ['clientid' => 8], ['id' => 42], ['albisenvironment' => 'live']] as $index => $changes) {
		$selected($all[$scopes[$index]], $changes, $sharedCache);
		$selected($all[$scopes[$index]], $changes, $sharedCache, 0);
	}
	$disabled(['clientid' => 9]);
	$disabled(['id' => 43]);
	unset($all[$prefix(7, 41, 'live')]);
	$write($all);
	$disabled(['albisenvironment' => 'live']); // TEST values cannot authorize LIVE.
	$environment($envCredentials, $prefix(7, 41, 'live'));
	$selected($envCredentials, ['albisenvironment' => 'live'], $sharedCache);
	$all[$prefix()]['client_secret'] = 'rotated-file-fixture-secret';
	$write($all);
	$selected($all[$prefix()], [], $sharedCache);
	$all[$prefix()]['cache_key'] = base64_encode(str_repeat('r', 32));
	$write($all);
	$selected($all[$prefix()], [], $sharedCache);
	$cached = serialize($sharedCache->entries);
	foreach (array_merge(array_values($all), [$envCredentials]) as $values) {
		check(!str_contains($cached, $values['client_secret']) && !str_contains($cached, $values['cache_key']), 'Configuration secret stored in plaintext cache');
	}
	check(!str_contains($cached, $token), 'Token encryption was lost');
	check(!file_exists($configurationLog) || file_get_contents($configurationLog) === '', 'ALBIS configuration produced error logs');
	echo "PASS: $checks ALBIS file/environment configuration, source priority, isolation, rotation and secret-suppression checks\n";
} finally {
	ini_set('error_log', $previousLog);
	foreach (array_keys($environmentKeys) as $key) putenv($key);
	putenv('DEWAWI_ALBIS_LITERAL');
	if (is_file($configurationPath)) { chmod($configurationPath, 0600); unlink($configurationPath); }
	elseif (is_dir($configurationPath)) rmdir($configurationPath);
	foreach ([$configurationLog, BASE_PATH . '/executed'] as $path) if (is_file($path)) unlink($path);
	rmdir(BASE_PATH . '/configs');
	rmdir(BASE_PATH);
}
