<?php

class Contacts_ContactController extends DEEC_Controller_Action
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
		$this->view->contactid = isset($params['contactid']) ? $params['contactid'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		$id = 0;
		if($this->view->contactid) $id = $this->view->contactid;
		elseif($this->view->id) $id = $this->view->id;
		if($id) $this->view->dirwritable = $this->_helper->Directory->isWritable($id, 'contact', $this->_flashMessenger);
		if($id) $this->view->dirwritable = $this->_helper->Directory->isWritable($id, 'attachment', $this->_flashMessenger);
	}

	public function getAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		//$contactDb = new Contacts_Model_DbTable_Contact();
		//$contact = $contactDb->getContact($this->_getParam('id', 0));

		$get = new Contacts_Model_Get();
		$contact = $get->contact($this->_getParam('id', 0));

		header('Content-type: application/json');
		echo Zend_Json::encode($contact);
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$toolbarInline = new Contacts_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');

		$get = new Contacts_Model_Get();
		$tags = $get->tags('contacts', 'contact');
		list($contacts, $records) = $get->contacts($params, $options);

		$tagEntites = array();
		foreach($contacts as $contact) {
			$tagEntites[$contact->id] = $get->tags('contacts', 'contact', $contact->id);
		}

		$this->view->tags = $tags;
		$this->view->tagEntites = $tagEntites;
		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->categories = $categories;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$toolbarInline = new Contacts_Form_ToolbarInline();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');

		$get = new Contacts_Model_Get();
		$tags = $get->tags('contacts', 'contact');
		list($contacts, $records) = $get->contacts($params, $options);

		$tagEntites = array();
		foreach($contacts as $contact) {
			$tagEntites[$contact->id] = $get->tags('contacts', 'contact', $contact->id);
		}

		$this->view->tags = $tags;
		$this->view->tagEntites = $tagEntites;
		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$contactid = $this->_getParam('contactid', 0);

		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');

		if($contactid) {
			$params['keyword'] = $contactid;
			//$toolbar->keyword->setValue($params['keyword']);
		}

		$get = new Contacts_Model_Get();
		list($contacts, $records) = $get->contacts($params, $options);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
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
		$contactDb  = new Contacts_Model_DbTable_Contact();

		// Load contact
		$contact = $contactDb->getContactForEdit($id);

		// Not found / not usable
		if (!$contact) {
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found'
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_CONTACT_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'contact');
		}

		// LOCK
		$this->_helper->Access->lock($id, $this->_user['id'], $contact['locked'] ?? 0, $contact['lockedtime'] ?? null);

		// POST: ajax save single field
		if ($request->isPost()) {
			// Edit via ajax -> JSON
			if ($isAjax) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->layout->disableLayout();

				$post = (array)$request->getPost();

				// Validate only posted subset
				if (!$form->isValidPartial($post)) {
					return $this->_helper->json([
						'ok' => false,
						'errors' => $this->toErrorMessages($form->getErrors(), $form),
					]);
				}

				// Filter/normalize only posted subset for DB
				$values = $form->getFilteredValuesPartial($post);

				// Save
				try {
					$contactDb->updateContact($id, $values);
				} catch (Exception $e) {
					return $this->_helper->json([
						'ok' => false,
						'message' => 'save_failed'
					]);
				}

				// Reload for derived values
				$contactNew = $contactDb->getContactForEdit($id);

				// Return only changed fields for display
				$changedFields = array_keys($values);

				$display = DEEC_Display::fromRow($form, $contactNew, $changedFields);

				return $this->_helper->json([
					'ok' => true,
					'id' => $id,

					// Raw DB values for JS logic
					'values' => array_intersect_key($contactNew, array_flip($changedFields)),

					// Formatted for UI
					'display' => $display,

					// Optional meta: if later derived values set server-side
					'meta' => [
						'recalc' => [],
					],
				]);
			}

			// NON-AJAX POST
			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				// Keep form with submitted values and errors
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();

				$contactDb->updateContact($id, $values);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');
				return $this->_helper->redirector->gotoSimple('edit', 'contact', null, ['id' => $id]);
			}
		} else {
			// GET: populate form with display values from DB
			$locale = Zend_Registry::get('Zend_Locale'); // for now, later replaced
			$contactDisplay = DEEC_Display::rowToFormValues($form, $contact, $locale);

			$form->setValues($contactDisplay);

			$this->_helper->MultiEntityLoader->populate($form, $id, 'contacts', 'contact');
		}

		// build view model once and assign in one shot
		$vmService = new Contacts_Service_ContactEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$contact);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		// Messages
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
		$contact = new Contacts_Model_DbTable_Contact();
		$data = $contact->getContact($id);
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

	public function exportAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		$tags = $get->tags('contacts', 'contact');
		$params['limit'] = 0;
		list($contacts, $records) = $get->contacts($params, $options);

		$tagEntites = array();
		foreach($contacts as $contact) {
			$tagEntites[$contact->id] = $get->tags('contacts', 'contact', $contact->id);
		}

		require_once(BASE_PATH.'/library/DEEC/Directory.php');
		$Directory = new DEEC_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';
		$contactsFileCsv = 'contacts-'.time().'.csv';
		$contactsFileZip = 'contacts-'.time().'.zip';
//print_r($contacts);

		//Get csv data
		if(count($contacts)) {
			//Create CSV data
			$csvRows = array();
			$csvRows[0]['id'] = 'id';
			$csvRows[0]['name1'] = 'name1';
			$csvRows[0]['name2'] = 'name2';
			$csvRows[0]['department'] = 'department';
			$csvRows[0]['street'] = 'street';
			$csvRows[0]['postcode'] = 'postcode';
			$csvRows[0]['city'] = 'city';
			$csvRows[0]['country'] = 'country';
			$csvRows[0]['taxnumber'] = 'taxnumber';
			$csvRows[0]['vatin'] = 'vatin';
			$csvRows[0]['phone'] = 'phone';
			$csvRows[0]['email'] = 'email';
			$csvRows[0]['contactperson'] = 'contactperson';
			$csvRows[0]['internet'] = 'internet';
			$csvRows[0]['category'] = 'category';
			$csvRows[0]['tags'] = 'tags';
			foreach($contacts as $contact) {
				// Common field data
				$categoryTitle = isset($options['categories'][$contact->catid])
					? $options['categories'][$contact->catid]['title']
					: '';

				$tags = '';
				foreach ($tagEntites[$contact->id] as $entity) {
					$tags .= $entity['tag'];
				}

				// Base row template
				$base = [
					$contact->id,
					$contact->name1,
					$contact->name2,
					$contact->department,
					$contact->street,
					$contact->postcode,
					$contact->city,
					$contact->country,
					$contact->taxnumber,
					$contact->vatin,
					$contact->phones,
					'', // email placeholder
					'', // contactperson placeholder
					$contact->internets,
					$categoryTitle,
					$tags
				];

				// --- company emails ---
				$companyEmails = preg_split('/[;,]+/', (string)$contact->emails);
				$companyEmails = array_filter(array_map('trim', $companyEmails));

				// --- collect contact-person emails first ---
				$personEmails = [];
				$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
				$emailDb = new Contacts_Model_DbTable_Email();
				$contactpersons = $contactpersonDb->getContactpersons($contact->id, 'contacts', 'contact');

				foreach ($contactpersons as $cp) {
					$cpMails = $emailDb->getEmails($cp['id'], 'contacts', 'contactperson');
					$list = [];
					foreach ((array)$cpMails as $e) {
						$list[] = $e['email'];
					}
					$list = array_filter(array_map('trim', $list));
					if (empty($list)) continue;

					$cpName = trim(($cp['salutation'] ?? '') . ' ' . ($cp['name2'] ?? ''));
					foreach ($list as $mail) {
						$personEmails[] = ['email' => $mail, 'name' => $cpName];
					}
				}

				// --- decide first line ---
				if (!empty($companyEmails)) {
					// first row = first company email
					$row = $base;
					$row[11] = $companyEmails[0];
					$csvRows[] = $row;

					// remaining company emails
					for ($i = 1; $i < count($companyEmails); $i++) {
						$r = $base;
						$r[11] = $companyEmails[$i];
						$csvRows[] = $r;
					}

					// then all contact-person emails
					foreach ($personEmails as $pe) {
						$r = $base;
						$r[11] = $pe['email'];
						$r[12] = $pe['name'];
						$csvRows[] = $r;
					}

				} else {
					// NO company email -> use FIRST contact-person email in the FIRST row
					if (!empty($personEmails)) {
						// first row from first contact person
						$first = array_shift($personEmails);
						$row = $base;
						$row[11] = $first['email'];
						$row[12] = $first['name'];
						$csvRows[] = $row;

						// remaining contact-person emails
						foreach ($personEmails as $pe) {
							$r = $base;
							$r[11] = $pe['email'];
							$r[12] = $pe['name'];
							$csvRows[] = $r;
						}
					} else {
						// no emails at all -> single empty email row
						$csvRows[] = $base;
					}
				}
			}
			//Save data to contacts.csv
			$contactsFile = fopen($filePath.$contactsFileCsv, 'w');
			foreach($csvRows as $fields) {
				fputcsv($contactsFile, $fields);
			}
			fclose($contactsFile);

			//Create product zip archive
			$zip = new ZipArchive;
			$status = $zip->open($filePath.$contactsFileZip, ZipArchive::CREATE);
			if($status === TRUE) {
				$zip->addFile($filePath.$contactsFileCsv, $contactsFileCsv);
				$zip->close();
			} else {
			}

			// We'll be outputting a PDF
			header('Content-type: text/csv');

			// It will be called downloaded.pdf
			header('Content-Disposition: attachment; filename="'.$contactsFileCsv.'"');

			// The PDF source is in original.pdf
			readfile($filePath.$contactsFileCsv);
		} else {
			$this->_helper->redirector->gotoSimple('index', 'contact');
		}
	}

	public function pinAction()
	{
		$id = $this->_getParam('id', 0);
		$this->_helper->Pin->toggle($id);
	}

	public function lockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->lock($id, $this->_user['id']);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
	}

	public function unlockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->unlock($id);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
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

	public function autocompleteAction()
	{
		$request = $this->getRequest();
		$order = $this->_getParam('order', null) ? $this->_getParam('order', 'id') : $request->getCookie('order', 'id');
		$sort = $this->_getParam('sort', null) ? $this->_getParam('sort', 'desc') : $request->getCookie('sort', 'desc');
		$keyword = $this->_getParam('keyword', null);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$keyword = trim($keyword);
		$columns = array('id', 'name1', 'name2');
		if($keyword) {
			$keywordArray = explode(" ", $keyword);
		}

		$query = "";
		foreach($columns as $column) {
			if($query) $query .= " OR ";
			$query .= "(";
			//Keyword
			if(isset($keywordArray)) {
				$query .= "(";
				$count = count($keywordArray);
				foreach($keywordArray as $key => $value) {
					$query .= $column." LIKE '%".$value."%'";
					if($count > ($key+1)) $query .= " AND ";
				}
				$query .= ") AND ";
			} elseif($keyword) {
				$query .= $column." LIKE '%".$keyword."%' AND ";
			}
			$query .= "clientid = ".$this->_client['id'].")";
		}

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Contacts_Model_Get();
		list($contacts, $records) = $get->contacts($params, $options);

		header('Content-type: application/json');
		//$suggestions = array("suggestions" => array());
		//foreach($contacts as $contact) {
		//	array_push($suggestions["suggestions"], array("id" => $contact->id, "name1" => $contact->name1));
		//}
		//echo Zend_Json::encode($suggestions);
echo '{
		// Query is not required as of version 1.2.5
		query: "Unit",
		suggestions: [
			{ value: "United Arab Emirates", data: "AE" },
			{ value: "United Kingdom", data: "UK" },
			{ value: "United States", data: "US" }
		]
	}';
	//print_r($suggestions);
	}
}
