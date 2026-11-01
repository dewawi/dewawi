<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/submission-limiter.php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path;
});

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

$directory = sys_get_temp_dir() . '/dewawi-submission-limiter-' . getmypid() . '-' . bin2hex(random_bytes(4));
$limiter = new DEEC_Site_SubmissionLimiter($directory, 100);
$now = 2000000000;

check($limiter->consume(7, 41, 'contact', '192.0.2.1', 2, 60, $now)['allowed'], 'First submission was rejected');
check($limiter->consume(7, 41, 'contact', '192.0.2.1', 2, 60, $now + 1)['allowed'], 'Second submission was rejected');
$blocked = $limiter->consume(7, 41, 'contact', '192.0.2.1', 2, 60, $now + 2);
check(!$blocked['allowed'] && $blocked['retryAfter'] === 58, 'Limit or Retry-After is incorrect');
check($limiter->consume(7, 41, 'contact', '192.0.2.1', 2, 60, $now + 61)['allowed'], 'Expired rate entry was not released');
check($limiter->consume(7, 42, 'contact', '192.0.2.1', 1, 60, $now + 2)['allowed'], 'Site scopes share rate state');
check($limiter->consume(8, 41, 'contact', '192.0.2.1', 1, 60, $now + 2)['allowed'], 'Client scopes share rate state');
check($limiter->consume(7, 41, 'checkout', '192.0.2.1', 1, 60, $now + 2)['allowed'], 'Operation scopes share rate state');
check($limiter->consume(7, 41, 'contact', '192.0.2.2', 2, 60, $now + 2)['allowed'], 'Independent remote addresses share rate state');

$children = [];
for ($i = 0; $i < 12; $i++) {
	$pid = pcntl_fork();
	if ($pid === -1) throw new RuntimeException('Could not fork limiter regression child');
	if ($pid === 0) {
		try {
			$result = (new DEEC_Site_SubmissionLimiter($directory))->consume(7, 41, 'parallel', '198.51.100.7', 5, 60, $now);
			exit($result['allowed'] ? 0 : 1);
		} catch (Throwable $e) {
			exit(2);
		}
	}
	$children[] = $pid;
}

$allowed = 0;
$denied = 0;
foreach ($children as $pid) {
	pcntl_waitpid($pid, $status);
	$code = pcntl_wexitstatus($status);
	if ($code === 0) $allowed++;
	elseif ($code === 1) $denied++;
	else throw new RuntimeException('Parallel limiter child failed');
}
check($allowed === 5 && $denied === 7, 'Concurrent submissions bypassed the shared limit');

for ($i = 0; $i < 110; $i++) {
	$limiter->consume(9, 51, 'bounded', '203.0.113.' . (($i % 250) + 1), 1, 3600, $now + $i);
}
$files = glob($directory . '/*.json');
check(count($files) === 6, 'Unexpected number of scoped rate files');
foreach ($files as $file) {
	$content = (string)file_get_contents($file);
	check(!str_contains($content, '192.0.2.') && !str_contains($content, '198.51.100.') && !str_contains($content, '203.0.113.'), 'Raw remote address was persisted');
	$state = json_decode($content, true);
	check(is_array($state['entries'] ?? null) && count($state['entries']) <= 100, 'Rate state is not bounded');
}

foreach ($files as $file) unlink($file);
rmdir($directory);

$controllerSource = file_get_contents($root . '/application/modules/shops/controllers/Action.php');
check(str_contains($controllerSource, "\$_SERVER['REMOTE_ADDR']"), 'Controller does not use the direct peer address');
check(!preg_match('/HTTP_(?:X_FORWARDED_FOR|FORWARDED|CLIENT_IP)/', $controllerSource), 'Controller trusts a free-form proxy header');

echo "OK ({$checks} checks)\n";
