<?php

class Contacts_EmailController extends Zend_Controller_Action
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
	}

	public function indexAction()
	{
		if($this->getRequest()->isPost()) $this->_helper->getHelper('layout')->disableLayout();

		$toolbar = new Contacts_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$request = $this->getRequest();
		$params['contactid'] = $this->_getParam('contactid', 0);
		$data = $request->getPost();
		$params['module'] = isset($data['module']) ? $data['module'] : 0;
		$params['controller'] = isset($data['controller']) ? $data['controller'] : 0;
		$params['documentid'] = isset($data['documentid']) ? $data['documentid'] : 0;

		//Get email messages
		$get = new Contacts_Model_Get();
		$emailmessages = $get->emailmessages($params, $options);
		foreach($emailmessages as $id => $emailmessage) {
			$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['documentid']);
		}

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->users = $users;
		$this->view->emailmessages = $emailmessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
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

		$request = $this->getRequest();
		$params['contactid'] = $this->_getParam('contactid', 0);
		$data = $request->getPost();
		$params['module'] = isset($data['module']) ? $data['module'] : 0;
		$params['controller'] = isset($data['controller']) ? $data['controller'] : 0;
		$params['documentid'] = isset($data['documentid']) ? $data['documentid'] : 0;

		//Get email messages
		$get = new Contacts_Model_Get();
		$emailmessages = $get->emailmessages($params, $options);
		foreach($emailmessages as $id => $emailmessage) {
			$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['documentid']);
		}

		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();

		$this->view->users = $users;
		$this->view->emailmessages = $emailmessages;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$emailDb = new Contacts_Model_DbTable_Email();
				$emailDataBefore = $emailDb->getEmails($data['parentid']);
				$latest = end($emailDataBefore);
				$emailDb->addEmail(array('contactid' => $data['parentid'], 'ordering' => $latest['ordering']+1));
				$emailDataAfter = $emailDb->getEmails($data['parentid']);
				$email = end($emailDataAfter);
				echo $this->view->MultiForm('contacts', 'email', $email);
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$emailDb = new Contacts_Model_DbTable_Email();
				if($id > 0) {
					$emailDb->updateEmail($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}

		$this->view->form = $form;
	}

	public function sendAction()
	{
		$request = $this->getRequest();
		$messageid = $this->_getParam('messageid', 0);
		$contactid = $this->_getParam('contactid', 0);
		$documentid = $this->_getParam('documentid', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Contacts_Form_Contact();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {

				//PHPMailer
				require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
				require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
				require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

				$userDb = new Users_Model_DbTable_User();
				$user = $userDb->getUser($this->_user['id']);

				if($messageid) {
					$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
					$emailmessage = $emailmessageDb->getEmailmessage($messageid);
					unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);
					$data = $emailmessage;
					$contactid = $emailmessage['contactid'];
					$documentid = $emailmessage['documentid'];
				}
				if(true) {
					$mail = new PHPMailer\PHPMailer\PHPMailer();

					//Server settings
					$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;				// Enable verbose debug output
					$mail->isSMTP();														// Send using SMTP
					$mail->Host		= $user['smtphost'];									// Set the SMTP server to send through
					$mail->SMTPAuth	= true;													// Enable SMTP authentication
					$mail->Username	= $user['smtpuser'];									// SMTP username
					$mail->Password	= $user['smtppass'];									// SMTP password
					$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
					$mail->Port		= 465;													// TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

					//Get email
					$emailDb = new Contacts_Model_DbTable_Email();
					$email = $emailDb->getEmail($data['recipient']);

					//Recipients
					$mail->setFrom($user['smtpuser'], $user['emailsender']);
					$mail->addAddress($email['email']);									// Add a recipient
					$data['replyto'] = str_replace(' ', '', $data['replyto']);				// Remove spaces
					if($data['replyto']) $mail->addReplyTo($data['replyto']);				// Add reply to
					$data['cc'] = str_replace(' ', '', $data['cc']);						// Remove spaces
					if($data['cc']) {														// Add copy recipients
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
					if($data['bcc']) $mail->addBCC($data['bcc']);

					//Get email attachments
					$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
					if($data['module'] == 'contacts') $attachmentsObject = $emailattachmentDb->getEmailattachments($contactid, $data['module'], $data['controller']);
					else $attachmentsObject = $emailattachmentDb->getEmailattachments($documentid, $data['module'], $data['controller']);
					$attachmentsAvailable = array();
					foreach($attachmentsObject as $attachment) $attachmentsAvailable[$attachment['id']] = $attachment;

					$attachmentsSent = array();
					if(isset($data['files'])) {
						if($data['module'] == 'contacts') $url = $this->_helper->Directory->getUrl($contactid);
						else $url = $this->_helper->Directory->getUrl($documentid);
						foreach($data['files'] as $file) {
							if(file_exists('files/attachments/'.$data['module'].'/'.$data['controller'].'/'.$url.'/'.$attachmentsAvailable[$file]['filename'])) {
								array_push($attachmentsSent, $attachmentsAvailable[$file]['filename']);
								$mail->addAttachment('files/attachments/'.$data['module'].'/'.$data['controller'].'/'.$url.'/'.$attachmentsAvailable[$file]['filename']);
							}
						}
					}

					//Save email message to the db
					$emailmessage = array();
					$emailmessage['contactid'] = $contactid;
					$emailmessage['documentid'] = $documentid;
					$emailmessage['recipient'] = $email['email'];
					$emailmessage['cc'] = $data['cc'];
					$emailmessage['bcc'] = $data['bcc'];
					$emailmessage['subject'] = $data['subject'];
					$emailmessage['body'] = $data['body'];
					$emailmessage['module'] = $data['module'];
					$emailmessage['controller'] = $data['controller'];
					$emailmessage['attachment'] = implode(',', $attachmentsSent);
					$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
					$messageid = $emailmessageDb->addEmailmessage($emailmessage);

					//Content
					$mail->isHTML(true);									// Set email format to HTML
					$mail->Subject = $data['subject'];
					$mail->Body	= $data['body'];
					//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

					//Content
					$mail->CharSet	= 'UTF-8';
					$mail->Encoding = 'base64';

					//Send the message, check for errors
					if(!$mail->send()) {
						//Save errors to the db
						$emailmessageDb->updateEmailmessage($messageid, array('response' => $mail->ErrorInfo));
					}
				}
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$emailDb = new Contacts_Model_DbTable_Email();
			$emailDb->deleteEmail($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
