<?php

class DEEC_Filter
{
	public static function applyAll(array $values, array $schema, $locale = null): array
	{
		foreach ($schema as $field => $fmt) {
			if (!array_key_exists($field, $values)) continue;
			$values[$field] = self::applyOne($values[$field], $fmt, $locale);
		}
		return $values;
	}

	protected static function applyOne($value, array $fmt, $locale)
	{
		if ($value === null) return null;
		if (is_string($value) && trim($value) === '') return null;

		$type = $fmt['type'] ?? null;

		switch ($type) {
			case 'bool':
				return in_array((string)$value, ['1','on','true'], true) ? 1 : 0;

			case 'int':
				return is_numeric($value) ? (int)$value : null;

			case 'decimal':
				$precision = isset($fmt['precision']) ? (int)$fmt['precision'] : null;
				$n = self::parseDecimalLocale((string)$value, $locale);
				if ($n === null) return null;
				if ($precision !== null) $n = round($n, $precision);
				if ((float)$n == 0.0) return null;
				return $n;

			case 'email':
				return self::email((string)$value);

			case 'html':
				return self::sanitizeHtml((string)$value, $fmt);

			case 'date':
				$dbPat = $fmt['pattern'] ?? 'Y-m-d';
				$uiPat = $fmt['displayPattern'] ?? null;
				return self::normalizeDate((string)$value, $dbPat, $uiPat);

			default:
				return is_string($value) ? trim($value) : $value;
		}
	}

	protected static function parseDecimalLocale(string $raw, $locale): ?float
	{
		$s = trim($raw);
		if ($s === '') return null;

		// simple locale aware: if de-style "1.234,56" => "1234.56"
		// keine default-locale setzen. wenn locale fehlt, machen wir nur minimal safe parse.
		$hasComma = strpos($s, ',') !== false;
		$hasDot = strpos($s, '.') !== false;

		if ($hasComma && $hasDot) {
			// typisch de: tausender '.' entfernen, ',' => '.'
			$s = str_replace('.', '', $s);
			$s = str_replace(',', '.', $s);
		} elseif ($hasComma && !$hasDot) {
			$s = str_replace(',', '.', $s);
		}

		// entferne alles außer digits, minus, dot
		$s = preg_replace('~[^0-9\.\-]~', '', $s);
		if ($s === '' || $s === '-' || $s === '.' || $s === '-.') return null;

		return is_numeric($s) ? (float)$s : null;
	}

	public static function email(string $value): ?string
	{
		$value = preg_replace('~^[\s\p{Z}]+|[\s\p{Z}]+$~u', '', $value);
		$value = preg_replace('~[\x{00AD}\x{200B}-\x{200F}\x{2028}\x{2029}\x{2060}-\x{206F}\x{FEFF}]~u', '', $value);
		$value = str_replace(['‐', '-', '‒', '–', '—', '−'], '-', $value);
		$value = preg_replace('~\s*@\s*~u', '@', $value);
		$value = preg_replace('~\s*\.\s*~u', '.', $value);
		$value = preg_replace('~^[\s\p{Z}]+|[\s\p{Z}]+$~u', '', $value);

		return $value === '' ? null : $value;
	}

	public static function isValidEmail($value): bool
	{
		$email = self::email((string)$value);

		if($email === null || substr_count($email, '@') !== 1) return false;

		list($local, $domain) = explode('@', $email, 2);

		if($local === '' || $domain === '') return false;

		if(preg_match('~[^\x00-\x7F]~', $domain)) {
			if(!function_exists('idn_to_ascii')) return false;

			if(defined('INTL_IDNA_VARIANT_UTS46')) {
				$domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
			} else {
				$domain = idn_to_ascii($domain);
			}

			if($domain === false) return false;
		}

		return filter_var($local.'@'.$domain, FILTER_VALIDATE_EMAIL) !== false;
	}

	protected static function sanitizeHtml(string $value, array $fmt): ?string
	{
		$value = trim($value);
		if ($value === '') return null;

		$allowTags = $fmt['allowTags'] ?? ['a', 'p', 'span', 'img', 'div', 'br', 'strong', 'b', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
		$allowAttribs = $fmt['allowAttribs'] ?? ['href', 'src', 'title', 'target', 'alt', 'class', 'width', 'height'];

		$filter = new Zend_Filter_StripTags([
			'allowTags' => $allowTags,
			'allowAttribs' => $allowAttribs,
		]);

		return self::sanitizeHtmlUrls(trim($filter->filter($value)));
	}

	protected static function sanitizeHtmlUrls(string $html): string
	{
		return preg_replace_callback(
			'~\s(href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~iu',
			function ($match) {
				$url = $match[2] ?? $match[3] ?? $match[4] ?? '';
				$url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$normalized = preg_replace('~[\x00-\x20\x7F]+~u', '', $url);

				if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $normalized) && !preg_match('~^(https?|mailto|tel):~i', $normalized)) {
					return '';
				}

				return $match[0];
			},
			$html
		);
	}

	protected static function normalizeDate(string $raw, string $dbPat, ?string $uiPat): ?string
	{
		$s = trim($raw);
		if ($s === '') return null;

		// wenn input schon db-like ist
		$dt = \DateTime::createFromFormat($dbPat, $s);
		if ($dt instanceof \DateTime) return $dt->format($dbPat);

		if ($uiPat) {
			$dt = \DateTime::createFromFormat($uiPat, $s);
			if ($dt instanceof \DateTime) return $dt->format($dbPat);
		}

		return null;
	}

	public static function slug(string $value): string
	{
		$value = preg_replace('~[^\pL\d]+~u', '-', $value);
		$value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = preg_replace('~[^-\w]+~', '', (string)$value);
		return strtolower(trim((string)$value, '-'));
	}
}
