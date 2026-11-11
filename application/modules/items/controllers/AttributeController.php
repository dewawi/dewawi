<?php

class Items_AttributeController extends DEEC_Controller_Action
{
	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		//Get primary tax rate
		$taxrates = new Application_Model_DbTable_Taxrate();
		$taxrate = $taxrates->getPrimaryTaxrate();

		//Get primary currency
		$currencies = new Application_Model_DbTable_Currency();
		$currency = $currencies->getPrimaryCurrency();

		$data = array();
		$data['catid'] = $catid;
		$data['taxid'] = $taxrate['id'];
		$data['currency'] = $currency['code'];
		$data['inventory'] = 1;

		$item = new Items_Model_DbTable_Item();
		$id = $item->addItem($data);

		$this->_helper->redirector->gotoSimple('edit', 'item', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getItem($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'item', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $item['locked'], $item['lockedtime']);

			$form = new Items_Form_Item();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$data = array_intersect_key($data, $form->getElements());
					if(array_key_exists('cost', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['cost'] = Zend_Locale_Format::getNumber($data['cost'],array('precision' => 2,'locale' => $locale));
						$data['margin'] = $item['price'] - $data['cost'];
					}
					if(array_key_exists('price', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['price'] = Zend_Locale_Format::getNumber($data['price'],array('precision' => 2,'locale' => $locale));
						$data['margin'] = $data['price'] - $item['cost'];
					}
					if(array_key_exists('quantity', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['quantity'] = Zend_Locale_Format::getNumber($data['quantity'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('margin', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['margin'] = Zend_Locale_Format::getNumber($data['margin'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('weight', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['weight'] = Zend_Locale_Format::getNumber($data['weight'],array('precision' => 4,'locale' => $locale));
					}
					$itemDb->updateItem($id, $data);
					echo Zend_Json::encode($itemDb->getItem($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$currency = $this->_helper->Currency->getCurrency($item['currency']);
					$item['cost'] = $currency->toCurrency($item['cost']);
					$item['price'] = $currency->toCurrency($item['price']);
					$item['margin'] = $currency->toCurrency($item['margin']);
					$locale = Zend_Registry::get('Zend_Locale');
					$item['quantity'] = ($item['quantity'] != 0) ? $currency->toCurrency($item['quantity'],array('precision' => 2,'locale' => $locale)) : '';
					$item['minquantity'] = ($item['minquantity'] != 0) ? $currency->toCurrency($item['minquantity'],array('precision' => 2,'locale' => $locale)) : '';
					$item['orderquantity'] = ($item['orderquantity'] != 0) ? $currency->toCurrency($item['orderquantity'],array('precision' => 2,'locale' => $locale)) : '';
					$item['width'] = ($item['width'] != 0) ? $currency->toCurrency($item['width'],array('precision' => 2,'locale' => $locale)) : '';
					$item['length'] = ($item['length'] != 0) ? $currency->toCurrency($item['length'],array('precision' => 2,'locale' => $locale)) : '';
					$item['height'] = ($item['height'] != 0) ? $currency->toCurrency($item['height'],array('precision' => 2,'locale' => $locale)) : '';
					$item['weight'] = ($item['weight'] != 0) ? $currency->toCurrency($item['weight'],array('precision' => 2,'locale' => $locale)) : '';
					$item['packwidth'] = ($item['packwidth'] != 0) ? $currency->toCurrency($item['packwidth'],array('precision' => 2,'locale' => $locale)) : '';
					$item['packlength'] = ($item['packlength'] != 0) ? $currency->toCurrency($item['packlength'],array('precision' => 2,'locale' => $locale)) : '';
					$item['packheight'] = ($item['packheight'] != 0) ? $currency->toCurrency($item['packheight'],array('precision' => 2,'locale' => $locale)) : '';
					$item['packweight'] = ($item['packweight'] != 0) ? $currency->toCurrency($item['packweight'],array('precision' => 2,'locale' => $locale)) : '';
					$form->populate($item);

					//Tags
					$get = new Items_Model_Get();
					$tags = $get->tags('items', 'item', $item['id']);

					//History
					$ledgerDb = new Items_Model_DbTable_Ledger();
					$ledger = $ledgerDb->getLedgerBySKU($item['sku']);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
					$this->view->tags = $tags;
					//$this->view->attributes = $attributes;
					//$this->view->attributegroups = $attributegroups;
					//$this->view->attributesByGroup = $attributesByGroup;
					$this->view->ledger = $ledger;
					$this->view->activeTab = $activeTab;
					$this->view->toolbar = $toolbar;
				}
			}
		}
		$this->view->messages = array_merge(
			$this->_helper->flashMessenger->getMessages(),
			$this->_helper->flashMessenger->getCurrentMessages()
		);
		$this->_helper->flashMessenger->clearCurrentMessages();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$item = new Items_Model_DbTable_Item();
		$data = $item->getItem($id);
		unset($data['id']);
		$data['quantity'] = 0;
		$data['inventory'] = 1;
		$data['title'] = $data['title'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		echo $itemid = $item->addItem($data);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$item = new Items_Model_DbTable_Item();
			$item->deleteItem($id);
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}

	public function importAction()
	{
		$request = $this->getRequest();
		$form = new Items_Form_Import();
		$form->file->setAttrib('multiple', null)->removeValidator('Count');
		$form->file->addValidator('Count', false, 1)->removeValidator('Extension');
		$form->file->addValidator('Extension', false, 'csv');

		if($request->isPost()) {
			$formData = $request->getPost();
			if($form->isValid($formData)) {
				$data = false;
				try {
					$clientid = (string)$this->view->client['id'];
					$dir = BASE_PATH.'/files/import/'.substr($clientid, 0, 1).'/'.(strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0').'/'.$clientid.'/';
					if(!is_dir($dir) && !mkdir($dir, 0777, true)) throw new RuntimeException('Import directory is unavailable');
					$form->file->setDestination($dir);
					if(!$form->file->receive()) throw new RuntimeException('CSV upload failed');
					$file = $form->file->getFileName();
					if(!is_string($file) || !is_file($file)) throw new InvalidArgumentException('Please upload one CSV file');
					$data = fopen($file, 'r');
					if($data === false) throw new RuntimeException('CSV file could not be opened');

					$itemAttribute = new Items_Model_DbTable_Itematr();
					$this->view->importResult = $itemAttribute->importCsv(
						$data,
						$formData['separator'] === 'semicolon' ? ';' : ',',
						$formData['delimiter'] === 'single' ? "'" : '"'
					);
				} catch(InvalidArgumentException $e) {
					$this->view->importError = $e->getMessage();
				} catch(Throwable $e) {
					error_log('Attribute import failed: '.$e->getMessage());
					$this->view->importError = 'Attribute import failed. No changes were saved. Please check the server log.';
				} finally {
					if(is_resource($data)) fclose($data);
				}
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
