<?php

/** Resolves request sorting exclusively against server-defined columns. */
class DEEC_List_Ordering
{
	public static function column($order, array $orders, string $default = 'modified')
	{
		$allowed = [];
		foreach ($orders as $key => $column) {
			if (is_string($key)) {
				$allowed[$key] = $column;
			} elseif (is_string($column)) {
				$allowed[$column] = $column;
			}
		}

		if (!$allowed) {
			throw new InvalidArgumentException('List ordering requires allowed columns.');
		}

		if (is_string($order)) {
			if (array_key_exists($order, $allowed)) {
				return $allowed[$order];
			}
			// Also support configured qualified column names used by older callers.
			foreach ($allowed as $column) {
				if (is_string($column) && $order === $column) {
					return $column;
				}
			}
		}

		return $allowed[$default] ?? reset($allowed);
	}

	public static function direction($sort): string
	{
		$sort = is_string($sort) ? strtoupper($sort) : '';
		return in_array($sort, ['ASC', 'DESC'], true) ? $sort : 'DESC';
	}

	public static function clause(array $params, array $orders, string $default = 'modified')
	{
		$column = self::column($params['order'] ?? $default, $orders, $default);
		$clause = (string)$column . ' ' . self::direction($params['sort'] ?? 'DESC');
		return $column instanceof Zend_Db_Expr ? new Zend_Db_Expr($clause) : $clause;
	}
}
