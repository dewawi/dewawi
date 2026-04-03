<?php

class Application_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	protected $cache = [];

	public function getOptions($form): array
	{
		$options = [];

		if ($form && method_exists($form, 'getElements') && method_exists($form, 'addOptions')) {
			$options = $this->applyFormOptions($form);
		}

		return $options;
	}

	public function applyFormOptions($form): array
	{
		$options = [];

		foreach ($form->getElements() as $el) {
			$name = isset($el['name']) ? (string)$el['name'] : '';
			if ($name === '') {
				continue;
			}

			$staticOptions = [];
			if (!empty($el['options']) && is_array($el['options'])) {
				foreach ($el['options'] as $k => $v) {
					$staticOptions[(string)$k] = $v;
				}
			}

			$sourceOptions = [];
			$source = isset($el['source']) ? trim((string)$el['source']) : '';
			if ($source !== '') {
				$sourceOptions = $this->loadBySource($source);
			}

			$finalOptions = $staticOptions + $sourceOptions;

			if (!empty($finalOptions)) {
				$form->addOptions($name, $finalOptions, 'replace');
				$options[$name] = $finalOptions;
			}
		}

		return $options;
	}

	protected function loadCategoriesFull(string $type): array
	{
		$key = 'categories_full:' . $type;
		if (isset($this->cache[$key])) return $this->cache[$key];

		$db = new Application_Model_DbTable_Category();
		// getCategories liefert volle rows (id => row array)
		$cats = (array)$db->getCategories($type);

		return $this->cache[$key] = $cats;
	}

	protected function loadBySource(string $source): array
	{
		if ($source === '') return [];
		if (isset($this->cache[$source])) return $this->cache[$source];

		// source format: "category:item:false" oder "taxrate" oder "tag:items:item"
		$parts = array_map('trim', explode(':', $source));
		$alias = strtolower($parts[0] ?? '');
		$args = array_slice($parts, 1);

		$class = $this->resolveModelClass($alias);
		if (!$class || !class_exists($class)) {
			return $this->cache[$source] = [];
		}

		$model = new $class();

		if (!method_exists($model, 'getSelectOptions')) {
			return $this->cache[$source] = [];
		}

		// args typisieren (true/false, ints)
		$typedArgs = [];
		foreach ($args as $a) {
			$la = strtolower($a);
			if ($la === 'true') { $typedArgs[] = true; continue; }
			if ($la === 'false') { $typedArgs[] = false; continue; }
			if (ctype_digit($a)) { $typedArgs[] = (int)$a; continue; }
			$typedArgs[] = $a;
		}

		$raw = call_user_func_array([$model, 'getSelectOptions'], $typedArgs);

		// normalize to string keys (html select values)
		$out = [];
		foreach ((array)$raw as $k => $v) {
			$out[(string)$k] = $v;
		}

		return $this->cache[$source] = $out;
	}

	protected function resolveModelClass(string $alias): ?string
	{
		if ($alias === '') return null;

		// singularize minimal:
		// categories -> category, taxrates -> taxrate, uoms -> uom
		if (substr($alias, -3) === 'ies') {
			$alias = substr($alias, 0, -3) . 'y';
		} elseif (substr($alias, -1) === 's') {
			$alias = substr($alias, 0, -1);
		}

		// studly
		$studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $alias)));

		return 'Application_Model_DbTable_' . $studly;
	}
}
