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
                if ($precision !== null) {
                    $n = round($n, $precision);
                }
                if ((float)$n == 0.0) return null;
                return $n;

            case 'date':
                // input kann d.m.Y sein, db soll Y-m-d
                $dbPat  = $fmt['pattern'] ?? 'Y-m-d';
                $uiPat  = $fmt['displayPattern'] ?? null;
                return self::normalizeDate((string)$value, $dbPat, $uiPat);

            default:
                // string trim als default
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
        $hasDot   = strpos($s, '.') !== false;

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
}
