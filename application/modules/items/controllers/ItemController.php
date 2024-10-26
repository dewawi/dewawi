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
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'export', $this->_flashMessenger);
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$tags = $get->tags('items', 'item');
		list($items, $records) = $get->items($params, $options);

		$tagEntites = array();
		foreach($items as $item) {
			$tagEntites[$item->id] = $get->tags('items', 'item', $item->id);
		}

		$this->view->tags = $tags;
		$this->view->tagEntites = $tagEntites;
		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
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
		$tags = $get->tags('items', 'item');
		list($items, $records) = $get->items($params, $options);

		$tagEntites = array();
		foreach($items as $item) {
			$tagEntites[$item->id] = $get->tags('items', 'item', $item->id);
		}

		$this->view->tags = $tags;
		$this->view->tagEntites = $tagEntites;
		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->parent = $this->_getParam('parent', NULL);
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		list($items, $records) = $get->items($params, $options);

		$this->view->items = $items;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->parent = $params['parent'];
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
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

					//Tags
					$get = new Items_Model_Get();
					$tags = $get->tags('items', 'item', $item['id']);

					//History
					$inventoryDb = new Items_Model_DbTable_Inventory();
					$inventory = $inventoryDb->getInventoryBySKU($item['sku']);

					//Toolbar
					$toolbar = new Items_Form_Toolbar();

					//Get images
					$imagesDb = new Application_Model_DbTable_Media();
					$images = $imagesDb->getMediaByParentID($id, 'items', 'item');

					//Get image path
					$clientid = $this->view->client['id'];
					$dir1 = substr($clientid, 0, 1);
					if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
					else $dir2 = '0';
					$imagePath = $dir1.'/'.$dir2.'/'.$clientid;

					// Scan subfolders in media/images
					$this->view->subfolders = $this->getSubfolders(BASE_PATH . '/media/'.$imagePath.'/images/');

					$this->view->form = $form;
					$this->view->tags = $tags;
					$this->view->images = $images;
					$this->view->imagePath = $imagePath;
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

	public function downloadAction()
	{
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$catid = $this->_getParam('catid', 0);

		if($id) {
			$this->_helper->viewRenderer->setRender('pdf');
			//$this->_helper->viewRenderer->setNoRender();

			$itemDb = new Items_Model_DbTable_Item();
			$item = $itemDb->getItem($id);

			//Get attribute sets
			$attributeSetsDb = new Items_Model_DbTable_Itematrset();
			$attributeSets = $attributeSetsDb->getPositionSets($item['id']);

			//Get attributes
			$attributesDb = new Items_Model_DbTable_Itematr();
			$attributes = $attributesDb->getPositions($item['id']);

			//Get images
			$imagesDb = new Application_Model_DbTable_Media();
			$images = $imagesDb->getMediaByParentID($item['id'], 'items', 'item');

			//Attributes
			$attributesByGroup = array();
			$attributesDb = new Items_Model_DbTable_Itematr();
			foreach($attributeSets as $id => $attributeSet) {
				$attributesByGroup[$id] = array();
				$attributesByGroup[$id]['title'] = $attributeSet['title'];
				$attributesByGroup[$id]['description'] = $attributeSet['description'];
				$attributesByGroup[$id]['attributes'] = $attributesDb->getPositions($item['id'], $attributeSet['id']);
			}
			$otherAttributes = $attributesDb->getPositions($item['id'], 0);
			if(count($otherAttributes)) {
				$attributesByGroup[] = array(
					'title' => 'Sonstiges',
					'description' => '',
					'attributes' => $otherAttributes
				);
			}

			$this->view->item = $item;
			$this->view->images = $images;
			$this->view->attributesByGroup = $attributesByGroup;
		}
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$item = new Items_Model_DbTable_Item();
			$item->deleteItem($id);

			//Delete eBay listing if exists
			$ebayListingDb = new Ebay_Model_DbTable_Listing();
			$ebayListingDb->deleteListingByItemID($id);
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

				//Get manufacturers
				$manufacturersDb = new Application_Model_DbTable_Manufacturer();
				$manufacturers = $manufacturersDb->getManufacturers();

				//Get delivery times
				$deliverytimesDb = new Application_Model_DbTable_Deliverytime();
				$deliverytimes = $deliverytimesDb->getDeliverytimes();

				//Get uoms
				$uomsDb = new Application_Model_DbTable_Uom();
				$uoms = $uomsDb->getUoms();

				//Get tax rates
				$taxratesDb = new Application_Model_DbTable_Taxrate();
				$taxrates = $taxratesDb->getTaxRates();

				//Get currencies
				$currenciesDb = new Application_Model_DbTable_Currency();
				$currencies = $currenciesDb->getCurrencies();

				if($data && ($data !== FALSE)) {
					$map = array();
					$dataTemplate = array();
					$itemDb = new Items_Model_DbTable_Item();
					$itemInfo = $itemDb->getInfo();
					$itemMedia = new Application_Model_DbTable_Media();
					$itemAttribute = new Items_Model_DbTable_Itematr();
					$itemAttributeSet = new Items_Model_DbTable_Itematrset();
					$itemOption = new Items_Model_DbTable_Itemopt();
					$itemOptionSet = new Items_Model_DbTable_Itemoptset();
					$ebayAccountDb = new Ebay_Model_DbTable_Account();
					$ebayListingDb = new Ebay_Model_DbTable_Listing();
					//$shopItemDb = new Shops_Model_DbTable_Item();
					//$shopCategoryDb = new Shops_Model_DbTable_Category();

					//Get categories
					$itemCategoryIndex = $this->getProductCategoryIndex('item');
					$shopCategoryIndex = $this->getProductCategoryIndex('shop');

					//print_r($shopCategoryIndex);

					//Get
					if($formData['separator'] == 'comma') $separator = ',';
					if($formData['separator'] == 'semicolon') $separator = ';';
					if($formData['delimiter'] == 'single') $delimeter = "'";
					if($formData['delimiter'] == 'double') $delimeter = '"';

					$row = 0;
					$rowsUpdated = 0;
					$rowsCreated = 0;
					while(($datacsv = fgetcsv($data, 0, $separator, $delimeter)) !== FALSE) {
						if($row == 0) {
							foreach($datacsv as $pos => $attr) {
								if($attr) {
									$map[$attr] = $pos;
								}
							}
						//if(count($map) > 1) {
						//SKU is mandatory
						} elseif(isset($map['sku']) && isset($datacsv[$map['sku']]) && $datacsv[$map['sku']]) {
							//print_r($itemInfo);
							$images = array();
							$attributes = array();
							$options = array();
							$placeholders = array();
							$updateData = array();
							$shopData = array();
							foreach($map as $attr => $pos) {
								if(isset($datacsv[$map[$attr]])) {
									if(array_search($attr, $itemInfo)) {
										if($attr == 'weight') {
											if(is_numeric($datacsv[$map['weight']])) $updateData['weight'] = $datacsv[$map['weight']];
										} elseif($attr == 'price') {
											if(isset($map['dewawidiscount']) && $datacsv[$map['dewawidiscount']]) {
												$updateData['price'] = $datacsv[$map['price']] * (100 - $datacsv[$map['dewawidiscount']])/100;
											} elseif($datacsv[$map[$attr]]) {
												if(is_numeric($datacsv[$map[$attr]])) {
													$updateData['price'] = $datacsv[$map[$attr]];
												} else {
													echo 'Price is not numeric for '.$datacsv[$map['sku']].': '.$datacsv[$map[$attr]]."<br>";
												}
											}
										} elseif($attr == 'quantity') {
											if($datacsv[$map[$attr]] && is_numeric($datacsv[$map[$attr]])) {
												$updateData[$attr] = $datacsv[$map[$attr]];
											} else {
												echo 'Quantity is not numeric for '.$datacsv[$map['sku']].': '.$datacsv[$map[$attr]]."<br>";
											}
										} elseif(($attr == 'deliverytime') || ($attr == 'deliverytimeoos')) {
											if($deliverytimeid = array_search($datacsv[$map[$attr]], $deliverytimes)) {
												$updateData[$attr] = $deliverytimeid;
											} else {
												echo 'No delivery time option found for '.$datacsv[$map['sku']].': '.$datacsv[$map[$attr]]."<br>";
											}
										} elseif($attr == 'currency') {
											if($currencyid = array_search($datacsv[$map[$attr]], $currencies)) {
												$updateData[$attr] = $currencyid;
											} else {
												echo 'No currency option found for '.$datacsv[$map['sku']].': '.$datacsv[$map[$attr]]."<br>";
											}
										} elseif($attr == 'inventory') {
											if($datacsv[$map[$attr]] && is_numeric($datacsv[$map[$attr]])) {
												$updateData[$attr] = $datacsv[$map[$attr]];
											}
										} else {
											$updateData[$attr] = $datacsv[$map[$attr]];
											//var_dump($datacsv[$map[$attr]]);
										}
									} elseif($attr == 'category') {
										$updateData['catid'] = 0;
										$currentCategory = $itemCategoryIndex;
										$itemCategories = explode(' > ', $datacsv[$map['category']]);
										foreach($itemCategories as $itemCategory) {
											if(isset($currentCategory[md5($itemCategory)])) {
												$currentCategory = $currentCategory[md5($itemCategory)];
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
									} elseif($attr == 'shopcategory') {
										$updateData['shopcatid'] = 0;
										$currentCategory = $shopCategoryIndex;
										$shopCategories = explode(' > ', $datacsv[$map['shopcategory']]);
										//print_r($currentCategory);
										foreach($shopCategories as $shopCategory) {
											if(isset($currentCategory[md5($shopCategory)])) {
												$currentCategory = $currentCategory[md5($shopCategory)];
												$updateData['shopcatid'] = $currentCategory['id'];
												if(isset($currentCategory['childs'])) $currentCategory = $currentCategory['childs'];
											} else {
												$updateData['shopcatid'] = 0;
											}
										}
										if($updateData['shopcatid'] == 0) {
											/* TO DO handle if no category found */
											echo 'No shop category found for '.$datacsv[$map['sku']].': '.$datacsv[$map['shopcategory']]."<br>";
										}
										//echo 'Set shop category for '.$datacsv[$map['sku']].': '.$datacsv[$map['shopcategory']].'/'.$updateData['shopcatid']."<br>";
									} elseif($attr == 'uom') {
										if($uomid = array_search($datacsv[$map[$attr]], $uoms)) {
											$updateData['uomid'] = $uomid;
										} else {
											echo 'No uom option found for '.$datacsv[$map['sku']].': '.$datacsv[$map[$attr]]."<br>";
										}
									} elseif($attr == 'manufacturer') {
										if($manufacturerid = array_search($datacsv[$map['manufacturer']], $manufacturers)) {
											$updateData['manufacturerid'] = $manufacturerid;
										} else {
											echo 'No manufacturer found for '.$datacsv[$map['sku']].': '.$datacsv[$map['manufacturer']]."<br>";
										}
									} elseif($attr == 'tax') {
										if($taxid = array_search($datacsv[$map['tax']], $taxrates)) {
											$updateData['taxid'] = $taxid;
										} else {
											echo 'No tax rate found for '.$datacsv[$map['tax']].': '.$datacsv[$map['taxid']]."<br>";
										}
									/*} elseif($attr == 'weightunit') {
										if($weightuomid = array_search($datacsv[$map['weightunit']], $uoms)) {
											$updateData['weightuomid'] = $weightuomid;
										}*/
									} elseif((strpos($attr, 'image') !== FALSE) && (strpos($attr, 'url') !== FALSE)) {
										$imageUrl = $datacsv[$map[$attr]];
										$url = '/media/'.$dir1.'/'.$dir2.'/'.$clientid.'/images/';

										if(file_exists(BASE_PATH.$url.$imageUrl)) {
											$imageID = str_replace('image', '', str_replace('url', '', $attr));
											$images[$imageID]['url'] = $imageUrl;
											if(isset($map['image'.$imageID.'ordering']) && $datacsv[$map['image'.$imageID.'ordering']]) {
												$images[$imageID]['ordering'] = $datacsv[$map['image'.$imageID.'ordering']];
											} else {
												$images[$imageID]['ordering'] = $imageID;
											}
										} else {
											echo 'Media file not exists for '.$datacsv[$map['sku']].': '.BASE_PATH.$url.$imageUrl."<br>";
										}
									} elseif((strpos($attr, 'image') !== FALSE) && (strpos($attr, 'title') !== FALSE)) {
										$images[str_replace('image', '', str_replace('title', '', $attr))]['title'] = $datacsv[$map[$attr]];
									}
									//Add system variables to placeholders
									if($datacsv[$map[$attr]]) {
										$placeholders[] = array('title' => $attr, 'value' => $datacsv[$map[$attr]]);
									}
								}
							}

							//Create and update the item
							if($item = $itemDb->getItemBySKU($datacsv[$map['sku']])) {
								//Get item attributes and add to placeholders
								$attributes = $itemAttribute->getPositions($item['id'])->toArray();
								foreach($attributes as $attribute) {
									$placeholders[] = array('title' => $attribute['title'], 'value' => $attribute['description']);
								}

								//Search and replace placeholders
								foreach($placeholders as $placeholder) {
									if($placeholder['title'] && $placeholder['value']) {
										//Search and replace placeholders in import data
										foreach($updateData as $key => $value) {
											if(strpos($value, '#'.$placeholder['title'].'#') !== false) {
												$updateData[$key] = str_replace('#'.$placeholder['title'].'#', $placeholder['value'], $value);
											}
										}
										//Search and replace placeholders in image titles
										if(count($images)) {
											foreach($images as $id => $image) {
												if(isset($image['title'])) {
													if(strpos($image['title'], '#'.$placeholder['title'].'#') !== false) {
														$images[$id]['title'] = str_replace('#'.$placeholder['title'].'#', $placeholder['value'], $image['title']);
													}
												}
											}
										}
									}
								}
								//print_r($placeholders);
								//print_r($updateData);

								//Update eBay listing
								if(isset($map['ebayuserid'])) {
									if($datacsv[$map['ebayuserid']] == 0) {
										$ebayListingDb->deleteListingByItemID($item['id']);
										echo 'Item deleted from eBay: '.$updateData['sku'].', itemid: '.$item['id'].'<br>';
									} elseif($datacsv[$map['ebayuserid']]) {
										$ebayListingDb->deleteListingByItemID($item['id']);
										$ebayAccount = $ebayAccountDb->getAccountByUserID($datacsv[$map['ebayuserid']]);
										$ebayListingData = array();
										$ebayListingData['itemid'] = $item['id'];
										$ebayListingData['accountid'] = $ebayAccount['id'];
										if(isset($map['ebaycategory1'])) $ebayListingData['category1'] = $datacsv[$map['ebaycategory1']];
										if(isset($map['ebaycategory2'])) $ebayListingData['category2'] = $datacsv[$map['ebaycategory2']];
										if(isset($map['ebaystorecategory1'])) $ebayListingData['ebaystorecategory1'] = $datacsv[$map['ebaystorecategory1']];
										if(isset($map['ebaystorecategory2'])) $ebayListingData['ebaystorecategory2'] = $datacsv[$map['ebaystorecategory2']];
										$ebayListingDb->addListing($ebayListingData);
									}
								}

								//Update shop listing
								/*if(isset($map['shopid'])) {
									$shopItemDb->deleteItemByItemId($item['id']);
									$slug = $this->slugify($item['title']);
									if(isset($map['shopid']) && ($datacsv[$map['shopid']] > 0) && $shopCategoryId) {
										$shopItemDb->addItem(array('itemid' => $item['id'], 'slug' => $slug, 'shopid' => $datacsv[$map['shopid']], 'catid' => $shopCategoryId));
										echo 'Item added to shop: '.$updateData['sku'].', itemid: '.$item['id'].' to '.$datacsv[$map['shopid']].'<br>';
									}
								}*/

								//Update slug
								//if(isset($updateData['title'])) {
								//	$updateData['slug'] = $this->slugify($item['title']);
								//}

								//print_r($updateData);
								$itemDb->updateItem($item['id'], $updateData);
								++$rowsUpdated;

								//error_log(print_r($updateData,true));

								//Delete existing images
								$itemMedia->deleteMediaByParentID($item['id'], 'items', 'item');

								//Create and update images
								foreach($images as $image) {
									if(isset($image['url']) && $image['url']) {
										$image['parentid'] = $item['id'];
										$image['module'] = 'items';
										$image['controller'] = 'item';
										//error_log(var_dump($image));
										$itemMedia->addMedia($image);
									}
								}
							} else {
								$updateData['sku'] = $datacsv[$map['sku']];
								if(!isset($updateData['taxid']) || !$updateData['taxid']) {
									//Get primary tax rate
									$taxrates = new Application_Model_DbTable_Taxrate();
									$taxrate = $taxratesDb->getPrimaryTaxrate();
									$updateData['taxid'] = $taxrate['id'];
								}
								if(!isset($updateData['currency']) || !$updateData['currency']) $updateData['currency'] = 'EUR';
								if(!isset($updateData['catid']) || !$updateData['catid']) $updateData['catid'] = 0;
								if(!isset($updateData['minquantity']) || !$updateData['minquantity']) $updateData['minquantity'] = NULL;
								if(!isset($updateData['orderquantity']) || !$updateData['orderquantity']) $updateData['orderquantity'] = NULL;
								if(!isset($updateData['length']) || !$updateData['length']) $updateData['length'] = NULL;
								if(!isset($updateData['width']) || !$updateData['width']) $updateData['width'] = NULL;
								if(!isset($updateData['height']) || !$updateData['height']) $updateData['height'] = NULL;
								if(!isset($updateData['inventory'])) $updateData['inventory'] = 1;
								$itemid = $itemDb->addItem($updateData);
								if(isset($map['ebayuserid'])) {
									if($datacsv[$map['ebayuserid']] == 0) {
										$ebayListingDb->deleteListingByItemID($itemid);
										echo 'Item deleted from eBay: '.$updateData['sku'].', itemid: '.$itemid.'<br>';
									} elseif($datacsv[$map['ebayuserid']]) {
										$ebayAccount = $ebayAccountDb->getAccountByUserID($datacsv[$map['ebayuserid']]);
										$ebayListingDb->addListing(array('itemid' => $itemid, 'accountid' => $ebayAccount['id']));
									}
								}
								/*if(isset($map['shopid'])) {
									echo 'Item shopid: '.$map['shopid'].'<br>';
									if($datacsv[$map['shopid']] == 0) {
										$shopItemDb->deleteItem($itemid);
										echo 'Item deleted from shop: '.$updateData['sku'].', itemid: '.$itemid.'<br>';
									} elseif($map['shopid']) {
										$shopItemDb->addItem(array('itemid' => $itemid, 'shopid' => $datacsv[$map['shopid']], 'catid' => 0));
										echo 'Item added to shop: '.$updateData['sku'].', itemid: '.$itemid.' to '.$datacsv[$map['shopid']].'<br>';
									}
								}*/
								++$rowsCreated;

								//Create images
								foreach($images as $image) {
									if(isset($image['url']) && $image['url']) {
										$image['parentid'] = $itemid;
										$image['module'] = 'items';
										$image['controller'] = 'item';
										//error_log(var_dump($image));
										$itemMedia->addMedia($image);
									}
								}

								//Create and item attributes
								/*$ordering = 1;
								foreach($attributes as $key => $attribute) {
									if(isset($attribute['value']) && $attribute['value']) {
										$attribute['itemid'] = $itemid;
										$attribute['ordering'] = $ordering;
										$itemAttribute->addItemattribute($attribute);
										++$ordering;
									}
								}*/
							}
						} else {
							echo 'There is no SKU column!'."<br>";
						}
						$row++;
					}
					//print_r($map);
					fclose($data);
				} else {
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

	public function exportAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Items_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Items_Model_Get();
		$tags = $get->tags('items', 'item');
		$params['limit'] = 0;
		list($items, $records) = $get->items($params, $options);

		$tagEntites = array();
		foreach($items as $item) {
			$tagEntites[$item->id] = $get->tags('items', 'item', $item->id);
		}

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';
		$itemsFileCsv = 'items-'.time().'.csv';
		$itemsFileZip = 'items-'.time().'.zip';

		//Get csv data
		if(count($items)) {
			//Create CSV data
			$csvData = array();
			$csvData[0]['id'] = 'id';
			$csvData[0]['sku'] = 'sku';
			$csvData[0]['gtin'] = 'gtin';
			$csvData[0]['title'] = 'title';
			$csvData[0]['description'] = 'description';
			$csvData[0]['quantity'] = 'quantity';
			$csvData[0]['price'] = 'price';
			$csvData[0]['currency'] = 'currency';
			$csvData[0]['taxid'] = 'taxid';
			$csvData[0]['manufacturersku'] = 'manufacturersku';
			$csvData[0]['manufacturergtin'] = 'manufacturergtin';
			$csvData[0]['length'] = 'length';
			$csvData[0]['width'] = 'width';
			$csvData[0]['height'] = 'height';
			$csvData[0]['weight'] = 'weight';
			$csvData[0]['tags'] = 'tags';
			foreach($items as $item) {
				$csvData[$item->id]['id'] = $item->id;
				$csvData[$item->id]['sku'] = $item->sku;
				$csvData[$item->id]['gtin'] = $item->gtin;
				$csvData[$item->id]['title'] = $item->title;
				$csvData[$item->id]['description'] = $item->description;
				$csvData[$item->id]['quantity'] = $item->quantity;
				$csvData[$item->id]['price'] = $item->price;
				$csvData[$item->id]['currency'] = $item->currency;
				$csvData[$item->id]['taxid'] = $item->taxid;
				$csvData[$item->id]['manufacturersku'] = $item->manufacturersku;
				$csvData[$item->id]['manufacturergtin'] = $item->manufacturergtin;
				$csvData[$item->id]['length'] = $item->length;
				$csvData[$item->id]['width'] = $item->width;
				$csvData[$item->id]['height'] = $item->height;
				$csvData[$item->id]['weight'] = $item->weight;
				if(isset($options['categories'][$item->catid])) $csvData[$item->id]['category'] = $options['categories'][$item->catid]['title'];
				$tags = '';
				foreach ($tagEntites[$item->id] as $entity) {
					$tags .= $entity['tag'];
				}
				$csvData[$item->id]['tags'] = $tags;
			}
			//Save data to items.csv
			$itemsFile = fopen($filePath.$itemsFileCsv, 'w');
			foreach($csvData as $fields) {
				fputcsv($itemsFile, $fields);
			}
			fclose($itemsFile);

			//Create product zip archive
			$zip = new ZipArchive;
			$status = $zip->open($filePath.$itemsFileZip, ZipArchive::CREATE);
			if($status === TRUE) {
				$zip->addFile($filePath.$itemsFileCsv, $itemsFileCsv);
				$zip->close();
			} else {
			}

			// We'll be outputting a PDF
			header('Content-type: text/csv');

			// It will be called downloaded.pdf
			header('Content-Disposition: attachment; filename="'.$itemsFileCsv.'"');

			// The PDF source is in original.pdf
			readfile($filePath.$itemsFileCsv);
		} else {
			$this->_helper->redirector->gotoSimple('index', 'item');
		}
	}

	public function pinAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Pin->toogle($id);
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

	public function getProductCategoryIndex($type) {
		$categoryDb = new Application_Model_DbTable_Category();
		$categories = $categoryDb->getCategories($type);
		//print_r($categories);
		$categoriesByID = array();
		foreach($categories as $category) {
			$categoriesByID[$category['id']] = array();
			$categoriesByID[$category['id']]['title'] = $category['title'];
			/*if(isset($category['shopid'])) {
				$categoriesByID[$category['id']]['shopid'] = $category['shopid'];
				$categoriesByID[$category['id']]['shopcatid'] = $category['shopcatid'];
			}*/
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
				/*if(isset($category['shopid'])) {
					$categoryIndex[md5($category['title'])]['shopid'] = $category['shopid'];
					$categoryIndex[md5($category['title'])]['shopcatid'] = $category['shopcatid'];
				}*/
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

	public function getSubCategoryIndex($categoriesByID, $childCategories, $id) {
		$subCategories = array();
		foreach($childCategories[$id] as $child) {
			$subCategories[md5($categoriesByID[$child]['title'])]['id'] = $child;
			$subCategories[md5($categoriesByID[$child]['title'])]['title'] = $categoriesByID[$child]['title'];
			/*if(isset($categoriesByID[$child]['shopid'])) {
				$subCategories[md5($categoriesByID[$child]['title'])]['shopid'] = $categoriesByID[$child]['shopid'];
				$subCategories[md5($categoriesByID[$child]['title'])]['shopcatid'] = $categoriesByID[$child]['shopcatid'];
			}*/
			if(isset($childCategories[$child])) {
				$subCategories[md5($categoriesByID[$child]['title'])]['childs'] = $this->getSubCategoryIndex($categoriesByID, $childCategories, $child);
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

	protected function slugify($string) {
		// Replace non letter or digits with hyphens
		$string = preg_replace('~[^\pL\d]+~u', '-', $string);

		// Transliterate
		$string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);

		// Remove unwanted characters
		$string = preg_replace('~[^-\w]+~', '', $string);

		// Trim hyphens from beginning and end
		$string = trim($string, '-');

		// Convert to lowercase
		$string = strtolower($string);

		if (empty($string)) {
			return 'n-a';
		}

		return $string;
	}

	protected function getSubfolders($directory)
	{
		$subfolders = [];
		if (is_dir($directory)) {
			$items = scandir($directory);
			foreach ($items as $item) {
				if ($item != '.' && $item != '..' && is_dir($directory . $item)) {
					$subfolders[] = $item;
				}
			}
		}
		return $subfolders;
	}
}
