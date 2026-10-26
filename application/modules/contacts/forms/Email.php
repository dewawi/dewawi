<?php

class Contacts_Form_Email extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'email',
			'label' => 'CONTACTS_EMAIL',
			'type' => 'text',
			'format' => ['type' => 'email'],
		]);

		$this->addElement([
			'name' => 'suppressed',
			'label' => 'CONTACTS_EMAIL_SUPPRESSED',
			'type' => 'checkbox',
			'format' => ['type' => 'bool'],
		]);
	}

	protected function renderAdditionalMultiActions(array $row, array $ctx): string
	{
		if (
			(string)($row['module'] ?? '') !== 'contacts'
			|| (string)($row['controller'] ?? '') !== 'contact'
			|| trim((string)($row['email'] ?? '')) === ''
		) {
			return '';
		}

		return '<button'
			. $this->renderHtmlAttribs([
				'type' => 'button',
				'class' => 'dw-btn dw-btn--icon apply',
				'data-email-contactperson-assign' => '1',
				'data-email-id' => (int)$row['id'],
				'data-email-address' => (string)$row['email'],
				'aria-label' => $this->translate('CONTACTS_EMAIL_ASSIGN_CONTACTPERSON'),
				'title' => $this->translate('CONTACTS_EMAIL_ASSIGN_CONTACTPERSON'),
			])
			. '></button>';
	}
}
