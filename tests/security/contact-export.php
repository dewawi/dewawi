<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/contact-export.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());

spl_autoload_register(static function(string $class) use ($root): void {
	if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) {
		require_once $path;
		return;
	}

	$parts = explode('_', $class);
	if (count($parts) >= 3) {
		$module = array_shift($parts);
		$base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
		$path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
		if (is_file($path)) require_once $path;
	}
});

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

class ContactExportTestTranslator
{
	public function t(string $key): string
	{
		$translations = [
			'CONTACTS_CONTACT_ID' => 'Kontaktnr.',
			'CONTACTS_NAME_ORGANISATION' => 'Firmenname',
			'CONTACTS_EXPORT_COMPANY' => 'Unternehmen',
		];

		return $translations[$key] ?? $key;
	}
}

class ContactExportTestService extends Contacts_Service_ContactExportService
{
	public array $related = [];
	public int $loadedClientId = 0;

	protected function loadRelatedData(array $contacts, int $clientId, array $context): array
	{
		$this->loadedClientId = $clientId;
		return $this->related;
	}
}

class ContactExportTestQuery extends DEEC_List_Query
{
	public function exportParams(array $params): array
	{
		return $this->normalizeParams($params);
	}
}

function csvRows(DEEC_List $list): array
{
	$stream = fopen('php://temp', 'w+');
	$list->writeCsv($stream);
	rewind($stream);
	$content = stream_get_contents($stream);
	fclose($stream);

	check(substr($content, 0, 3) === "\xEF\xBB\xBF", 'CSV output has no UTF-8 BOM');
	$content = substr($content, 3);
	$stream = fopen('php://temp', 'w+');
	fwrite($stream, $content);
	rewind($stream);
	$rows = [];
	while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) $rows[] = $row;
	fclose($stream);

	return $rows;
}

Zend_Registry::set('DEEC_Translate', new ContactExportTestTranslator());

$contacts = [
	[
		'id' => 11,
		'clientid' => 70,
		'contactid' => 1001,
		'catid' => 3,
		'name1' => '<b>=Acme &amp; Co</b>',
		'name2' => 'Nord; "West"',
		'department' => 'Vertrieb',
		'notes' => 'Internal note',
		'vatin' => 'DE123',
	],
	[
		'id' => 12,
		'clientid' => 70,
		'contactid' => 1002,
		'catid' => 4,
		'name1' => 'Ohne Ansprechpartner',
		'name2' => '',
		'department' => '',
	],
];

$service = new ContactExportTestService();
$service->related = [
	'addresses' => [
		11 => [
			['id' => 1, 'type' => 'other', 'street' => 'Falsche Straße', 'postcode' => '1', 'city' => 'Alt', 'country' => 'DE'],
			['id' => 2, 'type' => 'billing', 'street' => 'Königsallee 1', 'postcode' => '40212', 'city' => 'Düsseldorf', 'country' => 'DE'],
		],
	],
	'phones' => [
		11 => [['phone' => '+49 1'], ['phone' => '+49 2'], ['phone' => '+49 1']],
	],
	'emails' => [
		11 => [['email' => 'info@example.test'], ['email' => 'office@example.test']],
	],
	'internets' => [
		11 => [['internet' => 'https://example.test']],
	],
	'contactpersons' => [
		11 => [
			['id' => 501, 'firstname' => 'Max', 'lastname' => 'Mustermann', 'name1' => 'Legacy', 'name2' => 'Ignored', 'salutation' => 'Herr', 'title' => 'Dr.', 'position' => 'Einkauf', 'department' => 'Beschaffung'],
			['id' => 502, 'firstname' => 'Erika', 'lastname' => 'Musterfrau', 'name1' => 'Old', 'name2' => 'Name', 'salutation' => 'Frau', 'title' => '', 'position' => 'Leitung', 'department' => 'Vertrieb'],
		],
	],
	'contactpersonEmails' => [
		501 => [['email' => 'max@example.test'], ['email' => 'max.work@example.test']],
		502 => [['email' => 'erika@example.test']],
	],
	'categories' => [
		3 => ['title' => 'Kunden', 'breadcrumb' => 'Geschäftspartner > Kunden'],
		4 => ['title' => 'Partner', 'breadcrumb' => 'Geschäftspartner > Partner'],
	],
	'tags' => [
		11 => [['tag' => 'Gilventa'], ['tag' => '<i>VIP</i>']],
		12 => [['tag' => 'Gilventa']],
	],
];

$companyRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMPANIES, [$contacts[0], $contacts[0], $contacts[1]]));
check(count($companyRows) === 3, 'Company export does not contain exactly one row per contact');
check(count($companyRows[0]) === 13, 'Company export contains an unexpected column count');
check($companyRows[0][0] === 'Kontaktnr.' && $companyRows[0][1] === 'Firmenname', 'Company headers were not translated');
check($companyRows[1][1] === "'=Acme & Co", 'CSV formula injection or HTML sanitizing failed');
check($companyRows[1][2] === 'Nord; "West"', 'CSV quoting changed special characters');
check($companyRows[1][4] === 'Königsallee 1', 'Company export did not select the billing address');
check($companyRows[1][8] === "'+49 1, +49 2", 'Company phone values were not combined, deduplicated and CSV-protected');
check($companyRows[1][9] === 'info@example.test, office@example.test', 'Company email values were not combined');
check($companyRows[1][11] === 'Geschäftspartner > Kunden', 'Company category breadcrumb is missing');
check($companyRows[1][12] === 'Gilventa, VIP', 'Company tags were not combined or sanitized');
check(!str_contains(implode(';', $companyRows[0]), 'notes') && !str_contains(implode(';', $companyRows[0]), 'vatin'), 'Company export exposes sensitive columns');
check($service->loadedClientId === 70, 'Authorized parent contact client was not used for export details');

$personRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_CONTACTPERSONS, $contacts));
check(count($personRows) === 3, 'Contact person export does not contain exactly one row per active person');
check(count($personRows[0]) === 11, 'Contact person export contains an unexpected column count');
check($personRows[1][2] === 'Max' && $personRows[1][3] === 'Mustermann', 'Contact person export lost the new name fields');
check(!str_contains(implode(';', $personRows[1]), 'Legacy') && !str_contains(implode(';', $personRows[1]), 'Ignored'), 'Contact person export used legacy name fields');
check($personRows[1][8] === 'max@example.test, max.work@example.test', 'Contact person emails were not combined');
check(!str_contains(implode("\n", array_map(static fn(array $row): string => implode(';', $row), $personRows)), 'Ohne Ansprechpartner'), 'Contact without a person created an empty person row');
check($personRows[1][10] === 'Gilventa, VIP', 'Contact person export lost company tags');

$params = (new ContactExportTestQuery())->exportParams([
	'_export' => true,
	'page' => 4,
	'limit' => 25,
	'tagid' => 21,
	'catid' => 3,
	'country' => 'DE',
	'keyword' => 'Acme',
	'order' => 'name1',
	'sort' => 'ASC',
]);
check($params['limit'] === 0 && $params['offset'] === 0, 'Export is restricted to the visible list page');
check($params['tagid'] === 21 && $params['catid'] === 3 && $params['country'] === 'DE' && $params['keyword'] === 'Acme', 'Export normalization lost active filters');
check($params['order'] === 'name1' && $params['sort'] === 'ASC', 'Export normalization lost list sorting');

$contactsJs = file_get_contents($root . '/js/contacts.js');
$globalJs = file_get_contents($root . '/js/global.js');
$indexView = file_get_contents($root . '/application/modules/contacts/views/scripts/contact/index.phtml');
check(str_contains($contactsJs, "profile !== 'companies'") && str_contains($contactsJs, "profile !== 'contactpersons'"), 'Contact toolbar does not offer both export profiles');
check(str_contains($globalJs, 'submitExport: function (extraData)') && str_contains($globalJs, 'collectToolbarData()'), 'Contact export bypasses the active toolbar filters');
check(str_contains($indexView, 'data-contact-export-profile="companies"') && str_contains($indexView, 'data-contact-export-profile="contactpersons"'), 'Contact export profile dialog is incomplete');
check((new DEEC_Permission([]))->getPermissionForAction('export') === 'view', 'Contact export is not protected by view permission');

$german = require $root . '/languages/de_DE/contacts/contact.php';
$english = require $root . '/languages/en_US/contacts/contact.php';
check(($german['CONTACTS_EXPORT_COMPANIES'] ?? '') !== '' && ($english['CONTACTS_EXPORT_COMPANIES'] ?? '') !== '', 'Contact export translations are incomplete');

try {
	$service->createList(Contacts_Service_ContactExportService::PROFILE_COMPANIES, [$contacts[0], array_replace($contacts[1], ['clientid' => 71])]);
	throw new RuntimeException('Mixed tenant export was accepted');
} catch (RuntimeException $expected) {
	check(str_contains($expected->getMessage(), 'multiple client'), 'Mixed tenant export failed for an unexpected reason');
}

try {
	$service->createList('invalid', $contacts);
	throw new RuntimeException('Invalid export profile was accepted');
} catch (InvalidArgumentException $expected) {
	check(true, 'Invalid export profile was rejected');
}

echo 'PASS: ' . $checks . " contact export cases\n";
