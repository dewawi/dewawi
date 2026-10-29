<?php

class TrashController extends Zend_Controller_Action
{
	protected $_date = null;
	protected $_user = null;

	/**
	 * FlashMessenger helper.
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function addAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if (!$this->getRequest()->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'MESSAGES_INVALID_REQUEST',
			]);
		}

		$body = $this->getRequest()->getRawBody();
		$data = json_decode($body, true);

		$ids = $data['id'] ?? [];
		if (!is_array($ids)) {
			$ids = [$ids];
		}
		$ids = array_map('intval', $ids);
		$ids = array_filter($ids);

		$module = strtolower((string)($data['module'] ?? ''));
		$controller = strtolower((string)($data['controller'] ?? ''));

		if ($module === '' || $controller === '') {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'MESSAGES_INVALID_REQUEST',
			]);
		}

		$user = Zend_Registry::get('User');
		$permission = new DEEC_Permission((array)$user);
		$route = new Zend_Controller_Request_Simple('delete', $controller, $module);
		if (empty($user['id']) || !preg_match('/^[a-z][a-z0-9]*$/D', $controller) || !in_array($module, ['default', 'admin', 'contacts', 'items', 'sales', 'purchases', 'processes', 'tasks', 'campaigns'], true)
			|| ($module === 'admin' ? empty($user['admin']) : ($permission->hasModule($module) && !$permission->hasRoutePermission($route)))) {
			$this->getResponse()->setHttpResponseCode(403);
			return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_ACCESS_DENIED']);
		}
		$client = Zend_Registry::get('Client');
		$owner = (int)($client['modules'][$module] ?? $client['id']);
		$targetModule = $module === 'default' ? 'Application' : ucfirst($module);
		$targetController = ucfirst($controller);

		$modelClass = $targetModule . '_Model_DbTable_' . $targetController;
		$isPageblockController = ($module === 'admin' && $controller === 'pageblock');

		if ($isPageblockController) {
			$user = Zend_Registry::get('User');

			if (empty($user['admin'])) {
				$this->getResponse()->setHttpResponseCode(403);
				return $this->_helper->json(['ok' => false, 'message' => 'MESSAGES_ACCESS_DENIED']);
			}

			$modelClass = Application_Model_DbTable_Pageblock::class;
		}

		if (!class_exists($modelClass)) {
			throw new Zend_Controller_Exception("Model class {$modelClass} does not exist.");
		}

		$mainModel = new $modelClass();
		if (!$mainModel instanceof DEEC_Model_DbTable_Entity) throw new Zend_Controller_Action_Exception('Not found', 404);
		$mainModel->setClientId($owner);

		// Related positions model
		$positionClass = $targetModule . '_Model_DbTable_' . $targetController . 'pos';
		$positionsDb = class_exists($positionClass) ? new $positionClass() : null;
		if ($positionsDb instanceof DEEC_Model_DbTable_Entity) $positionsDb->setClientId($owner);

		// Items/item special case
		$isItemController = ($module === 'items' && $controller === 'item');
		$ebayListingDb = $isItemController ? new Ebay_Model_DbTable_Listing() : null;

		// Items/ledger special case
		$isLedgerController = ($module === 'items' && $controller === 'ledger');
		$itemDb = $isLedgerController ? new Items_Model_DbTable_Item() : null;

		// Delivery order special case
		$isDeliveryOrderController = ($controller === 'deliveryorder');
		$ledgerDb = $isDeliveryOrderController ? new Items_Model_DbTable_Ledger() : null;

		// Contact cascade delete
		$isContactController = ($module === 'contacts' && $controller === 'contact');

		$addressDb = $isContactController ? new Contacts_Model_DbTable_Address() : null;
		$phoneDb = $isContactController ? new Contacts_Model_DbTable_Phone() : null;
		$emailDb = $isContactController ? new Contacts_Model_DbTable_Email() : null;
		$internetDb = $isContactController ? new Contacts_Model_DbTable_Internet() : null;
		$bankaccountDb = $isContactController ? new Contacts_Model_DbTable_Bankaccount() : null;
		$contactpersonDb = $isContactController ? new Contacts_Model_DbTable_Contactperson() : null;

		// Adjust these two to the actual model classes/methods in your project
		$commentDb = $isContactController ? new Application_Model_DbTable_Comment() : null;
		$tagModel = $isContactController ? new Contacts_Model_Get() : null;

		// Permissions delete
		$isUserController = ($module === 'admin' && $controller === 'user');
		$permissionDb = $isUserController ? new Admin_Model_DbTable_Permission() : null;
		foreach ([$addressDb, $phoneDb, $emailDb, $internetDb, $bankaccountDb, $contactpersonDb, $commentDb, $permissionDb] as $relatedDb) {
			if ($relatedDb instanceof DEEC_Model_DbTable_Entity) $relatedDb->setClientId($owner);
		}

		$deletedIds = [];
		$blockedIds = [];
		$adapter = $mainModel->getAdapter();
		$adapter->beginTransaction();
		try {

		foreach ($ids as $id) {
			if ($id <= 0) {
				continue;
			}

			$row = $mainModel->getByIdForUpdate($id);

			if (!$row) {
				continue;
			}

			$parentId = (int)($row[$mainModel->getParentField()] ?? 0);
			if (!empty($row['module']) && !empty($row['controller']) && $parentId > 0
				&& !$permission->getObjectContext($parentId, (string)$row['module'], (string)$row['controller'])) {
				$blockedIds[] = $id;
				continue;
			}
			if (!$mainModel->canDelete($row) || (array_key_exists($mainModel->getParentField(), $row) && $mainModel->hasChildren($id, $row))
				|| ($isUserController && $id === (int)$user['id'])
				|| (DEEC_Permission::isDocument($module, $controller) && DEEC_Permission::isDocumentReadonly($module, $controller, $row))) {
				$blockedIds[] = $id;
				continue;
			}

			$mainModel->deleteById($id);

			if ($isPageblockController) {
				$mainModel->normalizeOrderingByRow($row);
			}

			// Delete related positions
			if ($positionsDb && method_exists($positionsDb, 'getPositions')) {
				$positions = $positionsDb->getPositions($id);
				foreach ($positions as $position) {
					$positionsDb->deleteById((int)$position['id']);
				}
			}

			// Delete eBay listings for items
			if ($ebayListingDb) {
				$ebayListingDb->deleteListingByItemID($id);
			}

			// Reverse stock effect for ledger deletion
			if ($itemDb) {
				(new Items_Service_Stock())->revert($row);
			}

			// Delete ledgers for delivery orders
			if ($ledgerDb) {
				$ledgers = $ledgerDb->getLedgers($id);
				foreach ($ledgers as $ledger) {
					$ledgerDb->deleteLedger($ledger->id);
				}
			}

			// Delete child entities only when deleting a contact
			if ($isContactController) {
				$addresses = $addressDb->getByParentId($id, 'contacts', 'contact');
				foreach ($addresses as $address) {
					$addressDb->deleteById((int)$address['id']);
				}

				$phones = $phoneDb->getByParentId($id, 'contacts', 'contact');
				foreach ($phones as $phone) {
					$phoneDb->deleteById((int)$phone['id']);
				}

				$emails = $emailDb->getByParentId($id, 'contacts', 'contact');
				foreach ($emails as $email) {
					$emailDb->deleteById((int)$email['id']);
				}

				$internets = $internetDb->getByParentId($id, 'contacts', 'contact');
				foreach ($internets as $internet) {
					$internetDb->deleteById((int)$internet['id']);
				}

				$bankaccounts = $bankaccountDb->getByParentId($id, 'contacts', 'contact');
				foreach ($bankaccounts as $bankaccount) {
					$bankaccountDb->deleteById((int)$bankaccount['id']);
				}

				$contactpersons = $contactpersonDb->getByParentId($id, 'contacts', 'contact');
				foreach ($contactpersons as $contactperson) {
					$contactpersonDb->deleteById((int)$contactperson['id']);
				}

				// Comments: adjust to your actual comment model API
				if ($commentDb) {
					$comments = $commentDb->getComments($id, 'contacts', 'contact');
					foreach ($comments as $comment) {
						$commentDb->deleteComment((int)$comment['id']);
					}
				}

				// Tags: adjust to your actual tag deletion API
				if ($tagModel && method_exists($tagModel, 'deleteTags')) {
					$tagModel->deleteTags('contacts', 'contact', $id);
				}
			}

			if ($permissionDb) {
				$userPermission = $permissionDb->getByUserId($id);

				if ($userPermission) {
					$permissionDb->deleteById(
						(int)$userPermission['id']
					);
				}
			}

			$deletedIds[] = $id;
		}
		$adapter->commit();
		} catch (Throwable $exception) {
			$adapter->rollBack();
			throw $exception;
		}

		if ($blockedIds) {
			$this->_flashMessenger->addMessage('MESSAGES_CANNOT_BE_DELETED');
		}

		if ($deletedIds) {
			$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
		}

		return $this->_helper->json([
			'ok' => !$blockedIds,
			'deletedIds' => $deletedIds,
			'blockedIds' => $blockedIds,
			'message' => $blockedIds
				? 'MESSAGES_CANNOT_BE_DELETED'
				: 'MESSAGES_SUCCESFULLY_DELETED',
		]);
	}
}
