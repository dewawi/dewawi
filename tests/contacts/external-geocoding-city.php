<?php

// This file runs only inside the guarded disposable SQL/mocked HTTP suite.
if (!isset($work, $db, $target) || !preg_match('~^/tmp/dewawi-contact-map-[a-zA-Z0-9_-]+$~D', $work)) exit(1);
$cityChecks = $checks;
$cityFields = ['street' => 'synthetic street 1', 'postcode' => '12345', 'city' => 'example city', 'country' => 'de'];
$precise = externalFeature(); $cityFeature = externalFeature('unmatched road', '999');
$cityFeature['properties']['rank'] = ['match_type' => 'match_by_street', 'confidence' => .2, 'confidence_city_level' => 1, 'confidence_street_level' => .1, 'confidence_building_level' => 0];
$cityFeature['properties']['result_type'] = 'street';
$cityResult = static fn(array $features) => ['type' => 'FeatureCollection', 'features' => $features];
$cityValidate = static fn(array $features) => OneTimeGeocoding::validate($cityResult($features), $cityFields, 'city');
externalCheck($cityValidate([$precise])['accuracy'] === 'building' && $cityValidate([$precise])['decision'] === 'exact_building', 'City mode lost exact building preference');
$cityPoint = $cityValidate([$cityFeature]);
externalCheck($cityPoint['accuracy'] === 'city' && $cityPoint['decision'] === 'city_postcode_consensus' && $cityPoint['evidence']['minimum_city_confidence'] === '1.0000' && isset(OneTimeGeocoding::validate($cityResult([$cityFeature]), $cityFields)['rejected']), 'Wrong street was not confined to explicit city mode');
externalCheck($cityValidate([$cityFeature, $precise])['accuracy'] === 'building', 'Compatible candidates hid an exact building');
foreach (['street', 'housenumber'] as $field) { $candidate = $cityFeature; unset($candidate['properties'][$field]); externalCheck($cityValidate([$candidate])['accuracy'] === 'city', 'Missing street/building blocked a reliable city'); }
foreach (['country_code' => 'at', 'city' => 'Other City', 'postcode' => '99999'] as $field => $value) {
	$wrong = $cityFeature; $wrong['properties'][$field] = $value;
	externalCheck(isset($cityValidate([$wrong])['rejected']), 'Wrong country/city/postcode accepted');
	foreach ([[$wrong, $precise], [$precise, $wrong]] as $features) externalCheck(isset($cityValidate($features)['rejected']), 'Precise result overrode conflicting geographic identity');
}
foreach (['country_code', 'city', 'postcode'] as $field) {
	foreach ([null, '', "bad\nvalue", false] as $value) { $bad = $cityFeature; $bad['properties'][$field] = $value; externalCheck(isset($cityValidate([$bad])['rejected']), 'Missing/invalid city identity accepted'); }
}
foreach ([null, .949, 1.01, true, 'NaN', INF] as $confidence) {
	$bad = $cityFeature; $bad['properties']['rank']['confidence_city_level'] = $confidence; externalCheck(isset($cityValidate([$bad])['rejected']), 'Missing/invalid city confidence accepted');
}
foreach (['country', 'state', 'county', 'unknown'] as $type) { $bad = $cityFeature; $bad['properties']['result_type'] = $type; externalCheck(isset($cityValidate([$bad])['rejected']), 'Coarse geographic type became a city'); }
$bad = $cityFeature; $bad['properties']['rank']['match_type'] = 'match_by_country_or_state'; externalCheck(isset($cityValidate([$bad])['rejected']), 'Country-only match became a city');
foreach ([['lat', 91], ['lon', -181], ['lat', 'NaN'], ['lat', true]] as [$field, $value]) { $bad = $cityFeature; $bad['properties'][$field] = $value; externalCheck(isset($cityValidate([$bad])['rejected']), 'Invalid city coordinate accepted'); }
$bad = $cityFeature; $bad['geometry']['coordinates'] = [9, 52]; externalCheck($cityValidate([$bad]) === ['rejected' => 'geometry_mismatch'], 'Inconsistent Point geometry accepted');
$bad = $cityFeature; $bad['properties']['lat'] = $bad['properties']['lon'] = 0; $bad['geometry']['coordinates'] = [0, 0]; externalCheck(isset($cityValidate([$bad])['rejected']), 'Null island accepted');
$bad = $cityFeature; $bad['bbox'] = [6.8, 51, 7, 52]; externalCheck($cityValidate([$bad]) === ['rejected' => 'invalid_bbox'], 'Point outside bbox accepted');
$bad['bbox'] = [-180, -90, 180, 90]; externalCheck(isset($cityValidate([$bad])['rejected']), 'Country-sized city bbox accepted');
$withBox = $cityFeature; $withBox['bbox'] = [6.5, 51, 7, 51.5]; externalCheck($cityValidate([$withBox])['accuracy'] === 'city', 'Plausible city bbox rejected');
$remoteCity = $cityFeature; $remoteCity['properties']['lat'] = 53.55; $remoteCity['properties']['lon'] = 10; $remoteCity['geometry']['coordinates'] = [10, 53.55];
foreach ([[$remoteCity, $cityFeature], [$cityFeature, $remoteCity]] as $features) externalCheck($cityValidate($features) === ['rejected' => 'geographic_conflict'], 'Distant namesakes resolved by result order');
foreach (['state', 'state_code', 'county'] as $field) {
	$a = $cityFeature; $b = $cityFeature; $a['properties'][$field] = 'Region A'; $b['properties'][$field] = 'Region B';
	externalCheck($cityValidate([$a, $b]) === ['rejected' => 'region_conflict'], 'Contradictory region accepted');
}
$regional = $cityFeature; $regional['properties']['state'] = 'Region A';
$regional['properties']['state_code'] = 'DE-XX'; $regional['properties']['county'] = 'County A';
externalCheck($cityValidate([$regional])['accuracy'] === 'city', 'Consistent region metadata blocked a reliable city');
$nearby = [];
foreach ([6.7, 6.71, 6.72] as $lon) { $near = $cityFeature; $near['properties']['lon'] = $lon; $near['geometry']['coordinates'][0] = $lon; $nearby[] = $near; }
$consensus = $cityValidate($nearby);
externalCheck($consensus['point']['longitude'] === '6.710000000' && $consensus['accuracy'] === 'city', 'City consensus did not select a real central candidate');
foreach ([array_reverse($nearby), [$nearby[1], $nearby[0], $nearby[2]]] as $features) externalCheck($cityValidate($features) === $consensus, 'City decision depends on provider order');
$nearby[0]['properties']['result_type'] = 'city';
externalCheck($cityValidate($nearby)['point']['longitude'] === '6.700000000', 'Explicit city position was not preferred');
foreach ([[], [null], [$cityFeature, []], array_fill(0, 4, $cityFeature)] as $features) externalCheck(isset($cityValidate($features)['rejected']), 'Malformed/unsupported response accepted');
externalReject(fn() => OneTimeGeocoding::validate($cityResult([$precise]), $cityFields, 'automatic'));

// Reassess existing results offline, preserve real legacy import files and protect imported points.
$cityIds = externalFixture(); [$cityTool, $cityDir] = externalTool(); $cityProvider = externalProvider($cityDir); $cityProvider['batch_size'] = 1000; $cityTool->behavior = 'immediate';
$cityHash = $cityTool->export($target)['export_sha256']; $cityTool->defer(3, $cityHash, 'tester', 'postal_review', 'CITY-DEFERRAL');
$cityTool->geocode($cityProvider, $cityHash); $strictPlan = $cityTool->plan('plan-strict.json', $target);
externalCheck($strictPlan['counts']['planned'] === 3 && $strictPlan['counts']['rejected'] === 1, 'Strict legacy eligibility changed');
$strictImport = $cityTool->import('plan-strict.json', $target, externalPermit($strictPlan['plan_sha256']), $strictPlan['plan_sha256'], 1);
externalCheck($strictImport['database_writes'] === 1 && $strictImport['remaining'] === 2, 'Legacy partial import fixture failed');
$immutable = [];
foreach (['export.json', 'jobs.json', 'results-0.json', 'deferrals.jsonl', 'plan-strict.json', 'backup-'.$strictPlan['plan_sha256'].'.json', 'import-'.$strictPlan['plan_sha256'].'.jsonl'] as $name) $immutable[$name] = file_get_contents($cityDir.'/'.$name);
$oldDb = $db->fetchAll('SELECT * FROM address ORDER BY id'); $oldCalls = count($cityTool->calls); $oldQuota = file_get_contents($cityProvider['quota_dir'].'/quota.json');
$cityPlan = $cityTool->plan('plan-city.json', $target, 'city');
externalCheck($cityPlan['counts'] === ['planned' => 3, 'conflicts' => 1, 'rejected' => 0, 'pending' => 0, 'deferred' => 1] && $cityPlan['accuracy_counts'] === ['building' => 2, 'city' => 1] && $cityPlan['accuracy_mode'] === 'city', 'Offline city reassessment ignored imports, deferrals or accuracy');
externalCheck(count($cityTool->calls) === $oldCalls && $oldDb === $db->fetchAll('SELECT * FROM address ORDER BY id') && $oldQuota === file_get_contents($cityProvider['quota_dir'].'/quota.json'), 'City planning used API/SQL writes or reset quota');
foreach ($immutable as $name => $bytes) externalCheck(file_get_contents($cityDir.'/'.$name) === $bytes, 'City reassessment rewrote a legacy file');
externalCheck($cityTool->status('strict')['counts']['rejected'] === 1 && $cityTool->status('city')['counts'] === ['processed' => 4, 'open' => 0, 'deferred' => 1, 'rejected' => 0], 'Status did not use explicitly selected accuracy');
unset($cityTool);
$cityCliArgs = ['--client=100', '--work='.$cityDir, '--mode=plan', '--config='.$work.'/readonly.ini', '--environment=synthetic', '--user=100', '--accuracy=city', '--plan=plan-city-cli.json'];
[$exit, $output, $errors] = externalCli($cityCliArgs); externalCheck($exit === 0 && $errors === '' && json_decode($output, true)['accuracy_counts'] === ['building' => 2, 'city' => 1], 'Offline city CLI plan failed');
[$cityTool] = externalTool($cityDir); $sealedCity = json_decode(file_get_contents($cityDir.'/plan-city.json'), true);
externalCheck($sealedCity['data']['version'] === 2 && $sealedCity['data']['validation_policy'] === 'city-postcode-v1' && $sealedCity['sha256'] !== $strictPlan['plan_sha256'], 'City mode is not bound into a separately released plan');
foreach (['policy', 'level', 'strict-downgrade', 'point'] as $attack) {
	$forgedCity = $sealedCity;
	if ($attack === 'policy') $forgedCity['data']['validation_policy'] = 'relaxed';
	if ($attack === 'level') $forgedCity['data']['updates'][0]['accuracy'] = 'country';
	if ($attack === 'strict-downgrade') { $forgedCity['data']['version'] = 1; unset($forgedCity['data']['accuracy_mode'], $forgedCity['data']['validation_policy']); }
	if ($attack === 'point') $forgedCity['data']['updates'][0]['point']['latitude'] = '53.000000000';
	$forgedCity['sha256'] = OneTimeGeocoding::digest($forgedCity['data']); file_put_contents($cityDir.'/plan-attack-'.$attack.'.json', json_encode($forgedCity)); chmod($cityDir.'/plan-attack-'.$attack.'.json', 0600);
	externalReject(fn() => $cityTool->import('plan-attack-'.$attack.'.json', $target, externalPermit($forgedCity['sha256']), $forgedCity['sha256']));
}
externalReject(fn() => $cityTool->import('plan-city.json', $target, externalPermit($strictPlan['plan_sha256']), $cityPlan['plan_sha256']));
mapActor(300); externalReject(fn() => $cityTool->plan('plan-foreign.json', $target, 'city')); externalReject(fn() => $cityTool->import('plan-city.json', $target, externalPermit($cityPlan['plan_sha256']), $cityPlan['plan_sha256'])); mapActor(100);
$cityImport = $cityTool->import('plan-city.json', $target, externalPermit($cityPlan['plan_sha256']), $cityPlan['plan_sha256'], 1);
externalCheck($cityImport['database_writes'] === 1 && $cityImport['remaining'] === 2, 'Bounded city import failed');
$cityJournal = $cityDir.'/import-'.$cityPlan['plan_sha256'].'.jsonl'; $journalBytes = file_get_contents($cityJournal);
$cityEvents = array_map(static fn($line) => json_decode($line, true), file($cityJournal));
externalCheck($cityEvents[0]['accuracy'] === 'city' && $cityEvents[0]['decision'] === 'city_postcode_consensus' && isset($cityEvents[0]['evidence']) && $cityEvents[1]['accuracy'] === 'city', 'Import intent/outcome omitted accuracy decision');
file_put_contents($cityJournal, str_replace('city_postcode_consensus', 'changed_decision', $journalBytes)); externalReject(fn() => $cityTool->import('plan-city.json', $target, externalPermit($cityPlan['plan_sha256']), $cityPlan['plan_sha256'])); file_put_contents($cityJournal, $journalBytes);
// Simulate a crash after SQL commit but before the outcome journal append.
file_put_contents($cityJournal, explode("\n", $journalBytes)[0]."\n");
$resumedCity = $cityTool->import('plan-city.json', $target, externalPermit($cityPlan['plan_sha256']), $cityPlan['plan_sha256']);
externalCheck($resumedCity['database_writes'] === 2 && $resumedCity['remaining'] === 0 && $resumedCity['counts']['recovered'] === 1, 'City import did not recover a committed write safely');
externalCheck($cityTool->import('plan-city.json', $target, externalPermit($cityPlan['plan_sha256']), $cityPlan['plan_sha256'])['counts']['already_logged'] === 3, 'Completed city import repeated SQL writes');
foreach ($oldDb as $row) {
	$after = $db->fetchRow('SELECT * FROM address WHERE id = ?', [$row['id']]);
	foreach ($row as $field => $value) if (!in_array($field, ['latitude', 'longitude', 'geoupdated'], true)) externalCheck($after[$field] === $value, 'City import changed postal/non-coordinate data');
	if ($row['latitude'] !== null && $row['longitude'] !== null) externalCheck($after === $row, 'Existing valid/imported coordinates were overwritten');
}
foreach ($immutable as $name => $bytes) externalCheck(file_get_contents($cityDir.'/'.$name) === $bytes, 'City import changed a strict plan/journal/export/backup');

// Scheduler ticks reuse the same durable jobs, locks, quota and uncertain-submission guards.
externalFixture(); [$runner, $runnerDir] = externalTool(); $runnerProvider = externalProvider($runnerDir); $runnerProvider['batch_size'] = 1000;
$runnerHash = $runner->export($target, 3)['export_sha256']; $runner->defer(3, $runnerHash, 'tester', 'privacy_review', 'RUN-DEFER');
$firstTick = $runner->run($runnerProvider, $runnerHash, 'city');
externalCheck($firstTick['status'] === 'pending' && $firstTick['external_requests'] === 1 && $firstTick['database_writes'] === 0 && $firstTick['counts']['deferred'] === 1 && $firstTick['next_at'] === $runner->clock + 300, 'Scheduler submission/status/deferral failed');
$firstJob = file_get_contents($runnerDir.'/jobs.json'); $firstQuota = file_get_contents($runnerProvider['quota_dir'].'/quota.json');
externalCheck($runner->run($runnerProvider, $runnerHash, 'city')['status'] === 'waiting' && count($runner->calls) === 1 && file_get_contents($runnerDir.'/jobs.json') === $firstJob && file_get_contents($runnerProvider['quota_dir'].'/quota.json') === $firstQuota, 'Repeated tick ignored provider delay or charged again');
[$exit, $output] = externalCli(['--client=100', '--work='.$runnerDir, '--mode=status', '--accuracy=city']); externalCheck($exit === 1 && $output === '', 'Concurrent scheduler bypassed workspace lock');
unset($runner); [$runner] = externalTool($runnerDir); $runner->clock += 300;
externalCheck($runner->run($runnerProvider, $runnerHash, 'city')['status'] === 'done' && $runner->calls[0][0] === 'GET', 'Restart submitted an existing job twice');
$runner->clock += 60; $completeTick = $runner->run($runnerProvider, $runnerHash, 'city');
externalCheck($completeTick['status'] === 'complete' && $completeTick['progress']['finished'] === 3 && $completeTick['progress']['percent'] === 100.0 && $completeTick['counts'] === ['processed' => 2, 'open' => 0, 'deferred' => 1, 'rejected' => 0] && count($runner->calls) === 1, 'Completed scheduler retried or lost progress');
externalCheck($runner->status('city')['automation']['status'] === 'complete' && !str_contains(file_get_contents($runnerDir.'/run.json'), 'Synthetic'), 'Private aggregate scheduler status missing or exposes postal text');
externalReject(fn() => $runner->run($runnerProvider, str_repeat('0', 64), 'city'));

// Resume an already downloaded legacy file without changing its digest when new region fields arrive.
class RegionalFixtureTool extends ExternalFixtureTool {
	protected function http(string $method, ?string $id, ?array $body, string $key): array {
		[$code, $response, $retry] = parent::http($method, $id, $body, $key);
		foreach ($response['results'] ?? [] as $index => $item) foreach ($item['result']['features'] ?? [] as $i => $feature) $response['results'][$index]['result']['features'][$i]['properties']['state'] = 'Synthetic Region';
		return [$code, $response, $retry];
	}
}
[$legacyDownload, $downloadDir] = externalTool(); $downloadProvider = externalProvider($downloadDir); $legacyDownload->behavior = 'immediate';
$downloadHash = $legacyDownload->export($target, 1)['export_sha256']; $legacyDownload->geocode($downloadProvider, $downloadHash);
$downloadBytes = file_get_contents($downloadDir.'/results-0.json'); $downloadJob = json_decode(file_get_contents($downloadDir.'/jobs.json'), true); $downloadJob['jobs'][0]['status'] = 'pending'; $downloadJob['jobs'][0]['next_at'] = 0;
file_put_contents($downloadDir.'/jobs.json', json_encode($downloadJob)); unset($legacyDownload);
$regionalDownload = new RegionalFixtureTool($downloadDir); $regionalDownload->clock += 300;
externalCheck($regionalDownload->run($downloadProvider, $downloadHash, 'city')['status'] === 'done' && file_get_contents($downloadDir.'/results-0.json') === $downloadBytes, 'Legacy downloaded result was rewritten or could not resume');
[$newRegions, $newRegionDir] = externalTool(); $newRegionProvider = externalProvider($newRegionDir); $newRegionHash = $newRegions->export($target, 1)['export_sha256']; unset($newRegions);
$newRegions = new RegionalFixtureTool($newRegionDir); $newRegions->behavior = 'immediate'; $newRegions->run($newRegionProvider, $newRegionHash, 'city');
$regionFile = json_decode(file_get_contents($newRegionDir.'/results-0.json'), true);
externalCheck(array_values($regionFile['data'])[0]['features'][0]['properties']['state'] === 'Synthetic Region', 'New provider results discarded region evidence');

[$unknownRun, $unknownRunDir] = externalTool(); $unknownProvider = externalProvider($unknownRunDir); $unknownHash = $unknownRun->export($target, 1)['export_sha256']; $unknownRun->behavior = 'timeout';
externalCheck($unknownRun->run($unknownProvider, $unknownHash, 'city')['status'] === 'unknown', 'Uncertain submission lost');
$unknownRun->clock += 86401; externalCheck($unknownRun->run($unknownProvider, $unknownHash, 'city')['status'] === 'submission_unknown' && count($unknownRun->calls) === 1, 'Scheduler automatically repeated uncertain submission');
[$budgetRun, $budgetDir] = externalTool(); $budgetProvider = externalProvider($budgetDir); $budgetProvider['daily_credits'] = 3; $budgetHash = $budgetRun->export($target)['export_sha256']; $budgetRun->behavior = 'immediate';
$budgetRun->run($budgetProvider, $budgetHash, 'city'); $budgetRun->clock += 60; $budgetWait = $budgetRun->run($budgetProvider, $budgetHash, 'city');
externalCheck($budgetWait['status'] === 'quota_wait' && $budgetWait['reason'] === 'rolling_daily_budget' && $budgetWait['next_at'] === 1800086400 && $budgetWait['counts']['open'] === 3 && count($budgetRun->calls) === 1, 'Scheduler budget status or exact retry time wrong');
$budgetRun->clock = $budgetWait['next_at']; externalCheck($budgetRun->run($budgetProvider, $budgetHash, 'city')['status'] === 'done' && count($budgetRun->calls) === 2, 'Scheduler did not resume at budget expiry');
$budgetRun->clock += 60; externalCheck($budgetRun->run($budgetProvider, $budgetHash, 'city')['status'] === 'complete', 'Budget resumed run never completed');
unset($budgetRun);
$disabledConfig = $budgetDir.'/provider.ini'; file_put_contents($disabledConfig, "[provider]\nenabled=false\n"); chmod($disabledConfig, 0600);
$runArgs = ['--client=100', '--work='.$budgetDir, '--mode=run', '--provider-config='.$disabledConfig, '--execute='.$budgetHash, '--accuracy=city'];
[$exit, $output] = externalCli($runArgs); externalCheck($exit === 1 && $output === '', 'CLI scheduler enabled HTTP without provider release');
foreach ([array_merge($runArgs, ['--config='.$work.'/readonly.ini']), array_replace($runArgs, [0 => '--client=300']), array_replace($runArgs, [5 => '--accuracy=country'])] as $args) { [$exit, $output] = externalCli($args); externalCheck($exit === 1 && $output === '', 'Scheduler accepted foreign scope, accuracy or SQL context'); }
echo 'PASS: '.($checks - $cityChecks)." city accuracy, conflicts, sealed plans, legacy files, audited import and resumable scheduler checks\n";
