<?php

// Tokenize local source without executing catalogues, application bootstrap or configuration.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function translationAuditString(string $literal): string
{
	$body = substr($literal, 1, -1);
	if ($literal[0] === "'") return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
	return preg_replace_callback('/\\\\(u\{[0-9a-fA-F]+\}|x[0-9a-fA-F]{1,2}|[0-7]{1,3}|[nrtvef\\\\$"])/', static function ($m) {
		if (str_starts_with($m[1], 'u{')) {
			$point = hexdec(substr($m[1], 2, -1));
			if ($point <= 0x7f) return chr($point);
			if ($point <= 0x7ff) return chr(0xc0 | ($point >> 6)) . chr(0x80 | ($point & 0x3f));
			if ($point <= 0xffff) return chr(0xe0 | ($point >> 12)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f));
			return chr(0xf0 | ($point >> 18)) . chr(0x80 | (($point >> 12) & 0x3f)) . chr(0x80 | (($point >> 6) & 0x3f)) . chr(0x80 | ($point & 0x3f));
		}
		$escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
		return $escapes[$m[1]] ?? chr($m[1][0] === 'x' ? hexdec(substr($m[1], 1)) : octdec($m[1]));
	}, $body);
}

function translationAuditTokens(string $source, bool $parse): array
{
	$tokens = []; $line = 1;
	foreach (token_get_all($source, $parse ? TOKEN_PARSE : 0) as $token) {
		$id = is_array($token) ? $token[0] : 0;
		$value = is_array($token) ? $token[1] : $token;
		if (!in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $tokens[] = [$id, $value, $line];
		$line += substr_count($value, "\n");
	}
	return $tokens;
}

function translationAuditCatalogue(string $source): array
{
	$tokens = translationAuditTokens($source, true);
	$i = 0;
	$take = static function ($expected) use (&$tokens, &$i): void {
		$token = $tokens[$i++] ?? null;
		if (!$token || (is_int($expected) ? $token[0] !== $expected : $token[1] !== $expected)) throw new RuntimeException('Unsupported catalogue expression at token ' . ($i - 1));
	};
	$take(T_OPEN_TAG); $take(T_RETURN);
	if (($tokens[$i][0] ?? 0) === T_ARRAY) { $take(T_ARRAY); $take('('); $close = ')'; }
	else { $take('['); $close = ']'; }
	$entries = [];
	while (($tokens[$i][1] ?? null) !== $close) {
		$key = $tokens[$i] ?? null; $take(T_CONSTANT_ENCAPSED_STRING); $take(T_DOUBLE_ARROW);
		$value = $tokens[$i] ?? null; $take(T_CONSTANT_ENCAPSED_STRING);
		$entries[] = ['key' => translationAuditString($key[1]), 'value' => translationAuditString($value[1]), 'line' => $key[2]];
		if (($tokens[$i][1] ?? null) === $close) break;
		$take(',');
	}
	$take($close); $take(';');
	if (($tokens[$i][0] ?? null) === T_CLOSE_TAG) $i++;
	if (isset($tokens[$i])) throw new RuntimeException('Unexpected content after returned array');
	return $entries;
}

function translationAuditSource(string $source): array
{
	$tokens = translationAuditTokens($source, false);
	$literals = []; $calls = []; $html = [];
	$cleanSource = '';
	foreach (token_get_all($source) as $part) {
		$value = is_array($part) ? $part[1] : $part;
		$cleanSource .= is_array($part) && in_array($part[0], [T_COMMENT, T_DOC_COMMENT], true) ? preg_replace('/[^\r\n]/', ' ', $value) : $value;
	}
	foreach ($tokens as $i => $token) {
		[$id, $value, $line] = $token;
		if ($id === T_CONSTANT_ENCAPSED_STRING) $literals[] = ['value' => translationAuditString($value), 'line' => $line];
		if ($id === T_INLINE_HTML) $html[] = ['value' => $value, 'line' => $line];
		if ($id !== T_STRING || !in_array(strtolower($value), ['t', 'translate', 'installertext', 'installertranslate'], true) || ($tokens[$i + 1][1] ?? '') !== '(') continue;
		if (($tokens[$i - 1][0] ?? 0) === T_FUNCTION) continue;
		if (!in_array(strtolower($value), ['installertext', 'installertranslate'], true) && !in_array($tokens[$i - 1][0] ?? 0, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) continue;
		$arg = []; $depth = 0;
		for ($j = $i + 2; isset($tokens[$j]); $j++) {
			$current = $tokens[$j];
			if (!$depth && in_array($current[1], [',', ')'], true)) break;
			if (in_array($current[1], ['(', '[', '{'], true)) $depth++;
			if (in_array($current[1], [')', ']', '}'], true)) $depth--;
			$arg[] = $current;
		}
		$literal = count($arg) === 1 && $arg[0][0] === T_CONSTANT_ENCAPSED_STRING;
		$prefix = !$literal && ($arg[0][0] ?? 0) === T_CONSTANT_ENCAPSED_STRING && ($arg[1][1] ?? '') === '.' ? translationAuditString($arg[0][1]) : null;
		$calls[] = ['method' => $value, 'line' => $line, 'key' => $literal ? translationAuditString($arg[0][1]) : null, 'prefix' => $prefix, 'expression' => substr(implode('', array_column($arg, 1)), 0, 300)];
	}
	return ['literals' => $literals, 'calls' => $calls, 'html' => $html, 'clean_source' => $cleanSource];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
	$result = [];
	foreach ($input['files'] as $file) {
		try {
			$source = file_get_contents($file);
			if ($source === false) throw new RuntimeException('Cannot read source');
			$result[$file] = $input['mode'] === 'catalogues' ? ['entries' => translationAuditCatalogue($source)] : translationAuditSource($source);
		} catch (Throwable $e) { $result[$file] = ['error' => get_class($e) . ': ' . $e->getMessage()]; }
	}
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
}
