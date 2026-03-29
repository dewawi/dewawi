<?php

class DEEC_Util
{
	public static function camelize(string $str): string
	{
		// address -> Address, email_message -> EmailMessage, email-message -> EmailMessage
		$str = str_replace(['-', '_'], ' ', strtolower($str));
		$str = ucwords($str);
		return str_replace(' ', '', $str);
	}

	public static function formClassFromModuleController(string $module, string $controller): string
	{
		$modulePart = self::camelize($module); // contacts -> Contacts
		$ctrlPart = self::camelize($controller); // address -> Address
		return $modulePart . '_Form_' . $ctrlPart; // Contacts_Form_Address
	}
}
