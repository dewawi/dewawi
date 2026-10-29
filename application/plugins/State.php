<?php

class Application_Plugin_State extends Zend_Controller_Plugin_Abstract
{
	public function preDispatch(Zend_Controller_Request_Abstract $request)
	{
		$module = $request->getModuleName();
		$controller = $request->getControllerName();
		$action = $request->getActionName();

		if (DEEC_Permission::isDocument($module, $controller)) {
			if (!in_array($action, ['edit', 'delete', 'import', 'apply', 'sort', 'state', 'cancel'], true)) return;
			if ($action === 'edit' && !$request->isPost()) return;
			if (!$request->isPost()) return $this->deny($request);

			$ids = $action === 'delete'
				? $request->getParam('ids', $request->getParam('selected', [$request->getParam('id', 0)]))
				: $request->getParam('id', 0);
			$ids = $this->ids($ids, $action === 'delete');
			if (!$ids) return $this->deny($request);

			foreach ($ids as $id) {
				$row = $this->document($module, $controller, $id);
				if (!$row) return $this->deny($request);
				if (DEEC_Permission::isDocumentReadonly($module, $controller, $row)) {
					$post = (array)$request->getPost();
					if ($action !== 'edit' || array_keys($post) !== ['notes'] || !is_string($post['notes'])) {
						return $this->deny($request);
					}
				}
			}
			return;
		}

		if (in_array($module, ['sales', 'purchases', 'items', 'processes', 'tasks', 'campaigns'], true) && in_array($controller, ['position', 'positionset'], true)) {
			if (!in_array($action, ['add', 'edit', 'delete', 'copy', 'sort', 'apply'], true)) return;
			if (!$request->isPost()) return $this->deny($request);

			$parent = $request->getParam('parent', '');
			$parentId = filter_var($request->getParam('parentid'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			$type = $request->getParam('type', 'pos');
			if (!is_string($parent) || !is_string($type) || !DEEC_Permission::isPositionContext($module, $parent, $type) || !$parentId) return $this->deny($request);
			if ($request->getPost('parentid') !== null
				&& filter_var($request->getPost('parentid'), FILTER_VALIDATE_INT) !== $parentId) return $this->deny($request);
			if (!$this->editable($module, $parent, $parentId)) return $this->deny($request);

			if ($controller === 'position') {
				foreach (['masterid' => $type, 'setid' => $type . 'set'] as $field => $suffix) {
					$value = $request->getParam($field, 0);
					if ($value === 0 || $value === '0' || $value === null || $value === '') continue;
					$ids = $this->ids($value, false);
					$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($parent . $suffix);
					$row = $ids ? (new $class())->getById($ids[0]) : null;
					if (!$row || (int)$row['parentid'] !== $parentId) return $this->deny($request);
				}
			}

			if ($action !== 'add' && $action !== 'apply') {
				$rawIds = ($action === 'delete' || ($controller === 'positionset' && $action === 'sort'))
					? $request->getPost('id', $request->getParam('id', 0)) : $request->getParam('id', 0);
				$ids = $this->ids($rawIds, $controller === 'position' && $action === 'delete');
				if (!$ids) return $this->deny($request);
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($parent . $type . ($controller === 'positionset' ? 'set' : ''));
				$db = new $class();
				foreach ($ids as $id) {
					$row = $db->getById($id);
					if (!$row || (int)$row['parentid'] !== $parentId) return $this->deny($request);
				}
			}

			// Legacy set controllers pass the posted values directly to the table.
			if ($action === 'edit') {
				$formClass = ucfirst($module) . '_Form_' . ucfirst($parent . $type);
				$form = new $formClass();
				$allowed = array_diff(array_keys($form->getElements()), ['id']);
				if (array_diff(array_keys((array)$request->getPost()), $allowed)) return $this->deny($request);
			}
			return;
		}

		if ($module === 'items' && $controller === 'pricerulepos' && in_array($action, ['add', 'edit', 'delete'], true)) {
			if (!$request->isPost()) return $this->deny($request);
			if ($action === 'add') {
				$context = [
					'module' => $request->getParam('parent_module', ''),
					'controller' => $request->getParam('parent_controller', ''),
					'parentid' => $request->getParam('parentid', 0),
				];
			} else {
				$ids = $this->ids($request->getParam('id', 0), false);
				$context = $ids ? (new Items_Model_DbTable_Pricerulepos())->getById($ids[0]) : null;
				if (!$context) return $this->deny($request);
			}
			if (!is_string($context['module']) || !is_string($context['controller'])) return $this->deny($request);
			$parent = substr($context['controller'], 0, -3);
			if (!DEEC_Permission::isPositionContext($context['module'], $parent, substr($context['controller'], -3))) return $this->deny($request);
			$class = ucfirst($context['module']) . '_Model_DbTable_' . ucfirst($context['controller']);
			$ids = $this->ids($context['parentid'], false);
			$client = Zend_Registry::get('Client');
			$position = $ids ? (new $class())->setClientId((int)($client['modules'][$context['module']] ?? $client['id']))->getById($ids[0]) : null;
			if (!$position || !$this->editable($context['module'], $parent, (int)$position['parentid'])) return $this->deny($request);
		}
	}

	private function ids($value, bool $multiple): array
	{
		if (!$multiple && is_array($value)) return [];
		$ids = [];
		foreach ((array)$value as $id) {
			$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($id === false) return [];
			$ids[] = $id;
		}
		return array_values(array_unique($ids));
	}

	private function document(string $module, string $controller, int $id): ?array
	{
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
		$client = Zend_Registry::get('Client');
		return (new $class())->setClientId((int)($client['modules'][$module] ?? $client['id']))->getById($id);
	}

	private function editable(string $module, string $controller, int $id): bool
	{
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		if ($permission->hasModule($module) && !$permission->hasActionPermission($module, $controller, 'edit')) return false;
		$row = $this->document($module, $controller, $id);
		return $row && !DEEC_Permission::isDocumentReadonly($module, $controller, $row)
			&& (DEEC_Permission::isDocument($module, $controller) || !in_array((int)($row['state'] ?? 0), [105, 106], true));
	}

	private function deny(Zend_Controller_Request_Abstract $request): void
	{
		$this->getResponse()->setHttpResponseCode(403);
		$request->setModuleName('default')->setControllerName('error')->setActionName('forbidden');
	}
}
