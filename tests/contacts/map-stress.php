<?php

require __DIR__.'/map-bootstrap.php';
$db->beginTransaction();
for ($index = 0; $index < 5000; $index++) {
	$id = 10000 + $index;
	$db->insert('contact', ['id' => $id, 'clientid' => 1, 'catid' => 0, 'contactid' => $id, 'name1' => 'Stress '.$id]);
	$db->insert('address', ['parentid' => $id, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'latitude' => -80 + $index % 160, 'longitude' => -170 + $index % 340]);
}
$db->commit();
$model = new Contacts_Model_Map();
$options = mapOptions();
$start = microtime(true);
$summary = $model->summary(Contacts_Model_Map::FILTERS, $options);
$markers = $model->markers(Contacts_Model_Map::FILTERS, $options, ['south' => -90, 'north' => 90, 'west' => -180, 'east' => 180, 'zoom' => 19]);
if ($summary['total'] !== 5248 || $markers['visible'] !== 5243 || count($markers['groups']) > 1400 || strlen(json_encode($markers)) > 500000) throw new RuntimeException('Large map response was truncated or unbounded');
class MapStressQuery extends Contacts_Model_Map { public function query(array $params, array $options): Zend_Db_Select { return $this->locations($params, $options); } }
$plans = [];
foreach (['', 'Stress'] as $keyword) $plans[$keyword ?: 'all'] = $db->fetchAll('EXPLAIN '.(new MapStressQuery())->query(array_replace(Contacts_Model_Map::FILTERS, ['keyword' => $keyword]), $options));
file_put_contents($work.'/map-explain.json', json_encode($plans, JSON_PRETTY_PRINT));
echo 'PASS: 5248 contacts, '.count($markers['groups']).' groups, '.strlen(json_encode($markers)).' response bytes, '.round(microtime(true) - $start, 3)." seconds including EXPLAIN\n";
