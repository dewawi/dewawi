<?php

class DEEC_Session
{
	public const REMEMBER_SECONDS = 2592000; // 30 days
	public const REMEMBER_KEY = 'dewawi_remember';

	public static function configure(): void
	{
		if (Zend_Session::isStarted()) {
			return;
		}

		Zend_Session::setOptions([
			'save_path' => BASE_PATH . '/session/',
			'gc_maxlifetime' => self::REMEMBER_SECONDS,
			'cookie_lifetime' => 0,
			'cookie_httponly' => true,
			'cookie_secure' => true,
			'cookie_samesite' => 'Lax',
			'use_only_cookies' => true,
		]);
	}

	public static function login(bool $remember): void
	{
		Zend_Session::regenerateId();

		$_SESSION[self::REMEMBER_KEY] = $remember;

		if ($remember) {
			self::refreshCookie();
		}
	}

	public static function refresh(): void
	{
		if (empty($_SESSION[self::REMEMBER_KEY])) {
			return;
		}

		self::refreshCookie();
	}

	public static function logout(): void
	{
		unset($_SESSION[self::REMEMBER_KEY]);
	}

	private static function refreshCookie(): void
	{
		$params = session_get_cookie_params();

		setcookie(
			session_name(),
			session_id(),
			[
				'expires' => time() + self::REMEMBER_SECONDS,
				'path' => $params['path'],
				'domain' => $params['domain'],
				'secure' => true,
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}
}
