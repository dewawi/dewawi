<?php

class Calendar_Form_Participant extends DEEC_Form
{
	public function __construct(array $users, bool $internal, bool $canMail = false)
	{
		$this->addCsrfToken();
		$this->addElement(['name' => 'operation', 'type' => 'hidden', 'value' => 'add']);
		$this->addElement(['name' => 'kind', 'type' => 'hidden', 'value' => $internal ? 'internal' : 'external']);
		$this->addElement(['name' => 'sequence', 'type' => 'hidden']);
		if ($internal) $this->addElement(['name' => 'userid', 'type' => 'select', 'label' => 'CALENDAR_INTERNAL', 'options' => ['' => 'CALENDAR_CHOOSE'] + $users, 'required' => true, 'col' => 12]);
		else {
			$this->addElement(['name' => 'name', 'type' => 'text', 'label' => 'CALENDAR_PARTICIPANT_NAME', 'required' => true, 'col' => 6, 'attribs' => ['maxlength' => 255]]);
			$this->addElement(['name' => 'email', 'type' => 'email', 'label' => 'CALENDAR_PARTICIPANT_EMAIL', 'required' => true, 'col' => 6, 'attribs' => ['maxlength' => 254]]);
		}
		if ($canMail) $this->addElement(['name' => 'sendinvite', 'type' => 'checkbox', 'label' => 'CALENDAR_SEND_ON_ADD', 'default' => '0', 'col' => 12, 'attribs' => ['data-calendar-send-on-add' => 'true']]);
	}
}
