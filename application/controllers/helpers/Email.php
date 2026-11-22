<?php

class Application_Controller_Action_Helper_Email extends Zend_Controller_Action_Helper_Abstract
{
	public function sendEmail($module, $controller, $redirect = false, $items = null, int $shopOrderId = 0)
	{
		$request = $this->getRequest();
		if (!$request->isPost()) {
			return ['ok' => false, 'errors' => ['Not a POST']];
		}

		$data = $request->getPost();
		unset($data['__shop_order']);
		$checkoutOrigin = $module === 'shops' && ($controller === 'checkout' || $request->getControllerName() === 'checkout');
		$verifiedOrderId = 0;
		if ($shopOrderId !== 0) {
			if ($shopOrderId <= 0 || $module !== 'shops' || $controller !== 'checkout' || !Zend_Registry::isRegistered('SiteContext') || !is_string($data['checkout_token'] ?? null)) throw new RuntimeException('Invalid shop order notification context');
			$context = Zend_Registry::get('SiteContext');
			$shop = Zend_Registry::get('Shop');
			if ((int)$shop['id'] !== (int)$context->getSiteId() || (int)$shop['clientid'] !== (int)$context->getClientId()) throw new RuntimeException('Shop notification sender context mismatch');
			$order = (new Shops_Model_DbTable_Order())->setClientId((int)$context->getClientId())->getByCheckoutToken((int)$context->getSiteId(), $data['checkout_token']);
			if (!$order || (int)$order['id'] !== $shopOrderId || strcasecmp((string)$order['email'], trim((string)($data['email'] ?? ''))) !== 0) throw new RuntimeException('Shop order notification is not available');
			$verifiedOrderId = $shopOrderId;
			if (Shops_Service_OrderSnapshot::decode($order['snapshot'] ?? null)) {
				$data['__shop_order'] = $order;
				$items = [];
				foreach ((new Shops_Model_DbTable_Orderpos())->setClientId((int)$context->getClientId())->getForOrder($order) as $position) {
					$row = Shops_Service_OrderSnapshot::decode($position['snapshot']);
					$items[] = ['title' => $row['title'], 'sku' => $row['sku'], 'price' => $position['price'], 'quantity' => $position['quantity']];
				}
			}
		}
		if ($checkoutOrigin) {
			// Checkout recipients and attachment routing must never come from public form input.
			$data = array_diff_key($data, array_flip(['cc', 'bcc', 'replyto', 'files', '__attach_paths']));
		}

		if ($module === 'shops' && ($controller === 'inquiry' || $controller === 'offer')) {
			$formDataSession = new Zend_Session_Namespace('MultiStepForm');

			if (!empty($formDataSession->formData)) {
				foreach ($formDataSession->formData as $step => $stepData) {
					if (is_array($stepData)) {
						$data = array_merge($stepData, $data); // Priorität für aktuelle POST-Daten
					}
				}
			}

			// Optional: Subject und Nachricht aus einem freien Feld ergänzen (z. B. wenn letzte Eingabeseite)
			if (!isset($data['subject'])) {
				$data['subject'] = 'Anfrageformular';
			}
			if (!isset($data['message'])) {
				$data['message'] = 'Dies ist eine automatisch generierte Anfrage.';
			}
		}
		if(true) {
			$messageid = $request->getParam('messageid', 0);
			$contactid = $request->getParam('contactid', 0);
			$documentid = $request->getParam('documentid', 0);
			$campaignid = $request->getParam('campaignid', 0);
			if ($checkoutOrigin) {
				$messageid = $contactid = $campaignid = 0;
				$documentid = $verifiedOrderId;
			}

			if($module == 'shops') {
				if($controller == 'checkout') {
					$form = new Shops_Form_Checkout();
				} else {
					$form = new Shops_Form_Contact();
				}

				$emailmessageDb = new Shops_Model_DbTable_Emailmessage();
			} else {
				$form = new Contacts_Form_Contact();

				$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();

				$recipients = array();

				if($messageid) {
					$emailmessage = $emailmessageDb->getById((int)$messageid);
					if(!$emailmessage) throw new RuntimeException('Email message is not available');
					if (($emailmessage['module'] ?? '') === 'calendar') throw new RuntimeException('Calendar invitations must use the calendar send action');
					$contactid = (int)($emailmessage['contactid'] ?? 0);
					$documentid = (int)($emailmessage['documentid'] ?? 0);
					$campaignid = (int)($emailmessage['campaignid'] ?? 0);

					unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);

					$data = $emailmessage;
				}

				$quoteRecipient = $this->getQuoteRecipient($data, (int)$documentid, (bool)$messageid, (int)$campaignid);

				if($quoteRecipient !== null) {
					$recipients[0] = $quoteRecipient;
				} elseif($messageid) {
					$recipients[0]['email'] = $data['recipient'];
					$recipients[0]['contactid'] = $contactid;
				} else {
					$emailDb = new Contacts_Model_DbTable_Email();
					$emailArray = $emailDb->getById((int)($data['recipient'] ?? 0));
					if(!$emailArray) throw new RuntimeException('Email recipient is not available');
					$recipients[0]['email'] = $emailArray['email'];

					if($emailArray['controller'] == 'contact') {
						$recipients[0]['contactid'] = $emailArray['parentid'];
					} elseif($emailArray['controller'] == 'contactperson') {
						$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
						$contactpersonDb->setClientId((int)$emailArray['clientid']);
						$contactperson = $contactpersonDb->getById($emailArray['parentid']);
						if(!$contactperson) throw new RuntimeException('Contact person is not available');

						$recipients[0]['contactid'] = (int)$contactperson['parentid'];
						foreach(['salutation', 'title', 'firstname', 'lastname', 'position', 'department'] as $field) {
							$recipients[0][$field] = (string)($contactperson[$field] ?? '');
						}
					}
				}
			}

			if($form->isValid($data) || true) {
				// Get form data
				if($controller == 'inquiry' || $controller === 'offer') {
					$formData = $data;
				} else {
					$formData = $form->getValues();
				}

				if ($checkoutOrigin) $formData = array_diff_key($formData, array_flip(['cc', 'bcc', 'replyto', 'files', '__attach_paths']));
				if($form->isValid($data) || true) {
					// Get SMTP settings
					list($smtpConfig, $fromEmail, $fromName) = $this->getEmailConfig($module);

					if($module === 'shops') {
						$customerEmail = DEEC_Filter::email((string)($data['email'] ?? '')) ?? '';
						$recipients = [[
							'email' => $customerEmail,
							'contactid' => 0,
							'roles' => ['customer'],
						]];

						if(strcasecmp($customerEmail, $fromEmail) === 0) {
							$recipients[0]['roles'][] = 'internal';
						} else {
							$recipients[] = [
								'email' => $fromEmail,
								'contactid' => 0,
								'roles' => ['internal'],
							];
						}
					}

					return $this->sendEmails($module, $controller, $recipients, $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items);
				}
			}
		} else {
			$flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
			$flashMessengerHelper->addMessage('MESSAGES_FORM_INVALID');
		}
	}

	private function getQuoteRecipient(array &$data, int $documentId, bool $resend, int $campaignId): ?array
	{
		$module = $data['module'] ?? 'contacts';
		$controller = $data['controller'] ?? 'contact';
		if(!is_string($module) || !is_string($controller)) throw new InvalidArgumentException('Invalid email context');
		$module = strtolower(trim($module));
		$controller = strtolower(trim($controller));
		if(!preg_match('/^[a-z][a-z0-9-]*$/D', $module) || !preg_match('/^[a-z][a-z0-9-]*$/D', $controller)) throw new InvalidArgumentException('Invalid email context');
		if($module !== 'sales' || $controller !== 'quote') return null;

		$data['module'] = $module;
		$data['controller'] = $controller;
		$user = Zend_Registry::get('User');
		if(empty($user['id']) || empty($user['clientid'])) throw new RuntimeException('Quote email client context is missing');
		$permission = new DEEC_Permission($user);
		if(!$permission->hasActionPermission('sales', 'quote', 'view') || !$permission->hasActionPermission('sales', 'quote', 'send')) throw new RuntimeException('Quote email permission denied');
		if($documentId <= 0 || $campaignId !== 0) throw new InvalidArgumentException('Invalid quote email context');

		$quoteDb = new Sales_Model_DbTable_Quote();
		$quoteDb->setClientId((int)$user['clientid']);
		$quote = $quoteDb->getById($documentId);
		if(!$quote || empty($quote['contactid'])) throw new RuntimeException('Quote is not available');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContactWithID((int)$quote['contactid']);
		if(!$contact) throw new RuntimeException('Quote contact is not available');

		$recipient = $data['recipient'] ?? null;
		$recipientId = $resend ? null : filter_var($recipient, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if(($resend && !is_string($recipient)) || (!$resend && !$recipientId)) throw new InvalidArgumentException('Invalid quote email recipient');

		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->setClientId((int)$contact['clientid']);
		foreach($emailDb->getContactRecipients((int)$contact['id']) as $email) {
			$matches = $resend ? strcasecmp(trim((string)$email['email']), trim($recipient)) === 0 : (int)$email['id'] === $recipientId;
			if(!$matches) continue;
			return [
				'email' => (string)$email['email'],
				'contactid' => (int)$email['contactid'],
				'salutation' => (string)($email['salutation'] ?? ''),
				'title' => (string)($email['title'] ?? ''),
				'firstname' => (string)($email['firstname'] ?? ''),
				'lastname' => (string)($email['lastname'] ?? ''),
				'position' => (string)($email['position'] ?? ''),
				'department' => (string)($email['department'] ?? ''),
			];
		}

		throw new RuntimeException('Email recipient does not belong to the quote contact');
	}

	private function sendEmails($module, $controller, $recipients, array $smtpConfig, $fromEmail, $fromName, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items)
	{
		$result = ['ok' => true, 'sent' => 0, 'failed' => 0];
		if($module === 'shops') {
			$result['customerAccepted'] = null;
			$result['internalAccepted'] = null;
		}

		foreach($recipients as $recipientIndex => $recipient) {
			$to = [$recipient['email']];

			if(isset($data['replyto']) && $data['replyto']) $data['replyto'] = str_replace(' ', '', $data['replyto']);
			if(isset($data['cc']) && $data['cc']) $data['cc'] = str_replace(' ', '', $data['cc']);
			if(isset($data['bcc']) && $data['bcc']) $data['bcc'] = str_replace(' ', '', $data['bcc']);
			$cc = $module !== 'shops' || $recipientIndex === 0 ? ($data['cc'] ?? '') : '';
			$bcc = $module !== 'shops' || $recipientIndex === 0 ? ($data['bcc'] ?? '') : '';

			$attachmentsSent = array();
			$attachmentSources = array();
			if($module == 'contacts') {
				//Get email attachments
				$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
				if($campaignid) $attachmentsObject = $emailattachmentDb->getEmailattachments($campaignid, $data['module'], $data['controller']);
				elseif($data['module'] == 'contacts') $attachmentsObject = $emailattachmentDb->getEmailattachments($recipient['contactid'], $data['module'], $data['controller']);
				else $attachmentsObject = $emailattachmentDb->getEmailattachments($documentid, $data['module'], $data['controller']);
				$attachmentsAvailable = array();
				foreach($attachmentsObject as $attachment) $attachmentsAvailable[$attachment['id']] = $attachment;

				if(isset($data['files'])) {
					if(!is_array($data['files'])) throw new InvalidArgumentException('Invalid email attachments');
					foreach($data['files'] as $file) {
						if (is_string($file) && preg_match('/^correction:([1-9][0-9]*)$/D', $file, $match)) {
							if (($data['module'] ?? '') !== 'sales' || ($data['controller'] ?? '') !== 'invoice' || !is_string($data['correction_csrf_token'] ?? null)) throw new RuntimeException('Invoice correction context is invalid');
							$source = (new Sales_Service_InvoiceCorrectionService())->attachment((int)$documentid, (int)$match[1], $data['correction_csrf_token']);
							$attachmentSources[] = $source; $attachmentsSent[] = $source['filename']; continue;
						}
						if(is_string($file) && preg_match('/^archive:([1-9][0-9]*)$/D', $file, $match)) {
							$archive = (new Application_Model_DbTable_Documentarchive())->getArchive((int)$match[1]);
							if(!$archive || (int)$archive['documentid'] !== (int)$documentid || $archive['module'] !== $data['module'] || $archive['controller'] !== $data['controller']) throw new RuntimeException('Document original is not available');
							$attachmentSources[] = ['path' => BASE_PATH . '/' . $archive['path'], 'filename' => $archive['filename'], 'documentarchiveid' => (int)$archive['id']];
							$attachmentsSent[] = $archive['filename'];
							continue;
						}
						$id = filter_var($file, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
						if(!$id || !isset($attachmentsAvailable[$id])) throw new RuntimeException('Email attachment is not available');
						$attachmentSources[] = ['path' => DEEC_Emailattachment::getPath($attachmentsAvailable[$id]), 'filename' => $attachmentsAvailable[$id]['filename'], 'emailattachmentid' => (int)$id];
						$attachmentsSent[] = $attachmentsAvailable[$id]['filename'];
					}
				}
			}

			$feedback = $this->prepareFeedback($module, $controller, $data, (int)$documentid);

			if($feedback['service']) {
				$data['body'] = $feedback['body'];
			}

			// Get email body and subject
			list($body, $subject) = $this->getEmailBody($module, $controller, $formData, $data, $items);

			// personalize for this recipient
			$body = $this->personalizeBody($body, $recipient);

			//Save email message to the db
			$trackedOrder = $module === 'shops' && $controller === 'checkout' && $documentid > 0;
			$messageModule = $module === 'shops' ? 'shops' : ($data['module'] ?? $module);
			$messageController = $module === 'shops' ? ($trackedOrder ? 'order' : $controller) : ($data['controller'] ?? $controller);
			if (!$trackedOrder && $messageModule === 'shops' && $messageController === 'order') throw new RuntimeException('Invalid shop order notification context');
			$emailmessage = DEEC_Email::prepareMessageData([
				'contactid' => $recipient['contactid'],
				'documentid' => $documentid,
				'campaignid' => $campaignid,
				'feedbackid' => !empty($feedback['request']['id']) ? (int)$feedback['request']['id'] : null,
				'module' => $messageModule,
				'controller' => $messageController,
				'sender' => $fromEmail,
				'recipient' => $recipient['email'],
				'cc' => $cc,
				'bcc' => $bcc,
				'replyto' => $data['replyto'] ?? '',
				'subject' => $subject ? $subject : 'Anfrageformular',
				'body' => $body,
				'attachments' => $attachmentsSent,
			]);

			if ($trackedOrder) $emailmessage['deliverystatus'] = 'pending';
			$messageid = $emailmessageDb->addEmailmessage($emailmessage);

			if(!empty($formData['__attach_paths']) && is_array($formData['__attach_paths'])) {
				foreach($formData['__attach_paths'] as $path) {
					$attachmentSources[] = ['path' => $path, 'filename' => basename($path)];
				}
			}

			$embeddedImages = [];
			if($module === 'shops') {
				$shop = Zend_Registry::get('Shop');
				$clientid = $shop['clientid'];
				$dir1 = substr($clientid, 0, 1);
				$dir2 = strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0';
				$embeddedImages[] = [
					'path' => BASE_PATH.'/media/'.$dir1.'/'.$dir2.'/'.$clientid.'/header/'.$shop['logo'],
					'cid' => 'logo_cid',
				];
			}

			try {
				$attachmentPaths = [];
				if($emailmessageDb instanceof Contacts_Model_DbTable_Emailmessage) {
					$copyDb = new Contacts_Model_DbTable_Emailmessageattachment();
					foreach($attachmentSources as $index => $source) {
						$copy = $copyDb->storeCopy((int)$messageid, $source, $index + 1);
						$attachmentPaths[] = ['path' => $copyDb->getPath($copy), 'name' => $copy['filename']];
					}
				} else {
					foreach($attachmentSources as $source) $attachmentPaths[] = ['path' => $source['path'], 'name' => $source['filename']];
				}
				if($module === 'shops' && !DEEC_Filter::isValidEmail($recipient['email'])) {
					throw new RuntimeException('Invalid shop email recipient');
				}

				$sendResult = DEEC_Email::sendMessage($smtpConfig, [
					'fromEmail' => $emailmessage['sender'],
					'fromName' => $fromName,
					'to' => $to,
					'cc' => $emailmessage['cc'],
					'bcc' => $emailmessage['bcc'],
					'replyTo' => $emailmessage['replyto'],
					'subject' => $emailmessage['subject'],
					'body' => $emailmessage['body'],
					'altBody' => html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $emailmessage['body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					'attachments' => $attachmentPaths,
					'embeddedImages' => $embeddedImages,
					'emailmessageId' => $messageid,
					'xMailer' => '',
				]);
			} catch(Throwable $e) {
				$sendResult = ['sent' => false, 'error' => $e->getMessage()];
			}

			$accepted = !empty($sendResult['sent']);
			if($module === 'shops') {
				foreach($recipient['roles'] as $role) $result[$role.'Accepted'] = $accepted;
			}

			if(!$accepted) {
				$result['ok'] = false;
				$result['failed']++;
				$error = trim((string)($sendResult['error'] ?? ''));
				error_log('Email transport did not confirm SMTP acceptance (message #' . (int)$messageid . '): ' . $error);
				$error = $error !== '' ? $error : 'Email transport did not confirm SMTP acceptance';
				if($emailmessageDb instanceof Contacts_Model_DbTable_Emailmessage) $emailmessageDb->markFailed((int)$messageid, $error);
				else $emailmessageDb->updateEmailmessage($messageid, array_merge(['response' => $error], $trackedOrder ? ['deliverystatus' => 'failed'] : []));

				if($feedback['service'] && $feedback['request'] && $feedback['created']) {
					$feedback['service']->deleteRequest($feedback['request']);
				}

				if($module !== 'shops' && !$this->getRequest()->isXmlHttpRequest()) {
					try {
						Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger')->addMessage('MESSAGES_EMAIL_SENT_ERROR');
					} catch(Exception $e) {
						error_log('Email failure message could not be stored: ' . $e->getMessage());
					}
				}
			} else {
				$result['sent']++;
				if($emailmessageDb instanceof Contacts_Model_DbTable_Emailmessage) $emailmessageDb->markAccepted((int)$messageid);
				else $emailmessageDb->updateEmailmessage($messageid, array_merge(['response' => 'sent'], $trackedOrder ? ['deliverystatus' => 'accepted'] : []));

				if($feedback['service'] && $feedback['request']) {
					try {
						$feedback['service']->attachEmailMessage($feedback['request'], (int)$messageid);
					} catch(Exception $e) {
						error_log('Email feedback could not be linked after SMTP acceptance: ' . $e->getMessage());
					}
				}

				if($module !== 'shops' && !$this->getRequest()->isXmlHttpRequest()) {
					try {
						Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger')->addMessage('MESSAGES_EMAIL_SENT_SUCCESS');
					} catch(Exception $e) {
						error_log('Email success message could not be stored after SMTP acceptance: ' . $e->getMessage());
					}
				}
			}
		}

		if ($result['sent'] === 0) $result['ok'] = false;

		return $result;
	}

	private function prepareFeedback($module, $controller, array $data, int $documentid): array
	{
		$result = [
			'service' => null,
			'request' => null,
			'body' => (string)($data['body'] ?? ''),
		];

		$documentModule = (string)($data['module'] ?? $module);
		$documentController = (string)($data['controller'] ?? $controller);

		if($documentModule !== 'sales' || $documentController !== 'quote' || $documentid <= 0) return $result;

		$service = new DEEC_Feedback();
		$result['service'] = $service;

		try {
			$user = Zend_Registry::get('User');

			$quoteDb = new Sales_Model_DbTable_Quote();
			$quoteDb->setClientId((int)$user['clientid']);

			$quote = $quoteDb->getById($documentid);
			if(!$quote) return $result;

			$result['locale'] = (string)($quote['language'] ?? '');

			$prepared = $service->prepareRequest(
				'sales',
				'quote',
				$documentid,
				(int)$quote['clientid'],
				DEEC_Feedback::TYPE_QUOTE,
				[
					'contactid' => (int)($quote['contactid'] ?? 0),
				],
				!empty($data['feedback'])
			);

			$request = $prepared['request'];

			if(!$request) {
				$result['body'] = $service->removeEmailBlock($result['body']);
				return $result;
			}

			$block = $service->buildEmailBlock(
				$request,
				$this->getPublicBaseUrl(),
				$result['locale']
			);

			$result['body'] = $service->replaceEmailBlock($result['body'], $block);
			$result['request'] = $request;
			$result['created'] = !empty($prepared['created']);
		} catch(Exception $e) {
			error_log('Quote feedback could not be created: ' . $e->getMessage());
		}

		return $result;
	}

	private function getPublicBaseUrl(): string
	{
		$request = $this->getRequest();
		$basePath = rtrim((string)Zend_Controller_Front::getInstance()->getBaseUrl(), '/');

		return rtrim($request->getScheme() . '://' . $request->getHttpHost() . $basePath, '/');
	}

	public function getEmailConfig($module): array
	{
		if($module === 'shops') {
			$shop = Zend_Registry::get('Shop');
			$smtpConfig = $this->getDirectSmtpConfig($shop, 'Shop');
			$fromEmail = DEEC_Filter::email((string)($shop['emailsender'] ?? ''));
			if(!DEEC_Filter::isValidEmail($fromEmail)) throw new RuntimeException('Shop sender email is missing or invalid');

			return [$smtpConfig, $fromEmail, (string)($shop['title'] ?? '')];
		}

		$user = Zend_Registry::get('User');
		$smtpConfig = $this->getDirectSmtpConfig($user, 'User');
		$fromEmail = DEEC_Filter::email((string)($user['smtpuser'] ?? ''));
		if(!DEEC_Filter::isValidEmail($fromEmail)) throw new RuntimeException('User SMTP username must be a valid sender email address');

		return [$smtpConfig, $fromEmail, (string)($user['emailsender'] ?? '')];
	}

	private function getDirectSmtpConfig(array $source, string $context): array
	{
		$host = trim((string)($source['smtphost'] ?? ''));
		$username = trim((string)($source['smtpuser'] ?? ''));
		$password = (string)($source['smtppass'] ?? '');

		if($host === '') throw new RuntimeException($context.' SMTP host is missing');
		if(strlen($host) > 255 || preg_match('/[\x00-\x20\x7f]/', $host)) throw new RuntimeException($context.' SMTP host is invalid');
		if($username === '') throw new RuntimeException($context.' SMTP username is missing');
		if($password === '') throw new RuntimeException($context.' SMTP password is missing');

		return [
			'host' => $host,
			'auth' => true,
			'username' => $username,
			'password' => $password,
			'secure' => 'ssl',
			'port' => 465,
		];
	}

	private function getEmailBody($module, $controller, $formData, $data, $items)
	{
		if ($module == 'shops') {
			$templateDb = new Shops_Model_DbTable_Emailtemplate();
			$template = $templateDb->getEmailtemplate($module, $controller);

			$body = $template['body'];
			$shop = Zend_Registry::get('Shop');

			$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
			$locale = DEEC_Translate::normalizeLocale((string)($shop['language'] ?? ''), '');
			$translator = null;
			if ($locale !== '') { $translator = new DEEC_Translate($locale); $translator->loadModule('shops'); }
			$translate = static function (string $key, array $args = []) use ($translator, $viewRenderer): string {
				return $translator ? $translator->t($key, $args) : $viewRenderer->view->translate($key, $args);
			};

			if(!isset($data['name'])) $data['name'] = '';

			// Replace dynamic placeholders with values
			$dynamicPlaceholders = [
				'#HELLO_NAME#' => sprintf(
					$translate('MESSAGES_HELLO_NAME'),
					htmlspecialchars($data['name'])
				),
				'#SHOP_TITLE#' => htmlspecialchars($shop['title']),
				'#SHOP_URL#' => htmlspecialchars($shop['url']),
				'#SHOP_EMAIL#' => htmlspecialchars($shop['emailsender']),
				'#MESSAGES_SUBJECT#' => htmlspecialchars($data['subject']),
				'#MESSAGES_MESSAGE#' => nl2br(htmlspecialchars($data['message'])),
				'#MESSAGES_BEST_REGARDS#' => htmlspecialchars($shop['emailsender']) . '<br>' . htmlspecialchars($shop['title']),
				'#MESSAGES_ALL_RIGHTS_RESERVED#' => date('Y') . ' ' . htmlspecialchars($shop['title'])
			];

			if ($controller == 'checkout') {
				if($formData['differentshippingaddress'] == 0) {
					$formData['shippingcompany'] = $formData['billingcompany'];
					$formData['shippingdepartment'] = $formData['billingdepartment'];
					$formData['shippingname'] = $formData['billingname'];
					$formData['shippingstreet'] = $formData['billingstreet'];
					$formData['shippingcity'] = $formData['billingcity'];
					$formData['shippingpostcode'] = $formData['billingpostcode'];
					$formData['shippingcountry'] = $formData['billingcountry'];
					$formData['shippingphone'] = $formData['billingphone'];
				}

				// Tabelle der Bestellartikel erstellen
				$orderItemsHtml = '';
				if($items) {
					foreach($items as $item) {
						$orderItemsHtml .= '
						<tr>
							<td>' . htmlspecialchars($item['title']) . '<br>' . htmlspecialchars($item['sku']) . '</td>
							<td>' . htmlspecialchars($item['quantity']) . '</td>
							<td>' . number_format($item['price'], 2, ',', '.') . ' €</td>
							<td>' . number_format($item['price'] * $item['quantity'], 2, ',', '.') . ' €</td>
						</tr>';
					}
				}
				$body = str_replace('#ORDER_ITEMS#', $orderItemsHtml, $body);

				// Replace dynamic placeholders with values
				$dynamicPlaceholders = [
					'#HELLO_NAME#' => sprintf(
						$translate('MESSAGES_HELLO_NAME'),
						htmlspecialchars($formData['billingname'])
					),
					'#SHOP_TITLE#' => htmlspecialchars($shop['title']),
					'#SHOP_URL#' => htmlspecialchars($shop['url']),
					'#SHOP_EMAIL#' => htmlspecialchars($shop['emailsender']),
					'#MESSAGES_SUBJECT#' => htmlspecialchars($formData['subject']),
					'#MESSAGES_MESSAGE#' => nl2br(htmlspecialchars($formData['message'])),
					'#MESSAGES_BEST_REGARDS#' => htmlspecialchars($shop['emailsender']) . '<br>' . htmlspecialchars($shop['title']),
					'#MESSAGES_ALL_RIGHTS_RESERVED#' => date('Y') . ' ' . htmlspecialchars($shop['title']),
					'#ORDER_NUMBER#' => htmlspecialchars($data['orderid']),
					'#ORDER_DATE#' => htmlspecialchars($data['orderdate']),
					'#PAYMENT_METHOD#' => htmlspecialchars($translate('SHOPS_CHECKOUT_NO_PAYMENT')),
					'#BILLING_COMPANY#' => htmlspecialchars($formData['billingcompany']),
					'#BILLING_DEPARTMENT#' => htmlspecialchars($formData['billingdepartment']),
					'#BILLING_NAME#' => htmlspecialchars($formData['billingname']),
					'#BILLING_ADDRESS#' => htmlspecialchars($formData['billingstreet']),
					'#BILLING_CITY#' => htmlspecialchars($formData['billingcity']),
					'#BILLING_POSTCODE#' => htmlspecialchars($formData['billingpostcode']),
					'#BILLING_COUNTRY#' => htmlspecialchars($formData['billingcountry']),
					'#BILLING_PHONE#' => htmlspecialchars($formData['billingphone']),
					'#SHIPPING_COMPANY#' => htmlspecialchars($formData['shippingcompany']),
					'#SHIPPING_DEPARTMENT#' => htmlspecialchars($formData['shippingdepartment']),
					'#SHIPPING_NAME#' => htmlspecialchars($formData['shippingname']),
					'#SHIPPING_ADDRESS#' => htmlspecialchars($formData['shippingstreet']),
					'#SHIPPING_CITY#' => htmlspecialchars($formData['shippingcity']),
					'#SHIPPING_POSTCODE#' => htmlspecialchars($formData['shippingpostcode']),
					'#SHIPPING_COUNTRY#' => htmlspecialchars($formData['shippingcountry']),
					'#SHIPPING_PHONE#' => htmlspecialchars($formData['shippingphone'])
				];
			}

			//print_r($formData);

			// Strip internal/technical fields before rendering
			$internalKeys = [
				'csrf', 'csrf_token', '_csrf', 'g-recaptcha-response',
				'step', 'next', 'back', 'submit', 'controller', 'module', 'action',
				'subject', 'message', 'inquiry_token', 'fax_number', 'privacy',
				'checkout_token', 'cart_revision', 'review_token', 'checkout_stage', 'status_only', 'checkout_edit'
			];
			$internalPrefixes = ['_', '__']; // e.g. __meta, _internal

			$cleanData = [];
			foreach ($formData as $k => $v) {
				// skip explicit internal keys
				if (in_array($k, $internalKeys, true)) continue;
				// skip keys starting with internal prefixes
				foreach ($internalPrefixes as $pref) {
					if (strpos($k, $pref) === 0) { continue 2; }
				}
				$cleanData[$k] = $v;
			}

			// build details table from $cleanData
			$userDetailsHtml = '<table style="width: 100%; border-collapse: collapse;">';
			foreach ($cleanData as $key => $value) {
				// Escape key and value
				$label = ucfirst(str_replace('_', ' ', htmlspecialchars($key)));
				$content = nl2br(htmlspecialchars($value));

				$userDetailsHtml .= "<tr>
					<td style=\"padding: 5px; font-weight: bold; border-bottom: 1px solid #ddd;\">{$label}</td>
					<td style=\"padding: 5px; border-bottom: 1px solid #ddd;\">{$content}</td>
				</tr>";
			}
			$userDetailsHtml .= '</table>';

			$dynamicPlaceholders['#USER_DETAILS#'] = $userDetailsHtml;
		
			// Replace placeholders in the template
			foreach ($dynamicPlaceholders as $key => $value) {
				$body = str_replace($key, $value, $body);
			}

			// Translate email template
			$body = preg_replace_callback(
				'/t#t(.*?)t#t/', // Regex to match text between t#t and t#t
				function ($matches) use ($translate) {
					return $translate($matches[1]);
				},
				$body
			);

			$subject = preg_replace_callback('/t#t(.*?)t#t/', static fn($matches) => $translate($matches[1]), $template['subject']);
			if ($controller === 'checkout' && isset($data['__shop_order'])) {
				$order = $data['__shop_order'];
				$freight = Shops_Service_OrderSnapshot::decode($order['snapshot'])['freight'] ?? null;
				$currency = new Zend_Currency($order['currency'], $locale ?: 'en_US');
				$summary = '<dl>';
				$rows = [];
				if ($freight) $rows = ['SHOPS_SHIPPING_GOODS' => $freight['goods_net'], 'SHOPS_SHIPPING_METHOD' => $freight['label'], 'SHOPS_SHIPPING_NET' => $freight['net']];
				$rows += ['SHOPS_ORDER_NET' => $order['total'], 'SHOPS_ORDER_TAX' => $order['taxes'], 'SHOPS_ORDER_GROSS' => $order['grosstotal']];
				foreach ($rows as $key => $value) {
					$content = $key === 'SHOPS_SHIPPING_METHOD' ? $value : ($value === null ? $translate('SHOPS_CHECKOUT_TAX_UNKNOWN') : $currency->toCurrency($value));
					$summary .= '<dt>' . htmlspecialchars($translate($key)) . '</dt><dd>' . htmlspecialchars((string)$content) . '</dd>';
				}
				$tax = Shops_Service_OrderSnapshot::decode($order['snapshot'])['tax'] ?? null;
				if (($tax['status'] ?? '') === 'calculated') foreach ($tax['groups'] as $group) {
					$label = $translate('SHOPS_ORDER_TAX') . ' ' . Zend_Locale_Format::toNumber($group['taxrate'], ['locale' => $locale ?: 'en_US']) . ' %';
					$summary .= '<dt>' . htmlspecialchars($label) . '</dt><dd>' . htmlspecialchars($currency->toCurrency($group['taxes'])) . '</dd>';
				}
				$summary .= '</dl>';
				if (!$freight || !empty($freight['legacy'])) $summary .= '<p>' . htmlspecialchars($translate('SHOPS_CHECKOUT_SHIPPING_NOTICE')) . '</p>';
				$body = stripos($body, '</body>') === false ? $body . $summary : str_ireplace('</body>', $summary . '</body>', $body);
			}
			return [$body, $subject];
		} else {
			$body = $data['body'];
			$user = Zend_Registry::get('User');
			//Add email signature
			$body = str_replace('[SIGNATURE]', $user['emailsignature'], $body);
			/*if($campaignid) {
				$data['body'] = str_replace('[BODY]', $data['body'], $template);
			}*/

			$subject = $data['subject'];
			return [$body, $subject];
		}
	}

	private function personalizeBody(string $body, array $recipient): string {
		if (strpos($body, '[SALUTATION]') !== false) {
			$salutation = DEEC_ContactpersonFormatter::formalSalutation($recipient);
			$body = str_replace('[SALUTATION]', $salutation, $body);
		}

		return $body;
	}
}
