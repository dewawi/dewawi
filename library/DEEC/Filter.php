<?php

class DEEC_Filter
{
    public static function trim($v)
    {
        return is_string($v) ? trim($v) : $v;
    }

    public static function emptyToNull($v)
    {
        if (is_string($v) && trim($v) === '') return null;
        return $v;
    }

    public static function number($v, $precision = 2, $locale = null)
    {
        $v = self::trim($v);
        if ($v === '' || $v === null) return null;

        if ($locale === null) $locale = Zend_Registry::get('Zend_Locale');

        return Zend_Locale_Format::getNumber(
            $v,
            array('precision' => (int)$precision, 'locale' => $locale)
        );
    }

    public static function date($v, $inputLocale = 'de')
    {
        $v = self::trim($v);
        if ($v === '' || $v === null) return null;

        try {
            $d = new Zend_Date($v, Zend_Date::DATES, $inputLocale);
            return $d->get('yyyy-MM-dd');
        } catch (Exception $e) {
            return null;
        }
    }

    // universal single-field ajax normalizer
    public static function normalizeByFormat(array $data, $element)
    {
		// extract meta
		$format = isset($data['_format']) ? $data['_format'] : null;
		$precision = isset($data['_precision']) ? (int)$data['_precision'] : 2;

		// remove meta fields so they don't get written to DB
		unset($data['_format'], $data['_precision']);

        if (!array_key_exists($element, $data)) return $data;

        // always trim strings
        $data[$element] = self::trim($data[$element]);

        switch ($format) {
            case 'number':
                $data[$element] = self::number($data[$element], $precision);
                break;

            case 'date':
                $data[$element] = self::date($data[$element], 'de');
                break;

            case 'string':
                // optional empty->null
                // $data[$element] = self::emptyToNull($data[$element]);
                break;

            default:
                // no special conversion
                break;
        }

        return $data;
    }
}

