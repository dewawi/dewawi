<?php

class Application_Controller_Action_Helper_MultiEntityLoader extends Zend_Controller_Action_Helper_Abstract
{
	public function populate(DEEC_Form $form, int $parentid, string $parentModule, string $parentController): void
	{
		if ($parentid <= 0) {
			return;
		}

		foreach ($form->getMultiElements() as $name => $el) {
			$module = (string)($el['module'] ?? '');
			$controller = (string)($el['controller'] ?? $name);

			if ($module === '' || $controller === '') {
				continue;
			}

			$db = $this->createDbTable($module, $controller);
			if (!$db) {
				continue;
			}

			$rows = $db->getByParentId($parentid, $parentModule, $parentController);

			$form->setElementData($name, [
				'parentid' => $parentid,
				'parent_module' => $parentModule,
				'parent_controller' => $parentController,
				'module' => $module,
				'controller' => $controller,
				'rows' => is_array($rows) ? $rows : [],
			]);
		}
	}

	protected function createDbTable(string $module, string $controller)
	{
		$class = $this->dbTableClassFromModuleController($module, $controller);

		if (!class_exists($class)) {
			return null;
		}

		$db = new $class();

		if (!method_exists($db, 'getByParentId')) {
			return null;
		}

		return $db;
	}

	protected function dbTableClassFromModuleController(string $module, string $controller): string
	{
		return $this->camelize($module) . '_Model_DbTable_' . $this->camelize($controller);
	}

	protected function camelize(string $str): string
	{
		$str = str_replace(['-', '_'], ' ', strtolower($str));
		$str = ucwords($str);

		return str_replace(' ', '', $str);
	}
}
