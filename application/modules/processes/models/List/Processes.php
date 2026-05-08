<?php

class Processes_Model_List_Processes extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'PROCESSES_PROCESS_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
				'empty_hide' => true,
			],
			[
				'name' => 'title',
				'label' => 'PROCESSES_TITLE',
				'type' => 'link',
				'fallback_field' => 'id',
			],
			[
				'name' => 'contact',
				'label' => 'PROCESSES_CONTACT',
				'type' => 'contact',
				'url' => [
					'module' => 'contacts',
					'controller' => 'contact',
					'action' => 'edit',
					'id_field' => 'cid',
				],
			],
			[
				'name' => 'notes',
				'label' => 'PROCESSES_NOTES',
				'type' => 'editable_note',
				'empty_label' => 'TOOLBAR_NEW',
			],
			[
				'name' => 'deliverydate',
				'label' => 'PROCESSES_DELIVERY_DATE',
				'type' => 'date',
				'format' => 'd.m.Y',
			],
			[
				'name' => 'paymentstatus',
				'label' => 'PROCESSES_PAYMENT_STATUS',
				'type' => 'state_badge',
				'option_key' => 'paymentstatus',
				'class' => 'dw-col-date',
				'editable' => function ($item, $element, $list) {
					return !$list->isReadonly($item);
				},
				'state_map' => [
					'waitingForPayment' => 'warning',
					'prepaymentReceived' => 'info',
					'paymentCompleted' => 'completed',
				],
			],
			[
				'name' => 'total',
				'label' => 'PROCESSES_TOTAL',
				'class' => 'dw-col-total',
			],
			[
				'name' => 'state',
				'label' => 'PROCESSES_STATE',
				'type' => 'state_badge',
				'option_key' => 'states',
				'class' => 'dw-col-state state',
				'editable' => function ($item, $element, $list) {
					return !$list->isReadonly($item);
				},
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
					[
						'name' => 'view',
						'show' => function ($item, $element, $list) {
							return $list->isReadonly($item);
						},
					],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							return !$list->isReadonly($item);
						},
					],
					['name' => 'copy'],
					['name' => 'delete'],
					['name' => 'pdf'],
				],
			],
		];
	}
}
