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
			return;
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
			throw new Zend_Controller_Exception('Module or controller not specified.');
		}

		$targetModule = ucfirst($module);
		$targetController = ucfirst($controller);

		$modelClass = $targetModule . '_Model_DbTable_' . $targetController;
		if (!class_exists($modelClass)) {
			throw new Zend_Controller_Exception("Model class {$modelClass} does not exist.");
		}

		$mainModel = new $modelClass();

		// Related positions model
		$positionClass = $targetModule . '_Model_DbTable_' . $targetController . 'pos';
		$positionsDb = class_exists($positionClass) ? new $positionClass() : null;

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

		foreach ($ids as $id) {
			if ($id <= 0) {
				continue;
			}

			// Delete main record
			if (!method_exists($mainModel, 'deleteById')) {
				throw new Zend_Controller_Exception("Model {$modelClass} must implement deleteById().");
			}

			$mainModel->deleteById($id);

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
				$ledger = $mainModel->getLedger($id);
				if ($ledger && ($item = $itemDb->getItemBySKU($ledger['sku']))) {
					$signed = (float)$ledger['quantity'];
					if ($ledger['type'] === 'outflow') {
						$signed = -$signed;
					}

					$delta = -$signed;
					$newQty = ((float)$item['quantity']) + $delta;

					$itemDb->updateItem($item['id'], ['quantity' => $newQty]);
				}
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
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
