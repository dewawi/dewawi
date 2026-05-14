<?php

class Items_OptionController extends DEEC_Controller_Action
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

		if($request->isPost()) {
			$formData = $request->getPost();
			if($form->isValid($formData)) {

				$clientid = $this->view->client['id'];
				$dir1 = substr($clientid, 0, 1);
				if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
				else $dir2 = '0';

				if(!file_exists(BASE_PATH.'/files/import/'.$dir1.'/'.$dir2.'/'.$clientid.'/')) {
					mkdir(BASE_PATH.'/files/import/'.$dir1.'/'.$dir2.'/'.$clientid.'/', 0777, true);
				}

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/import/'.$dir1.'/'.$dir2.'/'.$clientid.'/');
				try {
					// upload received file(s)
					$upload->receive();
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
				$file = $upload->getFileName();
				$data = fopen($file, 'r');

				//Get uoms
				$uomsDb = new Application_Model_DbTable_Uom();
				$uoms = $uomsDb->getUoms();

				if($data && ($data !== FALSE)) {
					$map = array();
					$itemDb = new Items_Model_DbTable_Item();
					$itemMedia = new Application_Model_DbTable_Media();
					$itemAttribute = new Items_Model_DbTable_Itematr();
					$itemOption = new Items_Model_DbTable_Itemopt();
					$itemOptionInfo = $itemOption->getInfo();
					$itemOptionSet = new Items_Model_DbTable_Itemoptset();

					//Get
					if($formData['separator'] == 'comma') $separator = ',';
					if($formData['separator'] == 'semicolon') $separator = ';';
					if($formData['delimiter'] == 'single') $delimeter = "'";
					if($formData['delimiter'] == 'double') $delimeter = '"';

					$row = 0;
					$images = array();
					$options = array();
					$optionSets = array();
					$options = array();
					$rowsUpdated = 0;
					$rowsCreated = 0;
					while(($datacsv = fgetcsv($data, 0, $separator, $delimeter)) !== FALSE) {
						if($row == 0) {
							foreach($datacsv as $pos => $attr) {
								if($attr) {
									$map[$attr] = $pos;
								}
							}
						} elseif(isset($map['parentsku']) && isset($datacsv[$map['parentsku']]) && $datacsv[$map['parentsku']] && $datacsv[$map['title']]) {
							//echo $datacsv[$map['parentsku']];
							//print_r($map);
							$optionData = array();
							foreach($map as $attr => $pos) {
								if(isset($datacsv[$map[$attr]])) {
									if(array_search($attr, $itemOptionInfo)) {
										if($attr == 'weight') {
											if(is_numeric($datacsv[$map['weight']])) $optionData['weight'] = $datacsv[$map['weight']];
										} elseif($attr == 'price') {
											if(isset($map['dewawidiscount']) && $datacsv[$map['dewawidiscount']]) {
												$optionData['price'] = $datacsv[$map['price']] * (100 - $datacsv[$map['dewawidiscount']])/100;
											} elseif($datacsv[$map[$attr]]) {
												if(is_numeric($datacsv[$map[$attr]])) {
													$optionData['price'] = $datacsv[$map[$attr]];
												} else {
													echo 'Price is not numeric for '.$datacsv[$map['parentsku']].': '.$datacsv[$map[$attr]]."<br>";
												}
											}
										} elseif($attr == 'quantity') {
											if($datacsv[$map[$attr]] && is_numeric($datacsv[$map[$attr]])) {
												$optionData[$attr] = $datacsv[$map[$attr]];
											}
										} elseif($attr == 'currency') {
											if($currencyid = array_search($datacsv[$map[$attr]], $currencies)) {
												$optionData[$attr] = $currencyid;
											} else {
												echo 'No currency option found for '.$datacsv[$map['parentsku']].': '.$datacsv[$map[$attr]]."<br>";
											}
										} elseif($attr == 'inventory') {
											if($datacsv[$map[$attr]] && is_numeric($datacsv[$map[$attr]])) {
												$optionData[$attr] = $datacsv[$map[$attr]];
											}
										} else {
											$optionData[$attr] = $datacsv[$map[$attr]];
											//var_dump($attr);
										}
									} elseif($attr == 'parentsku') {
										$optionData[$attr] = $datacsv[$map[$attr]];
									} elseif($attr == 'set') {
										$optionData[$attr] = $datacsv[$map[$attr]];
									}
								}
							}
							//var_dump($optionData);

							//Create and update the options
							if($item = $itemDb->getItemBySKU($optionData['parentsku'])) {
								//Get current item attributes and replace placeholders
								$currentAttributes = $itemAttribute->getPositions($item['id'])->toArray();
								foreach($currentAttributes as $currentAttribute) {
									if($currentAttribute['title'] && $currentAttribute['description']) {
										//Search and replace attributes in import data
										foreach($optionData as $key => $value) {
											if(strpos($value, '#'.$currentAttribute['title'].'#') !== false) {
												$optionData[$key] = str_replace('#'.$currentAttribute['title'].'#', $currentAttribute['description'], $value);
											}
										}
									}
								}

								//print_r($optionData);
								if(isset($optionSets[$item['id']])) {
									$optionSetKey = array_search($optionData['set'], $optionSets[$item['id']]);
									if($optionSetKey !== false) {
										//Key already exists
									} else {
										$optionSets[$item['id']][] = $optionData['set'];
									}
								} elseif(isset($optionData['set'])) {
									$optionSets[$item['id']][] = $optionData['set'];
								}
								$optionData['optsetid'] = 0;
								$optionData['parentid'] = $item['id'];
								$optionData['itemid'] = $item['id'];
								$optionData['description'] = $optionData['description'];
								$options[$item['id']][] = $optionData;
							}
						}
						$row++;

					}
					//print_r($options);

					$optionSetIds = array();
					foreach($optionSets as $itemid => $optionSet) {
						//Delete existing item option sets
						$itemOptionSet->deletePositionSetsByItemID($itemid);

						//Create new item option sets
						$ordering = 1;
						foreach($optionSet as $id => $title) {
							$test = array();
							$test['title'] = $title;
							$test['parentid'] = $itemid;
							$test['ordering'] = $ordering;
							$optionSetId = $itemOptionSet->addPositionSet($test);
							$optionSetIds[$itemid][$optionSetId] = $title;
							++$ordering;
						}
					}
					//print_r($options);

					foreach($options as $itemid => $optionSet) {
						//Delete existing item options
						$itemOption->deletePositionsByItemID($itemid);

						//Create new item options
						$ordering = 1;
						foreach($optionSet as $id => $option) {
							$option['optsetid'] = array_search($option['set'], $optionSetIds[$itemid]);
							$option['ordering'] = $ordering;
							unset($option['set']);
							unset($option['value']);
							unset($option['parentsku']);
							$itemOption->addPosition($option);
							++$ordering;
							++$rowsCreated;
						}
					}

					fclose($data);
				} else {
					//error_log(print_r($optionData,true));
					echo 'ERROR: No data recieved<br>';
				}

				echo ($row-1).' rows are processed<br>';
				echo $rowsUpdated.' existing rows are updated<br>';
				echo $rowsCreated.' new rows are created<br>';

				$this->view->data = $data;
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}
