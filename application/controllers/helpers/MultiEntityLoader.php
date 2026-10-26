<?php

class Application_Controller_Action_Helper_MultiEntityLoader extends Zend_Controller_Action_Helper_Abstract
{
	public function populate(DEEC_Form $form, int $parentId, string $parentModule, string $parentController, ?int $clientId = null): void
	{
		$form->setMultiContext($parentId, $parentModule, $parentController);

		foreach ($form->getMultiElements() as $name => $element) {
			$module = (string)($element['module'] ?? '');
			$controller = (string)($element['controller'] ?? '');

			if ($module === '' || $controller === '') {
				continue;
			}

			$db = $this->createDbTable($module, $controller, $clientId);

			if (!$db) {
				continue;
			}

			$rows = $db->getByParentId($parentId, $parentModule, $parentController);
			$rows = $this->populateNestedRows(is_array($rows) ? $rows : [], $module, $controller, $clientId);

			$form->setElementData($name, ['rows' => $rows]);
		}
	}

	protected function populateNestedRows(array $rows, string $module, string $controller, ?int $clientId = null): array
	{
		$form = $this->createForm($module, $controller);

		if (!$form) {
			return $rows;
		}

		$elements = $form->getMultiElements();

		if (!$elements) {
			return $rows;
		}

		foreach ($rows as &$row) {
			$rowId = (int)($row['id'] ?? 0);

			if ($rowId <= 0) {
				continue;
			}

			foreach ($elements as $name => $element) {
				$childModule = (string)($element['module'] ?? '');
				$childController = (string)($element['controller'] ?? '');

				if ($childModule === '' || $childController === '') {
					continue;
				}

				$db = $this->createDbTable($childModule, $childController, $clientId);

				if (!$db) {
					$row[$name] = [];
					continue;
				}

				$childRows = $db->getByParentId($rowId, $module, $controller);

				$row[$name] = $this->populateNestedRows(
					is_array($childRows) ? $childRows : [],
					$childModule,
					$childController,
					$clientId
				);
			}
		}
		unset($row);

		return $rows;
	}

	protected function createForm(string $module, string $controller): ?DEEC_Form
	{
		$className = DEEC_Util::formClassFromModuleController($module, $controller);

		if (!class_exists($className)) {
			return null;
		}

		$form = new $className();

		return $form instanceof DEEC_Form ? $form : null;
	}

	protected function createDbTable(string $module, string $controller, ?int $clientId = null)
	{
		$className = DEEC_Util::dbTableClassFromModuleController($module, $controller);

		if (!class_exists($className)) {
			return null;
		}

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			return null;
		}

		if ($clientId !== null && $clientId > 0) {
			$db->setClientId($clientId);
		}

		return $db;
	}
}
