<?php

class Shops_Controller_Action_Helper_Email extends Zend_Controller_Action_Helper_Abstract
{
	public function sendEmail($module, $controller, $items = null)
	{
		$request = $this->getRequest();
		if($controller == 'checkout') {
			$form = new Shops_Form_Checkout();
		} else {
			$form = new Shops_Form_Contact();
		}

		$flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

		$templateDb = new Shops_Model_DbTable_Emailtemplate();
		$template = $templateDb->getEmailtemplate($module, $controller);

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

					//Recipients
					$mail->clearAllRecipients( );											// clear all
					$mail->setFrom($shop['smtpuser'], $shop['emailsender']);
					$mail->addAddress($formData['email']);												// Add a recipient
					$mail->addAddress($shop['emailsender']);												// Add a recipient
					//$mail->addAddress('');												// Add a recipient
					//$data['replyto'] = str_replace(' ', '', $data['replyto']);				// Remove spaces
					//if($data['replyto']) $mail->addReplyTo($data['replyto']);				// Add reply to
					//$data['cc'] = str_replace(' ', '', $data['cc']);						// Remove spaces

					//Save email message to the db
					$emailmessage = array();
					$emailmessage['contactid'] = 0;
					$emailmessage['documentid'] = 0;
					$emailmessage['parentid'] = 0;
					$emailmessage['module'] = $data['module'];
					$emailmessage['controller'] = $data['controller'];
					$emailmessage['recipient'] = $data['email'];
					//$emailmessage['cc'] = $data['cc'];
					//$emailmessage['bcc'] = $data['bcc'];
					$emailmessage['subject'] = $data['subject'] ? $data['subject'] : 'Anfrageformular';
					$emailmessage['body'] = $data['message'];
					//$emailmessage['attachment'] = implode(',', $attachmentsSent);
					$emailmessageDb = new Shops_Model_DbTable_Emailmessage();
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
					$mail->Subject = $template['subject'];
					//$mail->Body	= $formData['message'];
					//$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

					//Get image path
					$clientid = $shop['clientid'];
					$dir1 = substr($clientid, 0, 1);
					if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
					else $dir2 = '0';
					$imagePath = $dir1.'/'.$dir2.'/'.$clientid;

					// Logo einbetten
					$mail->addEmbeddedImage(BASE_PATH.'/media/'.$imagePath.'/header/'.$shop['logo'], 'logo_cid');

					$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');

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

					// Replace dynamic placeholders with values
					$dynamicPlaceholders = [
						'#HELLO_NAME#' => sprintf(
							$viewRenderer->view->translate('MESSAGES_HELLO_NAME'),
							htmlspecialchars($formData['billingname'])
						),
						'#SHOP_TITLE#' => htmlspecialchars($shop['title']),
						'#SHOP_URL#' => htmlspecialchars($shop['url']),
						'#SHOP_EMAIL#' => htmlspecialchars($shop['emailsender']),
						'#MESSAGES_SUBJECT#' => htmlspecialchars($formData['subject']),
						'#MESSAGES_MESSAGE#' => nl2br(htmlspecialchars($formData['message'])),
						'#MESSAGES_BEST_REGARDS#' => htmlspecialchars($shop['emailsender']) . '<br>' . htmlspecialchars($shop['title']),
						'#MESSAGES_ALL_RIGHTS_RESERVED#' => date('Y') . ' ' . htmlspecialchars($shop['title']),
						'#ORDER_NUMBER#' => htmlspecialchars(654836),
						'#ORDER_DATE#' => htmlspecialchars('24.01.2024'),
						'#PAYMENT_METHOD#' => htmlspecialchars('Überweisung'),
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
					
					// Replace placeholders in the template
					foreach ($dynamicPlaceholders as $key => $value) {
						$template['body'] = str_replace($key, $value, $template['body']);
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
					$template['body'] = str_replace('#ORDER_ITEMS#', $orderItemsHtml, $template['body']);

					// Translate email template
					$mail->Body = preg_replace_callback(
						'/t#t(.*?)t#t/', // Regex to match text between t#t and t#t
						function ($matches) use ($viewRenderer) {
							return $viewRenderer->view->translate($matches[1]);
						},
						$template['body']
					);

					//Content
					$mail->CharSet	= 'UTF-8';
					$mail->Encoding = 'base64';

					//Send the message, check for errors
					if(!$mail->send()) {
						//Save errors to the db
						$emailmessageDb->updateEmailmessage($messageid, array('response' => $mail->ErrorInfo));
						$flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_ERROR');
						$redirector->gotoSimple('error', $controller, 'default');
					} else {
						$flashMessengerHelper->addMessage('MESSAGES_EMAIL_SENT_SUCCESS');
						$redirector->gotoSimple('success', $controller, 'default');
						//print_r($formData);
					}
				}
			} else {
				$flashMessengerHelper->addMessage('MESSAGES_FORM_INVALID');
			}
		}
	}
}
