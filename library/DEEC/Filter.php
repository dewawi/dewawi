<?php

class DEEC_Filter
{
    public static function strTrimOrNull($value)
    {
        if ($value === null) return null;
        $v = trim((string)$value);
        return ($v === '') ? null : $v;
    }

    public static function strTrim($value)
    {
        return trim((string)$value);
    }

    public static function bool01($value)
    {
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1','true','on','yes'], true) ? 1 : 0;
    }

    public static function intOrNull($value)
    {
        $v = self::strTrimOrNull($value);
        if ($v === null) return null;
        if (!preg_match('~^-?\d+$~', $v)) return null;
        return (int)$v;
    }

    // German number: "1.234,50" => 1234.50 (float), "" => null
    public static function numberDeToFloatOrNull($value, int $precision = 2)
    {
        $raw = self::strTrimOrNull($value);
        if ($raw === null) return null;

        // remove spaces + nbsp
        $raw = str_replace([' ', "\xC2\xA0"], '', $raw);

        // remove thousand separators and convert decimal comma
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (!is_numeric($raw)) return null;

        return round((float)$raw, $precision);
    }

    // date: "29.12.2025" => "2025-12-29", "" => null
    public static function dateDeToIsoOrNull($value)
    {
        $raw = self::strTrimOrNull($value);
        if ($raw === null) return null;

        $dt = DateTime::createFromFormat('d.m.Y', $raw);
        if (!$dt) return null;

        return $dt->format('Y-m-d');
    }

    // keep only allowed keys
    public static function only(array $data, array $allowedKeys)
    {
        $out = [];
        foreach ($allowedKeys as $k) {
            if (array_key_exists($k, $data)) $out[$k] = $data[$k];
        }
        return $out;
    }

    // apply callable filters per field
    public static function apply(array $data, array $map)
    {
        foreach ($map as $field => $filter) {
            if (!array_key_exists($field, $data)) continue;

            if (is_callable($filter)) {
                $data[$field] = $filter($data[$field]);
                continue;
            }

            // allow array of callables
            if (is_array($filter)) {
                $v = $data[$field];
                foreach ($filter as $fn) {
                    if (is_callable($fn)) $v = $fn($v);
                }
                $data[$field] = $v;
            }
        }
        return $data;
    }
}
