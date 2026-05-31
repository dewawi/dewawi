<?php

class Admin_ClientController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'clients',
			'list' => 'Admin_Model_List_Clients',
			'entity' => Admin_Model_Entity_Client::listConfig(),
		]);
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Client();
			$data = $request->getPost();
			if($form->isValid($data)) {
				$clientDb = new Admin_Model_DbTable_Client();
				$id = $clientDb->addClient($data);

				//Add category row
				$categoryDb = new Admin_Model_DbTable_Category();
				$categoryDb->addCategory(array(
									'title' => 'Privatkunden',
									'type' => 'contact',
									'ordering' => 1,
									'parentid' => 0
									), $id);
				$categoryDb->addCategory(array(
									'title' => 'Geschäftskunden',
									'type' => 'contact',
									'ordering' => 2,
									'parentid' => 0
									), $id);
				$categoryDb->addCategory(array(
									'title' => 'Lieferanten',
									'type' => 'contact',
									'ordering' => 3,
									'parentid' => 0
									), $id);

				//Add config row
				$configDb = new Admin_Model_DbTable_Config();
				$configDb->addConfig(array(
									'timezone' => 'Europe/Berlin',
									'language' => 'de_DE'
									), $id);

				//Add currency row
				$currencyDb = new Admin_Model_DbTable_Currency();
				$currencyDb->addCurrency(array(
									'code' => 'EUR',
									'name' => 'Euro',
									'symbol' => '€'
									), $id);
				$currencyDb->addCurrency(array(
									'code' => 'USD',
									'name' => 'US Dollar',
									'symbol' => '$'
									), $id);

				//Add exchange rate row
				//$currencyDb = new Admin_Model_DbTable_Currency();
				//$currencyDb->addCurrency(array(
				//					'code' => 'EUR',
				//					'name' => 'Euro',
				//					'symbol' => '€',
				//					'clientid' => $id
				//					));
				//$currencyDb->addCurrency(array(
				//					'code' => 'USD',
				//					'name' => 'US Dollar',
				//					'symbol' => '$',
				//					'clientid' => $id
				//					));

				//Add file name row
				$filenameDb = new Admin_Model_DbTable_Filename();
				$filenameDb->addFilename(array(
									'creditnote' => 'Gutschrift-%NUMBER%.pdf',
									'deliveryorder' => 'Lieferschein-%NUMBER%.pdf',
									'invoice' => 'Rechnung-%NUMBER%.pdf',
									'purchaseorder' => 'Bestellung-%NUMBER%.pdf',
									'quote' => 'Angebot-%NUMBER%.pdf',
									'quoterequest' => 'Anfrage-%NUMBER%.pdf',
									'reminder' => 'Mahnung-%NUMBER%.pdf',
									'salesorder' => 'Auftragsbestaetigung-%NUMBER%.pdf'
									), $id);

				//Add increment row
				$incrementDb = new Admin_Model_DbTable_Increment();
				$incrementDb->addIncrement(array(
									'clientid' => $id,
									'contactid' => 10000,
									'creditnoteid' => 10000,
									'deliveryorderid' => 10000,
									'invoiceid' => 10000,
									'purchaseorderid' => 10000,
									'quoteid' => 10000,
									'quoterequestid' => 10000,
									'reminderid' => 10000,
									'salesorderid' => 10000,
									'shoporderid' => 10000
									), $id);

				//Add language row
				//To do: get loaded languages
				$languageDb = new Admin_Model_DbTable_Language();
				$languageDb->addLanguage(array(
									'code' => 'de_DE',
									'name' => 'Deutsch'
									), $id);
				$languageDb->addLanguage(array(
									'code' => 'en_US',
									'name' => 'Englisch'
									), $id);

				//Add payment method row
				//$paymentmethodDb = new Admin_Model_DbTable_Paymentmethod();
				//$paymentmethodDb->addPaymentmethod(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$paymentmethodDb->addPaymentmethod(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add shipping method row
				//$shippingmethodDb = new Admin_Model_DbTable_Shippingmethod();
				//$shippingmethodDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$shippingmethodDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add state row
				//$stateDb = new Admin_Model_DbTable_State();
				//$stateDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));
				//$stateDb->addState(array(
				//					'code' => 'de_DE',
				//					'name' => 'Deutsch',
				//					'clientid' => $id
				//					));

				//Add taxrate row
				$taxrateDb = new Admin_Model_DbTable_Taxrate();
				$taxrateDb->addTaxrate(array(
									'name' => 'MwSt (16%)',
									'rate' => 16.0000
									), $id);
				$taxrateDb = new Admin_Model_DbTable_Taxrate();
				$taxrateDb->addTaxrate(array(
									'name' => 'MwSt (5%)',
									'rate' => 5.0000
									), $id);

				//Add template row
				$templateDb = new Admin_Model_DbTable_Template();
				$templateDb->addTemplate(array(
									'description' => 'Vorlage',
									'default' => 1
									), $id);

				//Add textblock row
				$textblockDb = new Admin_Model_DbTable_Textblock();
				$textblockDb->addTextblock($textblockDb->getTextblock(1), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(2), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(3), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(4), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(5), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(6), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(7), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(8), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(9), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(10), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(11), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(12), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(13), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(14), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(15), $id);
				$textblockDb->addTextblock($textblockDb->getTextblock(16), $id);

				//Add uom row
				$uomDb = new Admin_Model_DbTable_Uom();
				$uomDb->addUom(array(
									'title' => 'Stück'
									), $id);
				$uomDb->addUom(array(
									'title' => 'Pack.'
									), $id);
				$uomDb->addUom(array(
									'title' => 'Std.'
									), $id);
				$uomDb->addUom(array(
									'title' => 'kg'
									), $id);
				$uomDb->addUom(array(
									'title' => 'm'
									), $id);

				//Add warehouse row
				$warehouseDb = new Admin_Model_DbTable_Warehouse();
				$warehouseDb->addWarehouse(array(
									'title' => 'Hauptlager',
									'description' => 'Hauptlager'
									), $id);

				echo Zend_Json::encode($clientDb->getClient($id));
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}
	}

	public function editAction()
	{
		$this->disableView();

		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);

		$clientDb = new Admin_Model_DbTable_Client();
		$client = $clientDb->getById($id);

		if (!$client) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$lockResult = $this->_helper->Access->lock(
			$id,
			$this->_user['id'],
			$client['locked'] ?? 0,
			$client['lockedtime'] ?? null
		);

		if (is_array($lockResult) && isset($lockResult['ok']) && $lockResult['ok'] === false) {
			return $this->_helper->json($lockResult);
		}

		if (!$request->isPost()) {
			return $this->_helper->json([
				'ok' => true,
				'item' => $client,
			]);
		}

		$form = new Admin_Form_Client();
		$this->_helper->Options->applyFormOptions($form);

		return $this->_helper->json(
			$this->saveFormAjax($form, $clientDb, $id)
		);
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$clientDb = new Admin_Model_DbTable_Client();
		$data = $clientDb->getClient($id);
		unset($data['id']);
		$data['company'] = $data['company'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$clientid = $clientDb->addClient($data);

		//Copy config row
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByClientID($id);
		unset($config['id']);
		$config['clientid'] = $clientid;
		$config['modified'] = NULL;
		$config['modifiedby'] = 0;
		$config['locked'] = 0;
		$config['lockedtime'] = NULL;
		$configDb->addConfig($config);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}


	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			if($id == $this->_user['clientid']) {
				$this->_flashMessenger->addMessage('MESSAGES_OWN_CLINET_CAN_NOT_BE_DELETED');
			} else {
				$clientDb = new Admin_Model_DbTable_Client();
				$clientDb->deleteClient($id);
				$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
			}
		}
	}
}
