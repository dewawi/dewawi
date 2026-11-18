<?php

// Reuse only the guarded disposable fixture and mocked transport from the main suite.
if (!isset($work, $db, $target) || !preg_match('~^/tmp/dewawi-contact-map-[a-zA-Z0-9_-]+$~D', $work)) exit(1);
$deferralChecks = $checks;
externalFixture(); [$deferTool, $deferDir] = externalTool(); $deferProvider = externalProvider($deferDir); $deferProvider['batch_size'] = 1;
$deferExport = $deferTool->export($target); $deferHash = $deferExport['export_sha256'];
$initialEntries = $deferTool->entries();
externalCheck($initialEntries['counts'] === ['processed' => 0, 'open' => 5, 'deferred' => 0, 'rejected' => 0] && $initialEntries['queries']['open'] === 4 && $initialEntries['not_submitted_queries'] === 4, 'Unsubmitted status/deduplication counts incorrect');
externalCheck(array_column($initialEntries['entries'], 'entry') === [1, 2, 3, 4, 5] && array_column($initialEntries['entries'], 'contact_id') === [70008, 70006, 70005, 70002, 70001] && $initialEntries['entries'][3]['duplicate_count'] === 2 && !str_contains(json_encode($initialEntries), 'Synthetic'), 'Entry numbers do not match immutable export order or expose postal text');
$deferTool->behavior = 'immediate'; $deferTool->geocode($deferProvider, $deferHash);
$beforeDeferPlan = $deferTool->plan('plan-before-defer.json', $target);
$deferTool->clock += 60; $deferTool->behavior = 'pending'; $deferTool->geocode($deferProvider, $deferHash);
$preserved = [];
foreach (['export.json', 'jobs.json', 'results-0.json', 'plan-before-defer.json'] as $name) $preserved[$name] = file_get_contents($deferDir.'/'.$name);
$deferQuotaBefore = file_get_contents($deferProvider['quota_dir'].'/quota.json'); $deferDbBefore = $db->fetchAll('SELECT * FROM address ORDER BY id'); $callsBefore = count($deferTool->calls);
externalReject(fn() => $deferTool->defer(1, $deferHash, 'tester', 'postal_review', 'TEST-001'));
externalReject(fn() => $deferTool->defer(2, $deferHash, 'tester', 'postal_review', 'TEST-002'));
$deferResult = $deferTool->defer(3, $deferHash, 'tester', 'incomplete_address', 'TEST-003');
externalCheck($deferResult['status'] === 'deferred' && $deferResult['affected_addresses'] === 1 && $deferResult['external_requests'] === 0 && $deferResult['database_writes'] === 0, 'Unsent record was not deferred locally');
externalReject(fn() => $deferTool->defer(4, $deferHash, 'tester', 'postal_review', 'TEST-DUPLICATE'));
externalCheck($deferTool->defer(4, $deferHash, 'tester', 'postal_review', 'TEST-DUPLICATE', true)['affected_addresses'] === 2, 'Duplicate query could still transmit a deferred address');
$deferJournal = $deferDir.'/deferrals.jsonl'; $deferBytes = file_get_contents($deferJournal);
externalCheck($deferTool->defer(5, $deferHash, 'other-operator', 'other_review', 'OTHER-REFERENCE')['status'] === 'already_deferred' && file_get_contents($deferJournal) === $deferBytes, 'Repeated deferral changed the original audit event');
foreach ([0, 6] as $entry) externalReject(fn() => $deferTool->defer($entry, $deferHash, 'tester', 'postal_review', 'TEST-INVALID'));
externalReject(fn() => $deferTool->defer(3, str_repeat('0', 64), 'tester', 'postal_review', 'TEST-INVALID'));
externalReject(fn() => $deferTool->defer(3, $deferHash, "tester\n", 'postal_review', 'TEST-INVALID'));
externalReject(fn() => $deferTool->defer(3, $deferHash, 'tester', 'invented_reason', 'TEST-INVALID'));
externalReject(fn() => $deferTool->defer(3, $deferHash, 'tester', 'postal_review', 'Postal address text'));
$deferStatus = $deferTool->status();
externalCheck($deferStatus['counts'] === ['processed' => 1, 'open' => 1, 'deferred' => 3, 'rejected' => 0] && $deferStatus['queries'] === ['processed' => 1, 'open' => 1, 'deferred' => 2, 'rejected' => 0] && $deferStatus['in_progress_queries'] === 1 && $deferStatus['not_submitted_queries'] === 0, 'In-progress, deferred and processed entries were mixed');
$deferredPlan = $deferTool->plan('plan-deferred.json', $target);
externalCheck($deferredPlan['counts'] === ['planned' => 1, 'conflicts' => 0, 'rejected' => 0, 'pending' => 1, 'deferred' => 3] && $deferredPlan['pending_queries'] === 1, 'Deferrals remain pending or appear as importable updates');
externalReject(fn() => $deferTool->import('plan-before-defer.json', $target, externalPermit($beforeDeferPlan['plan_sha256']), $beforeDeferPlan['plan_sha256']));
foreach ($preserved as $name => $bytes) externalCheck(file_get_contents($deferDir.'/'.$name) === $bytes, 'Deferral changed an export, result, job or old plan');
externalCheck($db->fetchAll('SELECT * FROM address ORDER BY id') === $deferDbBefore && count($deferTool->calls) === $callsBefore && file_get_contents($deferProvider['quota_dir'].'/quota.json') === $deferQuotaBefore, 'Deferral made SQL/API changes or reset quotas');
$events = array_map(static fn($line) => json_decode($line, true), file($deferJournal));
externalCheck(count($events) === 2 && $events[1]['data']['previous_sha256'] === $events[0]['sha256'] && $events[0]['data']['entry'] === 3 && $events[0]['data']['address_id'] === $initialEntries['entries'][2]['address_id'] && $events[0]['data']['operator'] === 'tester' && $events[0]['data']['reason'] === 'incomplete_address' && $events[0]['data']['reference'] === 'TEST-003' && (fileperms($deferJournal) & 0077) === 0 && !str_contains($deferBytes, 'Synthetic'), 'Private audit mapping, reason, operator or hash chain missing');

// Uncertain, active and terminal assignments can never be reclassified by deferral.
foreach (['submitting', 'unknown', 'pending', 'done', 'expired', 'invalid_response', 'abandoned'] as $jobStatus) {
	$state = json_decode($preserved['jobs.json'], true); $state['jobs'][1]['status'] = $jobStatus; $state['jobs'][1]['result_sha256'] = str_repeat('a', 64);
	file_put_contents($deferDir.'/jobs.json', json_encode($state)); $jobBytes = file_get_contents($deferDir.'/jobs.json');
	externalReject(fn() => $deferTool->defer(2, $deferHash, 'tester', 'postal_review', 'TEST-ASSIGNED'));
	externalCheck(file_get_contents($deferDir.'/jobs.json') === $jobBytes && file_get_contents($deferJournal) === $deferBytes, 'Deferral changed an assigned provider job');
}
file_put_contents($deferDir.'/jobs.json', $preserved['jobs.json']);
$deferTool->clock += 300; $deferTool->geocode($deferProvider, $deferHash); $deferTool->clock += 60;
externalCheck($deferTool->geocode($deferProvider, $deferHash)['status'] === 'complete' && count($deferTool->calls) === 3, 'Deferred queries were submitted or blocked completion');
foreach ($deferTool->calls as [$method, $id, $body]) if ($method === 'POST') foreach ($body['inputs'] as $input) externalCheck(!in_array(OneTimeGeocoding::digest($input['params']), [$initialEntries['entries'][2]['query_key'], $initialEntries['entries'][3]['query_key']], true), 'A deferred postal query reached the provider');
$finishedStatus = $deferTool->status(); $finishedPlan = $deferTool->plan('plan-finished.json', $target);
externalCheck($finishedStatus['counts'] === ['processed' => 1, 'open' => 0, 'deferred' => 3, 'rejected' => 1] && $finishedPlan['counts'] === ['planned' => 1, 'conflicts' => 0, 'rejected' => 1, 'pending' => 0, 'deferred' => 3] && $finishedPlan['pending_queries'] === 0, 'Completed pilot retains deferred pending requests');

externalFixture(); [$allDeferred, $allDir] = externalTool(); $allProvider = externalProvider($allDir); $allHash = $allDeferred->export($target, 3)['export_sha256'];
foreach ([1, 2, 3] as $entry) $allDeferred->defer($entry, $allHash, 'tester', 'privacy_review', 'TEST-ALL');
externalCheck($allDeferred->geocode($allProvider, $allHash)['status'] === 'complete' && !$allDeferred->calls && !file_exists($allDir.'/jobs.json') && !file_exists($allProvider['quota_dir'].'/quota.json'), 'All-deferred run created jobs, charged credits or called HTTP');
$allPlan = $allDeferred->plan('plan.json', $target);
externalCheck($allPlan['counts']['deferred'] === 3 && $allPlan['counts']['planned'] === 0 && $allPlan['counts']['pending'] === 0 && $allPlan['pending_queries'] === 0, 'All-deferred plan cannot complete');
$allJournal = $allDir.'/deferrals.jsonl'; $allBytes = file_get_contents($allJournal);
$validEvent = json_decode(explode("\n", $allBytes)[0], true); $corruptions = ["invalid json\n", substr($allBytes, 0, -1), str_replace('privacy_review', 'postal_review', $allBytes), $allBytes.explode("\n", $allBytes)[0]."\n"];
foreach (['clientid' => 300, 'export_sha256' => str_repeat('0', 64), 'entry' => 999, 'affected_addresses' => 99] as $field => $value) { $bad = $validEvent; $bad['data'][$field] = $value; $bad['sha256'] = OneTimeGeocoding::digest($bad['data']); $corruptions[] = json_encode($bad)."\n"; }
foreach ($corruptions as $bytes) {
	file_put_contents($allJournal, $bytes);
	externalReject(fn() => $allDeferred->status()); externalReject(fn() => $allDeferred->geocode($allProvider, $allHash));
	externalCheck(!$allDeferred->calls && !file_exists($allProvider['quota_dir'].'/quota.json'), 'Corrupt policy failed open before HTTP');
}
file_put_contents($allJournal, $allBytes);

// A realistic 25-entry CLI pilot has one completed job; all deferrals remain offline.
externalFixture();
for ($n = 1; $n <= 20; $n++) {
	$db->insert('contact', ['id' => 71000 + $n, 'clientid' => 100, 'catid' => 0, 'created' => '2026-11-18']);
	$db->insert('address', ['parentid' => 71000 + $n, 'clientid' => 100, 'module' => 'contacts', 'controller' => 'contact', 'type' => 'billing', 'street' => 'Synthetic Street '.(100 + $n), 'postcode' => '12345', 'city' => 'Example City', 'country' => 'DE']);
}
[$pilot, $pilotDir] = externalTool(); $pilotProvider = externalProvider($pilotDir); $pilotProvider['batch_size'] = 1; $pilot->behavior = 'immediate';
$pilotHash = $pilot->export($target)['export_sha256']; $pilot->geocode($pilotProvider, $pilotHash); unset($pilot);
$pilotFiles = []; foreach (['export.json', 'jobs.json', 'results-0.json'] as $name) $pilotFiles[$name] = file_get_contents($pilotDir.'/'.$name);
$pilotQuota = file_get_contents($pilotProvider['quota_dir'].'/quota.json'); $pilotDb = $db->fetchAll('SELECT * FROM address ORDER BY id');
$pilotArgs = ['--client=100', '--work='.$pilotDir];
[$exit, $output, $errors] = externalCli(array_merge($pilotArgs, ['--mode=entries'])); $pilotEntries = json_decode($output, true);
externalCheck($exit === 0 && $errors === '' && count($pilotEntries['entries']) === 25 && $pilotEntries['entries'][0]['status'] === 'processed' && $pilotEntries['entries'][6]['entry'] === 7 && !str_contains($output, 'Synthetic'), '25-entry CLI mapping is incomplete, unsafe or out of order');
$deferArgs = array_merge($pilotArgs, ['--mode=defer', '--execute='.$pilotHash, '--operator=test-operator', '--reason=postal_review', '--reference=TEST-PILOT']);
foreach ([7, 8] as $entry) {
	[$exit, $output, $errors] = externalCli(array_merge($deferArgs, ['--entry='.$entry]));
	externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['affected_addresses'] === 1, 'Offline CLI deferral failed');
}
[$exit, $output, $errors] = externalCli(array_merge($deferArgs, ['--entry=24'])); externalCheck($exit === 1 && $output === '', 'CLI silently deferred a duplicate group');
[$exit, $output, $errors] = externalCli(array_merge($deferArgs, ['--entry=24', '--include-duplicates=true'])); externalCheck($exit === 0 && json_decode($output, true)['affected_addresses'] === 2, 'Explicit CLI duplicate deferral failed');
$pilotJournal = file_get_contents($pilotDir.'/deferrals.jsonl');
[$exit, $output, $errors] = externalCli(array_merge($deferArgs, ['--entry=7'])); externalCheck($exit === 0 && json_decode($output, true)['status'] === 'already_deferred' && file_get_contents($pilotDir.'/deferrals.jsonl') === $pilotJournal, 'Repeated CLI deferral changed its audit history');
foreach ([['--entry=1'], ['--entry=0'], ['--entry=26'], ['--entry=7 OR 1=1'], ['--entry=9', '--include-duplicates=false'], ['--entry=9', '--config='.$work.'/readonly.ini']] as $invalid) {
	[$exit, $output, $errors] = externalCli(array_merge($deferArgs, $invalid)); externalCheck($exit === 1 && $output === '' && !str_contains($errors, $work), 'Invalid CLI scope/entry exposed data or changed the pilot');
}
[$exit, $output, $errors] = externalCli(array_merge(array_replace($deferArgs, [0 => '--client=300']), ['--entry=9'])); externalCheck($exit === 1 && $output === '', 'Foreign tenant deferred client 100 entries');
$pilotLock = new OneTimeGeocoding($pilotDir);
[$exit, $output, $errors] = externalCli(array_merge($deferArgs, ['--entry=9'])); externalCheck($exit === 1 && $output === '' && file_get_contents($pilotDir.'/deferrals.jsonl') === $pilotJournal, 'Concurrent process bypassed the workspace lock'); unset($pilotLock);
[$exit, $output, $errors] = externalCli(array_merge($pilotArgs, ['--mode=status'])); $pilotStatus = json_decode($output, true);
externalCheck($exit === 0 && $errors === '' && $pilotStatus['counts'] === ['processed' => 1, 'open' => 20, 'deferred' => 4, 'rejected' => 0], 'CLI overview does not reconcile all 25 entries');
foreach ($pilotFiles as $name => $bytes) externalCheck(file_get_contents($pilotDir.'/'.$name) === $bytes, 'CLI deferral modified an export/result/completed job');
externalCheck(file_get_contents($pilotProvider['quota_dir'].'/quota.json') === $pilotQuota && $db->fetchAll('SELECT * FROM address ORDER BY id') === $pilotDb, 'Offline CLI reset credits or wrote SQL');

// Legacy plans remain importable when no deferral policy exists.
[$legacyTool, $legacyDir] = externalTool(); $legacyProvider = externalProvider($legacyDir); $legacyTool->behavior = 'immediate'; $legacyHash = $legacyTool->export($target, 1)['export_sha256'];
$legacyTool->geocode($legacyProvider, $legacyHash); $legacyTool->plan('plan.json', $target);
$legacyFile = json_decode(file_get_contents($legacyDir.'/plan.json'), true); unset($legacyFile['data']['counts']['deferred']); $legacyFile['sha256'] = OneTimeGeocoding::digest($legacyFile['data']); file_put_contents($legacyDir.'/plan-legacy.json', json_encode($legacyFile)); chmod($legacyDir.'/plan-legacy.json', 0600);
externalCheck($legacyFile['data']['results_sha256'] === OneTimeGeocoding::digest(json_decode(file_get_contents($legacyDir.'/jobs.json'), true)), 'No-deferral result hash changed');
externalCheck($legacyTool->import('plan-legacy.json', $target, externalPermit($legacyFile['sha256']), $legacyFile['sha256'])['database_writes'] === 1, 'Legacy plan/release cannot resume without deferrals');
echo 'PASS: '.($checks - $deferralChecks)." deferral audit, unsent-only selection, duplicate safety, immutable pilot, status, CLI and legacy checks\n";
