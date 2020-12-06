<?php

class Items_ItemController extends Zend_Controller_Action
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
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$items = $get->items($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$items = $get->items($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$items = $get->items($params, $options['categories']);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

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

					//History
					$inventoryDb = new Items_Model_DbTable_Inventory();
					$inventory = $inventoryDb->getInventoryBySKU($item['sku']);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					$this->view->form = $form;
					$this->view->inventory = $inventory;
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

	public function importAction()
	{
		//$this->_helper->viewRenderer->setNoRender();
		//$this->_helper->getHelper('layout')->disableLayout();

		$form = new Application_Form_Upload();

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				if(!file_exists(BASE_PATH.'/files/import/')) {
					mkdir(BASE_PATH.'/files/import/');
					chmod(BASE_PATH.'/files/import/', 0777);
				}

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/import/');
				try {
					// upload received file(s)
					$upload->receive();
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
				$file = $upload->getFileName();
				$data = fopen($file, 'r');

				//Get manufacturers
				$manufacturersDb = new Application_Model_DbTable_Manufacturer();
				$manufacturers = $manufacturersDb->getManufacturers();

				//Get uoms
				$uomsDb = new Application_Model_DbTable_Uom();
				$uoms = $uomsDb->getUoms();

				if($data && ($data !== FALSE)) {
					$map = array();
					$dataTemplate = array();
					$row = 0;
					$itemDb = new Items_Model_DbTable_Item();
					$itemInfo = $itemDb->getInfo();
					$itemImage = new Items_Model_DbTable_Itemimage();
					$ebayitemDb = new Items_Model_DbTable_Ebayitem();
					$shopitemDb = new Items_Model_DbTable_Shopitem();

					//Get categories
					$categoryIndex = $this->getProductCategoryIndex();

					while(($datacsv = fgetcsv($data, 0, ',')) !== FALSE) {
						if($row == 0) {
							foreach($datacsv as $pos => $attr) {
								if($attr) {
									$map[$attr] = $pos;
								}
							}
						} elseif($datacsv[$map['sku']]) {
							//echo $datacsv[$map['sku']];
							//print_r($map);
							$images = array();
							$attributes = array();
							$updateData = array();
							$shopData = array();
							foreach($map as $attr => $pos) {
								if(array_search($attr, $itemInfo)) {
									if($attr == 'weight') {
										if(is_numeric($datacsv[$map['weight']])) $updateData['weight'] = $datacsv[$map['weight']];
									} elseif($attr == 'price') {
										if(isset($map['dewawidiscount']) && $datacsv[$map['dewawidiscount']]) {
											$updateData['price'] = $datacsv[$map['price']] * (100 - $datacsv[$map['dewawidiscount']])/100;
										} else {
											$updateData['price'] = $datacsv[$map[$attr]];
										}
									} else {
										$updateData[$attr] = $datacsv[$map[$attr]];
										//var_dump($datacsv[$map[$attr]]);
									}
								} elseif($attr == 'category') {
									$updateData['catid'] = 0;
									$currentCategory = $categoryIndex;
									$shopCategories = explode(' > ', $datacsv[$map['category']]);
									foreach($shopCategories as $shopCategory) {
										if(isset($currentCategory[md5($shopCategory)])) {
											$currentCategory = $currentCategory[md5($shopCategory)];
											$updateData['catid'] = $currentCategory['id'];
											if(isset($currentCategory['childs'])) $currentCategory = $currentCategory['childs'];
										} else {
											$updateData['catid'] = 0;
										}
									}
									if($updateData['catid'] == 0) {
										/* TO DO handle if no category found */
										echo 'No category found for '.$datacsv[$map['sku']].': '.$datacsv[$map['category']]."<br>";
									}
								} elseif($attr == 'manufacturer') {
									if($manufacturerid = array_search($datacsv[$map['manufacturer']], $manufacturers)) {
										$updateData['manufacturerid'] = $manufacturerid;
									}
								/*} elseif($attr == 'weightunit') {
									if($weightuomid = array_search($datacsv[$map['weightunit']], $uoms)) {
										$updateData['weightuomid'] = $weightuomid;
									}*/
								} elseif(strpos($attr, 'attributetitle') !== FALSE) {
									if($datacsv[$map[$attr]]) {
										$attributes[str_replace('attributetitle', '', $attr)]['title'] = $datacsv[$map[$attr]];
									}
								} elseif(strpos($attr, 'attributevalue') !== FALSE) {
									if($datacsv[$map[$attr]]) {
										$attributes[str_replace('attributevalue', '', $attr)]['value'] = $datacsv[$map[$attr]];
									}
								} elseif((strpos($attr, 'image') !== FALSE) && (strpos($attr, 'url') !== FALSE)) {
									$imageUrl = $datacsv[$map[$attr]];

									$clientid = $this->view->client['id'];
									$dir1 = substr($clientid, 0, 1);
									if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
									else $dir2 = '0';
									$url = '/media/items/'.$dir1.'/'.$dir2.'/'.$clientid;

									if(file_exists(BASE_PATH.$url.$imageUrl)) {
										$imageID = str_replace('image', '', str_replace('url', '', $attr));
										$images[$imageID]['url'] = $imageUrl;
										if(isset($map['image'.$imageID.'ordering']) && $datacsv[$map['image'.$imageID.'ordering']]) {
											$images[$imageID]['ordering'] = $datacsv[$map['image'.$imageID.'ordering']];
										} else {
											$images[$imageID]['ordering'] = $imageID;
										}
									}
								} elseif((strpos($attr, 'image') !== FALSE) && (strpos($attr, 'title') !== FALSE)) {
									$images[str_replace('image', '', str_replace('title', '', $attr))]['title'] = $datacsv[$map[$attr]];
								}
								//Add system variables to attributes
								if($attr == 'sku') {
									if($datacsv[$map['sku']]) {
										$attributes[$attr]['title'] = 'Artikelnummer';
										$attributes[$attr]['value'] = $datacsv[$map['sku']];
									}
								} elseif($attr == 'weight') {
									if($datacsv[$map['weight']]) {
										$attributes[$attr]['title'] = 'Gewicht';
										$attributes[$attr]['value'] = $datacsv[$map['weight']];
									}
								} elseif($attr == 'manufacturer') {
									if($datacsv[$map['manufacturer']]) {
										$attributes[$attr]['title'] = 'Hersteller';
										$attributes[$attr]['value'] = $datacsv[$map['manufacturer']];
									}
								}
							}
							//Search and replace variable placeholders
							foreach($updateData as $key => $variable) {
if(($key == 'sku') && ($variable =='MGM-103EA11XA')) {
error_log($variable);
error_log($updateData['shoptitle']);
}
								if(isset($updateData['title']) && (strpos($updateData['title'], '#'.$key.'#') !== false)) {
									$updateData['title'] = str_replace('#'.$key.'#', $variable, $updateData['title']);
								}
								if(isset($updateData['shoptitle']) && (strpos($updateData['shoptitle'], '#'.$key.'#') !== false)) {
									$updateData['shoptitle'] = str_replace('#'.$key.'#', $variable, $updateData['shoptitle']);
								}
if(($key == 'sku') && ($variable =='MGM-103EA11XA')) {
error_log($variable);
error_log($updateData['shoptitle']);
}
								if(isset($updateData['ebaytitle']) && (strpos($updateData['ebaytitle'], '#'.$key.'#') !== false)) {
									$updateData['ebaytitle'] = str_replace('#'.$key.'#', $variable, $updateData['ebaytitle']);
								}
								if(isset($updateData['description']) && (strpos($updateData['description'], '#'.$key.'#') !== false)) {
									$updateData['description'] = str_replace('#'.$key.'#', $variable, $updateData['description']);
								}
								if(isset($updateData['shopdescription']) && (strpos($updateData['shopdescription'], '#'.$key.'#') !== false)) {
									$updateData['shopdescription'] = str_replace('#'.$key.'#', $variable, $updateData['shopdescription']);
								}
								if(isset($updateData['shopdescriptionshort']) && (strpos($updateData['shopdescriptionshort'], '#'.$key.'#') !== false)) {
									$updateData['shopdescriptionshort'] = str_replace('#'.$key.'#', $variable, $updateData['shopdescriptionshort']);
								}
								if(isset($updateData['shopdescriptionmini']) && (strpos($updateData['shopdescriptionmini'], '#'.$key.'#') !== false)) {
									$updateData['shopdescriptionmini'] = str_replace('#'.$key.'#', $variable, $updateData['shopdescriptionmini']);
								}
								if(count($images)) {
									foreach($images as $id => $image) {
										if(isset($image['title'])) {
											if(strpos($image['title'], '#'.$key.'#') !== false) {
												$images[$id]['title'] = str_replace('#'.$key.'#', $variable, $image['title']);
											}
										}
									}
								}
							}
							//Search and replace attribute placeholders
							foreach($attributes as $attribute) {
								if(isset($updateData['title']) && (strpos($updateData['title'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['title'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['title']);
								}
								if(isset($updateData['shoptitle']) && (strpos($updateData['shoptitle'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['shoptitle'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['shoptitle']);
								}
								if(isset($updateData['ebaytitle']) && (strpos($updateData['ebaytitle'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['ebaytitle'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['ebaytitle']);
								}
								if(isset($updateData['description']) && (strpos($updateData['description'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['description'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['description']);
								}
								if(isset($updateData['shopdescription']) && (strpos($updateData['shopdescription'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['shopdescription'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['shopdescription']);
								}
								if(isset($updateData['shopdescriptionshort']) && (strpos($updateData['shopdescriptionshort'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['shopdescriptionshort'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['shopdescriptionshort']);
								}
								if(isset($updateData['shopdescriptionmini']) && (strpos($updateData['shopdescriptionmini'], '#'.$attribute['title'].'#') !== false)) {
									$updateData['shopdescriptionmini'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $updateData['shopdescriptionmini']);
								}
								if(count($images)) {
									foreach($images as $id => $image) {
										if(isset($image['title'])) {
											if(strpos($image['title'], '#'.$attribute['title'].'#') !== false) {
												$images[$id]['title'] = str_replace('#'.$attribute['title'].'#', $attribute['value'], $image['title']);
											}
										}
									}
								}
							}
							//if(strpos($updateData['description'], '#manufacturer#') !== false) {
							//	$updateData['description'] = str_replace('#manufacturer#', $updateData['manufacturer'], $updateData['description']);
							//}
							$updateData['attributes'] = json_encode($attributes);
							//print_r('--------------------------------------');
							//print_r($updateData);
							//print_r('--------------------------------------');
							//print_r(json_decode($attributes, true));

							//Create and update the item
							if($item = $itemDb->getItemBySKU($datacsv[$map['sku']])) {
								//error_log(print_r($updateData,true));
								$itemDb->updateItem($item['id'], $updateData);
								if(isset($map['ebayuserid'])) {
									if($datacsv[$map['ebayuserid']] == 0) {
										$ebayitemDb->deleteEbayitem($item['id']);
									} elseif(!$ebayitemDb->getEbayitem($item['id'], $datacsv[$map['ebayuserid']])) {
										$ebayitemDb->addEbayitem(array('itemid' => $item['id'], 'ebayuserid' => $datacsv[$map['ebayuserid']]));
									}
								}
								if(isset($map['shopid'])) {
									if($datacsv[$map['shopid']] == 0) {
										$shopitemDb->deleteShopitem($item['id']);
									} elseif(!$shopitemDb->getShopitem($item['id'], $datacsv[$map['shopid']])) {
										$shopitemDb->addShopitem(array('itemid' => $item['id'], 'shopid' => $datacsv[$map['shopid']]));
									}
								}
							} else {
								$updateData['sku'] = $datacsv[$map['sku']];
								$updateData['inventory'] = 1;
								$updateData['currency'] = 'EUR';
								$updateData['taxid'] = 1;
								if(!$updateData['catid']) $updateData['catid'] = 0;
								$itemid = $itemDb->addItem($updateData);
								if(isset($map['ebayuserid'])) {
									if($datacsv[$map['ebayuserid']] == 0) {
										$ebayitemDb->deleteEbayitem($itemid);
									} else {
										$ebayitemDb->addEbayitem(array('itemid' => $itemid, 'ebayuserid' => $datacsv[$map['ebayuserid']]));
									}
								}
								if(isset($map['shopid'])) {
									if($datacsv[$map['shopid']] == 0) {
										$shopitemDb->deleteShopitem($itemid);
									} else {
										$shopitemDb->addShopitem(array('itemid' => $itemid, 'shopid' => $datacsv[$map['shopid']]));
									}
								}
							}

							//Delete existing item images
							$itemImage->deleteItemimagesByItemID($item['id']);

							//Create and update item images
							foreach($images as $image) {
								if(isset($image['url']) && $image['url']) {
									$image['itemid'] = $item['id'];
									//error_log(var_dump($image));
									$itemImage->addItemimage($image);
								}
							}
						}
						$row++;
					}
					//print_r($map);
					fclose($data);
				}

				$this->view->data = $data;
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	protected function uploadAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$form = new Application_Form_Upload();
		//$form->file->setDestination('/var/www/dewawi/files/');

		if ($this->getRequest()->isPost()) {
			$formData = $this->getRequest()->getPost();
			if ($form->isValid($formData)) {
				$id = $this->_getParam('id', 0);
				$contactid = $this->_getParam('contactid', 0);

				if(!file_exists(BASE_PATH.'/files/images/')) {
					mkdir(BASE_PATH.'/files/images/');
					chmod(BASE_PATH.'/files/images/', 0777);
				}

				/* Uploading Document File on Server */
				$upload = new Zend_File_Transfer_Adapter_Http();
				$upload->setDestination(BASE_PATH.'/files/images/');
				try {
					// upload received file(s)
					$upload->receive();
					$location = $upload->getFileName();
					$locationArray = explode('/',$location);
					$data = array();
					$data['image'] = end($locationArray);
					$item = new Items_Model_DbTable_Item();
					$item->updateItem($id, $data);
				} catch (Zend_File_Transfer_Exception $e) {
					$e->getMessage();
				}
			} else {
				$form->populate($formData);
			}
		}

		$this->view->form = $form;
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

	public function lockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->lock($id, $this->_user['id']);
	}

	public function unlockAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->unlock($id);
	}

	public function keepaliveAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->_helper->Validate();
	}

	public function getProductCategoryIndex() {
		$categoryDb = new Application_Model_DbTable_Category();
		$categories = $categoryDb->getCategories('item');
		$categoriesByID = array();
		foreach($categories as $category) {
			$categoriesByID[$category['id']] = $category['title'];
		}

		$childCategories = array();
		foreach($categories as $category) {
			if(isset($childCategories[$category['parentid']])) {
				array_push($childCategories[$category['parentid']], $category['id']);
			} else {
				$childCategories[$category['parentid']] = array($category['id']);
			}
		}

		$categoryIndex = array();
		foreach($categories as $category) {
			if($category['parentid'] == 0) {
				$categoryIndex[md5($category['title'])]['id'] = $category['id'];
				$categoryIndex[md5($category['title'])]['title'] = $category['title'];
				if(isset($childCategories[$category['id']])) {
					$categoryIndex[md5($category['title'])]['childs'] = $this->getSubCategoryIndex($categoriesByID, $childCategories, $category['id']);
				}
			}
		}
		//var_dump($categoriesByID);
		//var_dump($childCategories);
		//var_dump($categoryIndex);

		return $categoryIndex;
	}

	public function getSubCategoryIndex($categories, $childCategories, $id) {
		$subCategories = array();
		foreach($childCategories[$id] as $child) {
			$subCategories[md5($categories[$child])]['id'] = $child;
			$subCategories[md5($categories[$child])]['title'] = $categories[$child];
			if(isset($childCategories[$child])) {
				$subCategories[md5($categories[$child])]['childs'] = $this->getSubCategoryIndex($categories, $childCategories, $child);
			}
		}
		return $subCategories;
	}

	// NOTE: This is experimental and not properly tested
	protected function magento($updateDataMagento)
	{
		if(file_exists(BASE_PATH.'/configs/magento.ini')) {
			$magentoConfig = new Zend_Config_Ini(BASE_PATH.'/configs/magento.ini', 'production');
			//$this->_helper->viewRenderer->setNoRender();
			//$this->_helper->getHelper('layout')->disableLayout();

			// Created by Rafael Corrêa Gomes
			// Reference http://devdocs.magento.com/guides/m1x/api/rest/introduction.html#RESTAPIIntroduction-RESTResources
			// Custom Resource
			$apiResources = "products?limit=2";
			// Custom Values
			$isAdminUser = true;
			$adminUrl = "admin";
			$host = $magentoConfig->host;
			$fetchUrl = $magentoConfig->fetchUrl;
			$callbackUrl = $magentoConfig->callbackUrl;
			$consumerKey	= $magentoConfig->consumerKey;
			$consumerSecret = $magentoConfig->consumerSecret;
			// Don't change
			$temporaryCredentialsRequestUrl = $host . "oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
			$adminAuthorizationUrl = ($isAdminUser) ? $host . $adminUrl . "/oauth_authorize" : $host . "oauth/authorize";
			$accessTokenRequestUrl = $host . "oauth/token";
			$apiUrl = $host . "api/rest/";
			//session_start();
			if(!isset($_SESSION['state'])) $_SESSION['state'] = 0;
			if (!isset($_GET['oauth_token']) && isset($_SESSION['state']) && $_SESSION['state'] == 1) {
				$_SESSION['state'] = 0;
			}
			//print_r($_GET);
			//print_r($_SESSION);
			try {
				$authType = ($_SESSION['state'] == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;
				//$oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_PLAINTEXT, $authType);
				$oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);
				//print_r($oauthClient);
				$oauthClient->enableDebug();
				//print_r('test');
				if (!isset($_GET['oauth_token']) && !$_SESSION['state']) {
					//print_r($temporaryCredentialsRequestUrl);
					$requestToken = $oauthClient->getRequestToken($temporaryCredentialsRequestUrl);
					//print_r($requestToken);
					$_SESSION['secret'] = $requestToken['oauth_token_secret'];
					$_SESSION['state'] = 1;
					header('Location: ' . $adminAuthorizationUrl . '?oauth_token=' . $requestToken['oauth_token']);
					exit;
				} else if ($_SESSION['state'] == 1) {
					$oauthClient->setToken($_GET['oauth_token'], $_SESSION['secret']);
					$accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);
					$_SESSION['state'] = 2;
					$_SESSION['token'] = $accessToken['oauth_token'];
					$_SESSION['secret'] = $accessToken['oauth_token_secret'];
					//print_r($accessToken);
					//print_r($_SESSION);
					header('Location: ' . $callbackUrl);
					exit;
				} else {
					$oauthClient->setToken($_SESSION['token'], $_SESSION['secret']);
					//$resourceUrl = $apiUrl.$apiResources;
					$oauthClient->fetch($fetchUrl.$updateDataMagento['sku'], array(), 'GET', array('Content-Type' => 'application/json', 'Accept' => '*/*'));
					$product = json_decode($oauthClient->getLastResponse(), true);
					//$product = $oauthClient->getLastResponse();

					$updateData = array();
					foreach($updateDataMagento as $attr => $data) {
						if(array_key_exists($attr, $product)) {
							if($attr == 'weight') {
								$updateData['weight'] = preg_replace("/[^0-9]/", '', $data);
							} else {
								$updateData[$attr] = $data;
							}
						}
					}
					unset($updateData['manufacturer']);
					unset($updateData['refrigerant']);
					unset($updateData['delivery_time']);
					unset($updateData['delivery_time_oos']);
					//print_r($updateData);
					$updateData = json_encode($updateData);

					//print_r($updateData);

					//$updateData['sku'] = $updateDataMagento['sku'];
					//$updateData['name'] = $updateDataMagento['name'];
					//print_r($updateData);
					//print_r($product['sku']);
					//print_r('<br>');
					//print_r($product['entity_id']);
					//print_r('<br>');

					$oauthClient->fetch($fetchUrl.$product['entity_id'], $updateData, 'PUT', array('Content-Type' => 'application/json', 'Accept' => '*/*'));
					//$response = json_decode($oauthClient->getLastResponse(), true);
					//print_r($response);
					//print_r($oauthClient);
					//print_r(opcache_get_status());
				}
			} catch (OAuthException $e) {
				echo "<pre>";
				print_r($e->getMessage());
				echo "<br/>";
				print_r($e->lastResponse);
				echo "</pre>";
			}
		}
	}
}
