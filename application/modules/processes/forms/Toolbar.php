<?php

class Processes_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'edit'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'delete'],
		]);

		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'filter'],
		]);

		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'wrap' => false,
			'toolbar' => 'actions',
			'attribs' => ['class' => 'reset'],
		]);

		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'default' => '',
			'wrap' => false,
			'toolbar' => 'search',
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'wrap' => false,
			'toolbar' => 'search',
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'name' => 'limit',
			'type' => 'select',
			'default' => '25',
			'options' => [
				'10' => '10',
				'25' => '25',
				'50' => '50',
				'100' => '100',
			],
			'wrap' => false,
			'toolbar' => 'meta',
			'format' => ['type' => 'int'],
		]);

		$this->addElement([
			'name' => 'order',
			'type' => 'select',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'modified',
			'options' => [
				'modified' => 'TOOLBAR_MODIFIED',
				'created' => 'TOOLBAR_CREATED',
				'processid' => 'PROCESSES_PROCESS_ID',
				'name1' => 'CONTACTS_NAME',
			],
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'sort',
			'type' => 'select',
			'label' => 'TOOLBAR_SORT',
			'default' => 'DESC',
			'options' => [
				'ASC' => 'TOOLBAR_ASC',
				'DESC' => 'TOOLBAR_DESC',
			],
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'states',
			'type' => 'multicheckbox',
			'label' => 'TOOLBAR_STATE',
			'default' => ['100', '101', '102', '103', '104'],
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_PROCESS',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
				'105' => 'STATES_COMPLETED',
				'106' => 'STATES_CANCELLED',
			],
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'select',
			'label' => 'TOOLBAR_COUNTRY',
			'default' => '0',
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'paymentstatus',
			'type' => 'select',
			'label' => 'PROCESSES_PAYMENT_STATUS',
			'default' => '0',
			'options' => [
				'0' => 'TOOLBAR_ALL',
				'waitingForPayment' => 'PROCESSES_WAITING_FOR_PAYMENT',
				'prepaymentReceived' => 'PROCESSES_PREPAYMENT_RECEIVED',
				'paymentCompleted' => 'PROCESSES_PAYMENT_COMPLETED',
			],
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'daterange',
			'type' => 'select',
			'label' => 'TOOLBAR_DATE_RANGE',
			'default' => 'last30days',
			'options' => [
				'today' => 'TOOLBAR_TODAY',
				'yesterday' => 'TOOLBAR_YESTERDAY',
				'last7days' => 'TOOLBAR_LAST_7_DAYS',
				'last14days' => 'TOOLBAR_LAST_14_DAYS',
				'last30days' => 'TOOLBAR_LAST_30_DAYS',
				'thisMonth' => 'TOOLBAR_THIS_MONTH',
				'lastMonth' => 'TOOLBAR_LAST_MONTH',
				'thisYear' => 'TOOLBAR_THIS_YEAR',
				'lastYear' => 'TOOLBAR_LAST_YEAR',
				'custom' => 'TOOLBAR_CUSTOM',
			],
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'from',
			'type' => 'text',
			'label' => 'TOOLBAR_FROM',
			'default' => date('Y-m-d', strtotime('-1 month')),
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'date'],
			'attribs' => ['class' => 'datePicker'],
		]);

		$this->addElement([
			'name' => 'to',
			'type' => 'text',
			'label' => 'TOOLBAR_TO',
			'default' => date('Y-m-d'),
			'wrap' => false,
			'toolbar' => 'filters',
			'filter' => true,
			'format' => ['type' => 'date'],
			'attribs' => ['class' => 'datePicker'],
		]);

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'default' => 'all',
			'wrap' => false,
			'toolbar' => 'category',
			'filter' => true,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'wrap' => false,
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'source' => 'category:contact',
			'toolbar' => 'category',
			'default' => 'all',
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'state',
			'type' => 'select',
			'label' => 'TOOLBAR_STATE',
			'default' => '100',
			'options' => [
				'100' => 'STATES_CREATED',
				'101' => 'STATES_IN_PROCESS',
				'102' => 'STATES_PLEASE_CHECK',
				'103' => 'STATES_PLEASE_DELETE',
				'104' => 'STATES_RELEASED',
				'105' => 'STATES_COMPLETED',
				'106' => 'STATES_CANCELLED',
			],
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}
}
