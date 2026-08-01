<?php

abstract class DEEC_Controller_PositionAction extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->disableLayout();

		$params = $this->getPositionParams();
		$this->validatePositionParams($params);

		$parent = $this->getParentDb()->getById(
			$params['parentid']
		);

		if (!$parent) {
			throw new RuntimeException(
				'Position parent was not found'
			);
		}

		$this->beforeBuildPositionIndex(
			$params
		);

		$positionsDb = $this->getPositionDb();

		$positions = $positionsDb->getPositions(
			$params['parentid']
		);

		$positionSets = $this->getPositionSetDb()
			->getPositionSets(
				$params['parentid']
			);

		$locale = Zend_Registry::get(
			'Zend_Locale'
		);

		$uoms = $this->getPositionUoms();
		$taxrates = $this->getPositionTaxrates();

		$currencyHelper = $this->_helper->Currency;
		$currency = $currencyHelper->getCurrency();

		$context = $this->buildPositionIndexContext(
			$params,
			$parent,
			$positions
		);

		$sets = $this->buildPositionSets(
			$positionSets
		);

		$taxes = $this->buildPositionTaxes(
			$parent,
			$positions,
			$taxrates,
			$locale
		);

		$forms = [];
		$childs = [];
		$options = [];

		foreach ($positions as $position) {
			$positionId = (int)$position->id;

			$masterId = $this->normalizeMasterId(
				$position->masterid
			);

			$price = $this->getPositionDisplayPrice(
				$position,
				$context
			);

			$this->addPositionTax(
				$taxes,
				$parent,
				$position,
				$price
			);

			$this->formatPositionValues(
				$position,
				$price,
				$currencyHelper,
				$currency,
				$locale
			);

			$setField =
				$params['type'] . 'setid';

			$setId = (int)$position->{$setField};

			$form = $this->buildPositionForm(
				$position,
				$uoms,
				$taxrates,
				$positionsDb->getPositionOrderings(
					$params['parentid'],
					$setId,
					$masterId
				),
				$locale,
				$this->isPositionParentReadonly(
					$parent
				)
			);

			if ($masterId !== null) {
				$childs[$masterId][] = $form;
			} else {
				$forms[$setId][$positionId]['form'] =
					$form;
			}

			$positionOptions =
				$this->getPositionOptions(
					$position
				);

			if ($positionOptions !== null) {
				$options[$positionId] =
					$positionOptions;
			}
		}

		$this->formatPositionIndexContext(
			$context,
			$currency
		);

		$this->formatPositionParent(
			$parent,
			$taxes,
			$params,
			$currencyHelper,
			$currency
		);

		$this->view->assign([
			'sets' => $sets,
			'forms' => $forms,
			'childs' => $childs,
			'parent' => $parent,
			'options' => $options,
			'pricerules' => $context['priceRules'] ?? [],
			'toolbar' =>
				$this->getPositionToolbar(),
			'toolbarPositions' =>
				$this->getPositionToolbar(),
		]);
	}

	protected function addPositionTax(
		array &$taxes,
		array $parent,
		$position,
		float $price
	): void {
		if (!empty($parent['taxfree'])) {
			return;
		}

		$taxrateKey = (string)$position->taxrate;

		if (!array_key_exists($taxrateKey, $taxes)) {
			return;
		}

		$taxes[$taxrateKey]['value'] +=
			$price
			* (float)$position->quantity
			* (float)$position->taxrate
			/ 100;
	}

	protected function formatPositionValues(
		$position,
		float $price,
		$currencyHelper,
		$currency,
		$locale
	): void {
		$currencyHelper->setCurrency(
			$currency,
			$position->currency,
			'USE_SYMBOL'
		);

		$position->total = $currency->toCurrency(
			$price * (float)$position->quantity
		);

		$currencyHelper->setCurrency(
			$currency,
			$position->currency,
			'NO_SYMBOL'
		);

		$position->price = $currency->toCurrency(
			$position->price
		);

		$position->quantity =
			Zend_Locale_Format::toNumber(
				$position->quantity,
				[
					'precision' => 2,
					'locale' => $locale,
				]
			);
	}

	protected function beforeBuildPositionIndex(
		array $params
	): void {
		$this->_helper->Calculate(
			$params['parentid'],
			$this->_date,
			$this->_user['id']
		);
	}

	protected function buildPositionIndexContext(
		array $params,
		array $parent,
		$positions
	): array {
		return [];
	}

	protected function isPositionParentReadonly(
		array $parent
	): bool {
		return in_array(
			(int)($parent['state'] ?? 0),
			[105, 106],
			true
		);
	}

	protected function getPositionOptions(
		$position
	) {
		if ((int)$position->itemid < 1) {
			return null;
		}

		$optionsDb =
			new Items_Model_DbTable_Itemopt();

		return $optionsDb->getPositions(
			(int)$position->itemid
		);
	}

	protected function formatPositionIndexContext(
		array &$context,
		$currency
	): void {
		if (empty($context['priceRules'])) {
			return;
		}

		foreach (
			$context['priceRules']
			as $positionId => $rules
		) {
			foreach ($rules as $key => $rule) {
				$context['priceRules']
					[$positionId]
					[$key]
					['amount'] =
						$currency->toCurrency(
							$rule['amount']
						);
			}
		}
	}

	protected function formatPositionParent(
		array &$parent,
		array $taxes,
		array $params,
		$currencyHelper,
		$currency
	): void {
		$currencyHelper->setCurrency(
			$currency,
			$parent['currency'],
			'USE_SYMBOL'
		);

		if (isset($parent['subtotal'])) {
			$parent['subtotal'] =
				$currency->toCurrency(
					$parent['subtotal']
				);
		}

		if (isset($parent['total'])) {
			$parent['total'] =
				$currency->toCurrency(
					$parent['total']
				);
		}

		foreach ($taxes as $rate => $tax) {
			$taxes[$rate]['value'] =
				$currency->toCurrency(
					$tax['value']
				);
		}

		$parent['taxes'] = $taxes;
		$parent['type'] = $params['parent'];
	}

	protected function getPositionToolbar()
	{
		$className =
			$this->getModuleClassPrefix()
			. '_Form_ToolbarPositions';

		if (!class_exists($className)) {
			return null;
		}

		return new $className();
	}

	protected function getPositionModule(): string
	{
		return $this->getRequest()->getModuleName();
	}

	protected function getPositionParams(): array
	{
		return [
			'id' => (int)$this->_getParam('id', 0),
			'parent' => (string)$this->_getParam('parent'),
			'type' => (string)$this->_getParam('type', 'pos'),
			'parentid' => (int)$this->_getParam('parentid'),
			'setid' => (int)$this->_getParam('setid', 0),
			'masterid' => $this->normalizeMasterId(
				$this->_getParam('masterid')
			),
		];
	}

	protected function buildPositionSets(
		$positionSets
	): array {
		if (!count($positionSets)) {
			return [
				0 => [
					'title' => '',
				],
			];
		}

		$sets = [];

		foreach ($positionSets as $positionSet) {
			$sets[(int)$positionSet->id] = [
				'title' => $positionSet->title,
			];
		}

		return $sets;
	}

	protected function buildPositionTaxes(
		array $parent,
		$positions,
		array $taxrates,
		$locale
	): array {
		if (!empty($parent['taxfree'])) {
			return [
				0 => [
					'value' => 0,
					'title' => 0,
				],
			];
		}

		$taxes = [];

		foreach ($positions as $position) {
			$taxrateId = array_search(
				$position->taxrate,
				$taxrates,
				true
			);

			if ($taxrateId === false) {
				continue;
			}

			$rate = (string)$position->taxrate;

			if (isset($taxes[$rate])) {
				continue;
			}

			$taxes[$rate] = [
				'value' => 0,
				'title' =>
					Zend_Locale_Format::toNumber(
						$position->taxrate,
						[
							'precision' => 1,
							'locale' => $locale,
						]
					)
					. ' %',
			];
		}

		return $taxes;
	}

	protected function getPositionDisplayPrice(
		$position,
		array $context
	): float {
		$positionId = (int)$position->id;

		$masterId = $this->normalizeMasterId(
			$position->masterid
		);

		$priceRules =
			$context['priceRules'] ?? [];

		$priceRuleMasters =
			$context['priceRuleMasters'] ?? [];

		if (
			$masterId !== null
			&& !empty($priceRuleMasters[$masterId])
			&& isset($priceRules[$masterId])
		) {
			return (float)$this->_helper->PriceRule
				->usePriceRules(
					$priceRules[$masterId],
					$position->price
				);
		}

		if (
			$masterId === null
			&& isset($priceRules[$positionId])
		) {
			return (float)$this->_helper->PriceRule
				->usePriceRules(
					$priceRules[$positionId],
					$position->price
				);
		}

		return (float)$position->price;
	}

	protected function getParentDb(): DEEC_Model_DbTable_Entity
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
		);

		if (!class_exists($className)) {
			throw new RuntimeException(
				'Parent DB class not found: ' . $className
			);
		}

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			throw new RuntimeException(
				$className
				. ' must extend DEEC_Model_DbTable_Entity'
			);
		}

		return $db;
	}

	protected function getPositionDb(): DEEC_Model_DbTable_Position
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent'] . $params['type']
		);

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Position) {
			throw new RuntimeException(
				$className
				. ' must extend DEEC_Model_DbTable_Position'
			);
		}

		return $db;
	}

	protected function getPositionSetDb(): DEEC_Model_DbTable_Entity
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
			. $params['type']
			. 'set'
		);

		if (!class_exists($className)) {
			throw new RuntimeException(
				'Position set DB class not found: '
				. $className
			);
		}

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			throw new RuntimeException(
				$className
				. ' must extend DEEC_Model_DbTable_Entity'
			);
		}

		return $db;
	}

	protected function buildDbTableClass(
		string $name
	): string {
		return ucfirst($this->getPositionModule())
			. '_Model_DbTable_'
			. ucfirst($name);
	}

	protected function getPositionFormClass(): string
	{
		$params = $this->getPositionParams();

		return ucfirst($this->getPositionModule())
			. '_Form_'
			. ucfirst(
				$params['parent']
				. $params['type']
			);
	}

	protected function createPositionForm(): DEEC_Form
	{
		$className = $this->getPositionFormClass();
		$form = new $className();

		if (!$form instanceof DEEC_Form) {
			throw new RuntimeException(
				$className
				. ' must extend DEEC_Form'
			);
		}

		return $form;
	}

	protected function getPositionUoms(): array
	{
		$uomDb = new Application_Model_DbTable_Uom();

		return $uomDb->getUoms();
	}

	protected function getPositionTaxrates(): array
	{
		$taxrateDb = new Application_Model_DbTable_Taxrate();

		return $taxrateDb->getTaxrates();
	}

	protected function getPrimaryPositionTaxrate(): array
	{
		$taxrateDb = new Application_Model_DbTable_Taxrate();

		return $taxrateDb->getPrimaryTaxrate();
	}

	protected function buildPositionTaxrateOptions(array $taxrates, $locale): array {
		$options = [];

		foreach ($taxrates as $id => $rate) {
			$options[$id] =
				Zend_Locale_Format::toNumber(
					$rate,
					[
						'precision' => 1,
						'locale' => $locale,
					]
				)
				. ' %';
		}

		return $options;
	}

	protected function buildPositionForm(
		$position,
		array $uoms,
		array $taxrates,
		array $orderingOptions,
		$locale,
		bool $readonly = false
	): DEEC_Form {
		$form = $this->createPositionForm();

		$form->setValues(
			$position->toArray()
		);

		$form->addOptions(
			'uom',
			$uoms,
			'replace'
		);

		if ($position->uom !== '') {
			$uomId = array_search(
				$position->uom,
				$uoms,
				true
			);

			if ($uomId !== false) {
				$form->setValue(
					'uom',
					$uomId
				);
			}
		}

		$form->addOptions(
			'ordering',
			$orderingOptions,
			'replace'
		);

		$form->addOptions(
			'taxrate',
			$this->buildPositionTaxrateOptions(
				$taxrates,
				$locale
			),
			'replace'
		);

		$taxrateId = array_search(
			$position->taxrate,
			$taxrates,
			true
		);

		if ($taxrateId !== false) {
			$form->setValue(
				'taxrate',
				$taxrateId
			);
		}

		if ($readonly) {
			$form->setMode('readonly');
		}

		return $form;
	}

	protected function normalizeMasterId($masterId): ?int
	{
		$masterId = (int)$masterId;

		return $masterId > 0
			? $masterId
			: null;
	}

	protected function preparePositionEditValues(array $values, array $post, array $uoms, array $taxrates, $locale): array {
		$element = key($post);

		if (
			$element === 'taxrate'
			&& isset($values['taxrate'])
			&& $values['taxrate'] != 0
		) {
			$values['taxrate'] =
				$taxrates[$values['taxrate']] ?? 0;
		}

		if (
			in_array(
				$element,
				[
					'price',
					'quantity',
					'priceruleamount',
				],
				true
			)
			&& isset($values[$element])
		) {
			$values[$element] =
				Zend_Locale_Format::getNumber(
					$post[$element],
					[
						'precision' => 2,
						'locale' => $locale,
					]
				);
		}

		if (
			$element === 'uom'
			&& isset($values['uom'])
			&& $values['uom'] != 0
		) {
			$values['uom'] =
				$uoms[$values['uom']] ?? '';
		}

		return $values;
	}

	public function addAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPositionError(
				'Invalid request method'
			);
		}

		$params = $this->getPositionParams();
		$this->validatePositionParams($params);

		$parent = $this->getParentDb()->getById(
			$params['parentid']
		);

		if (!$parent) {
			return $this->sendPositionError(
				'Parent was not found'
			);
		}

		$data = $this->getNewPositionData(
			$params,
			$parent
		);

		$id = $this->getPositionDb()->addPosition(
			$data
		);

		$this->afterPositionAdd(
			$id,
			$data,
			$params,
			$parent
		);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
		]);
	}

	protected function getNewPositionData(
		array $params,
		array $parent
	): array {
		$taxrate = $this->getPrimaryPositionTaxrate();

		$data = [
			'parentid' => $params['parentid'],
			$params['type'] . 'setid' =>
				$params['setid'],
			'masterid' => $params['masterid'],
			'itemid' => 0,
			'sku' => '',
			'title' => '',
			'image' => '',
			'description' => '',
			'price' => 0,
			'taxrate' => $taxrate['rate'],
			'quantity' => 1,
			'total' => 0,
			'currency' => $parent['currency'],
			'uom' => '',
		];

		$itemId = (int)$this->_getParam(
			'itemid',
			0
		);

		if ($itemId > 0) {
			$itemDb = new Items_Model_DbTable_Item();
			$item = $itemDb->getItemForEdit($itemId);

			if (!$item) {
				return $data;
			}

			$taxrates = $this->getPositionTaxrates();
			$uoms = $this->getPositionUoms();

			$data['itemid'] = (int)$item['id'];
			$data['sku'] = (string)$item['sku'];
			$data['title'] = (string)$item['title'];
			$data['description'] =
				(string)$item['description'];
			$data['price'] = (float)$item['price'];
			$data['currency'] =
				(string)($item['currency'] ?: $parent['currency']);

			$taxId = (int)($item['taxid'] ?? 0);

			if (isset($taxrates[$taxId])) {
				$data['taxrate'] = $taxrates[$taxId];
			}

			$uomId = (int)($item['uomid'] ?? 0);

			if (isset($uoms[$uomId])) {
				$data['uom'] = $uoms[$uomId];
			}

			return $data;
		}

		$optionId = (int)$this->_getParam(
			'optionid',
			0
		);

		if ($optionId < 1) {
			return $data;
		}

		$optionDb = new Items_Model_DbTable_Itemopt();
		$option = $optionDb->getPosition($optionId);

		if (!$option) {
			return $data;
		}

		$data['itemid'] = (int)$option['itemid'];
		$data['sku'] = $option['sku'];
		$data['title'] = $option['title'];
		$data['description'] = $option['description'];
		$data['price'] = $option['price'];
		$data['uom'] = $option['uom'];

		return $data;
	}

	protected function afterPositionAdd(
		int $id,
		array $data,
		array $params,
		array $parent
	): void {
	}

	public function editAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPositionError(
				'Invalid request method'
			);
		}

		$params = $this->getPositionParams();
		$this->validatePositionParams($params);

		if ($params['id'] < 1) {
			return $this->sendPositionError(
				'Position ID is missing'
			);
		}

		$locale = Zend_Registry::get(
			'Zend_Locale'
		);

		$uoms = $this->getPositionUoms();
		$taxrates = $this->getPositionTaxrates();

		$form = $this->buildPositionFormForRequest(
			$params,
			$uoms,
			$taxrates,
			$locale
		);

		$post = (array)$request->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages(
					$form->getErrors(),
					$form
				),
			]);
		}

		$values = $form->getFilteredValuesPartial(
			$post
		);

		$values = $this->preparePositionEditValues(
			$values,
			$post,
			$uoms,
			$taxrates,
			$locale
		);

		try {
			$this->getPositionDb()->updatePosition(
				$params['id'],
				$values
			);
		} catch (Throwable $exception) {
			return $this->sendPositionError(
				'save_failed'
			);
		}

		if ($this->affectsPositionCalculation($values)) {
			$calculations =
				$this->calculatePositionsParent(
					$params
				);

			return $this->_helper->json(
				$calculations['locale']
			);
		}

		return $this->_helper->json([
			'ok' => true,
			'id' => $params['id'],
			'values' => $values,
		]);
	}

	protected function buildPositionFormForRequest(
		array $params,
		array $uoms,
		array $taxrates,
		$locale
	): DEEC_Form {
		$form = $this->createPositionForm();

		$form->addOptions(
			'uom',
			$uoms,
			'replace'
		);

		$form->addOptions(
			'ordering',
			$this->getPositionDb()
				->getPositionOrderings(
					$params['parentid'],
					$params['setid'],
					$params['masterid']
				),
			'replace'
		);

		$form->addOptions(
			'taxrate',
			$this->buildPositionTaxrateOptions(
				$taxrates,
				$locale
			),
			'replace'
		);

		return $form;
	}

	protected function affectsPositionCalculation(
		array $values
	): bool {
		return (bool)array_intersect(
			array_keys($values),
			[
				'price',
				'quantity',
				'taxrate',
				'pricerulemaster',
			]
		);
	}

	public function sortAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendSortResponse(
				false,
				'Invalid request method'
			);
		}

		$id = (int)$this->_getParam('id', 0);
		$direction = (string)$this->_getParam(
			'direction',
			''
		);
		$ordering = (int)$this->_getParam(
			'ordering',
			0
		);

		if ($id < 1) {
			return $this->sendSortResponse(
				false,
				'Position ID is missing'
			);
		}

		$positionDb = $this->getPositionDb();

		if (
			$direction === 'up'
			|| $direction === 'down'
		) {
			$sorted = $positionDb->moveOrdering(
				$id,
				$direction
			);
		} elseif ($ordering > 0) {
			$sorted = $positionDb->moveToOrdering(
				$id,
				$ordering
			);
		} else {
			return $this->sendSortResponse(
				false,
				'Invalid sort target'
			);
		}

		return $this->sendSortResponse(
			$sorted,
			$sorted
				? null
				: 'Position could not be sorted'
		);
	}

	protected function sendSortResponse(
		bool $success,
		?string $message = null
	) {
		return $this->_helper->json([
			'ok' => $success,
			'message' => $message,
		]);
	}

	public function copyAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return $this->sendPositionError(
				'Invalid request method'
			);
		}

		$params = $this->getPositionParams();

		if ($params['id'] < 1) {
			return $this->sendPositionError(
				'Position ID is missing'
			);
		}

		$positionDb = $this->getPositionDb();

		try {
			$newId = $positionDb->copyPosition(
				$params['id']
			);

			$this->afterPositionCopy(
				$params['id'],
				$newId,
				$params
			);
		} catch (Throwable $exception) {
			return $this->sendPositionError(
				'copy_failed'
			);
		}

		$calculations = $this->calculatePositionsParent(
			$params
		);

		return $this->_helper->json(
			$calculations['locale']
		);
	}

	protected function afterPositionCopy(
		int $oldId,
		int $newId,
		array $params
	): void {
	}

	protected function validatePositionParams(array $params): void
	{
		if ($params['parent'] === '') {
			throw new InvalidArgumentException(
				'Position parent is missing'
			);
		}

		if ($params['parentid'] < 1) {
			throw new InvalidArgumentException(
				'Position parent ID is missing'
			);
		}

		if ($params['type'] === '') {
			throw new InvalidArgumentException(
				'Position type is missing'
			);
		}
	}

	protected function calculatePositionsParent(
		array $params
	): array {
		return $this->_helper->Calculate(
			$params['parentid'],
			$this->_date,
			$this->_user['id']
		);
	}

	public function deleteAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			return;
		}

		$data = $this->getRequest()->getPost();

		if (($data['delete'] ?? null) !== 'Yes') {
			return;
		}

		$ids = $data['id'] ?? [];

		if (!is_array($ids)) {
			$ids = [$ids];
		}

		$positionDb = $this->getPositionDb();
		$positionDb->deletePositions($ids);

		$params = $this->getPositionParams();

		$calculations = $this->calculatePositionsParent(
			$params
		);

		return $this->_helper->json(
			$calculations['locale']
		);
	}

	protected function sendPositionError(
		string $message
	) {
		return $this->_helper->json([
			'ok' => false,
			'message' => $message,
		]);
	}
}
