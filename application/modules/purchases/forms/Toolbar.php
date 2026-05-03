<?php

class Purchases_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'edit'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
		]);

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
			'toolbar' => 'meta',
			'wrap' => false,
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
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
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'from',
			'type' => 'text',
			'label' => 'TOOLBAR_FROM',
			'default' => date('Y-m-d', strtotime('-1 month')),
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'date'],
			'attribs' => ['class' => 'datePicker'],
		]);

		$this->addElement([
			'name' => 'to',
			'type' => 'text',
			'label' => 'TOOLBAR_TO',
			'default' => date('Y-m-d'),
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'date'],
			'attribs' => ['class' => 'datePicker'],
		]);

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'source' => 'category:contact',
			'filter' => true,
			'toolbar' => 'category',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);
	}
}
