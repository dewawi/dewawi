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
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
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
				} else {
					throw new Exception('Form is invalid');
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
					$this->view->dirwritable = $dirwritable;
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

				if ($data && ($data !== FALSE)) {
					$map = array();
					$dataTemplate = array();
					$row = 0;
					while (($datacsv = fgetcsv($data, 0, ',')) !== FALSE) {
						//print_r($datacsv);
						if($row == 0) {
							foreach($datacsv as $pos => $attr) {
								if($attr) {
									$map[$attr] = $pos;
								}
							}
						//print_r($map);
						} elseif($row == 1) {
							if(isset($map['name_dewawi'])) $dataTemplate['name_dewawi'] = $datacsv[$map['name_dewawi']];
							if(isset($map['name_magento'])) $dataTemplate['name_magento'] = $datacsv[$map['name_magento']];
							if(isset($map['name_ebay'])) $dataTemplate['name_ebay'] = $datacsv[$map['name_ebay']];
							if(isset($map['mini_description_magento'])) $dataTemplate['mini_description_magento'] = $datacsv[$map['mini_description_magento']];
							if(isset($map['short_description_magento'])) $dataTemplate['short_description_magento'] = $datacsv[$map['short_description_magento']];
							if(isset($map['description_magento'])) $dataTemplate['description_magento'] = $datacsv[$map['description_magento']];
							if(isset($map['description_dewawi'])) $dataTemplate['description_dewawi'] = $datacsv[$map['description_dewawi']];
						} else {
							if(isset($map['mini_description_magento'])) $dataTemplate['mini_description_magento'] .= $datacsv[$map['mini_description_magento']];
							if(isset($map['short_description_magento'])) $dataTemplate['short_description_magento'] .= $datacsv[$map['short_description_magento']];
							if(isset($map['description_magento'])) $dataTemplate['description_magento'] .= $datacsv[$map['description_magento']];
							if(isset($map['description_dewawi'])) $dataTemplate['description_dewawi'] .= "\n".$datacsv[$map['description_dewawi']];
						}
						$row++;
					}
					fclose($data);
					$data = fopen($file, 'r');
					$row = 0;
					$item = new Items_Model_DbTable_Item();
					while (($datacsv = fgetcsv($data, 0, ',')) !== FALSE) {
						if($row == 0) {
							foreach($datacsv as $pos => $attr) {
								if($attr) {
									$map[$attr] = $pos;
								}
							}
						} elseif($datacsv[$map['sku']]) {
							//echo $datacsv[$map['sku']];
							//print_r($datacsv);
							$itemArray = $item->getItemBySKU($datacsv[$map['sku']]);
							//print_r($itemArray);
							$updateData = array();
							foreach($map as $attr => $pos) {
								if(isset($itemArray[$attr])) {
									if($attr == 'weight') {
										$updateData['weight'] = preg_replace("/[^0-9]/", '', $datacsv[$map[$attr]]);
									} elseif($attr != 'price') {
										$updateData[$attr] = $datacsv[$map[$attr]];
									}
								} elseif($attr == 'discount_dewawi') {
									$updateData['price'] = $datacsv[$map['price']] * (100 - $datacsv[$map['discount_dewawi']])/100;
									$updateData['price'] = str_replace(',', '.', $updateData['price']);
								} elseif($attr == 'price_dewawi') {
									$updateData['price'] = $datacsv[$map['price_dewawi']];
									$updateData['price'] = str_replace(',', '.', $updateData['price']);
								} elseif($attr == 'name_dewawi') {
									$name_dewawi = $dataTemplate['name_dewawi'];
									foreach($map as $attrSub => $pos) {
										if (strpos($dataTemplate['name_dewawi'], '#'.$attrSub.'#') !== false) {
											$name_dewawi = str_replace('#'.$attrSub.'#', $datacsv[$map[$attrSub]], $name_dewawi);
										}
									}
									$updateData['title'] = $name_dewawi;
								} elseif($attr == 'description_dewawi') {
									$description_dewawi = $dataTemplate['description_dewawi'];
									foreach($map as $attrSub => $pos) {
										if (strpos($dataTemplate['description_dewawi'], '#'.$attrSub.'#') !== false) {
											$description_dewawi = str_replace('#'.$attrSub.'#', $datacsv[$map[$attrSub]], $description_dewawi);
										}
									}
									$updateData['description'] = trim($description_dewawi);
								}
							}
							//print_r($updateData);
							$item->updateItem($itemArray['id'], $updateData);

							if(isset($map['shop_enabled']) && $datacsv[$map['shop_enabled']]) {
								$updateDataMagento = array();
								foreach($map as $attr => $pos) {
									if($attr == 'discount_shop') {
										$updateDataMagento['special_price'] = $datacsv[$map['price']] * (100 - $datacsv[$map['discount_shop']])/100;
										$updateDataMagento['special_price'] = str_replace(',', '.', $updateDataMagento['special_price']);
									} elseif($attr == 'price_shop') {
										$updateDataMagento['special_price'] = $datacsv[$map['price_shop']];
										$updateDataMagento['special_price'] = str_replace(',', '.', $updateDataMagento['special_price']);
									} elseif($attr == 'name_magento') {
										$name_magento = $dataTemplate['name_magento'];
										foreach($map as $attr => $pos) {
											if (strpos($dataTemplate['name_magento'], '#'.$attr.'#') !== false) {
												$name_magento = str_replace('#'.$attr.'#', $datacsv[$map[$attr]], $name_magento);
											}
										}
										$updateDataMagento['name'] = $name_magento;
									} elseif($attr == 'name_ebay') {
										$name_ebay = $dataTemplate['name_ebay'];
										foreach($map as $attr => $pos) {
											if (strpos($dataTemplate['name_ebay'], '#'.$attr.'#') !== false) {
												$name_ebay = str_replace('#'.$attr.'#', $datacsv[$map[$attr]], $name_ebay);
											}
										}
										$updateDataMagento['name_ebay'] = $name_ebay;
									} elseif($attr == 'mini_description_magento') {
										$mini_description_magento = $dataTemplate['mini_description_magento'];
										foreach($map as $attr => $pos) {
											if (strpos($dataTemplate['mini_description_magento'], '#'.$attr.'#') !== false) {
												$mini_description_magento = str_replace('#'.$attr.'#', $datacsv[$map[$attr]], $mini_description_magento);
											}
										}
										$updateDataMagento['mini_description'] = $mini_description_magento;
									} elseif($attr == 'short_description_magento') {
										$short_description_magento = $dataTemplate['short_description_magento'];
										foreach($map as $attr => $pos) {
											if (strpos($dataTemplate['short_description_magento'], '#'.$attr.'#') !== false) {
												$short_description_magento = str_replace('#'.$attr.'#', $datacsv[$map[$attr]], $short_description_magento);
											}
										}
										$updateDataMagento['short_description'] = $short_description_magento;
									} elseif($attr == 'description_magento') {
										$description_magento = $dataTemplate['description_magento'];
										foreach($map as $attr => $pos) {
											if (strpos($dataTemplate['description_magento'], '#'.$attr.'#') !== false) {
												$description_magento = str_replace('#'.$attr.'#', $datacsv[$map[$attr]], $description_magento);
											}
										}
										$updateDataMagento['description'] = $description_magento;
									} else {
										$updateDataMagento[$attr] = $datacsv[$map[$attr]];
									}
								}
								//print_r($updateDataMagento);
								$this->magento($updateDataMagento);
							}
						}
						$row++;
					}
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
