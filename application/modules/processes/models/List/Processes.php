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
				'name' => 'delivery',
				'label' => 'PROCESSES_DELIVERY',
				'type' => 'delivery',
				'class' => 'dw-col-delivery',
				'option_key' => 'deliverystatus',
				'format' => 'd.m.Y',
				'editable' => function ($item, $element, $list) {
					return !$list->isReadonly($item);
				},
				'state_map' => [
					'deliveryIsWaiting' => 'warning',
					'partialDelivered' => 'info',
					'deliveryCompleted' => 'completed',
				],
			],
			[
				'name' => 'payment',
				'label' => 'PROCESSES_PAYMENT',
				'type' => 'payment',
				'class' => 'dw-col-payment',
				'option_key' => 'paymentstatus',
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
