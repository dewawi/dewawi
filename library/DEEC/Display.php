<?php

final class DEEC_Display
{
	/**
	 * DB row -> Form values (strings) nach element['format'] + locale
	 * - nutzt KEIN parse
	 * - setzt nichts auf "de" als default
	 */
	public static function rowToFormValues(DEEC_Form $form, array $row, $locale = null): array
	{
		$out = $row;

		foreach ($form->getElements() as $name => $el) {
			if (!array_key_exists($name, $row)) continue;

			$type = (string)($el['type'] ?? 'text');

			// Nicht formatieren bei Feldern, deren value exakt matchen muss
			// (select option values, checkbox, hidden)
			if (in_array($type, ['select','checkbox','hidden'], true)) {
				// trotzdem als string zurückgeben, damit Render-Vergleich stabil ist
				$out[$name] = ($row[$name] === null) ? '' : (string)$row[$name];
				continue;
			}

			$fmt = $el['format'] ?? null;
			if (!$fmt) continue;

			$out[$name] = self::formatValue($row[$name], $fmt, $locale);
		}

		return $out;
	}

	/**
	 * Form values subset -> display (für ajax response)
	 * $fields optional, sonst alle form fields
	 */
	public static function fromRow(DEEC_Form $form, array $row, array $fields = null, $locale = null): array
	{
		$fields = $fields ?: array_keys($form->getElements());
		$out = [];

		foreach ($fields as $name) {
			if (!isset($form->getElements()[$name])) continue;
			if (!array_key_exists($name, $row)) continue;

			$el = $form->getElements()[$name];
			$fmt = $el['format'] ?? null;

			$out[$name] = $fmt
				? self::formatValue($row[$name], $fmt, $locale)
				: $row[$name];
		}

		return $out;
	}

	private static function formatValue($value, $format, $locale = null)
	{
		if ($value === null || $value === '') return '';

		// format kann string sein ("decimal") oder array (['type'=>'decimal','precision'=>2])
		if (is_string($format)) {
			$format = ['type' => $format];
		}
		if (!is_array($format)) return (string)$value;

		$type = $format['type'] ?? null;

		switch ($type) {
			case 'decimal':
			case 'number': {
				$precision = isset($format['precision']) ? (int)$format['precision'] : 2;

				// Zend_Currency kann auch numbers formatieren (ohne Währungssymbol) über toCurrency,
				// aber je nach setup ist Zend_Locale_Format stabiler.
				// Wir bleiben simpel: Zend_Locale_Format::toNumber
				if ($locale) {
					// erwartet float/int; wenn db string kommt -> cast
					$n = is_numeric($value) ? (float)$value : (float)str_replace(',', '.', (string)$value);
					return Zend_Locale_Format::toNumber($n, [
						'locale' => $locale,
						'precision' => $precision
					]);
				}

				// locale fehlt -> neutral, ohne "de" default
				return number_format((float)$value, $precision, '.', '');
			}

			case 'int': {
				if ($locale) {
					return Zend_Locale_Format::toNumber((int)$value, [
						'locale' => $locale,
						'precision' => 0
					]);
				}
				return (string)((int)$value);
			}

			case 'date': {
				// db: Y-m-d oder Y-m-d H:i:s -> form: locale short date
				$s = (string)$value;
				if ($s === '0000-00-00' || $s === '0000-00-00 00:00:00') return '';

				try {
					$dt = new Zend_Date($s, null, $locale ?: null);
					// du kannst hier 'short'/'medium' steuern
					return $dt->toString(Zend_Date::DATE_MEDIUM, $locale ?: null);
				} catch (Exception $e) {
					return $s;
				}
			}

			default:
				return (string)$value;
		}
	}
}
