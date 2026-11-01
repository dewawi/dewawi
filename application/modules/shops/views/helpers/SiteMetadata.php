<?php

class Zend_View_Helper_SiteMetadata extends Zend_View_Helper_Abstract
{
	protected array $metadata = [];

	public function SiteMetadata(?array $metadata = null): self
	{
		if ($metadata !== null) $this->apply($metadata);

		return $this;
	}

	public function finalize(): self
	{
		if ($this->metadata) return $this;

		$title = $this->view->headTitle()->getValue();
		if (is_array($title)) $title = implode(' - ', $title);

		return $this->apply([
			'title' => (string)$title,
			'path' => $this->view->canonicalPath ?? null,
		]);
	}

	public function apply(array $metadata): self
	{
		$site = $this->getSite();
		$siteName = $this->cleanText((string)($site['title'] ?? ''));
		$title = $this->cleanText((string)($metadata['title'] ?? ''));
		$description = $this->cleanText((string)($metadata['description'] ?? ''));
		if (!preg_match('/[\p{L}\p{N}]/u', $description) || preg_match('/^\{[a-z0-9_-]+\}$/i', $description)) $description = '';

		if ($title === '') $title = $siteName;

		$documentTitle = $title;
		$containsSiteName = $siteName !== '' && preg_match('/(?:^|[\s|:–—-])' . preg_quote($siteName, '/') . '$/iu', $title);
		if ($siteName !== '' && $title !== '' && !$containsSiteName) {
			$documentTitle .= ' - ' . $siteName;
		}

		if ($documentTitle !== '') $this->view->headTitle()->set($documentTitle);
		if ($description !== '') $this->view->headMeta()->setName('description', mb_substr($description, 0, 160, 'UTF-8'));

		$path = $metadata['path'] ?? ($this->view->canonicalPath ?? null);
		$url = is_string($path) ? $this->absoluteUrl($path) : null;
		$image = null;

		if (!empty($metadata['imageDirectory']) && !empty($metadata['image'])) {
			$image = $this->mediaUrl((string)$metadata['imageDirectory'], (string)$metadata['image']);
		}

		$type = trim((string)($metadata['type'] ?? 'website'));
		if (!in_array($type, ['website', 'article', 'product'], true)) $type = 'website';

		$openGraph = [
			'og:type' => $type,
			'og:title' => $documentTitle,
			'og:description' => $description !== '' ? mb_substr($description, 0, 160, 'UTF-8') : '',
			'og:url' => $url,
			'og:image' => $image,
			'og:site_name' => $siteName,
			'og:locale' => $this->getLocale(),
		];

		foreach ($openGraph as $property => $content) {
			if ($content !== null && $content !== '') $this->view->headMeta()->setProperty($property, (string)$content);
		}

		$this->metadata = [
			'title' => $title,
			'documentTitle' => $documentTitle,
			'description' => $description,
			'url' => $url,
			'image' => $image,
		];

		return $this;
	}

	public function absoluteUrl(string $path): ?string
	{
		$baseUrl = $this->getContext()->getCanonicalBaseUrl();
		$path = trim($path);

		if ($baseUrl === null || $path === '' || str_starts_with($path, '//') || str_contains($path, "\0")) return null;

		$parts = parse_url($path);
		if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return null;

		$localPath = (string)($parts['path'] ?? '');
		if ($localPath === '' || preg_match('~(?:^|/)\.\.(?:/|$)~', $localPath)) return null;

		$applicationBase = rtrim((string)$this->view->baseUrl(), '/');
		if ($applicationBase !== '' && ($localPath === $applicationBase || str_starts_with($localPath, $applicationBase . '/'))) {
			$localPath = substr($localPath, strlen($applicationBase));
			if ($localPath === '') $localPath = '/';
		}

		$url = rtrim($baseUrl, '/') . '/' . ltrim($localPath, '/');
		if (isset($parts['query']) && $parts['query'] !== '') $url .= '?' . $parts['query'];

		return $url;
	}

	public function mediaUrl(string $directory, string $filename): ?string
	{
		if (!in_array($directory, ['category', 'header', 'images'], true)) return null;

		$filename = trim($filename);
		if ($filename === '' || str_contains($filename, "\0") || str_contains($filename, '\\') || str_starts_with($filename, '/') || str_contains($filename, '?') || str_contains($filename, '#')) return null;
		if (preg_match('~(?:^|/)\.\.?(/|$)~', $filename) || !preg_match('~\.(?:avif|gif|jpe?g|png|svg|webp)$~i', $filename)) return null;

		$segments = array_map('rawurlencode', explode('/', $filename));

		return $this->absoluteUrl('/media/' . $directory . '/' . implode('/', $segments));
	}

	public function renderOrganization(): string
	{
		$site = $this->getSite();
		$name = $this->cleanText((string)($site['title'] ?? ''));
		$url = $this->getContext()->getCanonicalBaseUrl();

		if ($name === '' || $url === null) return '';

		$data = [
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'name' => $name,
			'url' => $url . '/',
		];

		$logo = $this->mediaUrl('header', (string)($site['logo'] ?? ''));
		if ($logo !== null) $data['logo'] = $logo;

		return $this->jsonLd($data);
	}

	public function jsonLd(array $data): string
	{
		$json = Zend_Json::encode($data);
		$json = str_ireplace('</script', '<\\/script', $json);

		return '<script type="application/ld+json">' . $json . '</script>';
	}

	public function getMetadata(): array
	{
		return $this->metadata;
	}

	protected function getContext(): DEEC_Site_Context
	{
		if (!($this->view->siteContext ?? null) instanceof DEEC_Site_Context) {
			throw new RuntimeException('Site context is missing');
		}

		return $this->view->siteContext;
	}

	protected function getSite(): array
	{
		$site = $this->getContext()->getSite();

		return is_array($site) ? $site : [];
	}

	protected function getLocale(): string
	{
		$locale = str_replace('-', '_', trim((string)$this->getContext()->getLanguage()));
		if ($locale === 'de') $locale = 'de_DE';
		if ($locale === 'en') $locale = 'en_US';

		return preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) ? $locale : '';
	}

	protected function cleanText(string $value): string
	{
		$value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim((string)preg_replace('/\s+/u', ' ', $value));
	}
}
