<?php

class TrashController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
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

		if ($this->getRequest()->isPost()) {
			// Get raw POST body and decode JSON input
			$body = $this->getRequest()->getRawBody();
			$data = json_decode($body, true);

			// Extract IDs to be deleted
			$ids = isset($data['id']) ? $data['id'] : [];

			// Extract target module and controller for dynamic handling
			$targetModule = isset($data['module']) ? ucfirst(strtolower($data['module'])) : null;
			$targetController = isset($data['controller']) ? ucfirst(strtolower($data['controller'])) : null;

			if (!$targetModule || !$targetController) {
				throw new Zend_Controller_Exception("Module or Controller not specified.");
			}

			// Dynamically load the main DB model class
			$modelClass = $targetModule . '_Model_DbTable_' . $targetController;
			if (!class_exists($modelClass)) {
				throw new Zend_Controller_Exception("Model class {$modelClass} does not exist.");
			}
			$mainModel = new $modelClass();

			// Optionally load a related positions model, if it exists
			$positionClass = $targetModule . '_Model_DbTable_' . $targetController . 'pos';
			$positionsDb = class_exists($positionClass) ? new $positionClass() : null;

			// Special case: items module - delete eBay listings
			$isItemModule = (strtolower($data['module']) === 'items') && (strtolower($data['controller']) === 'item');
			$ebayListingDb = $isItemModule ? new Ebay_Model_DbTable_Listing() : null;

			// Special case: contacts module - delete phones, emails, internets
			$isContactModule = strtolower($data['module']) === 'contacts';
			$phoneDb = $isContactModule ? new Contacts_Model_DbTable_Phone() : null;
			$emailDb = $isContactModule ? new Contacts_Model_DbTable_Email() : null;
			$internetDb = $isContactModule ? new Contacts_Model_DbTable_Internet() : null;

			// Special case: deliveryorder controller - delete ledger
			$isDeliveryOrder = strtolower($data['controller']) === 'deliveryorder';
			$ledgerDb = $isDeliveryOrder ? new Items_Model_DbTable_Ledger() : null;

			foreach ($ids as $id) {
				if (!empty($id)) {
					// Check if controller name ends with 'pos' (e.g., Pricerulepos)
					if (substr($targetController, -3) === 'pos') {
						if (method_exists($mainModel, 'deletePosition')) {
							$mainModel->deletePosition($id);
						}
					} else {
						// Call a custom delete method if it exists (e.g., deleteQuote), otherwise skip
						$deleteMethod = 'delete' . $targetController;
						if (method_exists($mainModel, $deleteMethod)) {
							$mainModel->$deleteMethod($id);
						}
					}

					// If positions model exists, delete associated positions
					if ($positionsDb && method_exists($positionsDb, 'getPositions')) {
						$positions = $positionsDb->getPositions($id);
						foreach ($positions as $position) {
							if (method_exists($positionsDb, 'deletePosition')) {
								$positionsDb->deletePosition($position->id);
							}
						}
					}

					// Delete eBay listing
					if ($ebayListingDb) {
						$ebayListingDb->deleteListingByItemID($id);
					}

					// Delete ledger if deliveryorder
					if ($ledgerDb) {
						$ledgers = $ledgerDb->getLedgers($id);
						foreach ($ledgers as $ledger) {
							$ledgerDb->deleteLedger($ledger->id);
						}
					}

					// Delete phones, emails, internets if contacts
					if ($isContactModule) {
						$phones = $phoneDb->getPhone($id);
						foreach ($phones as $phone) {
							$phoneDb->deletePhone($phone['id']);
						}

						$emails = $emailDb->getEmails($id);
						foreach ($emails as $email) {
							$emailDb->deleteEmail($email['id']);
						}

						$internets = $internetDb->getInternet($id);
						foreach ($internets as $internet) {
							$internetDb->deleteInternet($internet['id']);
						}
					}
				}
			}
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
