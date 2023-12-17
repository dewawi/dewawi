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
			if($emailmessage['documentid']) {
				$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['documentid']);
			} elseif($emailmessage['campaignid']) {
				$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['campaignid']);
			} elseif($emailmessage['contactid']) {
				$emailmessages[$id]['url'] = $this->_helper->Directory->getUrl($emailmessage['contactid']);
			}
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
				$emailDataBefore = $emailDb->getEmails($data['parentid'], $data['module'], $data['controller']);
				$latestOrdering = is_array($emailDataBefore) && !empty($emailDataBefore)
					? end($emailDataBefore)['ordering']
					: 0;
				$dataArray = array();
				$dataArray['module'] = $data['module'];
				$dataArray['controller'] = $data['controller'];
				$dataArray['parentid'] = $data['parentid'];
				$dataArray['password'] = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
				$dataArray['ordering'] = $latestOrdering+1;
				$emailDb->addEmail($dataArray);
				$emailDataAfter = $emailDb->getEmails($data['parentid'], $data['module'], $data['controller']);
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
		$campaignid = $this->_getParam('campaignid', 0);

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
					$campaignid = $emailmessage['campaignid'];
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

					$emails = array();
					if($campaignid) {
						$campaignDb = new Campaigns_Model_DbTable_Campaign();
						$campaign = $campaignDb->getCampaign($campaignid);

						//Toolbar
						$toolbar = new Campaigns_Form_Toolbar();
						$options = $this->_helper->Options->getOptions($toolbar);
						$params = $this->_helper->Params->getParams($toolbar, $options);

						//Get contacts
						$get = new Contacts_Model_Get();
						$params['limit'] = 0;
						$params['catid'] = $campaign['contactcatid'];
						list($contacts, $records) = $get->contacts($params, $options);

						//Get already sent emails on champaign
						$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
						$emailmessageArray = $emailmessageDb->getEmailmessages(NULL, $campaignid, 'campaigns', 'campaign');
						$emailmessages = array();
						foreach($emailmessageArray as $emailmessage) {
							$emailmessages[$emailmessage['contactid']] = $emailmessage;
						}

						$i = 0;
						$limit = 3;
						foreach($contacts as $contact) {
							if(($i < $limit) && !isset($emailmessages[$contact->id])) {
								if(strpos($contact->emails, ',') !== false) {
									$recipientEmails = explode(',', $contact->emails);
									foreach($recipientEmails as $recipientEmail) {
										$recipients[$i]['email'] = $recipientEmail;
										$recipients[$i]['contactid'] = $contact->id;
										++$i;
									}
								} elseif($contact->emails) {
									$recipients[$i]['email'] = $contact->emails;
									$recipients[$i]['contactid'] = $contact->id;
									++$i;
								}
							}
						}
					} else {
						//Get email
						$emailDb = new Contacts_Model_DbTable_Email();
						$emailArray = $emailDb->getEmail($data['recipient']);
						$recipients[0]['email'] = $emailArray['email'];
						if($emailArray['controller'] == 'contact') $recipients[0]['contactid'] = $emailArray['parentid'];
					}
/*
$template = '
<head>
<style>
body, table{
	font-size: 14px;
	line-height: 1.4;
	font-family: sans-serif;
}
h2{
	font-size: 20px;
	line-height: 1.3;
}
h3{
	font-size: 17px;
	line-height: 1.4;
}
img{
	max-width: 100%;
	height: auto;
}
</style>
</head>
<body width="100%" style="margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 0; padding: 1em; min-width: 300px; background-color: #ffffff;">
		[BODY]
    </div>
</body>
';*/

					//Add email signature
					$data['body'] = str_replace('[SIGNATURE]', $this->_user['emailsignature'], $data['body']);
					/*if($campaignid) {
						$data['body'] = str_replace('[BODY]', $data['body'], $template);
					}*/

					foreach($recipients as $recipient) {
						//Recipients
						$mail->clearAllRecipients( );											// clear all
						$mail->setFrom($user['smtpuser'], $user['emailsender']);
						$mail->addAddress($recipient['email']);												// Add a recipient
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
						}

						//Save email message to the db
						$emailmessage = array();
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
						$messageid = $emailmessageDb->addEmailmessage($emailmessage);

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
