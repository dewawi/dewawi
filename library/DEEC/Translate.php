<?php

class DEEC_Translate
{
	public const LOCALES = ['de_DE', 'en_US'];
	protected $locale = '';
	protected $data = [];
	protected $loaded = [];
	protected $files = [];
	protected $issues = [];

	public function __construct(string $locale)
	{
		$this->locale = self::normalizeLocale($locale);
	}

	public static function normalizeLocale(string $locale, string $fallback = 'de_DE'): string
	{
		$aliases = ['de' => 'de_DE', 'de_de' => 'de_DE', 'en' => 'en_US', 'en_us' => 'en_US'];
		return $aliases[strtolower(str_replace('-', '_', trim($locale)))] ?? $fallback;
	}

	public function setLocale(string $locale): void
	{
		$locale = self::normalizeLocale($locale);
		if ($locale === $this->locale) return;
		$this->locale = $locale;
		$this->data = $this->loaded = $this->files = $this->issues = [];
	}

	public function getLocale(): string { return $this->locale; }

	public function getIssues(): array { return array_values($this->issues); }

	protected function issue(string $type, string $value): void
	{
		$this->issues[$type . ':' . $value] = ['type' => $type, 'value' => $value];
	}

	protected function readFile(string $path): ?array
	{
		if (isset($this->files[$path])) return $this->files[$path];
		if (!is_file($path) || !is_readable($path)) {
			$this->issue('missing_file', $path);
			return null;
		}
		try { $entries = require $path; }
		catch (Throwable $error) { $this->issue('invalid_file', $path); return null; }
		if (!is_array($entries) || count(array_filter($entries, 'is_string')) !== count($entries)) {
			$this->issue('invalid_catalogue', $path);
			return null;
		}
		return $this->files[$path] = $entries;
	}

	public function load(string $domain, string $filePath): void
	{
		if ($domain === '' || isset($this->loaded[$domain])) return;
		$entries = $this->readFile($filePath);
		if ($entries === null) return;
		$this->data[$domain] = $entries;
		$this->loaded[$domain] = true;
	}

	public function loadDir(string $domain, string $dirPath): void
	{
		if ($domain === '' || isset($this->loaded[$domain])) return;
		if (!is_dir($dirPath)) { $this->issue('missing_directory', $dirPath); return; }
		$files = glob(rtrim($dirPath, '/') . '/*.php');
		if ($files === false) { $this->issue('invalid_directory', $dirPath); return; }
		sort($files);
		$data = [];
		foreach ($files as $file) {
			$entries = $this->readFile($file);
			if ($entries === null) return;
			$data = array_merge($data, $entries);
		}
		$this->data[$domain] = $data;
		$this->loaded[$domain] = true;
	}

	// Shared module labels remain global; detail catalogues are loaded only for the requested module.
	public function loadModule(string $module): void
	{
		if (!preg_match('/^[a-z][a-z0-9]*$/D', $module)) { $this->issue('invalid_module', $module); return; }
		$root = dirname(__DIR__, 2);
		$base = $root . '/languages/' . $this->locale;
		$this->loadDir('default', $base . '/default');
		foreach (glob($base . '/*/common.php') as $file) {
			$name = basename(dirname($file));
			if ($name !== 'default' && is_dir($root . '/application/modules/' . $name)) $this->load($name . '_common', $file);
		}
		if (is_dir($base . '/' . $module)) $this->loadDir($module, $base . '/' . $module);
	}

	public function t(string $key, array $args = [], array $domains = []): string
	{
		if ($key === '') return '';
		if (!$domains) $domains = array_keys($this->data);
		$text = null;
		foreach ($domains as $domain) {
			if (isset($this->data[$domain][$key])) { $text = $this->data[$domain][$key]; break; }
		}
		if ($text === null) { $this->issue('missing_key', $key); $text = $key; }
		if ($args) {
			try { $text = vsprintf($text, $args); }
			catch (Throwable $error) { $this->issue('invalid_format', $key); }
		}
		return $text;
	}
}
