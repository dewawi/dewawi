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

	public static function isValidDecimal($value, $locale = null): bool
	{
		if(!is_scalar($value)) return false;

		$value = trim((string)$value);
		if($value === '' || preg_match('~\p{L}~u', $value)) return false;

		return self::parseDecimalLocale($value, $locale) !== null;
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
		$allowAttribs = $fmt['allowAttribs'] ?? ['href', 'src', 'title', 'alt', 'class', 'width', 'height'];

		$filter = new Zend_Filter_StripTags([
			'allowTags' => $allowTags,
			'allowAttribs' => $allowAttribs,
		]);

		$html = trim($filter->filter($value));
		$html = self::sanitizeHtmlAttributes($html);
		$html = self::sanitizeHtmlUrls($html, !empty($fmt['managedMediaOnly']));

		return preg_replace('~<img\b(?![^>]*\bsrc\s*=)[^>]*>~iu', '', $html);
	}

	protected static function sanitizeHtmlAttributes(string $html): string
	{
		$html = preg_replace('~\s(?:on[a-z0-9_-]+|srcdoc|formaction)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~iu', '', $html);

		return preg_replace_callback(
			'~\sstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~iu',
			function ($match) {
				$value = html_entity_decode($match[1] ?? $match[2] ?? $match[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$declarations = [];
				$allowed = [
					'text-align', 'color', 'background-color', 'font-size', 'font-weight', 'font-style',
					'text-decoration', 'line-height', 'margin', 'margin-top', 'margin-right',
					'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right',
					'padding-bottom', 'padding-left', 'width', 'max-width', 'height',
				];

				foreach (explode(';', $value) as $declaration) {
					if (strpos($declaration, ':') === false) continue;
					list($property, $propertyValue) = explode(':', $declaration, 2);
					$property = strtolower(trim($property));
					$propertyValue = trim($propertyValue);
					if (!in_array($property, $allowed, true) || $propertyValue === '') continue;
					if (preg_match('~(?:url\s*\(|expression\s*\(|@import|behavior\s*:|-moz-binding|[{}\\\\])~i', $propertyValue)) continue;
					$propertyValue = preg_replace('~\s*!important\s*$~i', '', $propertyValue);
					if ($propertyValue === '') continue;
					$declarations[] = $property . ': ' . $propertyValue;
				}

				if (!$declarations) return '';
				return ' style="' . htmlspecialchars(implode('; ', $declarations), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
			},
			$html
		);
	}

	protected static function sanitizeHtmlUrls(string $html, bool $managedMediaOnly = false): string
	{
		$html = preg_replace_callback(
			'~\s(href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~iu',
			function ($match) use ($managedMediaOnly) {
				$attribute = strtolower($match[1]);
				$url = $match[2] ?? $match[3] ?? $match[4] ?? '';
				$url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$normalized = preg_replace('~[\x00-\x20\x7F]+~u', '', $url);

				if ($normalized === '' || ($attribute === 'href' && $normalized === '#')) return '';
				if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $normalized)) {
					if ($attribute === 'src' && !preg_match('~^https?:~i', $normalized)) return '';
					if ($attribute === 'href' && !preg_match('~^(https?|mailto|tel):~i', $normalized)) return '';
				}
				if ($attribute === 'src' && $managedMediaOnly) {
					if (preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $normalized)) return '';
					$path = rawurldecode((string)parse_url($normalized, PHP_URL_PATH));
					$path = preg_replace('~^(?:\./)+~', '', $path);
					if (str_contains($path, '..') || !preg_match('~^(?:/[^/?#]+)*/media/(?:[^/]+/)*[^/]+$|^media/(?:[^/]+/)*[^/]+$~D', $path)) return '';
				}
				if ($attribute === 'href') {
					$path = rawurldecode((string)parse_url($normalized, PHP_URL_PATH));
					if (preg_match('~(?:^|/)\.\.(?:/|$)~', $path)) return '';
					if (preg_match('~(?:^|/)(?:files|cache|library)(?:/|$)~i', ltrim($path, '/'))) return '';
				}

				return $match[0];
			},
			$html
		);

		return preg_replace('~<a\b(?![^>]*\b(?:href|id|name)\s*=)[^>]*>(.*?)</a>~isu', '$1', $html);
	}

	public static function removeEmptyHeadings(string $html, int $level = 1): string
	{
		if ($level < 1 || $level > 6 || $html === '') return $html;

		return preg_replace_callback(
			'~<h' . $level . '\b[^>]*>(.*?)</h' . $level . '\s*>~isu',
			function ($match) {
				$text = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$text = preg_replace('~[\s\p{Z}\x{00AD}\x{200B}-\x{200F}\x{2060}-\x{206F}\x{FEFF}]+~u', '', $text);
				return preg_match('~[\pL\pN]~u', $text) ? $match[0] : '';
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
		if ($dt instanceof \DateTime && self::isDateParseValid()) return $dt->format($dbPat);

		if ($uiPat) {
			$dt = \DateTime::createFromFormat($uiPat, $s);
			if ($dt instanceof \DateTime && self::isDateParseValid()) return $dt->format($dbPat);
		}

		return null;
	}

	protected static function isDateParseValid(): bool
	{
		$errors = \DateTime::getLastErrors();
		return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
	}

	public static function slug(string $value): string
	{
		$value = strtr($value, [
			'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ẞ' => 'Ss',
			'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
		]);
		$value = preg_replace('~[^\pL\d]+~u', '-', $value);
		$value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = preg_replace('~[^a-zA-Z0-9]+~', '-', (string)$value);
		return strtolower(trim((string)$value, '-'));
	}
}
