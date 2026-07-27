<?php

abstract class DEEC_Controller_PositionAction extends DEEC_Controller_Action
{
	protected function getPositionModule(): string
	{
		return $this->getRequest()->getModuleName();
	}

	protected function getPositionParams(): array
	{
		return [
			'parent' => (string)$this->_getParam('parent'),
			'type' => (string)$this->_getParam('type', 'pos'),
			'parentid' => (int)$this->_getParam('parentid'),
			'setid' => (int)$this->_getParam('setid', 0),
			'masterid' => $this->normalizeMasterId(
				$this->_getParam('masterid')
			),
		];
	}

	protected function getParentDb()
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
		);

		return new $className();
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

	protected function getPositionSetDb()
	{
		$params = $this->getPositionParams();
		$className = $this->buildDbTableClass(
			$params['parent']
			. $params['type']
			. 'set'
		);

		return new $className();
	}

	public function editAction()
	{
		$this->disablePositionRendering();

		$request = $this->getRequest();

		if (!$request->isPost()) {
			return;
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

		$form = $this->buildPositionFormForRequest(
			$params,
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

	protected function affectsPositionCalculation(array $values): bool {
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

	protected function calculatePositionsParent(array $params): array {
		return $this->_helper->Calculate(
			$params['parentid'],
			$this->_date,
			$this->_user['id']
		);
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

		if (!class_exists($className)) {
			throw new RuntimeException(
				'Position form '
				. $className
				. ' was not found'
			);
		}

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
		$taxrateDb =
			new Application_Model_DbTable_Taxrate();

		return $taxrateDb->getTaxrates();
	}

	protected function getPrimaryPositionTaxrate(): array
	{
		$taxrateDb =
			new Application_Model_DbTable_Taxrate();

		return $taxrateDb->getPrimaryTaxrate();
	}

	protected function buildPositionForm($position, array $uoms, array $taxrates, array $orderingOptions, $locale, bool $readonly = false): DEEC_Form {
		$form = $this->createPositionForm();

		$form->setValues(
			$position->toArray()
		);

		$form->addOptions(
			'uom',
			$uoms,
			'replace'
		);

		if ($position->uom) {
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
			$this->buildTaxrateOptions(
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

	protected function buildTaxrateOptions(array $taxrates, $locale): array {
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

	protected function buildPositionFormForRequest(array $params, $locale): DEEC_Form {
		$form = $this->createPositionForm();

		$form->addOptions(
			'uom',
			$this->getPositionUoms(),
			'replace'
		);

		$form->addOptions(
			'ordering',
			$this->_helper->Ordering->getOrdering(
				$params['parent'],
				$params['type'],
				$params['parentid'],
				$params['setid']
			),
			'replace'
		);

		$form->addOptions(
			'taxrate',
			$this->buildTaxrateOptions(
				$this->getPositionTaxrates(),
				$locale
			),
			'replace'
		);

		return $form;
	}

	protected function normalizeMasterId($masterId): ?int
	{
		$masterId = (int)$masterId;

		return $masterId > 0
			? $masterId
			: null;
	}

	protected function disablePositionRendering(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$this->copyPositions($oldId, $newId);
	}

	protected function copyPositions(int $oldId, int $newId): void
	{
		$positionsDbClass = $this->getPositionsDbTableClass();

		if(!class_exists($positionsDbClass)) {
			return;
		}

		$positionsDb = new $positionsDbClass();

		if(!method_exists($positionsDb, 'getPositions')) {
			return;
		}

		$positions = $positionsDb->getPositions($oldId);

		$this->_helper->Position->copyPositions(
			$positions,
			$newId,
			$this->getRequest()->getModuleName(),
			$this->getRequest()->getControllerName(),
			$this->_date
		);
	}

	public function sortAction()
	{
		$this->disablePositionRendering();

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

	public function deleteAction()
	{
		$this->disablePositionRendering();

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

		$calculations = $this->_helper->Calculate(
			$params['parentid'],
			$this->_date,
			$this->_user['id']
		);

		return $this->_helper->json(
			$calculations['locale']
		);
	}

	protected function getPositionsDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName()
			. 'pos';
	}
}
