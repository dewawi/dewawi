<?php

class Application_Controller_Action_Helper_Email extends Zend_Controller_Action_Helper_Abstract
{
	public function sendEmail($module, $controller, $items = null)
	{
		$request = $this->getRequest();
		if($request->isPost()) {
			$data = $request->getPost();
			if(true) {
	            $messageid = $request->getParam('messageid', 0);
	            $contactid = $request->getParam('contactid', 0);
	            $documentid = $request->getParam('documentid', 0);
	            $campaignid = $request->getParam('campaignid', 0);

		        if($module == 'shops') {
                    if($controller == 'checkout') {
			            $form = new Shops_Form_Checkout();
		            } else {
			            $form = new Shops_Form_Contact();
		            }

			        $emailmessageDb = new Shops_Model_DbTable_Emailmessage();

					$recipients = array();
					$recipients[0]['email'] = $data['email'];
					$recipients[0]['contactid'] = 0;
		        } else {
			        $form = new Contacts_Form_Contact();

			        $emailmessageDb = new Contacts_Model_DbTable_Emailmessage();

			        if($messageid) {
				        $emailmessage = $emailmessageDb->getEmailmessage($messageid);
				        unset($emailmessage['id'], $emailmessage['messagesent'], $emailmessage['messagesentby'], $emailmessage['response']);
				        $data = $emailmessage;
				        $contactid = $emailmessage['contactid'];
				        $documentid = $emailmessage['documentid'];
				        $campaignid = $emailmessage['campaignid'];
			        }

					$recipients = array();
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
		        }

                if($form->isValid($data) || true) {
				    // Get form data
				    $formData = $form->getValues();

				    //PHPMailer
				    require_once(BASE_PATH.'/library/PHPMailer/Exception.php');
				    require_once(BASE_PATH.'/library/PHPMailer/PHPMailer.php');
				    require_once(BASE_PATH.'/library/PHPMailer/SMTP.php');

				    if($form->isValid($data) || true) {
                        // Get SMTP settings
                        list($smtpHost, $smtpUser, $smtpPass, $emailSender) = $this->getSmtpDetails($module);

                        $this->sendEmails($module, $controller, $recipients, $smtpHost, $smtpUser, $smtpPass, $emailSender, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items);
				    }
				}
			} else {
		        $flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
				$flashMessengerHelper->addMessage('MESSAGES_FORM_INVALID');
			}
		}
	}

    private function sendEmails($module, $controller, $recipients, $smtpHost, $smtpUser, $smtpPass, $emailSender, $formData, $data, $emailmessageDb, $documentid, $campaignid, $items)
    {
		$mail = new PHPMailer\PHPMailer\PHPMailer();

		//Server settings
		$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;				// Enable verbose debug output
		$mail->isSMTP();														// Send using SMTP
		$mail->Host		= $smtpHost;		        							// Set the SMTP server to send through
		$mail->SMTPAuth	= true;													// Enable SMTP authentication
		$mail->Username	= $smtpUser;									        // SMTP username
		$mail->Password	= $smtpPass;									        // SMTP password
		$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;	// Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
		$mail->Port		= 465;													// TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

		foreach($recipients as $recipient) {
		    //Recipients
		    $mail->clearAllRecipients( );											    // clear all
		    $mail->setFrom($smtpUser, $emailSender);
		    $mail->addAddress($recipient['email']);										// Add a recipient
		    if($module == 'shops') $mail->addAddress($emailSender);		    	    	// Add a recipient
            if(isset($data['replyto']) && $data['replyto']) {
			    $data['replyto'] = str_replace(' ', '', $data['replyto']);				// Remove spaces
			    if($data['replyto']) $mail->addReplyTo($data['replyto']);				// Add reply to
            }
            if(isset($data['cc']) && $data['cc']) {
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
            }
            if(isset($data['bcc']) && $data['bcc']) {
			    $data['bcc'] = str_replace(' ', '', $data['bcc']);
			    if($data['bcc']) $mail->addBCC($data['bcc']);
            }


		    $attachmentsSent = array();
            if($module == 'contacts') {
			    //Get email attachments
			    $emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
			    if($campaignid) $attachmentsObject = $emailattachmentDb->getEmailattachments($campaignid, $data['module'], $data['controller']);
			    elseif($data['module'] == 'contacts') $attachmentsObject = $emailattachmentDb->getEmailattachments($recipient['contactid'], $data['module'], $data['controller']);
			    else $attachmentsObject = $emailattachmentDb->getEmailattachments($documentid, $data['module'], $data['controller']);
			    $attachmentsAvailable = array();
			    foreach($attachmentsObject as $attachment) $attachmentsAvailable[$attachment['id']] = $attachment;

			    if(isset($data['files'])) {
                    $directoryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Directory');
				    if($data['module'] == 'contacts') $url = $directoryHelper->getUrl($recipient['contactid']);
				    else $url = $directoryHelper->getUrl($documentid);
				    foreach($data['files'] as $file) {
					    if(file_exists($attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename'])) {
						    array_push($attachmentsSent, $attachmentsAvailable[$file]['filename']);
						    $mail->addAttachment($attachmentsAvailable[$file]['location'].'/'.$attachmentsAvailable[$file]['filename']);
					    }
				    }
			    }
			}

            // Get email body and subject
            list($body, $subject) = $this->getEmailBody($module, $controller, $formData, $data, $items);

		    //Save email message to the db
		    $emailmessage = array();
		    $emailmessage['contactid'] = $recipient['contactid'];
		    $emailmessage['documentid'] = $documentid;
		    $emailmessage['parentid'] = $campaignid;
		    $emailmessage['module'] = $data['module'];
		    $emailmessage['controller'] = $data['controller'];
		    $emailmessage['recipient'] = $recipient['email'];
		    if(isset($data['cc'])) $emailmessage['cc'] = $data['cc'];
		    if(isset($data['bcc'])) $emailmessage['bcc'] = $data['bcc'];
		    $emailmessage['subject'] = $subject ? $subject : 'Anfrageformular';
		    $emailmessage['body'] = $body;
		    $emailmessage['attachment'] = implode(',', $attachmentsSent);
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
		    $mail->Subject = $subject ? $subject : 'Anfrageformular';
		    $mail->Body	= $body;
		    //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            if ($module === 'shops') {
                $shop = Zend_Registry::get('Shop');
	            //Get image path
	            $clientid = $shop['clientid'];
	            $dir1 = substr($clientid, 0, 1);
	            if(strlen($clientid) > 1) $dir2 = substr($clientid, 1, 1);
	            else $dir2 = '0';
	            $imagePath = $dir1.'/'.$dir2.'/'.$clientid;

	            // Logo einbetten
	            $mail->addEmbeddedImage(BASE_PATH.'/media/'.$imagePath.'/header/'.$shop['logo'], 'logo_cid');
            }

		    //Content
		    $mail->CharSet	= 'UTF-8';
		    $mail->Encoding = 'base64';

	        $flashMessengerHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
	        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

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
    }

    private function getSmtpDetails($module)
    {
        if ($module === 'shops') {
            $shop = Zend_Registry::get('Shop');
            return [$shop['smtphost'], $shop['smtpuser'], $shop['smtppass'], $shop['emailsender']];
        } else {
            $user = Zend_Registry::get('User');
            return [$user['smtphost'], $user['smtpuser'], $user['smtppass'], $user['emailsender']];
        }
    }

    private function getEmailBody($module, $controller, $formData, $data, $items)
    {
        if ($module == 'shops') {
            $templateDb = new Shops_Model_DbTable_Emailtemplate();
            $template = $templateDb->getEmailtemplate($module, $controller);

            $body = $template['body'];
            $shop = Zend_Registry::get('Shop');

		    $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');

            // Replace dynamic placeholders with values
            $dynamicPlaceholders = [
	            '#HELLO_NAME#' => sprintf(
		            $viewRenderer->view->translate('MESSAGES_HELLO_NAME'),
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
            }

            print_r($formData);
	    
	        // Replace placeholders in the template
	        foreach ($dynamicPlaceholders as $key => $value) {
		        $body = str_replace($key, $value, $body);
	        }

		    // Translate email template
		    $body = preg_replace_callback(
			    '/t#t(.*?)t#t/', // Regex to match text between t#t and t#t
			    function ($matches) use ($viewRenderer) {
				    return $viewRenderer->view->translate($matches[1]);
			    },
			    $body
		    );

            $subject = $template['subject'];
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
}
