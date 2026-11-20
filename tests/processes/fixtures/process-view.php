<?php

// Extend the original MVC fixture with stored values; writes remain forbidden by its adapter.
$adapter->processView = true;
set_error_handler(static function ($severity, $message, $file, $line) {
	if (($severity & error_reporting()) || str_starts_with($file, APPLICATION_PATH . '/modules/processes/')) throw new ErrorException($message, 0, $severity, $file, $line);
	return false;
});
$scenario = (string)$_GET['fixture_process_view'];
$row = array_replace($processDefaults, [
	'id' => 17, 'clientid' => 70, 'deleted' => 0, 'state' => 105, 'completed' => 1, 'cancelled' => 0,
	'title' => 'Stored process', 'contactid' => 10017, 'billingname1' => 'Stored recipient snapshot',
	'subtotal' => '800.1234', 'taxes' => '93.4567', 'total' => '893.5801', 'currency' => 'EUR', 'locked' => 0,
]);
$adapter->rows['process'] = [$row];
$adapter->rows['contact'] = [
	['id' => 31, 'contactid' => 10017, 'clientid' => 70, 'deleted' => 0, 'name1' => 'Current contact'],
	['id' => 91, 'contactid' => 10017, 'clientid' => 99, 'deleted' => 0, 'name1' => 'Foreign contact'],
];
$position = ['id' => 101, 'parentid' => 17, 'clientid' => 70, 'ordering' => 2, 'deleted' => 0, 'masterid' => null, 'possetid' => 0,
	'sku' => 'STORED-A', 'title' => 'Stored line A', 'description' => "Stored\ntext", 'price' => '12.3456', 'quantity' => '2.5000', 'taxrate' => '19.0000', 'total' => '99.9999', 'currency' => 'EUR', 'uom' => 'piece'];
$adapter->rows['processpos'] = [
	$position,
	array_replace($position, ['id' => 102, 'ordering' => 1, 'title' => 'Stored line B', 'price' => '25.4321', 'quantity' => '3.0000', 'taxrate' => '7.0000', 'description' => null]),
	array_replace($position, ['id' => 100, 'ordering' => 1, 'title' => 'Stored line C']),
	array_replace($position, ['id' => 201, 'clientid' => 99, 'title' => 'FOREIGN POSITION']),
	array_replace($position, ['id' => 202, 'parentid' => 18, 'title' => 'OTHER PROCESS POSITION']),
	array_replace($position, ['id' => 203, 'deleted' => 1, 'title' => 'DELETED POSITION']),
];
if ($scenario === 'empty') $adapter->rows['processpos'] = [];
if ($scenario === 'draft') {
	$adapter->rows['process'][0]['state'] = 100;
	$adapter->rows['process'][0]['completed'] = 0;
}
if ($scenario === 'no-contact') $adapter->rows['process'][0]['contactid'] = null;
if ($scenario === 'missing-contact') $adapter->rows['process'][0]['contactid'] = 99999;
if ($scenario === 'foreign-contact') $adapter->rows['contact'] = [$adapter->rows['contact'][1]];
if ($scenario === 'deleted-contact') $adapter->rows['contact'][0]['deleted'] = 1;
if ($scenario === 'no-contact-rights') $adapter->rows['permission'][0]['contacts'] = json_encode(['contact' => []]);
if ($scenario === 'untitled') $adapter->rows['process'][0]['title'] = null;
if ($scenario === 'deleted-process') $adapter->rows['process'][0]['deleted'] = 1;
if ($scenario === 'child-owner') {
	$client['id'] = 71; $client['modules']['processes'] = 70; $client['modules']['contacts'] = 70;
	Zend_Registry::set('Client', $client);
	$user['clientid'] = 71; Zend_Registry::set('User', $user); Zend_Auth::getInstance()->getStorage()->write((object)$user);
	$adapter->rows['process'][0]['clientid'] = 71;
	foreach ([0, 1, 2] as $key) $adapter->rows['processpos'][$key]['clientid'] = 71;
	$adapter->rows['processpos'][] = array_replace($position, ['id' => 204, 'clientid' => 70, 'title' => 'OTHER OWNER POSITION']);
}
$processViewBefore = hash('sha256', serialize($adapter->rows));
$processViewProbe = new class($adapter, $processViewBefore) extends Zend_Controller_Plugin_Abstract {
	public function __construct(private ButtonPageReadAdapter $db, private string $before) {}
	public function postDispatch(Zend_Controller_Request_Abstract $request) {
		if ($request->getActionName() !== 'view') return;
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view;
		$probe = [
			'unchanged' => $this->before === hash('sha256', serialize($this->db->rows)),
			'mode' => $view->form->getMode(),
			'positions' => $view->positions,
			'contact' => $view->contact,
			'activeTab' => $view->activeTab,
			'fileWrites' => (new Zend_Session_Namespace('FileManager'))->writable,
			'positionQueries' => array_values(array_filter($this->db->queries, static fn($sql) => preg_match('/FROM `processpos`/', $sql))),
			'contactQueries' => array_values(array_filter($this->db->queries, static fn($sql) => preg_match('/FROM `contact`/', $sql) && str_contains($sql, 'contactid ='))),
		];
		$this->getResponse()->setHeader('X-Process-View-Probe', base64_encode(json_encode($probe)), true);
	}
};
Zend_Controller_Front::getInstance()->registerPlugin($processViewProbe);
