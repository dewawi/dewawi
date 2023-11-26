<?php

class Contacts_ContactController extends Zend_Controller_Action
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
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

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
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

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
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function selectAction()
	{
		$contactid = $this->_getParam('contactid', 0);

		$this->_helper->getHelper('layout')->setLayout('plain');

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		if($contactid) {
			$params['keyword'] = $contactid;
			$toolbar->keyword->setValue($params['keyword']);
		}

		$get = new Contacts_Model_Get();
		list($contacts, $records) = $get->contacts($params, $options);

		$this->view->contacts = $contacts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($contacts));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		$data = array();
		$data['catid'] = $catid;

		$client = Zend_Registry::get('Client');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$id = $contactDb->addContact($data);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$contactDb->updateContact($id, array('contactid' => $increment));
		$incrementDb->setIncrement(($increment+1), 'contactid');

		$addressDb = new Contacts_Model_DbTable_Address();
		$addressDb->addAddress(array('contactid' => $id, 'type' => 'billing', 'country' => $client['country'], 'ordering' => 1));

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phoneDb->addPhone(array('parentid' => $id, 'type' => 'phone', 'ordering' => 1));

		$emailDb = new Contacts_Model_DbTable_Email();

		$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
		$emailDb->addEmail(array('parentid' => $id, 'ordering' => 1, 'password' => $password));

		$internetDb = new Contacts_Model_DbTable_Internet();
		$internetDb->addInternet(array('parentid' => $id, 'ordering' => 1));

		$this->_helper->redirector->gotoSimple('edit', 'contact', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$contactDb = new Contacts_Model_DbTable_Contact();
		if($id) $contact = $contactDb->getContact($id);

		//Redirect to index if there is no data
		if(!$contact) {
			$this->_helper->redirector->gotoSimple('index', 'contact');
			$this->_flashMessenger->addMessage('MESSAGES_NOT_FOUND');
		}

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'contact', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $contact['locked'], $contact['lockedtime']);

			$form = new Contacts_Form_Contact();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if($element == 'contactinfo') {
					$data['info'] = $data['contactinfo'];
					unset($data['contactinfo']);
					$element = 'info';
				}
				if($element == 'customerinfo') {
					$data['info'] = $data['customerinfo'];
					unset($data['customerinfo']);
					$element = 'info';
				}
				if(isset($form->$element) && $form->isValidPartial($data)) {
					if(array_key_exists('priceruleamount', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['priceruleamount'] = Zend_Locale_Format::getNumber($data['priceruleamount'],array('precision' => 2,'locale' => $locale));
					}
					if(array_key_exists('cashdiscountpercent', $data)) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['cashdiscountpercent'] = Zend_Locale_Format::getNumber($data['cashdiscountpercent'],array('precision' => 2,'locale' => $locale));
					}
					$contactDb->updateContact($id, $data);
					echo Zend_Json::encode($contactDb->getContact($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $contact;
					$currency = $this->_helper->Currency->getCurrency();
					if($data['priceruleamount'] == '0.0000') $data['priceruleamount'] = '';
					else $data['priceruleamount'] = $currency->toCurrency($data['priceruleamount']);
					if($data['cashdiscountdays'] == '0') $data['cashdiscountdays'] = '';
					if($data['cashdiscountpercent'] == '0.0000') $data['cashdiscountpercent'] = '';
					else $data['cashdiscountpercent'] = $currency->toCurrency($data['cashdiscountpercent']);
					$form->populate($data);

					//Phone
					$phoneDb = new Contacts_Model_DbTable_Phone();
					$phone = $phoneDb->getPhone($id);

					//Email
					$emailDb = new Contacts_Model_DbTable_Email();
					$email = $emailDb->getEmails($id);

					//Internet
					$internetDb = new Contacts_Model_DbTable_Internet();
					$internet = $internetDb->getInternet($id);

					//Bank account
					$bankAccountDb = new Contacts_Model_DbTable_Bankaccount();
					$bankAccount = $bankAccountDb->getBankaccount($id);

					//Addresses
					$addressDb = new Contacts_Model_DbTable_Address();
					$address = $addressDb->getAddress($id);

					//Contact persons
					$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
					$contactpersons = $contactpersonDb->getContactpersons($id, 'contacts', 'contact');

					$emailContactPersons = array();
					foreach($contactpersons as $contactperson) {
						$emailContactPersons[$contactperson['id']] = $emailDb->getEmails($contactperson['id'], 'contacts', 'contactperson');
					}

					//Comments
					$commentDb = new Application_Model_DbTable_Comment();
					$comments = $commentDb->getComments($id, 'contacts', 'contact');

					//History and tags
					$get = new Contacts_Model_Get();
					$tags = $get->tags('contacts', 'contact', $contact['id']);
					$history = $get->history($contact['contactid']);

					//Get email form
					$emailForm = new Contacts_Form_Emailmessage();
					if($email) {
						foreach($email as $option) {
							$emailForm->recipient->addMultiOption($option['id'], $option['email']);
						}
					}

					//Get email templates
					$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
					if($emailtemplate = $emailtemplateDb->getEmailtemplate('contacts', 'contact')) {
						if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
						if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
						if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
						$emailForm->subject->setValue($emailtemplate['subject']);
						$emailForm->body->setValue($emailtemplate['body']);
					}
					$this->view->emailForm = $emailForm;
					$this->view->url = $this->_helper->Directory->getUrl($contact['contactid']);

					//Get email attachments
					$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
					$attachments = $emailattachmentDb->getEmailattachments($id, 'contacts', 'contact');

					//Files
					$files = array();
					$path = BASE_PATH.'/files/contacts/';
					if(file_exists($path.$id) && is_dir($path.$id)) {
						if($handle = opendir($path.$id)) {
							$files['contactSpecific'] = array();
							while (false !== ($entry = readdir($handle))) {
								if(substr($entry, 0, strlen('.')) !== '.') array_push($files['contactSpecific'], $entry);
							}
							closedir($handle);
						}
					}

					//Downloads
					$downloadsDb = new Contacts_Model_DbTable_Download();
					$downloads = $downloadsDb->getDownloads($id);

					//Download tracking
					$downloadtrackingsDb = new Contacts_Model_DbTable_Downloadtracking();
					$downloadtrackings = $downloadtrackingsDb->getDownloadtrackings($id);

					$clientid = $this->_user['clientid'];
					$dir1 = substr($clientid, 0, 1);
					if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
					else $dir2 = '0';

					//Toolbar
					$toolbar = new Contacts_Form_Toolbar();

					$this->view->form = $form;
					$this->view->options = $options;
					$this->view->tags = $tags;
					$this->view->history = $history;
					$this->view->files = $files;
					$this->view->address = $address;
					$this->view->phone = $phone;
					$this->view->email = $email;
					$this->view->internet = $internet;
					$this->view->bankAccount = $bankAccount;
					$this->view->attachments = $attachments;
					$this->view->contactpersons = $contactpersons;
					$this->view->emailContactPersons = $emailContactPersons;
					$this->view->comments = $comments;
					$this->view->downloads = $downloads;
					$this->view->downloadsurl = $dir1.'/'.$dir2.'/'.$clientid.'/';
					$this->view->downloadtrackings = $downloadtrackings;
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
		$incrementDb->setIncrement(($increment+1), 'contactid');

		$data['name1'] = $data['name1'].' 2';
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['contactid'] = $increment;

		echo $contactid = $contact->addContact($data);

		//Copy addresses
		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getAddress($id);
		foreach($addresses as $address) {
			$data = array(
				'contactid' => $contactid,
				'type' => $address['type'],
				'street' => $address['street'],
				'postcode' => $address['postcode'],
				'city' => $address['city'],
				'country' => $address['country'],
				'ordering' => $address['ordering']
			);
			$addressDb->addAddress($data);
		}

		//Phone
		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phones = $phoneDb->getPhone($id);
		foreach($phones as $phone) {
			$phoneDb->addPhone(array('contactid' => $contactid, 'type' => $phone['type'], 'phone' => $phone['phone'], 'ordering' => $phone['ordering']));
		}

		//Email
		$emailDb = new Contacts_Model_DbTable_Email();
		$emails = $emailDb->getEmails($id);
		foreach($emails as $email) {
			$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
			$emailDb->addEmail(array('contactid' => $contactid, 'email' => $email['email'], 'ordering' => $email['ordering'], 'password' => $password));
		}

		//Internet
		$internetDb = new Contacts_Model_DbTable_Internet();
		$internets = $internetDb->getInternet($id);
		foreach($internets as $internet) {
			$internetDb->addInternet(array('contactid' => $contactid, 'internet' => $internet['internet'], 'ordering' => $internet['ordering']));
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contactDb->deleteContact($id);

			$phoneDb = new Contacts_Model_DbTable_Phone();
			$phones = $phoneDb->getPhone($id);
			foreach($phones as $phone) {
				$phoneDb->deletePhone($phone['id']);
			}

			$emailDb = new Contacts_Model_DbTable_Email();
			$emails = $emailDb->getEmails($id);
			foreach($emails as $email) {
				$emailDb->deleteEmail($email['id']);
			}

			$internetDb = new Contacts_Model_DbTable_Internet();
			$internets = $internetDb->getInternet($id);
			foreach($internets as $internet) {
				$internetDb->deleteInternet($internet['id']);
			}
		}
		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
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
								$incrementDb->setIncrement(($increment+1), 'contactid');

								$address = array();
								$address['street'] = isset($map['street']) ? $datacsv[$map['street']] : NULL;
								$address['postcode'] = isset($map['postcode']) ? $datacsv[$map['postcode']] : NULL;
								$address['city'] = isset($map['city']) ? $datacsv[$map['city']] : NULL;
								$address['country'] = isset($map['country']) ? $datacsv[$map['country']] : NULL;

								$addressDb = new Contacts_Model_DbTable_Address();
								$addressDb->addAddress(array(
														'contactid' => $id,
														'type' => 'billing',
														'street' => $address['street'],
														'postcode' => $address['postcode'],
														'city' => $address['city'],
														'country' => $address['country'],
														'ordering' => 1
													));

								if(isset($map['phone']) && isset($datacsv[$map['phone']]) && $datacsv[$map['phone']]) {
									$phoneDb = new Contacts_Model_DbTable_Phone();
									$phoneDb->addPhone(array('contactid' => $id, 'type' => 'phone', 'phone' => $datacsv[$map['phone']], 'ordering' => 1));
								}

								if(isset($map['email']) && isset($datacsv[$map['email']]) && $datacsv[$map['email']]) {
									$emailDb = new Contacts_Model_DbTable_Email();
									$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
									$emailDb->addEmail(array('contactid' => $id, 'email' => $datacsv[$map['email']], 'ordering' => 1, 'password' => $password));
								}

								if(isset($map['internet']) && isset($datacsv[$map['internet']]) && $datacsv[$map['internet']]) {
									$internetDb = new Contacts_Model_DbTable_Internet();
									$internetDb->addInternet(array('contactid' => $id, 'internet' => $datacsv[$map['internet']], 'ordering' => 1));
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

		require_once(BASE_PATH.'/library/Dewawi/Directory.php');
		$Directory = new Dewawi_Directory();
		$fileUrl = $Directory->getShortUrl($this->_user['clientid']);
		$filePath = BASE_PATH.'/files/export/'.$fileUrl.'/';
		$contactsFileCsv = 'contacts-'.time().'.csv';
		$contactsFileZip = 'contacts-'.time().'.zip';

		//Get csv data
		if(count($contacts)) {
			//Create CSV data
			$csvData = array();
			$csvData[0]['id'] = 'id';
			$csvData[0]['name1'] = 'name1';
			$csvData[0]['name2'] = 'name2';
			$csvData[0]['department'] = 'department';
			$csvData[0]['street'] = 'street';
			$csvData[0]['postcode'] = 'postcode';
			$csvData[0]['city'] = 'city';
			$csvData[0]['country'] = 'country';
			$csvData[0]['taxnumber'] = 'taxnumber';
			$csvData[0]['vatin'] = 'vatin';
			$csvData[0]['phone'] = 'phone';
			$csvData[0]['email'] = 'email';
			$csvData[0]['internet'] = 'internet';
			$csvData[0]['$category'] = 'category';
			$csvData[0]['tags'] = 'tags';
			foreach($contacts as $contact) {
				$csvData[$contact->id]['id'] = $contact->id;
				$csvData[$contact->id]['name1'] = $contact->name1;
				$csvData[$contact->id]['name2'] = $contact->name2;
				$csvData[$contact->id]['department'] = $contact->department;
				$csvData[$contact->id]['street'] = $contact->street;
				$csvData[$contact->id]['postcode'] = $contact->postcode;
				$csvData[$contact->id]['city'] = $contact->city;
				$csvData[$contact->id]['country'] = $contact->country;
				$csvData[$contact->id]['taxnumber'] = $contact->taxnumber;
				$csvData[$contact->id]['vatin'] = $contact->vatin;
				$csvData[$contact->id]['phone'] = $contact->phones;
				$csvData[$contact->id]['email'] = $contact->emails;
				$csvData[$contact->id]['internet'] = $contact->internets;
				$csvData[$contact->id]['category'] = $options['categories'][$contact->catid]['title'];
				$tags = '';
				foreach ($tagEntites[$contact->id] as $entity) {
					$tags .= $entity['tag'];
				}
				$csvData[$contact->id]['tags'] = $tags;
			}
			//Save data to contacts.csv
			$contactsFile = fopen($filePath.$contactsFileCsv, 'w');
			foreach($csvData as $fields) {
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
			{ value: "United Kingdom",	   data: "UK" },
			{ value: "United States",		data: "US" }
		]
	}';
	//print_r($suggestions);
	}
}
