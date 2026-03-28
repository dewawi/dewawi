<?php

class Application_Controller_Action_Helper_Options extends Zend_Controller_Action_Helper_Abstract
{
	protected $cache = [];

	public function getOptions($form): array
	{
		$options = [];

		// 1) element options (select lists)
		if ($form && method_exists($form, 'getElements') && method_exists($form, 'addOptions')) {
			$options = $this->applyFormOptions($form);
		}

		// 2) layout options (full rows, trees, tags, etc.)
		//$layout = $this->getLayoutOptions();

		return $options;
	}

	public function applyFormOptions($form): array
	{
		$options = [];

		foreach ($form->getElements() as $el) {
			$name = $el['name'] ?? null;
			$source = $el['source'] ?? null;

			if (!$name || !$source) continue;

			$opts = $this->loadBySource((string)$source);
			if (!$opts) continue;

			$form->addOptions((string)$name, $opts);
			$options[(string)$name] = $opts;
		}

		return $options;
	}

	public function getLayoutOptions(): array
	{
		$out = [];

		// full category data for menu
		//$out['itemcategories'] = $this->loadCategoriesFull('item');
		//$out['categories'] = $this->loadCategoriesFull('contact');

		/*//Get countries
		$countryDb = new Application_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$out['countries'] = $countries;

		//Get states
		$stateDb = new Application_Model_DbTable_State();
		$states = $stateDb->getStates();
		$out['states'] = $states;

		//Get payment methods
		$paymentmethodDb = new Application_Model_DbTable_Paymentmethod();
		$paymentmethods = $paymentmethodDb->getPaymentmethods();
		$out['paymentmethods'] = $paymentmethods;

		//Get currencies
		$currencyDb = new Application_Model_DbTable_Currency();
		$currencies = $currencyDb->getCurrencies();
		$out['currencies'] = $currencies;

		//Get price rule actions
		$priceruleactionDb = new Application_Model_DbTable_Priceruleaction();
		$priceruleactions = $priceruleactionDb->getPriceruleactions();
		$out['priceruleactions'] = $priceruleactions;

		//Get tags
		$tagDb = new Application_Model_DbTable_Tag();
		$tags = $tagDb->getTags('contacts', 'contact');
		$out['tags'] = $tags;

		//Get download sets
		$downloadsetDb = new Contacts_Model_DbTable_Downloadset();
		$downloadsets = $downloadsetDb->getDownloadsets();
		$out['downloadsets'] = $downloadsets;

		//Get templates
		$templateDb = new Application_Model_DbTable_Template();
		$templates = $templateDb->getTemplates();
		$out['templates'] = $templates;

		//Get languages
		$languageDb = new Application_Model_DbTable_Language();
		$languages = $languageDb->getLanguages();
		$out['languages'] = $languages;

		//Get users
		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();
		$out['users'] = $users;*/

		return $out;
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
