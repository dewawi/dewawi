<?php

class Tasks_Model_List_Tasks extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			[
				'name' => 'id',
				'label' => 'TASKS_ID',
				'type' => 'link',
				'class' => 'dw-col-id',
			],
			[
				'name' => 'title',
				'label' => 'TASKS_TITLE',
				'type' => 'link',
				'class' => 'dw-col-title',
			],
			[
				'name' => 'taskdate',
				'label' => 'TASKS_TASKDATE',
				'type' => 'date',
			],
			[
				'name' => 'duedate',
				'label' => 'TASKS_DUEDATE',
				'type' => 'date',
			],
			[
				'name' => 'priority',
				'label' => 'TASKS_PRIORITY',
				'type' => 'text',
			],
			[
				'name' => 'state',
				'label' => 'TASKS_STATE',
				'type' => 'state',
			],
			[
				'name' => 'completed',
				'label' => 'TASKS_COMPLETED',
				'type' => 'bool',
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
						'name' => 'apply',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') === 'select';
						},
					],
					[
						'name' => 'view',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'edit',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'copy',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
					[
						'name' => 'delete',
						'show' => function ($item, $element, $list) {
							return $list->getContext('action') !== 'select';
						},
					],
				],
			],
		];
	}
}
