<?php

class Tasks_Model_Entity_Task
{
	public static function listConfig(): array
	{
		return [
			'tableClass' => 'Tasks_Model_DbTable_Task',
			'alias' => 't',

			'pinned' => true,

			'search' => [
				'title',
				'description',
				'info',
				'notes',
				'billingname1',
				'billingname2',
				'suppliername',
			],

			'filters' => [
				'state' => [
					'type' => 'state',
					'column' => 'state',
				],
				'completed' => [
					'type' => 'bool',
					'column' => 'completed',
				],
				'cancelled' => [
					'type' => 'bool',
					'column' => 'cancelled',
				],
				'responsible' => [
					'type' => 'user',
					'column' => 'responsible',
				],
			],

			'orders' => [
				'id',
				'title',
				'taskdate',
				'duedate',
				'priority',
				'state',
				'completed',
				'created',
				'modified',
			],

			'normalizers' => [
				'description' => [
					'type' => 'truncate',
					'length' => 80,
				],
			],
		];
	}
}
