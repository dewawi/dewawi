<?php

class Campaigns_Model_List_Recipients extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'CAMPAIGNS_CONTACT_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'contactid',
				'label' => 'CAMPAIGNS_CONTACT_CONTACT_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'name1',
				'label' => 'CAMPAIGNS_CONTACT_NAME',
				'type' => 'link',
				'class' => 'dw-col-title',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'id',
				],
			],
			[
				'name' => 'emails',
				'label' => 'CAMPAIGNS_EMAIL',
			],
			[
				'name' => 'contactpersons',
				'label' => 'CAMPAIGNS_CONTACT_PERSONS',
				'type' => 'callback',
				'callback' => [$this, 'renderContactPersons'],
			],
			[
				'name' => 'messages',
				'label' => 'CAMPAIGNS_RECIPIENT_STATUS',
				'type' => 'callback',
				'callback' => [$this, 'renderMessages'],
			],
		];
	}

	public function renderContactPersons($contact): string
	{
		$contactId = (int)$this->getFieldValue($contact, 'id');
		$personsByCompany = (array)$this->getOption('contactPersonsByCompany', []);
		$persons = $personsByCompany[$contactId] ?? [];

		if(!$persons) return '';

		$html = [];

		foreach($persons as $person) {
			$name = trim((string)($person['display_name'] ?? ''));
			$emails = array_values(array_filter(array_map('trim', explode(',', (string)($person['email_list'] ?? '')))));
			$content = '';

			if($name !== '') $content .= '<strong>'.$this->escape($name).'</strong>';
			foreach($emails as $email) $content .= '<div class="dw-list-value">'.$this->escape($email).'</div>';

			if($content !== '') $html[] = '<div>'.$content.'</div>';
		}

		return implode('', $html);
	}

	public function renderMessages($contact): string
	{
		$contactId = (int)$this->getFieldValue($contact, 'id');
		$messagesByContact = (array)$this->getOption('emailmessages', []);
		$messages = $messagesByContact[$contactId] ?? [];

		if(!$messages) return '';

		$html = [];

		foreach($messages as $message) {
			$response = (string)($message['response'] ?? '');
			$label = $response === 'sent'
				? $this->translate('CAMPAIGNS_RECIPIENT_SENT')
				: ($response === 'pending' ? $this->translate('CAMPAIGNS_RECIPIENT_PENDING') : $this->translate('CAMPAIGNS_RECIPIENT_FAILED'));

			$content = '<div>'.$this->escape((string)($message['recipient'] ?? '')).'</div>';

			if(!empty($message['messagesent'])) $content .= '<div class="dw-list-value">'.$this->escape($message['messagesent']).'</div>';

			$content .= '<div class="dw-list-value">'.$this->escape($label).'</div>';
			$content .= '<button type="button" class="dw-btn dw-btn--secondary" onclick="resendMessage('.(int)$message['id'].')">'.$this->escape($this->translate('CAMPAIGNS_RESEND')).'</button>';

			$html[] = '<div>'.$content.'</div>';
		}

		return implode('', $html);
	}
}
