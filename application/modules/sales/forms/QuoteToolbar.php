<?php

class Sales_Form_QuoteToolbar extends Sales_Form_Toolbar
{
	public function __construct()
	{
		parent::__construct();

		$this->addElement([
			'name' => 'monitoring',
			'type' => 'select',
			'label' => 'QUOTES_MONITORING',
			'default' => 'all',
			'options' => [
				'all' => 'TOOLBAR_ALL',
				'action_required' => 'QUOTES_MONITORING_ACTION_REQUIRED',
				'follow_up' => 'QUOTES_MONITORING_FOLLOW_UP',
				'feedback_open' => 'QUOTES_MONITORING_FEEDBACK_OPEN',
				'feedback_response' => 'QUOTES_MONITORING_FEEDBACK_RECEIVED',
				'check_send' => 'QUOTES_MONITORING_CHECK_SEND',
				'followup_draft' => 'QUOTES_MONITORING_FOLLOWUP_DRAFT',
				'continued' => 'QUOTES_MONITORING_CONTINUED',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);
	}
}
