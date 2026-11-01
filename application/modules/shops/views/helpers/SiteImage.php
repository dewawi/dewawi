<?php

class Zend_View_Helper_SiteImage extends Zend_View_Helper_Abstract
{
	private array $imageCache = [];
	private ?Contacts_Model_DbTable_Emailattachment $publisher = null;

	public function SiteImage(string $source, string $alt = '', array $options = []): string
	{
		$image = $this->getImage($source);
		if (!$image) return '';

		$attributes = ['src' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'alt' => $alt];
		$variants = [];
		foreach ([300, 500, 800, 1000] as $width) {
			$variant = $this->getImage($this->variantSource($image['relative'], $width));
			if (!$variant || !$variant['width'] || !$variant['height'] || !$this->sameAspectRatio($image, $variant)) continue;
			$variants[(int)$variant['width']] = $variant['url'];
		}
		if ($image['width'] && $image['height']) $variants[(int)$image['width']] = $image['url'];
		ksort($variants, SORT_NUMERIC);
		if (count($variants) > 1) {
			$srcset = [];
			foreach ($variants as $width => $url) $srcset[] = $url . ' ' . $width . 'w';
			$attributes['srcset'] = implode(', ', $srcset);
			if (!empty($options['sizes'])) $attributes['sizes'] = (string)$options['sizes'];
		}

		foreach (['id', 'class', 'style'] as $name) {
			if (isset($options[$name]) && $options[$name] !== '') $attributes[$name] = (string)$options[$name];
		}
		if (($options['loading'] ?? 'lazy') === 'lazy') $attributes['loading'] = 'lazy';
		if (isset($attributes['loading'])) $attributes['decoding'] = 'async';
		if (($options['fetchpriority'] ?? '') === 'high') $attributes['fetchpriority'] = 'high';

		$html = '<img';
		foreach ($attributes as $name => $value) {
			if ($value === null || ($value === '' && $name !== 'alt')) continue;
			$html .= ' ' . $name . '="' . $this->view->escape((string)$value) . '"';
		}
		return $html . '>';
	}

	private function getImage(string $source): ?array
	{
		$relative = $this->relativePath($source);
		if ($relative === null) return null;
		$cacheKey = Zend_Registry::isRegistered('SiteContext') ? Zend_Registry::get('SiteContext')->getClientId() . ':' . $relative : $relative;
		if (array_key_exists($cacheKey, $this->imageCache)) return $this->imageCache[$cacheKey];

		try {
			$this->publisher ??= new Contacts_Model_DbTable_Emailattachment();
			$published = $this->publisher->getPublishedFilePath($relative);
			if ($published === null) return $this->imageCache[$cacheKey] = null;
			$path = DEEC_Directory::storedPath($published);
			$type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
			if (!$type || !str_starts_with($type, 'image/')) return $this->imageCache[$cacheKey] = null;
			$size = @getimagesize($path);
		} catch (Throwable $exception) {
			return $this->imageCache[$cacheKey] = null;
		}

		return $this->imageCache[$cacheKey] = [
			'relative' => $relative,
			'url' => $this->publicUrl($relative),
			'width' => is_array($size) && !empty($size[0]) ? (int)$size[0] : null,
			'height' => is_array($size) && !empty($size[1]) ? (int)$size[1] : null,
		];
	}

	private function relativePath(string $source): ?string
	{
		$source = trim($source);
		if ($source === '' || str_contains($source, "\0") || preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $source)) return null;
		$path = parse_url($source, PHP_URL_PATH);
		if (!is_string($path) || $path === '' || $path !== $source) return null;
		$baseUrl = rtrim((string)$this->view->baseUrl(), '/');
		if ($baseUrl !== '' && str_starts_with($path, $baseUrl . '/')) $path = substr($path, strlen($baseUrl));
		$relative = ltrim(rawurldecode($path), '/');
		return preg_match('~^media/(?:[^/]+/)*[^/]+$~D', $relative) ? $relative : null;
	}

	private function publicUrl(string $relative): string
	{
		$segments = array_map('rawurlencode', explode('/', $relative));
		return rtrim((string)$this->view->baseUrl(), '/') . '/' . implode('/', $segments);
	}

	private function variantSource(string $relative, int $width): string
	{
		$directory = dirname($relative);
		return ($directory === '.' ? '' : $directory . '/') . $width . '/' . basename($relative);
	}

	private function sameAspectRatio(array $original, array $variant): bool
	{
		if (!$original['width'] || !$original['height']) return false;
		$originalRatio = $original['width'] / $original['height'];
		$variantRatio = $variant['width'] / $variant['height'];
		return abs($originalRatio - $variantRatio) / $originalRatio < 0.01;
	}
}
