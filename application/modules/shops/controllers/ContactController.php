<?php

class Shops_ContactController extends Zend_Controller_Action
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

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);
	}

	public function indexAction()
	{
		$shop = Zend_Registry::get('Shop');

		$this->_helper->getHelper('layout')->setLayout('shop');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories($shop['id']);

		$slideDb = new Shops_Model_DbTable_Slide();
		$slides = $slideDb->getSlides($shop['id']);

		$images = array();
		$imageDb = new Shops_Model_DbTable_Image();
		$images['categories'] = $imageDb->getCategoryImages($categories);

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->shop = $shop;
		$this->view->images = $images;
		$this->view->slides = $slides;
		$this->view->categories = $categories;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function searchAction()
	{
		$type = $this->_getParam('type', 'index');

		$this->_helper->viewRenderer->setRender($type);
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Shops_Form_Account();
		$toolbar = new Shops_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$get = new Shops_Model_Get();
		$stats = array();
		$items = array();
		$accounts = $get->accounts($params, $options);
		foreach($accounts as $account) {
			$params['limit'] = 0;
			$params['shopid'] = $account['id'];
			list($items[$account['id']], $records) = $get->items($params, $options);
			$stats[$account['id']]['total'] = count($items[$account['id']]);
			$stats[$account['id']]['listed'] = 0;
			foreach($items[$account['id']] as $item) {
				if($item->listedby) ++$stats[$account['id']]['listed'];
			}
		}

		$this->view->form = $form;
		$this->view->stats = $stats;
		$this->view->accounts = $accounts;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function sendAction()
	{
		$request = $this->getRequest();
		$messageid = $this->_getParam('messageid', 0);
		$contactid = $this->_getParam('contactid', 0);
		$documentid = $this->_getParam('documentid', 0);
		$campaignid = $this->_getParam('campaignid', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Shops_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
		        // Get form data
		        $formData = $form->getValues();

				//PHPMailer
				require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
				require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
				require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

				$shop = Zend_Registry::get('Shop');

				/*if($messageid) {
					$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
					$emailmessage = $emailmessageDb->getEmailmessage($messageid);
					unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);
					$data = $emailmessage;
					$contactid = $emailmessage['contactid'];
					$documentid = $emailmessage['documentid'];
					$campaignid = $emailmessage['campaignid'];
				}*/
				if(true) {
					$mail = new PHPMailer\PHPMailer\PHPMailer();

					//Server settings
					$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;				// Enable verbose debug output
					$mail->isSMTP();														// Send using SMTP
					$mail->Host		= $shop['smtphost'];									// Set the SMTP server to send through
					$mail->SMTPAuth	= true;													// Enable SMTP authentication
					$mail->Username	= $shop['smtpuser'];									// SMTP username
					$mail->Password	= $shop['smtppass'];									// SMTP password
					$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
					$mail->Port		= 465;													// TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

					$emails = array();

					//Add email signature
					//$data['body'] = str_replace('[SIGNATURE]', $this->_user['emailsignature'], $data['body']);
					/*if($campaignid) {
						$data['body'] = str_replace('[BODY]', $data['body'], $template);
					}*/

					//Recipients
					$mail->clearAllRecipients( );											// clear all
					$mail->setFrom($shop['smtpuser'], $shop['emailsender']);
					$mail->addAddress($formData['email']);												// Add a recipient
					//$mail->addAddress('');												// Add a recipient
					//$data['replyto'] = str_replace(' ', '', $data['replyto']);				// Remove spaces
					//if($data['replyto']) $mail->addReplyTo($data['replyto']);				// Add reply to
					//$data['cc'] = str_replace(' ', '', $data['cc']);						// Remove spaces
					/*if($data['cc']) {														// Add copy recipients
						if(strpos($data['cc'], ',') !== false) {
							$ccs = explode(',', $data['cc']);
							foreach($ccs as $cc) {
								$mail->addCC($cc);
							}
						} else {
							$mail->addCC($data['cc']);
						}
					}
					$data['bcc'] = str_replace(' ', '', $data['bcc']);
					if($data['bcc']) $mail->addBCC($data['bcc']);*/

					//Get email attachments
					/*$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
					if($campaignid) $attachmentsObject = $emailattachmentDb->getEmailattachments($campaignid, $data['module'], $data['controller']);
					elseif($data['module'] == 'contacts') $attachmentsObject = $emailattachmentDb->getEmailattachments($contactid, $data['module'], $data['controller']);
					else $attachmentsObject = $emailattachmentDb->getEmailattachments($documentid, $data['module'], $data['controller']);
					$attachmentsAvailable = array();
					foreach($attachmentsObject as $attachment) $attachmentsAvailable[$attachment['id']] = $attachment;
print_r($attachmentsObject);

					$attachmentsSent = array();
					if(isset($data['files'])) {
						if($data['module'] == 'contacts') $url = $this->_helper->Directory->getUrl($contactid);
						else $url = $this->_helper->Directory->getUrl($documentid);
						foreach($data['files'] as $file) {
							if(file_exists($attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename'])) {
								array_push($attachmentsSent, $attachmentsAvailable[$file]['filename']);
								$mail->addAttachment($attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename']);
							}
						}
					}*/

					//Save email message to the db
					/*$emailmessage = array();
					$emailmessage['contactid'] = $recipient['contactid'];
					$emailmessage['documentid'] = $documentid;
					$emailmessage['parentid'] = $campaignid;
					$emailmessage['module'] = $data['module'];
					$emailmessage['controller'] = $data['controller'];
					$emailmessage['recipient'] = $recipient['email'];
					$emailmessage['cc'] = $data['cc'];
					$emailmessage['bcc'] = $data['bcc'];
					$emailmessage['subject'] = $data['subject'];
					$emailmessage['body'] = $data['body'];
					$emailmessage['attachment'] = implode(',', $attachmentsSent);
					$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
					$messageid = $emailmessageDb->addEmailmessage($emailmessage);*/

					//Get portal TODO
					/*$portalDb = new Portals_Model_DbTable_Portal();
					$portal = $portalDb->getPortal($email['clientid']);
					if($portal) {
						$key = hash('sha256', $email['id'].$email['contactid'].$email['clientid'].hash('sha256', $email['password']));
						$url = $portal->url.'/portals';
						$link = $url.'/auth/login/target/download/key/'.$key;
						$html = '<a href="'.$link.'">'.$link.'</a>';
						$data['body'] = str_replace('[LINK]', $html, $data['body']);

						$hash = hash('sha256', $messageid.$contactid.$email['clientid']);
						$data['body'] .= '<img src="'.$url.'/email/view/key/'.$hash.'" border="0" width="1" height="1">';
					}*/

					//Content
					$mail->isHTML(true);									// Set email format to HTML
					$mail->Subject = $formData['name'];
					$mail->Body	= $formData['message'];
					//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

					//Content
					$mail->CharSet	= 'UTF-8';
					$mail->Encoding = 'base64';

					//Send the message, check for errors
					if(!$mail->send()) {
						//Save errors to the db
						//$emailmessageDb->updateEmailmessage($messageid, array('response' => $mail->ErrorInfo));
					}
				}
			}
		}
        $this->_helper->redirector->gotoUrl('/');

		$this->view->form = $form;
	}

	public function syncAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$shopid = $this->_getParam('shopid', 0);

		if($shopid) {
			$accountDb = new Shops_Model_DbTable_Account();
			$account = $accountDb->getAccount($shopid);

			if($account) {
				$config = parse_ini_file(BASE_PATH.'/configs/database.ini');

				// DB Settings 
				define('DB_SERVER', $config['resources.db.params.host']);
				define('DB_USER', $config['resources.db.params.username']);
				define('DB_PASSWORD', $config['resources.db.params.password']);
				define('DB_NAME', $config['resources.db.params.dbname']);

				require_once(BASE_PATH.'/library/DEEC/Shop.php');
				$Shops = new DEEC_Shop(BASE_PATH, DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
				$Shops->listItems($shopid);

				$accountDb->updateAccount($shopid, array('updated' => date('Y-m-d H:i:s'), 'updatedby' => $this->_user['id']));

				$this->_flashMessenger->addMessage('MESSAGES_RECORDS_SUCCESFULLY_UPDATED');
			}
		}

		$this->_helper->redirector->gotoSimple('index', 'index');
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Shops_Form_Account();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			if($form->isValid($data)) {
				$accountDb = new Shops_Model_DbTable_Account();
				$id = $accountDb->addAccount($data);
				echo Zend_Json::encode($accountDb->getAccount($id));
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$activeTab = $request->getCookie('tab', null);

		$accountDb = new Shops_Model_DbTable_Account();
		$account = $accountDb->getAccount($id);

		if(false) {
			$this->_helper->redirector->gotoSimple('view', 'account', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $account['locked'], $account['lockedtime']);

			$form = new Shops_Form_Account();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data)) {
					$accountDb->updateAccount($id, $data);
					echo Zend_Json::encode($accountDb->getAccount($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$form->populate($account);

					//Toolbar
					$toolbar = new Shops_Form_Toolbar();

					$this->view->form = $form;
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
		$item = new Shops_Model_DbTable_Item();
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
			$item = new Shops_Model_DbTable_Item();
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

	public function getItemCategoryIndex() {
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
}
