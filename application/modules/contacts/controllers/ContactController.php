<?php

class Contacts_ContactController extends DEEC_Controller_Action
{
	public function init()
	{
		parent::init();

		$this->view->contactid = (int)$this->_getParam('contactid', 0);

		$id = $this->view->contactid ?: $this->view->id;

		if ($id) {
			$this->view->dirwritable = $this->_helper->Directory->isWritable(
				$id,
				'contact',
				$this->_flashMessenger
			);
			$this->view->dirwritable = $this->_helper->Directory->isWritable(
				$id,
				'attachment',
				$this->_flashMessenger
			);
		}
	}

	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'contacts',
			'list' => 'Contacts_Model_List_Contacts',
			'entity' => Contacts_Model_Entity_Contact::listConfig(),
		]);
	}

	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		$data = [
			'catid' => $catid,
		];

		$client = Zend_Registry::get('Client');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$id = $contactDb->addContact($data);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$contactDb->updateContact($id, array('contactid' => $increment));
		$incrementDb->setIncrement($increment, 'contactid');

		$addressDb = new Contacts_Model_DbTable_Address();
		$addressDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'billing',
			'country' => $client['country'] ?? '0',
		]);

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phoneDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'phone',
		]);

		$emailDb = new Contacts_Model_DbTable_Email();

		$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
		$emailDb->createForParent($id, 'contacts', 'contact', [
			'password' => $password,
		]);

		$internetDb = new Contacts_Model_DbTable_Internet();
		$internetDb->createForParent($id, 'contacts', 'contact');

		$this->_helper->redirector->gotoSimple('edit', 'contact', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$form = new Contacts_Form_Contact();
		$options = $this->_helper->Options->applyFormOptions($form);

		$toolbar = new Contacts_Form_Toolbar();
		$contactDb = new Contacts_Model_DbTable_Contact();

		$contact = $contactDb->getById($id);

		if (!$contact) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found',
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_CONTACT_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'contact');
		}

		$this->_helper->Access->lock($id, $this->_user['id'], $contact['locked'] ?? 0, $contact['lockedtime'] ?? null);

		if ($request->isPost()) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json(
					$this->saveFormAjax($form, $contactDb, $id)
				);
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				$contactDb->updateById($id, $values);

				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple('edit', 'contact', null, ['id' => $id]);
			}
		} else {
			$locale = Zend_Registry::get('Zend_Locale');
			$contactDisplay = DEEC_Display::rowToFormValues($form, $contact, $locale);

			$form->setValues($contactDisplay);

			$this->_helper->MultiEntityLoader->populate($form, $id, 'contacts', 'contact');
		}

		$vmService = new Contacts_Service_ContactEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$contact);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		$this->assignMessages();
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id']);
		unset(
			$data['street'],
			$data['postcode'],
			$data['city'],
			$data['country'],
			$data['shippingname1'],
			$data['shippingname2'],
			$data['shippingdepartment'],
			$data['shippingstreet'],
			$data['shippingpostcode'],
			$data['shippingcity'],
			$data['shippingcountry'],
			$data['shippingphone']
		);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$incrementDb->setIncrement(($increment), 'contactid');

		$data['name1'] = $data['name1'].' 2';
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['contactid'] = $increment;

		echo $contactid = $contact->addContact($data);

		//Copy addresses
		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getByParentId($id, 'contacts', 'contact');
		foreach($addresses as $address) {
			$data = array(
				'type' => $address['type'],
				'street' => $address['street'],
				'postcode' => $address['postcode'],
				'city' => $address['city'],
				'country' => $address['country'],
				'ordering' => $address['ordering']
			);
			$addressDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Phone
		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phones = $phoneDb->getByParentId($id, 'contacts', 'contact');
		foreach($phones as $phone) {
			$data = array(
				'type' => $phone['type'],
				'phone' => $phone['phone'],
				'ordering' => $phone['ordering']
			);
			$phoneDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Email
		$emailDb = new Contacts_Model_DbTable_Email();
		$emails = $emailDb->getByParentId($id, 'contacts', 'contact');
		foreach($emails as $email) {
			$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
			$data = array(
				'email' => $email['email'],
				'ordering' => $email['ordering'],
				'password' => $password
			);
			$emailDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Internet
		$internetDb = new Contacts_Model_DbTable_Internet();
		$internets = $internetDb->getByParentId($id, 'contacts', 'contact');
		foreach($internets as $internet) {
			$data = array(
				'internet' => $internet['internet'],
				'ordering' => $internet['ordering']
			);
			$internetDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function importAction()
	{
		$request = $this->getRequest();

		$form = new Contacts_Form_Import();

		if($request->isPost()) {
			$formData = $request->getPost();
			if($form->isValid($formData)) {

				$clientid = $this->_user['clientid'];
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

				if($data && ($data !== FALSE)) {
					$map = array();
					$dataTemplate = array();
					$contactDb = new Contacts_Model_DbTable_Contact();
					$contactInfo = $contactDb->getInfo();
					$contactAddress = new Contacts_Model_DbTable_Address();
					$contactEmail = new Contacts_Model_DbTable_Email();
					$contactInternet = new Contacts_Model_DbTable_Internet();
					$contactPhone = new Contacts_Model_DbTable_Phone();

					//Get categories
					$categoryIndex = $this->getProductCategoryIndex();

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
						} elseif(isset($map['name1']) && isset($datacsv[$map['name1']]) && $datacsv[$map['name1']]) {
							//echo $datacsv[$map['sku']];
							//print_r($map);
							$updateData = array();
							foreach($map as $attr => $pos) {
								if(isset($datacsv[$map[$attr]])) {
									if(array_search($attr, $contactInfo)) {
										if($attr == 'priceruleamount') {
											if(is_numeric($datacsv[$map['priceruleamount']])) {
												$updateData['priceruleamount'] = $datacsv[$map['priceruleamount']];
											} else {
												echo 'Price rule amount is not numeric for '.$datacsv[$map['name1']].': '.$datacsv[$map[$attr]]."<br>";
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
											echo 'No category found for '.$datacsv[$map['name1']].': '.$datacsv[$map['category']]."<br>";
										}
									}
								}
							}

							//Create and update the contact
							if($contact = $contactDb->getContactWithID($datacsv[$map['name1']])) {
								//error_log(print_r($updateData,true));
								$contactDb->updateContact($contact['id'], $updateData);
								++$rowsUpdated;
							} else {
								if(!isset($updateData['catid'])) $updateData['catid'] = 0;

								$id = $contactDb->addContact($updateData);

								//Set increment value
								$incrementDb = new Application_Model_DbTable_Increment();
								$increment = $incrementDb->getIncrement('contactid');
								$contactDb->updateContact($id, array('contactid' => $increment));
								$incrementDb->setIncrement(($increment), 'contactid');

								$address = array();
								$address['street'] = isset($map['street']) ? $datacsv[$map['street']] : NULL;
								$address['postcode'] = isset($map['postcode']) ? $datacsv[$map['postcode']] : NULL;
								$address['city'] = isset($map['city']) ? $datacsv[$map['city']] : NULL;
								$address['country'] = isset($map['country']) ? $datacsv[$map['country']] : NULL;

								$addressDb = new Contacts_Model_DbTable_Address();
								$data = array(
									'type' => 'billing',
									'street' => $address['street'],
									'postcode' => $address['postcode'],
									'city' => $address['city'],
									'country' => $address['country'],
									'ordering' => 1
								);
								$addressDb->createForParent($id, 'contacts', 'contact', $data);

								if(isset($map['phone']) && isset($datacsv[$map['phone']]) && $datacsv[$map['phone']]) {
									$phoneDb = new Contacts_Model_DbTable_Phone();
									$phoneDb->addPhone(array('parentid' => $id, 'type' => 'phone', 'phone' => $datacsv[$map['phone']], 'ordering' => 1));
								}

								if(isset($map['email']) && isset($datacsv[$map['email']]) && $datacsv[$map['email']]) {
									$emailDb = new Contacts_Model_DbTable_Email();
									$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
									$emailDb->addEmail(array('parentid' => $id, 'email' => $datacsv[$map['email']], 'ordering' => 1, 'password' => $password));
								}

								if(isset($map['internet']) && isset($datacsv[$map['internet']]) && $datacsv[$map['internet']]) {
									$internetDb = new Contacts_Model_DbTable_Internet();
									$internetDb->addInternet(array('parentid' => $id, 'internet' => $datacsv[$map['internet']], 'ordering' => 1));
								}
								++$rowsCreated;
							}
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

	public function getProductCategoryIndex() {
		$categoryDb = new Application_Model_DbTable_Category();
		$categories = $categoryDb->getCategories('contact');
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

	public function suggestAction()
	{
		$this->disableView();

		$keyword = trim((string)$this->_getParam('q', ''));

		if (mb_strlen($keyword) < 2) {
			return $this->_helper->json([
				'ok' => true,
				'items' => [],
			]);
		}

		$contactDb = new Contacts_Model_DbTable_Contact();

		return $this->_helper->json([
			'ok' => true,
			'items' => $contactDb->suggestContacts($keyword, (int)$this->_user['clientid']),
		]);
	}
}
