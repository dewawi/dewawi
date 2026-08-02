<?php

class DEEC_Util
{
	public static function camelize(string $str): string
	{
		$str = str_replace(
			['-', '_'],
			' ',
			strtolower($str)
		);

		return str_replace(
			' ',
			'',
			ucwords($str)
		);
	}

	public static function moduleClassPrefix(
		string $module
	): string {
		return $module === 'default'
			? 'Application'
			: self::camelize($module);
	}

	public static function formClassFromModuleController(
		string $module,
		string $controller
	): string {
		return self::moduleClassPrefix($module)
			. '_Form_'
			. self::camelize($controller);
	}

	public static function dbTableClassFromModuleController(
		string $module,
		string $controller
	): string {
		return self::moduleClassPrefix($module)
			. '_Model_DbTable_'
			. self::camelize($controller);
	}
}
