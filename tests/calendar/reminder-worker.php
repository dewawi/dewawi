<?php

require __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli' || count($argv) !== 6) exit(1);
[$batch, $worker, $id, $revision] = array_map('intval', array_slice($argv, 1, 4));
file_put_contents($work . '/ready-' . $batch . '-' . $worker, '1');
$deadline = microtime(true) + 10;
while (!file_exists($work . '/go-' . $batch)) { if (microtime(true) > $deadline) exit(1); usleep(1000); }
try {
	$model = new Calendar_Model_DbTable_Event();
	if ($argv[5] === 'mail') {
		$pid = (int)$model->participants($id)[0]['id'];
		echo json_encode((new Calendar_Model_Invitation())->send($id, [$pid], $revision, 'en_US')); exit;
	}
	elseif ($argv[5] === 'edit') $model->saveEvent(json_decode(file_get_contents($work . '/reminder-edit.json'), true), $id);
	else $model->updateReminder($id, $revision, $argv[5] === 'snooze' ? 5 : null);
	echo json_encode(['code' => 200]);
} catch (RuntimeException $e) { echo json_encode(['code' => $e->getCode()]); }
