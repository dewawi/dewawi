<?php

class Campaigns_Model_List_Campaigns extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'campaignid',
				'label' => 'CAMPAIGNS_CAMPAIGN_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'CAMPAIGNS_TITLE',
				'type' => 'link',
				'fallback_field' => 'id',
			],
			[
				'name' => 'billingname1',
				'label' => 'CAMPAIGNS_CUSTOMER',
			],
			[
				'name' => 'emailsubject',
				'label' => 'CAMPAIGNS_EMAIL_SUBJECT',
			],
			[
				'name' => 'startdate',
				'label' => 'CAMPAIGNS_START_DATE',
				'type' => 'date',
				'format' => 'd.m.Y H:i',
			],
			[
				'name' => 'duedate',
				'label' => 'CAMPAIGNS_DUE_DATE',
				'type' => 'date',
				'format' => 'd.m.Y H:i',
			],
			[
				'name' => 'state',
				'label' => 'CAMPAIGNS_STATE',
				'type' => 'state_badge',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'state_map' => [
					'100' => 'created',
					'101' => 'in-process',
					'102' => 'check',
					'103' => 'delete',
					'104' => 'released',
					'105' => 'completed',
					'106' => 'cancelled',
				],
			],
			[
				'name' => 'pin',
				'label' => '',
				'type' => 'pin',
			],
			[
				'name' => 'actions',
				'label' => '',
				'type' => 'actions',
				'elements' => [
					['name' => 'edit'],
					['name' => 'copy'],
					['name' => 'delete'],
				],
			],
		];
	}
}
