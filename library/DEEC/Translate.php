<?php

class DEEC_Translate
{
	protected $locale = '';
	protected $data = [];
	protected $loaded = [];

	public function __construct(string $locale)
	{
		$this->locale = $locale;
	}

	public function setLocale(string $locale): void
	{
		$this->locale = $locale ?: 'de';
	}

	public function getLocale(): string
	{
		return $this->locale;
	}

	/**
	 * Load a PHP array file once into a domain (e.g. "items", "application")
	 * File must return array(key => text).
	 */
	public function load(string $domain, string $filePath): void
	{
		if ($domain === '' || isset($this->loaded[$domain])) return;

		$this->data[$domain] = $this->data[$domain] ?? [];

		if (is_file($filePath)) {
			$arr = require $filePath;
			if (is_array($arr)) {
				// merge, later keys overwrite earlier ones
				$this->data[$domain] = array_merge($this->data[$domain], $arr);
			}
		}

		$this->loaded[$domain] = true;
	}


	// NEU: alle *.php in einem Ordner laden (als 1 domain)
	public function loadDir(string $domain, string $dirPath): void
	{
		if ($domain === '' || isset($this->loaded[$domain])) return;
		if (!is_dir($dirPath)) return;

		$this->data[$domain] = $this->data[$domain] ?? [];

		$files = glob(rtrim($dirPath, '/'). '/*.php');
		if (!$files) {
			$this->loaded[$domain] = true;
			return;
		}

		sort($files); // stabile Reihenfolge

		foreach ($files as $file) {
			$arr = require $file;
			if (is_array($arr)) {
				// späteres überschreibt früheres
				$this->data[$domain] = array_merge($this->data[$domain], $arr);
			}
		}

		$this->loaded[$domain] = true;
	}

	/**
	 * Translate key, optionally with sprintf args.
	 * Looks through domains in the order given.
	 */
	public function t(string $key, array $args = [], array $domains = []): string
	{
		$key = (string)$key;
		if ($key === '') return '';

		// if domains not specified, search all loaded domains
		if (!$domains) {
			$domains = array_keys($this->data);
		}

		$text = null;
		foreach ($domains as $d) {
			if (isset($this->data[$d][$key])) {
				$text = $this->data[$d][$key];
				break;
			}
		}

		if ($text === null) {
			$text = $key; // fallback
		}

		if ($args) {
			// safe sprintf: if mismatch, just return raw text
			try {
				$text = vsprintf($text, $args);
			} catch (Exception $e) {
			}
		}

		return (string)$text;
	}
}

