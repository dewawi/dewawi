<?php

class Shops_Model_Search
{
	public const PER_PAGE = 20;

	protected DEEC_Site_Context $siteContext;
	protected Shops_Model_DbTable_Slug $slugDb;
	protected array $supplementalMatchCache = [];

	public function __construct(DEEC_Site_Context $siteContext)
	{
		$this->siteContext = $siteContext;
		$this->slugDb = new Shops_Model_DbTable_Slug();
	}

	public function search(string $query, int $page = 1, string $type = ''): array
	{
		$query = self::cleanText($query);
		$terms = $this->getTerms($query);
		$page = max(1, $page);
		$type = in_array($type, ['page', 'category', 'tag', 'item'], true) ? $type : '';

		$result = [
			'query' => $query,
			'terms' => $terms,
			'results' => [],
			'total' => 0,
			'page' => 1,
			'pages' => 0,
			'perPage' => self::PER_PAGE,
			'type' => $type,
			'typeCounts' => [],
			'valid' => $query !== '' && mb_strlen($query, 'UTF-8') <= 100 && (bool)$terms,
		];

		if (!$result['valid']) {
			return $result;
		}

		$this->preloadPaths();
		$candidates = array_merge($this->getPageCandidates(), $this->getCategoryCandidates(), $this->getTagCandidates());
		$normalizedQuery = self::normalize($query);
		$candidates = $this->prepareCandidates($candidates, $normalizedQuery, $terms);
		$itemCount = $this->siteContext->hasFeature('catalog') ? $this->countItems($terms) : 0;

		$typeCounts = array_fill_keys(['page', 'category', 'tag', 'item'], 0);
		foreach ($candidates as $candidate) {
			$typeCounts[$candidate['type']]++;
		}
		$typeCounts['item'] = $itemCount;
		$result['typeCounts'] = array_filter($typeCounts);

		$nonItemCandidates = $type === '' || $type !== 'item'
			? array_values(array_filter($candidates, static function(array $candidate) use ($type): bool {
				return $type === '' || $candidate['type'] === $type;
			}))
			: [];

		$total = count($nonItemCandidates);
		if ($type === '' || $type === 'item') $total += $itemCount;

		$result['total'] = $total;
		$result['pages'] = $total > 0 ? (int)ceil($total / self::PER_PAGE) : 0;
		$result['page'] = $result['pages'] > 0 ? min($page, $result['pages']) : 1;

		$offset = ($result['page'] - 1) * self::PER_PAGE;
		$itemCandidates = [];

		if ($itemCount > 0 && ($type === '' || $type === 'item')) {
			$itemCandidates = $this->prepareCandidates(
				$this->getItemCandidates($terms, $offset + self::PER_PAGE),
				$normalizedQuery,
				$terms
			);
		}

		$all = array_merge($nonItemCandidates, $itemCandidates);
		$this->sortCandidates($all);
		$result['results'] = array_slice($all, $offset, self::PER_PAGE);

		return $result;
	}

	protected function preloadPaths(): void
	{
		$this->slugDb->preloadPaths();
	}

	protected function getPageCandidates(): array
	{
		$pageDb = new Shops_Model_DbTable_Page();
		$pages = $pageDb->getPages();

		if (!$pages) {
			return [];
		}

		$blockDb = new Application_Model_DbTable_Pageblock();
		$blockDb->setClientId($this->siteContext->getClientId());
		$blocks = $blockDb->getPublicBlocksByPageIds(array_column($pages, 'id'));
		$blocksByPage = [];

		foreach ($blocks as $block) {
			$blocksByPage[(int)$block['pageid']][] = $block;
		}

		$candidates = [];
		foreach ($pages as $page) {
			$isHome = (string)($page['type'] ?? '') === 'home';
			$path = $isHome ? '' : $this->slugDb->getPath('page', (int)$page['id']);

			if (!$isHome && !$path) {
				continue;
			}

			$pageBlocks = $blocksByPage[(int)$page['id']] ?? [];
			$blockText = $this->getVisibleBlockText($pageBlocks);
			$content = [(string)($page['content'] ?? '')];

			if ($isHome) {
				$site = $this->siteContext->getSite();
				$content[] = (string)($site['footer'] ?? '');
				$content[] = (string)($site['copyright'] ?? '');
				if ($this->siteContext->hasFeature('contact')) $content[] = (string)($site['contact'] ?? '');

				$hasRootBlock = (bool)array_filter($pageBlocks, static function(array $block): bool {
					return (int)$block['parentid'] === 0;
				});

				if (!$hasRootBlock) {
					$slideDb = new Shops_Model_DbTable_Slide();
					$slide = $slideDb->getByPosition('home');
					if ($slide) {
						$mediaDb = new Shops_Model_DbTable_Media();
						foreach ($mediaDb->getSlideImages((int)$slide['id']) as $media) {
							$blockText['headings'][] = (string)($media['title'] ?? '');
							$blockText['content'][] = (string)($media['description'] ?? '');
						}
					}
				}
			}

			$candidates[] = [
				'type' => 'page',
				'id' => (int)$page['id'],
				'title' => (string)($page['title'] ?: $this->siteContext->getTitle()),
				'url' => '/' . ltrim((string)$path, '/'),
				'titles' => [(string)($page['title'] ?? ''), (string)($page['metatitle'] ?? '')],
				'headings' => $blockText['headings'],
				'content' => array_merge($content, $blockText['content']),
				'descriptions' => [(string)($page['metadescription'] ?? '')],
			];
		}

		return $candidates;
	}

	protected function getCategoryCandidates(): array
	{
		if (!$this->siteContext->hasFeature('catalog')) {
			return [];
		}

		$categories = (new Shops_Model_DbTable_Category())->getCategories();
		$mediaByCategory = [];
		$mediaDb = new Shops_Model_DbTable_Media();

		foreach ($mediaDb->getByParentIds(array_keys($categories), 'shops', 'category') as $media) {
			$mediaByCategory[(int)$media['parentid']][] = (string)($media['title'] ?? '');
		}

		$candidates = [];
		foreach ($categories as $category) {
			$path = $this->slugDb->getPath('category', (int)$category['id']);
			if (!$path) continue;

			$candidates[] = [
				'type' => 'category',
				'id' => (int)$category['id'],
				'title' => (string)$category['title'],
				'url' => '/' . ltrim($path, '/'),
				'titles' => [(string)$category['title'], (string)$category['metatitle']],
				'headings' => [(string)$category['subtitle'], (string)$category['keyword']],
				'content' => array_merge([(string)$category['header'], (string)$category['shortdescription'], (string)$category['description'], (string)$category['minidescription'], (string)$category['footer']], $mediaByCategory[(int)$category['id']] ?? []),
				'descriptions' => [(string)$category['metadescription']],
			];
		}

		return $candidates;
	}

	protected function getTagCandidates(): array
	{
		if (!$this->siteContext->hasFeature('catalog')) {
			return [];
		}

		$candidates = [];
		foreach ((new Shops_Model_DbTable_Tag())->getTags('shops', 'category') as $tag) {
			$path = $this->slugDb->getPath('tag', (int)$tag['id']);
			if (!$path) continue;

			$candidates[] = [
				'type' => 'tag',
				'id' => (int)$tag['id'],
				'title' => (string)$tag['title'],
				'url' => '/' . ltrim($path, '/'),
				'titles' => [(string)$tag['title']],
				'headings' => [(string)$tag['keyword']],
				'content' => [(string)$tag['header'], (string)$tag['description'], (string)$tag['footer']],
				'descriptions' => [],
			];
		}

		return $candidates;
	}

	protected function countItems(array $terms): int
	{
		$select = $this->getItemSelect($terms)->reset(Zend_Db_Select::COLUMNS)->columns('COUNT(DISTINCT i.id)');
		return (int)Zend_Db_Table::getDefaultAdapter()->fetchOne($select);
	}

	protected function getItemCandidates(array $terms, int $limit): array
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$select = $this->getItemSelect($terms)
			->columns(['i.*'])
			->order(new Zend_Db_Expr($this->getItemOrder($terms) . ' DESC'))
			->order('i.title ASC')
			->order('i.id ASC')
			->limit(max(self::PER_PAGE, $limit));

		$items = $db->fetchAll($select);
		$supplemental = $this->getItemSupplementalText(array_column($items, 'id'));
		$candidates = [];
		foreach ($items as $item) {
			$path = $this->slugDb->getPath('item', (int)$item['id']);
			if (!$path) continue;
			$fallbackDescription = empty($item['shopdescription']) || empty($item['shopdescriptionshort']) ? (string)$item['description'] : '';

			$candidates[] = [
				'type' => 'item',
				'id' => (int)$item['id'],
				'title' => (string)($item['shoptitle'] ?: $item['title']),
				'url' => '/' . ltrim($path, '/'),
				'titles' => [(string)$item['shoptitle'], (string)$item['title']],
				'identifiers' => [(string)$item['sku'], (string)$item['manufacturersku'], (string)$item['gtin']],
				'headings' => array_merge([(string)$item['subtitle']], $supplemental[(int)$item['id']]['headings'] ?? []),
				'content' => array_merge([(string)$item['shopdescriptionshort']], $supplemental[(int)$item['id']]['content'] ?? []),
				'descriptions' => [(string)$item['shopdescription'], $fallbackDescription],
				'sku' => (string)$item['sku'],
			];
		}

		return $candidates;
	}

	protected function getItemSupplementalText(array $itemIds): array
	{
		$itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
		if (!$itemIds) return [];

		$db = Zend_Db_Table::getDefaultAdapter();
		$clientId = $this->siteContext->getClientId();
		$text = [];

		$media = $db->select()
			->from('media', ['parentid', 'title'])
			->where('parentid IN (?)', $itemIds)
			->where('module = ?', 'items')
			->where('controller = ?', 'item')
			->where('clientid = ?', $clientId)
			->where('deleted = ?', 0);

		foreach ($db->fetchAll($media) as $row) {
			$text[(int)$row['parentid']]['content'][] = (string)$row['title'];
		}

		foreach ([['itematrset', 'itematr', 'atrsetid'], ['itemoptset', 'itemopt', 'optsetid']] as [$setTable, $positionTable, $setField]) {
			$sets = $db->select()
				->from(['s' => $setTable], ['parentid', 'title'])
				->where('s.parentid IN (?)', $itemIds)
				->where('s.clientid = ?', $clientId)
				->where('s.deleted = ?', 0);

			foreach ($db->fetchAll($sets) as $row) {
				$text[(int)$row['parentid']]['headings'][] = (string)$row['title'];
			}

			$positions = $db->select()
				->from(['p' => $positionTable], ['parentid', 'title', 'description'])
				->joinInner(['s' => $setTable], 's.id = p.' . $setField . ' AND s.parentid = p.parentid', [])
				->where('p.parentid IN (?)', $itemIds)
				->where('p.clientid = ?', $clientId)
				->where('p.deleted = ?', 0)
				->where('s.clientid = ?', $clientId)
				->where('s.deleted = ?', 0);

			foreach ($db->fetchAll($positions) as $row) {
				$text[(int)$row['parentid']]['content'][] = (string)$row['title'];
				$text[(int)$row['parentid']]['content'][] = (string)$row['description'];
			}
		}

		return $text;
	}

	protected function getItemSelect(array $terms): Zend_Db_Select
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$termWhere = [];

		foreach ($terms as $term) {
			$termWhere[] = 'LOCATE(' . $db->quote($term) . ', ' . $this->getItemHaystack() . ') > 0';
		}

		$matchWhere = '(' . implode(' OR ', $termWhere) . ')';
		$supplementalIds = $this->getSupplementalMatchingItemIds($terms);
		if ($supplementalIds) $matchWhere .= ' OR i.id IN (' . implode(',', $supplementalIds) . ')';

		return $db->select()
			->from(['i' => 'item'], [])
			->joinInner(['c' => 'category'], 'c.id = i.shopcatid', [])
			->where('i.clientid = ?', $this->siteContext->getClientId())
			->where('i.shopid = ?', $this->siteContext->getSiteId())
			->where('i.deleted = ?', 0)
			->where('i.shopenabled = ?', 1)
			->where('c.clientid = ?', $this->siteContext->getClientId())
			->where('c.shopid = ?', $this->siteContext->getSiteId())
			->where('c.type = ?', 'shop')
			->where('c.activated = ?', 1)
			->where('c.deleted = ?', 0)
			->where('EXISTS (SELECT 1 FROM slug s WHERE s.entityid = i.id AND s.module = ' . $db->quote('shops') . ' AND s.controller = ' . $db->quote('item') . ' AND s.clientid = i.clientid AND s.shopid = i.shopid AND s.deleted = 0 AND s.slug <> ' . $db->quote('') . ')')
			->where($matchWhere);
	}

	protected function getItemHaystack(): string
	{
		return "LOWER(CONCAT_WS(' ', i.sku, i.gtin, i.title, i.subtitle, i.shoptitle, i.shopdescription, i.shopdescriptionshort, IF(COALESCE(i.shopdescription, '') = '' OR COALESCE(i.shopdescriptionshort, '') = '', i.description, ''), i.manufacturersku))";
	}

	protected function getSupplementalMatchingItemIds(array $terms): array
	{
		$key = implode("\n", $terms);
		if (isset($this->supplementalMatchCache[$key])) return $this->supplementalMatchCache[$key];

		$db = Zend_Db_Table::getDefaultAdapter();
		$clientId = $this->siteContext->getClientId();
		$ids = [];

		$mediaWhere = [];
		foreach ($terms as $term) $mediaWhere[] = 'LOCATE(' . $db->quote($term) . ", LOWER(COALESCE(title, ''))) > 0";
		$media = $db->select()
			->distinct()
			->from('media', ['parentid'])
			->where('module = ?', 'items')
			->where('controller = ?', 'item')
			->where('clientid = ?', $clientId)
			->where('deleted = ?', 0)
			->where(implode(' OR ', $mediaWhere));

		foreach ($db->fetchCol($media) as $id) $ids[(int)$id] = true;

		foreach ([['itematrset', 'itematr', 'atrsetid'], ['itemoptset', 'itemopt', 'optsetid']] as [$setTable, $positionTable, $setField]) {
			$positionWhere = [];
			foreach ($terms as $term) {
				$positionWhere[] = 'LOCATE(' . $db->quote($term) . ", LOWER(CONCAT_WS(' ', p.title, p.description, s.title))) > 0";
			}

			$positions = $db->select()
				->distinct()
				->from(['p' => $positionTable], ['parentid'])
				->joinInner(['s' => $setTable], 's.id = p.' . $setField . ' AND s.parentid = p.parentid', [])
				->where('p.clientid = ?', $clientId)
				->where('p.deleted = ?', 0)
				->where('s.clientid = ?', $clientId)
				->where('s.deleted = ?', 0)
				->where(implode(' OR ', $positionWhere));

			foreach ($db->fetchCol($positions) as $id) $ids[(int)$id] = true;
		}

		unset($ids[0]);
		return $this->supplementalMatchCache[$key] = array_keys($ids);
	}

	protected function getItemOrder(array $terms): string
	{
		$db = Zend_Db_Table::getDefaultAdapter();
		$title = "LOWER(TRIM(COALESCE(NULLIF(i.shoptitle, ''), i.title)))";
		$allTerms = [];
		$score = [];

		foreach ($terms as $term) {
			$quoted = $db->quote($term);
			$allTerms[] = 'LOCATE(' . $quoted . ', ' . $this->getItemHaystack() . ') > 0';
			$score[] = '(CASE WHEN LOWER(TRIM(i.sku)) = ' . $quoted . ' OR ' . $title . ' = ' . $quoted . ' THEN 1000 WHEN LOCATE(' . $quoted . ', LOWER(i.sku)) = 1 OR LOCATE(' . $quoted . ', ' . $title . ') = 1 THEN 500 WHEN LOCATE(' . $quoted . ', ' . $title . ') > 0 THEN 200 ELSE 20 END)';
		}

		$supplementalIds = $this->getSupplementalMatchingItemIds($terms);
		$supplementalScore = $supplementalIds ? ' + (CASE WHEN i.id IN (' . implode(',', $supplementalIds) . ') THEN 100 ELSE 0 END)' : '';
		return '(CASE WHEN ' . implode(' AND ', $allTerms) . ' THEN 400 ELSE 0 END) + ' . implode(' + ', $score) . $supplementalScore;
	}

	protected function getVisibleBlockText(array $blocks): array
	{
		$children = [];
		foreach ($blocks as $block) {
			if (empty($block['activated']) || !empty($block['deleted'])) continue;
			$children[(int)$block['parentid']][] = $block;
		}

		$text = ['headings' => [], 'content' => []];
		$this->collectBlockText($children, 0, null, $text);
		return $text;
	}

	protected function collectBlockText(array $children, int $parentId, ?string $parentType, array &$text): void
	{
		foreach ($children[$parentId] ?? [] as $block) {
			$type = (string)($block['type'] ?? '');
			try {
				$definition = DEEC_Site_Block::getDefinition($type);
			} catch (InvalidArgumentException $e) {
				continue;
			}

			$requiredParent = (string)($definition['parent'] ?? '');
			if (($parentType === null && $requiredParent !== '') || ($parentType !== null && $requiredParent !== $parentType)) continue;
			if ($type === 'contact' && !$this->siteContext->hasFeature('contact')) continue;
			if ($type === 'categories' && !$this->siteContext->hasFeature('catalog')) continue;

			$data = json_decode((string)($block['data'] ?? ''), true);
			if (!is_array($data)) continue;

			foreach ($this->getVisibleBlockFields($type, $data) as $field) {
				$value = (string)($data[$field] ?? '');
				if ($value === '') continue;
				if (in_array($field, ['title', 'eyebrow', 'trust'], true)) $text['headings'][] = $value;
				else $text['content'][] = $value;
			}

			$this->collectBlockText($children, (int)$block['id'], $type, $text);
		}
	}

	protected function getVisibleBlockFields(string $type, array $data): array
	{
		$fields = [
			'cta' => ['eyebrow', 'title', 'text'],
			'hero' => ['eyebrow', 'title', 'text', 'trust'],
			'text' => ['title', 'text'],
			'image_text' => ['title', 'text'],
			'contact' => ['title'],
			'categories' => ['title'],
			'cards' => ['eyebrow', 'title', 'text'],
			'card' => ['eyebrow', 'title', 'text'],
		][$type] ?? [];

		if (!empty($data['image']) && in_array($type, ['image_text', 'card'], true)) $fields[] = 'imagealt';
		if ($this->isVisibleUrl((string)($data['buttonurl'] ?? ''))) $fields[] = 'buttonlabel';
		if ($this->isVisibleUrl((string)($data['buttonurl2'] ?? ''))) $fields[] = 'buttonlabel2';

		return $fields;
	}

	protected function isVisibleUrl(string $url): bool
	{
		$url = trim($url);
		return $url !== '' && (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) || (bool)preg_match('~^(https?|mailto|tel):~i', $url));
	}

	protected function prepareCandidates(array $candidates, string $query, array $terms): array
	{
		$prepared = [];

		foreach ($candidates as $candidate) {
			$score = $this->scoreCandidate($candidate, $query, $terms);
			if ($score <= 0 || empty($candidate['url'])) continue;

			$candidate['score'] = $score;
			$candidate['excerpt'] = $this->createExcerpt($candidate, $terms);
			$key = (string)$candidate['url'];

			if (!isset($prepared[$key]) || $prepared[$key]['score'] < $score) {
				$prepared[$key] = $candidate;
			}
		}

		return array_values($prepared);
	}

	protected function scoreCandidate(array $candidate, string $query, array $terms): int
	{
		$groups = [];
		foreach (['identifiers', 'titles', 'headings', 'content', 'descriptions'] as $group) {
			$groups[$group] = array_map([self::class, 'normalize'], $candidate[$group] ?? []);
		}

		$score = 0;
		$matchedTerms = 0;

		foreach ($groups['identifiers'] as $value) {
			if ($value === $query) $score = max($score, 1200);
			elseif ($query !== '' && str_starts_with($value, $query)) $score = max($score, 700);
		}

		foreach ($groups['titles'] as $value) {
			if ($value === $query) $score = max($score, 1100);
			elseif ($query !== '' && str_starts_with($value, $query)) $score = max($score, 650);
		}

		foreach ($terms as $term) {
			$termMatched = false;
			foreach (['identifiers' => 280, 'titles' => 220, 'headings' => 150, 'content' => 55, 'descriptions' => 35] as $group => $weight) {
				foreach ($groups[$group] as $value) {
					if ($value !== '' && str_contains($value, $term)) {
						$score += $weight;
						$termMatched = true;
						break;
					}
				}
			}

			if ($termMatched) $matchedTerms++;
		}

		if ($matchedTerms === count($terms)) $score += 400;
		return $matchedTerms > 0 ? $score : 0;
	}

	protected function createExcerpt(array $candidate, array $terms): string
	{
		$values = array_merge($candidate['headings'] ?? [], $candidate['content'] ?? [], $candidate['descriptions'] ?? []);
		$selected = '';

		foreach ($values as $value) {
			$plain = self::cleanText((string)$value);
			if ($plain === '') continue;
			if ($selected === '') $selected = $plain;
			$normalized = self::normalize($plain);
			if (array_filter($terms, static fn(string $term): bool => str_contains($normalized, $term))) {
				$selected = $plain;
				break;
			}
		}

		if ($selected === '') return '';
		if (mb_strlen($selected, 'UTF-8') <= 220) return $selected;

		$normalized = self::normalize($selected);
		$position = null;
		foreach ($terms as $term) {
			$found = mb_strpos($normalized, $term, 0, 'UTF-8');
			if ($found !== false && ($position === null || $found < $position)) $position = $found;
		}

		$start = max(0, (int)$position - 70);
		$excerpt = trim(mb_substr($selected, $start, 220, 'UTF-8'));
		return ($start > 0 ? '…' : '') . $excerpt . (mb_strlen($selected, 'UTF-8') > $start + 220 ? '…' : '');
	}

	protected function sortCandidates(array &$candidates): void
	{
		usort($candidates, static function(array $a, array $b): int {
			return ($b['score'] <=> $a['score'])
				?: strnatcasecmp((string)$a['title'], (string)$b['title'])
				?: strcmp((string)$a['type'], (string)$b['type'])
				?: ((int)$a['id'] <=> (int)$b['id']);
		});
	}

	protected function getTerms(string $query): array
	{
		$terms = preg_split('/\s+/u', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		return array_slice(array_values(array_unique($terms)), 0, 10);
	}

	public static function cleanText(string $value): string
	{
		$value = preg_replace('/\{[^}]+\}/u', ' ', $value);
		$value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return trim((string)preg_replace('/\s+/u', ' ', $value));
	}

	public static function normalize(string $value): string
	{
		return mb_strtolower(self::cleanText($value), 'UTF-8');
	}
}
