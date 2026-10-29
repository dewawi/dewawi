<?php

class Contacts_Form_InquiryToolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'filter'],
		]);
		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
		]);
		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'default' => '',
			'toolbar' => 'search',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);
		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'toolbar' => 'search',
			'wrap' => false,
			'attribs' => ['class' => 'clear nolabel', 'rel' => 'keyword'],
		]);
		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'CONTACT_INQUIRIES_WEBSITE',
			'default' => 'all',
			'options' => ['all' => 'TOOLBAR_ALL'],
			'source' => 'shop',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'inquirytype',
			'type' => 'select',
			'label' => 'CONTACT_INQUIRIES_TYPE',
			'default' => 'all',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'contact' => 'CONTACT_INQUIRIES_TYPE_CONTACT',
				'technical_inquiry' => 'CONTACT_INQUIRIES_TYPE_TECHNICAL',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'status',
			'type' => 'select',
			'label' => 'CONTACT_INQUIRIES_STATUS',
			'default' => 'all',
			'options' => ['all' => 'TOOLBAR_ALL'] + Contacts_Model_DbTable_Inquiry::STATUSES,
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'order',
			'type' => 'radio',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'created',
			'options' => [
				'created' => 'CONTACT_INQUIRIES_DATE',
				'modified' => 'TOOLBAR_MODIFIED',
				'website_title' => 'CONTACT_INQUIRIES_WEBSITE',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
		$this->addElement([
			'name' => 'sort',
			'type' => 'radio',
			'label' => 'TOOLBAR_SORT',
			'default' => 'DESC',
			'options' => ['ASC' => 'TOOLBAR_ASC', 'DESC' => 'TOOLBAR_DESC'],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}
}
