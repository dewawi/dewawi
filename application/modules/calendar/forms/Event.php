<?php

class Calendar_Form_Event extends DEEC_Form
{
	public function __construct()
	{
		$this->addCsrfToken();
		$this->addElement(['name' => 'title', 'type' => 'text', 'label' => 'CALENDAR_TITLE', 'required' => true, 'col' => 12, 'attribs' => ['maxlength' => 255]]);
		$this->addElement(['name' => 'description', 'type' => 'textarea', 'label' => 'CALENDAR_DESCRIPTION', 'col' => 12, 'attribs' => ['rows' => 4, 'maxlength' => 15000]]);
		$this->addElement(['name' => 'allday', 'type' => 'checkbox', 'label' => 'CALENDAR_ALLDAY', 'col' => 12, 'attribs' => ['data-calendar-allday' => 'true']]);
		foreach (['start', 'end'] as $field) {
			$this->addElement(['name' => $field, 'type' => 'datetime-local', 'label' => 'CALENDAR_' . strtoupper($field), 'required' => true, 'col' => 6, 'attribs' => ['data-calendar-time' => 'true', 'step' => 1]]);
			$this->addElement(['name' => $field . 'fold', 'type' => 'select', 'label' => 'CALENDAR_' . strtoupper($field) . '_FOLD', 'options' => ['' => 'CALENDAR_FOLD_AUTO', 'earlier' => 'CALENDAR_FOLD_EARLIER', 'later' => 'CALENDAR_FOLD_LATER'], 'col' => 6]);
		}
		$this->addElement(['name' => 'timezone', 'type' => 'select', 'label' => 'CALENDAR_TIMEZONE', 'options' => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()), 'required' => true, 'col' => 6]);
		$this->addElement(['name' => 'visibility', 'type' => 'select', 'label' => 'CALENDAR_VISIBILITY', 'options' => ['team' => 'CALENDAR_TEAM', 'private' => 'CALENDAR_PRIVATE'], 'required' => true, 'col' => 6]);
		$this->addElement(['name' => 'location', 'type' => 'text', 'label' => 'CALENDAR_LOCATION', 'col' => 12, 'attribs' => ['maxlength' => 255]]);
		$this->addElement(['name' => 'reminderminutes', 'type' => 'select', 'label' => 'CALENDAR_REMINDER', 'default' => 'none', 'options' => ['none' => 'CALENDAR_REMINDER_NONE', '0' => 'CALENDAR_REMINDER_START', '5' => 'CALENDAR_REMINDER_5', '15' => 'CALENDAR_REMINDER_15', '30' => 'CALENDAR_REMINDER_30', '60' => 'CALENDAR_REMINDER_60', '1440' => 'CALENDAR_REMINDER_DAY'], 'description' => 'CALENDAR_REMINDER_HELP', 'col' => 12]);
	}
}
