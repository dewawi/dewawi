<?php

class Application_Controller_Action_Helper_MultiEntityLoader extends Zend_Controller_Action_Helper_Abstract
{
	public function populate(
		DEEC_Form $form,
		int $parentId,
		string $parentModule,
		string $parentController
	): void {
		$form->setMultiContext(
			$parentId,
			$parentModule,
			$parentController
		);

		foreach ($form->getMultiElements() as $name => $element) {
			$module = (string)($element['module'] ?? '');
			$controller = (string)($element['controller'] ?? '');

			if ($module === '' || $controller === '') {
				continue;
			}

			$db = $this->createDbTable(
				$module,
				$controller
			);

			if (!$db) {
				continue;
			}

			$rows = $db->getByParentId(
				$parentId,
				$parentModule,
				$parentController
			);

			$form->setElementData(
				$name,
				[
					'rows' => is_array($rows)
						? $rows
						: [],
				]
			);
		}
	}

	protected function createDbTable(
		string $module,
		string $controller
	) {
		$className = DEEC_Util::dbTableClassFromModuleController(
			$module,
			$controller
		);

		if (!class_exists($className)) {
			return null;
		}

		$db = new $className();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			return null;
		}

		return $db;
	}
}
