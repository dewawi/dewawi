<?php

class Campaigns_FeedbackController extends Zend_Controller_Action
{
	public function init()
	{
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
	}

	public function indexAction()
	{
		if(!$this->getRequest()->isPost()) {
			$this->getResponse()->setHttpResponseCode(405);
			return;
		}

		$payload = json_decode((string)file_get_contents('php://input'), true);

		if(!is_array($payload) || empty($payload['TopicArn'])) {
			$this->getResponse()->setHttpResponseCode(400);
			return;
		}

		$configDb = new Application_Model_DbTable_Config();
		$config = $configDb->getBySesFeedbackTopicArn((string)$payload['TopicArn']);

		if(!$config) {
			$this->getResponse()->setHttpResponseCode(403);
			return;
		}

		$sns = new DEEC_Sns();

		if(!$sns->verify($payload, (string)$config['sesfeedbacktopicarn'])) {
			$this->getResponse()->setHttpResponseCode(403);
			return;
		}

		if(($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
			if(!$sns->confirm($payload, (string)$config['sesfeedbacktopicarn'])) {
				$this->getResponse()->setHttpResponseCode(500);
			}

			return;
		}

		if(($payload['Type'] ?? '') !== 'Notification') return;

		$event = json_decode((string)($payload['Message'] ?? ''), true);

		if(!is_array($event)) {
			$this->getResponse()->setHttpResponseCode(400);
			return;
		}

		$this->processEvent($event, $config);
	}

	private function processEvent(array $event, array $config): void
	{
		$type = (string)($event['notificationType'] ?? $event['eventType'] ?? '');
		$providerMessageId = trim((string)($event['mail']['messageId'] ?? ''));

		if($providerMessageId === '' || $type === '') return;

		$emailmessageDb = new Contacts_Model_DbTable_Emailmessage();
		$message = $emailmessageDb->getByProviderMessageId($providerMessageId);

		if(!$message) return;
		if((int)$message['clientid'] !== (int)$config['clientid']) return;
		if($message['module'] !== 'campaigns' || $message['controller'] !== 'campaign') return;

		$recipient = strtolower(trim((string)$message['recipient']));
		$recipients = $this->getRecipients($event, $type);
		$recipientMatches = in_array($recipient, $recipients, true);

		if($type === 'Complaint') {
			if($recipients && !$recipientMatches) return;

			if(!$recipients && (trim((string)$message['cc']) !== '' || trim((string)$message['bcc']) !== '')) {
				error_log('SES complaint could not be mapped because the message has copy recipients: '.$providerMessageId);
				return;
			}
		} elseif(!$recipientMatches) {
			return;
		}

		if($type === 'Delivery') {
			$emailmessageDb->updateDelivery((int)$message['id'], (int)$message['clientid'], [
				'deliverystatus' => 'delivered',
				'deliverydate' => $this->formatDate($event['delivery']['timestamp'] ?? null, $config),
				'deliveryresponse' => (string)($event['delivery']['smtpResponse'] ?? ''),
			]);

			return;
		}

		if($type === 'Bounce') {
			$bounce = $event['bounce'] ?? [];
			$response = $this->getBounceResponse($bounce, $recipient);

			$emailmessageDb->updateDelivery((int)$message['id'], (int)$message['clientid'], [
				'deliverystatus' => 'bounce',
				'deliverydate' => $this->formatDate($bounce['timestamp'] ?? null, $config),
				'deliveryresponse' => $response,
			]);

			if(($bounce['bounceType'] ?? '') === 'Permanent') {
				$this->suppress($message, 'bounce');
			}

			return;
		}

		if($type === 'Complaint') {
			$complaint = $event['complaint'] ?? [];

			$emailmessageDb->updateDelivery((int)$message['id'], (int)$message['clientid'], [
				'deliverystatus' => 'complaint',
				'deliverydate' => $this->formatDate($complaint['timestamp'] ?? null, $config),
				'deliveryresponse' => (string)($complaint['complaintFeedbackType'] ?? $complaint['complaintSubType'] ?? ''),
			]);

			$this->suppress($message, 'complaint');
		}
	}

	private function getRecipients(array $event, string $type): array
	{
		$recipients = [];

		if($type === 'Delivery') {
			$recipients = $event['delivery']['recipients'] ?? [];
		} elseif($type === 'Bounce') {
			foreach(($event['bounce']['bouncedRecipients'] ?? []) as $recipient) {
				if(!empty($recipient['emailAddress'])) $recipients[] = $recipient['emailAddress'];
			}
		} elseif($type === 'Complaint') {
			foreach(($event['complaint']['complainedRecipients'] ?? []) as $recipient) {
				if(!empty($recipient['emailAddress'])) $recipients[] = $recipient['emailAddress'];
			}
		}

		return array_values(array_unique(array_filter(array_map(function($email) {
			return strtolower(trim((string)$email));
		}, $recipients))));
	}

	private function getBounceResponse(array $bounce, string $email): string
	{
		foreach(($bounce['bouncedRecipients'] ?? []) as $recipient) {
			if(strtolower(trim((string)($recipient['emailAddress'] ?? ''))) !== $email) continue;

			return trim(implode(' ', array_filter([
				$bounce['bounceType'] ?? '',
				$bounce['bounceSubType'] ?? '',
				$recipient['status'] ?? '',
				$recipient['diagnosticCode'] ?? '',
			])));
		}

		return trim((string)($bounce['bounceType'] ?? ''));
	}

	private function suppress(array $message, string $reason): void
	{
		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->setClientId((int)$message['clientid']);
		$emailDb->suppressByEmail((string)$message['recipient'], $reason);
	}

	private function formatDate($value, array $config): ?string
	{
		if(!$value) return null;

		try {
			$date = new DateTime((string)$value);
			$date->setTimezone(new DateTimeZone($config['timezone'] ?: 'Europe/Berlin'));
			return $date->format('Y-m-d H:i:s');
		} catch(Exception $e) {
			return null;
		}
	}
}
