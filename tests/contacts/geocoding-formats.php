<?php

// Reuse the disposable database, mocked transport and assertions from geocoding.php.
if (!isset($db, $work, $fixture) || !preg_match('~^/tmp/dewawi-geocoding-[^/]+$~D', $work)) exit(1);
$formatChecks = $checks;
$db->query("INSERT INTO country (clientid, code, name, language) VALUES (100, 'US', 'US', 'en'), (100, 'GB', 'GB', 'en'), (100, 'FR', 'FR', 'fr'), (100, 'NL', 'NL', 'nl'), (100, 'FI', 'FI', 'fi')");
$formats = [
	["Musterstraße\r\n12 a", 'musterstraße 12a', 'Musterstraße', '12 a', 'DE', '12345'],
	["Musterstraße\t12 A", 'musterstraße 12a', 'Musterstraße', '12a', 'DE', '12345'],
	['Musterstraße 12 a - 14 b', 'musterstraße 12a-14b', 'Musterstraße', '12a – 14b', 'DE', '12345'],
	['12A Beispielweg', '12a beispielweg', 'Beispielweg', '12 A', 'DE', '12345'],
	['123 Main Street', '123 main street', 'Main Street', '123', 'US', '02108'],
	['12–14 Test Road', '12-14 test road', 'Test Road', '12 - 14', 'GB', 'SW1A 1AA'],
	['12 Rue Exemple', '12 rue exemple', 'Rue Exemple', '12', 'FR', '75001'],
	['Voorbeeldstraat 12 / 1', 'voorbeeldstraat 12/1', 'Voorbeeldstraat', '12 / 1', 'NL', '1234 AB'],
	['Esimerkkitie 12 B', 'esimerkkitie 12b', 'Esimerkkitie', '12B', 'FI', '00100'],
	["Synthetic\u{00a0}Street\u{2009}1", 'synthetic street 1', 'Synthetic Street', '1', 'DE', '12345'],
	['Straße des 17. Juni 12 a', 'straße des 17. juni 12a', 'Straße des 17. Juni', '12a', 'DE', '12345'],
	['Alfa–Beta-Weg 12', 'alfa–beta-weg 12', 'Alfa–Beta-Weg', '12', 'DE', '12345'],
	['12 A Street', '12 a street', 'A Street', '12', 'US', '02108'],
];
foreach ($formats as [$street, $queryStreet, $road, $number, $country, $postcode]) {
	$id = seed(['street' => $street, 'country' => $country, 'postcode' => $postcode]);
	$before = stored($id); $service = new GeocodingTestAddress();
	$service->expectedStreet = $queryStreet; $service->expectedCountry = strtolower($country); $service->expectedPostcode = strtolower($postcode);
	$response = result(); $response['address'] = array_replace($response['address'], ['road' => $road, 'house_number' => $number, 'country_code' => strtolower($country), 'postcode' => str_replace(' ', '', $postcode)]);
	$service->response[0] = json_encode([$response]);
	check($service->inspectGeocoding($id)['status'] === 'needs_geocoding', 'Supported postal format was excluded');
	$outcome = attempt($service, $id);
	check($outcome['status'] === 'updated' && $service->requests === 1, 'Formatted fixture failed exact matching: '.$queryStreet.' / '.$outcome['status'].' / '.json_encode($service->lastQuery));
	$after = stored($id); foreach (['latitude', 'longitude', 'geoupdated'] as $field) unset($before[$field], $after[$field]);
	check($after === $before, 'Geocoding rewrote stored postal text');
	check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 1, 'Formatted address was unnecessarily queried again');
}

foreach (['street' => "Synthetic\r\nStreet\t1", 'city' => "Synthetic\r\nCity", 'postcode' => "\t12345\r\n", 'country' => "\tDE\n"] as $field => $value) {
	$id = seed([$field => $value]); $before = stored($id); $service = new GeocodingTestAddress();
	$explained = $service->explain($id);
	check($explained['newly_reviewable'] && in_array('line_feed_'.$field, $explained['format_details'], true), 'Harmless whitespace not classified by field');
	check(attempt($service, $id)['status'] === 'updated', 'Harmless postal whitespace remained unprocessable');
	check(stored($id)[$field] === $before[$field], 'Whitespace normalization changed the database address');
}
$id = seed(['street' => 'Synthetic Street 12–14']); $service = new GeocodingTestAddress(); $details = $service->explain($id);
check($details['newly_reviewable'] && in_array('house_number_range_dash', $details['format_details'], true), 'Newly supported number range was not classified');
$id = seed(['street' => "Synthetic\r\nStreet\t1", 'postcode' => '']); $service = new GeocodingTestAddress();
check($service->explain($id)['reasons'] === ['missing_postcode'] && !isset($service->explain($id)['fingerprint']), 'Whitespace normalization concealed an incomplete address');
foreach (array_merge(range(0, 8), [11, 12], range(14, 31), [127, 128, 133, 159]) as $code) {
	$id = seed(['street' => 'Synthetic'.mb_chr($code, 'UTF-8').'Street 1']); $service = new GeocodingTestAddress(); $before = stored($id);
	$review = $service->explain($id);
	check($review['reasons'] === ['control_characters_street'] && in_array('problematic_control_street', $review['format_details'], true), 'Problematic C0/C1 control was normalized away');
	check(attempt($service, $id)['status'] === 'public_review_required' && $service->requests === 0 && stored($id) === $before, 'Bad import data caused API traffic or writes');
}
foreach (['street', 'city', 'postcode', 'country'] as $field) {
	foreach (["\u{feff}", "\u{200b}", "\u{202e}"] as $character) {
		$defaults = ['street' => 'Synthetic Street 1', 'city' => 'Synthetic City', 'postcode' => '12345', 'country' => 'DE'];
		$id = seed([$field => $character.$defaults[$field]]); $service = new GeocodingTestAddress();
		check($service->explain($id)['reasons'] === ['format_characters_'.$field] && $service->requests === 0, 'Invisible/bidi import characters were silently removed');
	}
}
foreach (['Synthetic Street', 'Synthetic Street 12 bis', 'Synthetic Street 12½', '12/1/2 Synthetic Street', 'Synthetic Street 12,14'] as $street) {
	$id = seed(['street' => $street]); $service = new GeocodingTestAddress();
	check(!isset($service->inspectGeocoding($id)['fingerprint']) && $service->requests === 0, 'Uncertain house number was invented or discarded');
}
// Preserve all meaningful number tokens; never merge adjacent digits or choose a range endpoint.
foreach (['1 2', '12/1', '12b', '12-14', '12 bis', '12;', "1\v2"] as $number) {
	$id = seed(['street' => 'Synthetic Street 12']); $service = new GeocodingTestAddress(); $service->expectedStreet = 'synthetic street 12';
	$response = result(); $response['address']['house_number'] = $number; $service->response[0] = json_encode([$response]);
	check(attempt($service, $id)['status'] === 'review' && stored($id)['latitude'] === null, 'Different or ambiguous provider house number was accepted');
}
foreach (['Synthetic Street 12 & 14', 'Synthetic Street 12 14'] as $street) {
	$id = seed(['street' => $street]); $service = new GeocodingTestAddress(); $service->expectedStreet = strtolower($street);
	$response = result(); $response['address']['house_number'] = '14'; $service->response[0] = json_encode([$response]);
	check(attempt($service, $id)['status'] === 'review', 'Ambiguous tokens were removed from the query or result comparison');
}
$id = seed(['street' => 'Synthetic Street 12 a']); $service = new GeocodingTestAddress(); $service->expectedStreet = 'synthetic street 12a';
$response = result(); $response['address']['house_number'] = '12'; $service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'review', 'Letter suffix was silently dropped when matching a result');
$id = seed(['street' => 'Synthetic Street 12 - 14']); $service = new GeocodingTestAddress(); $service->expectedStreet = 'synthetic street 12-14';
$response = result(); $response['address']['house_number'] = '12'; $service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'review', 'A range endpoint was accepted as the full house number');
$id = seed(['street' => '12 A Street']); $service = new GeocodingTestAddress(); $service->expectedStreet = '12 a street';
$response = result(); $response['address']['road'] = 'Street'; $response['address']['house_number'] = '12a'; $service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'review', 'Ambiguous leading letter was moved from road to number');
foreach (['country_code' => 'at', 'road' => 'Wrong Street', 'postcode' => '99999', 'city' => 'Wrong City'] as $field => $value) {
	$id = seed(['street' => "Synthetic Street\n12 a"]); $service = new GeocodingTestAddress(); $service->expectedStreet = 'synthetic street 12a';
	$response = result(); $response['address']['house_number'] = '12a'; $response['address'][$field] = $value; $service->response[0] = json_encode([$response]);
	check(attempt($service, $id)['status'] === 'review' && stored($id)['latitude'] === null, 'Format normalization weakened another geographic match criterion');
}

function legacyFormatFingerprint(int $id, array $row, ?string &$key = null): string {
	$fields = [];
	foreach (['street', 'postcode', 'city', 'country'] as $field) $fields[$field] = mb_strtolower(trim(preg_replace('/[\s\p{Z}]+/u', ' ', Normalizer::normalize($row[$field], Normalizer::FORM_C))), 'UTF-8');
	$key = hash('sha256', json_encode([100, 'https://nominatim.openstreetmap.org/search', 1, $fields]));
	$snapshot = array_intersect_key($row, array_flip(['street', 'postcode', 'city', 'country', 'parentid', 'type', 'modified', 'geoupdated', 'latitude', 'longitude']));
	return hash('sha256', $id.':'.$key.':'.json_encode($snapshot));
}
$id = seed(); $service = new GeocodingTestAddress();
check($service->inspectGeocoding($id)['fingerprint'] === legacyFormatFingerprint($id, $service->getAddress($id)), 'Unchanged query invalidated an existing approval');
// This range already passed the old parser with an incorrect road/number split.
$id = seed(['street' => 'Synthetic Street 12 - 14']); $service = new GeocodingTestAddress();
$oldFingerprint = legacyFormatFingerprint($id, $service->getAddress($id)); $review = $service->inspectGeocoding($id);
check($review['fingerprint'] !== $oldFingerprint, 'Changed canonical query retained an old approval');
check($service->geocodeAddress($id, $oldFingerprint)['status'] === 'public_review_required' && $service->requests === 0, 'Old format approval authorized the new query');

// A new parser must not mistake its own canonicalization for a customer address edit.
$id = seed(['street' => 'Synthetic Street 12 - 14', 'latitude' => 51.2, 'longitude' => 6.7]); $service = new GeocodingTestAddress();
$before = stored($id); legacyFormatFingerprint($id, $before, $legacyKey);
$directory = options()['state_dir']; mkdir($directory, 0700);
$legacyPoint = ['latitude' => '51.200000000', 'longitude' => '6.700000000'];
file_put_contents($directory.'/manual.json', json_encode(['version' => 1, 'entries' => [$legacyKey => ['status' => 'success', 'point' => $legacyPoint]], 'addresses' => [(string)$id => ['key' => $legacyKey, 'point' => $legacyPoint]], 'next_request_at' => 0, 'requests_today' => 0]));
check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 0 && stored($id) === $before, 'Legacy tracked coordinates triggered a normalization-only refresh');
$db->query("UPDATE address SET street = 'Synthetic Street 13 - 15' WHERE id = $id");
check($service->inspectGeocoding($id)['status'] === 'postal_address_changed', 'Legacy compatibility hid a real number change');

$id = seed(['street' => 'Synthetic Street 12a']); $service = new GeocodingTestAddress(); $service->expectedStreet = 'synthetic street 12a';
$response = result(); $response['address']['house_number'] = '12a'; $service->response[0] = json_encode([$response]);
check(attempt($service, $id)['status'] === 'updated', 'Canonical cache donor failed');
$db->query("UPDATE address SET street = 'Synthetic Street 12 a' WHERE id = $id");
check(attempt($service, $id)['status'] === 'unchanged' && $service->requests === 1, 'Safe number formatting discarded tracked valid coordinates');
$db->query("UPDATE address SET latitude = NULL, longitude = NULL, geoupdated = NULL WHERE id = $id");
$oldFingerprint = $service->inspectGeocoding($id)['fingerprint'];
$db->query("UPDATE address SET street = 'Synthetic Street\n12 a' WHERE id = $id");
check($service->geocodeAddress($id, $oldFingerprint)['status'] === 'public_review_required' && $service->requests === 1, 'Original postal snapshot was lost during canonicalization');
$cached = attempt($service, $id);
check($cached['status'] === 'updated' && $cached['cached'] && $service->requests === 1, 'Equivalent format caused a duplicate API request');
check(!str_contains(file_get_contents(options()['state_dir'].'/manual.json'), 'Synthetic'), 'Format analysis leaked postal text into cache');

$id = seed(['street' => "Synthetic Street\n1"]); $service = new GeocodingTestAddress();
$service->duringRequest = static function() use ($other, $id): void { $other->query("UPDATE address SET street = 'Synthetic Street 1' WHERE id = $id"); };
check(attempt($service, $id)['status'] === 'address_changed' && stored($id)['latitude'] === null, 'Same-query concurrent formatting edit bypassed snapshot protection');
echo 'PASS: '.($checks - $formatChecks)." postal format, control character, exact match, cache and approval compatibility checks\n";
