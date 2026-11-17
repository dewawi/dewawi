<?php

require __DIR__.'/map-bootstrap.php';
$pdo = $db->getConnection();
$schema = file_get_contents($root.'/install/config/structure.sql');
foreach (['contact', 'address', 'category', 'contactrole', 'tag', 'tagentity', 'phone', 'email', 'internet', 'permission', 'country', 'bankaccount', 'contactperson', 'comment'] as $table) {
	preg_match('/CREATE TABLE IF NOT EXISTS `'.$table.'` .*?;/s', $schema, $match);
	$pdo->exec($match[0]);
}
foreach ([1, 3] as $owner) foreach (['DE', 'AT', 'US'] as $code) $db->insert('country', ['clientid' => $owner, 'code' => $code, 'name' => $code, 'language' => 'de']);
foreach ([1, 2, 3, 4] as $id) $db->insert('permission', ['userid' => $id, 'clientid' => $id, 'contacts' => json_encode(['contact' => $id === 4 ? [] : ['view']])]);
foreach ([10 => 0, 11 => 10, 12 => 10, 20 => 0] as $id => $parent) $db->insert('category', ['id' => $id, 'parentid' => $parent, 'shopid' => 0, 'clientid' => 1, 'type' => 'contact', 'title' => 'Category '.$id, 'activated' => 1]);
$db->insert('category', ['id' => 99, 'parentid' => 0, 'shopid' => 0, 'clientid' => 3, 'type' => 'contact', 'title' => 'Foreign category', 'activated' => 1]);
foreach ([1 => 1, 2 => 1, 99 => 3] as $id => $client) $db->insert('tag', ['id' => $id, 'clientid' => $client, 'module' => 'contacts', 'controller' => 'contact', 'title' => 'Tag '.$id]);
$xss = '<img src=x onerror="window.mapXss=1">';
for ($id = 1; $id <= 245; $id++) {
	$even = $id % 2 === 0;
	$db->insert('contact', ['id' => $id, 'clientid' => 1, 'catid' => $id <= 240 ? ($even ? 11 : 12) : 0, 'name1' => $id === 1 ? $xss : 'Synthetic Contact '.$id, 'contactid' => 1000 + $id, 'taxnumber' => 'PRIVATE_TAX', 'notes' => 'PRIVATE_NOTES']);
	if ($id === 245) continue;
	$lat = $id <= 120 ? 51.2 : 51.3 + ($id % 20) / 1000;
	$lon = $id <= 120 ? 6.7 : 6.8 + floor($id / 20) / 1000;
	if ($id === 241) $lat = $lon = null;
	if ($id === 242) $lon = null;
	if ($id === 243) $lat = 91;
	if ($id === 244) $lon = 181;
	$db->insert('address', ['parentid' => $id, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'ordering' => 5, 'street' => 'PRIVATE_STREET', 'postcode' => '12345', 'city' => 'Synthetic City', 'country' => 'DE', 'latitude' => $lat, 'longitude' => $lon]);
	$db->insert('contactrole', ['parentid' => $id, 'clientid' => 1, 'role' => $even ? 'customer' : 'partner']);
	$db->insert('tagentity', ['entityid' => $id, 'tagid' => $even ? 1 : 2, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact']);
}
foreach ([
	[0, 1, 0, 'billing', 52, 13, 'AT', 'Chosen Berlin'],
	[0, 1, 0, 'billing', 40, -100, 'US', 'Later equal ordering'],
	[-1, 1, 1, 'billing', 30, -90, 'US', 'Deleted address'],
	[-1, 3, 0, 'billing', 20, -80, 'US', 'Foreign address'],
	[-1, 1, 0, 'shipping', 10, -70, 'US', 'Shipping address'],
] as [$ordering, $client, $deleted, $type, $lat, $lon, $country, $city]) $db->insert('address', ['parentid' => 1, 'clientid' => $client, 'deleted' => $deleted, 'ordering' => $ordering, 'module' => 'contacts', 'controller' => 'contact', 'type' => $type, 'latitude' => $lat, 'longitude' => $lon, 'country' => $country, 'city' => $city, 'postcode' => '99999']);
$db->insert('contact', ['id' => 3000, 'clientid' => 3, 'catid' => 99, 'name1' => 'FOREIGN_PRIVATE', 'contactid' => 3000]);
$db->insert('address', ['parentid' => 3000, 'clientid' => 3, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'latitude' => 50, 'longitude' => 10, 'country' => 'DE']);
$db->insert('contact', ['id' => 3001, 'clientid' => 1, 'catid' => 0, 'name1' => 'DELETED_PRIVATE', 'contactid' => 3001, 'deleted' => 1]);
$db->insert('contactrole', ['parentid' => 1, 'clientid' => 1, 'role' => 'supplier']);
$db->insert('contactrole', ['parentid' => 1, 'clientid' => 1, 'role' => 'prospect']);
$db->insert('phone', ['parentid' => 1, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'phone' => '123456789']);

$checks = 0;
function check(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function rejects(callable $callback, int $code = 0): void {
	try { $callback(); } catch (InvalidArgumentException | RuntimeException $e) { check($code === 0 ? $e instanceof InvalidArgumentException : $e->getCode() === $code, 'Unexpected failure: '.$e); return; }
	throw new RuntimeException('Invalid/unauthorized map request accepted');
}
$model = new Contacts_Model_Map();
$options = mapOptions();
$db->query("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
$filters = Contacts_Model_Map::FILTERS;
$world = ['south' => -90, 'north' => 90, 'west' => -180, 'east' => 180, 'zoom' => 5];
$before = $db->fetchAll('SELECT * FROM address ORDER BY id');
$summary = $model->summary($filters, $options);
check($summary['total'] === 245 && $summary['positioned'] === 240 && $summary['missing'] === 3 && $summary['invalid'] === 2, 'Incorrect complete contact/coordinate counts');
$groups = $model->markers($filters + ['limit' => 25, 'page' => 50], $options, $world);
check($groups['visible'] === 240 && array_sum(array_column($groups['groups'], 'count')) === 240, 'Map was paginated or lost contacts');
check(!str_contains(json_encode($groups), 'PRIVATE_'), 'Map projection exposed private contact/address fields');
foreach ([
	[['catid' => '10'], 240], [['catid' => '11'], 120], [['catid' => '12'], 120], [['catid' => '0'], 5],
	[['role' => 'partner'], 122], [['role' => 'supplier'], 1], [['role' => 'prospect'], 1],
	[['role' => 'partner', 'catid' => '10', 'tagid' => '2', 'country' => 'DE', 'keyword' => 'Synthetic City'], 119],
	[['keyword' => 'Chosen Berlin'], 1], [['keyword' => '99999'], 1], [['keyword' => '123456789'], 1],
	[['country' => 'AT', 'tagid' => '2', 'role' => 'partner'], 1], [['country' => 'US'], 0],
] as [$changes, $count]) check($model->summary(array_replace($filters, $changes), $options)['total'] === $count, 'Filter mismatch: '.json_encode($changes));
$single = $model->contacts(array_replace($filters, ['keyword' => 'Chosen Berlin']), $options, $world)['contacts'];
check(count($single) === 1 && (float)$single[0]['latitude'] === 52.0 && (float)$single[0]['longitude'] === 13.0, 'Billing address ordering/type/deletion/tenant selection differs');
$site = ['south' => 51.2, 'north' => 51.2, 'west' => 6.7, 'east' => 6.7, 'zoom' => 19];
$siteGroups = $model->markers($filters, $options, $site);
check($siteGroups['visible'] === 119 && count($siteGroups['groups']) === 1, 'Identical coordinates were lost');
$ids = []; $cursor = 0;
do {
	$page = $model->contacts($filters, $options, $site + ['after' => $cursor]);
	$ids = array_merge($ids, array_column($page['contacts'], 'id'));
	$cursor = $page['after'];
} while ($cursor !== null);
check(count($ids) === 119 && count(array_unique($ids)) === 119, 'Co-located contacts cannot all be paged independently');
check($db->fetchAll('SELECT * FROM address ORDER BY id') === $before, 'Map reads modified address/geocoding data');
foreach ([['role' => 'fachpartner'], ['catid' => '99'], ['catid' => '999999'], ['tagid' => '99'], ['country' => 'XX'], ['keyword' => []], ['role' => []], ['keyword' => str_repeat('a', 201)], ['keyword' => "\xFF"]] as $invalid) rejects(fn() => $model->summary(array_replace($filters, $invalid), $options));
foreach ([['north' => 91], ['south' => 70, 'north' => 60], ['west' => 181], ['east' => []], ['north' => 'NaN'], ['zoom' => -1], ['zoom' => 20], ['zoom' => []]] as $invalid) rejects(fn() => $model->markers($filters, $options, array_replace($world, $invalid)));
rejects(fn() => $model->contacts($filters, $options, $world + ['after' => '1 OR 1=1']));
check($model->summary($filters + ['clientid' => 3], $options)['total'] === 245, 'Submitted tenant override was used');
mapActor(2);
check($model->summary($filters, mapOptions()) === $summary, 'Authorized parent-child contact sharing changed');
mapActor(3);
check($model->summary($filters, mapOptions())['total'] === 1, 'Foreign tenant received parent contacts');
mapActor(1, false);
rejects(fn() => $model->summary($filters, $options), 403);
mapActor(1, true, true);
rejects(fn() => $model->summary($filters, $options), 403);
mapActor();
foreach ([[0, 0], [90, 180], [-90, -180]] as $offset => [$lat, $lon]) {
	$id = 4000 + $offset;
	$db->insert('contact', ['id' => $id, 'contactid' => $id, 'clientid' => 1, 'catid' => 0, 'name1' => 'Boundary '.$id]);
	$db->insert('address', ['parentid' => $id, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'latitude' => $lat, 'longitude' => $lon]);
}
check($model->summary($filters, $options)['positioned'] === 243, 'Zero/polar/boundary coordinates were rejected');
check($model->markers($filters, $options, ['south' => -90, 'north' => 90, 'west' => 170, 'east' => -170, 'zoom' => 3])['visible'] === 2, 'Antimeridian viewport omitted valid contacts');

class MapExplainQuery extends Contacts_Model_Map { public function query(array $params, array $options): Zend_Db_Select { return $this->locations($params, $options); } }
$explain = $db->fetchAll('EXPLAIN '.(new MapExplainQuery())->query($filters, $options));
file_put_contents($work.'/map-explain.json', json_encode($explain, JSON_PRETTY_PRINT));
echo 'PASS: '.$checks." map database, filtering, pagination, privacy and authorization checks\n";
