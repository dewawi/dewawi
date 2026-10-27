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

require_once $root . '/application/modules/contacts/controllers/ContactController.php';

$checks = 0;
function check(bool $condition, string $message): void
{
	global $checks;
	if (!$condition) throw new RuntimeException($message);
	$checks++;
}

class ContactExportTestTranslator
{
	private array $translations;

	public function __construct(string $locale = 'de_DE')
	{
		$directory = dirname(__DIR__, 2) . '/languages/' . $locale . '/contacts/';
		$this->translations = (require $directory . 'contact.php') + (require $directory . 'contactperson.php');
	}

	public function t(string $key): string
	{
		return $this->translations[$key] ?? $key;
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

	public function build(array $params, array $options): Zend_Db_Select
	{
		$config = Contacts_Model_Entity_Contact::listConfig();
		$db = new ContactExportTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
		$select = $db->select()->from(['c' => 'contact']);
		$this->applyFilters($select, $params, $options, $config);
		$this->applyGrouping($select, $config);
		$this->applyOrdering($select, $params, $config);
		$this->applyLimit($select, $params);
		return $select;
	}
}

class ContactExportTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
	protected function _connect() {}
	protected function _quote($value) { return Zend_Db_Adapter_Abstract::_quote($value); }
	public function query($sql, $bind = []) { throw new RuntimeException('Unexpected database access'); }
}

class ContactExportTestController extends Contacts_ContactController
{
	public array $contacts = [];
	public function init() {}

	public function previewFile(string $file): array
	{
		return $this->buildContactImportPreview($file, 'auto', 70);
	}

	protected function getContactImportReferenceData(int $clientId): array
	{
		return ['contacts' => $this->contacts, 'categories' => []];
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
	'roles' => [
		11 => ['customer', 'supplier'],
		12 => [],
	],
];

$companyRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMPANIES, [$contacts[0], $contacts[0], $contacts[1]]));
check(count($companyRows) === 3, 'Company export does not contain exactly one row per contact');
check(count($companyRows[0]) === 14, 'Company export contains an unexpected column count');
check($companyRows[0][0] === 'Kontaktnr.' && $companyRows[0][1] === 'Name/Organisation', 'Company headers were not translated');
check($companyRows[1][1] === "'=Acme & Co", 'CSV formula injection or HTML sanitizing failed');
check($companyRows[1][2] === 'Nord; "West"', 'CSV quoting changed special characters');
check($companyRows[1][4] === 'Königsallee 1', 'Company export did not select the billing address');
check($companyRows[1][8] === "'+49 1, +49 2", 'Company phone values were not combined, deduplicated and CSV-protected');
check($companyRows[1][9] === 'info@example.test, office@example.test', 'Company email values were not combined');
check($companyRows[1][11] === 'Geschäftspartner > Kunden', 'Company category breadcrumb is missing');
check($companyRows[1][12] === 'Gilventa, VIP', 'Company tags were not combined or sanitized');
check($companyRows[1][13] === 'Kunde, Lieferant', 'Company roles were not exported');
check(!str_contains(implode(';', $companyRows[0]), 'notes') && !str_contains(implode(';', $companyRows[0]), 'vatin'), 'Company export exposes sensitive columns');
check($service->loadedClientId === 70, 'Authorized parent contact client was not used for export details');

$personRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_CONTACTPERSONS, $contacts));
check(count($personRows) === 3, 'Contact person export does not contain exactly one row per active person');
check(count($personRows[0]) === 12, 'Contact person export contains an unexpected column count');
check($personRows[1][2] === 'Max' && $personRows[1][3] === 'Mustermann', 'Contact person export lost the new name fields');
check(!str_contains(implode(';', $personRows[1]), 'Legacy') && !str_contains(implode(';', $personRows[1]), 'Ignored'), 'Contact person export used legacy name fields');
check($personRows[1][8] === 'max@example.test, max.work@example.test', 'Contact person emails were not combined');
check(!str_contains(implode("\n", array_map(static fn(array $row): string => implode(';', $row), $personRows)), 'Ohne Ansprechpartner'), 'Contact without a person created an empty person row');
check($personRows[1][10] === 'Gilventa, VIP', 'Contact person export lost company tags');
check($personRows[1][11] === 'Kunde, Lieferant', 'Contact person export lost company roles');

$combinedList = $service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, [$contacts[0], $contacts[0], $contacts[1]]);
$combinedRows = csvRows($combinedList);
check(count($combinedRows) === 4, 'Combined export needs two person rows and exactly one company-only row');
check(count($combinedRows[0]) === 21 && count(array_unique($combinedRows[0])) === 21, 'Combined columns are incomplete or ambiguous');
check(array_slice($combinedRows[1], 0, 14) === $companyRows[1] && array_slice($combinedRows[2], 0, 14) === $companyRows[1], 'Combined export did not repeat all company data per person');
check(array_slice($combinedRows[3], 0, 14) === $companyRows[2] && array_slice($combinedRows[3], 14) === array_fill(0, 7, ''), 'Company without persons needs exactly one row with empty person fields');
check(array_slice($combinedRows[1], 14) === ['Herr', 'Dr.', 'Max', 'Mustermann', 'Einkauf', 'Beschaffung', 'max@example.test, max.work@example.test'], 'Combined person columns or their order are incorrect');
check($combinedRows[2][16] === 'Erika' && $combinedRows[2][20] === 'erika@example.test', 'Second person was lost or duplicated');
check($combinedRows[1][9] === 'info@example.test, office@example.test' && !str_contains($combinedRows[1][20], 'info@'), 'Company and person emails were mixed');
check($combinedRows[1][3] === 'Vertrieb' && $combinedRows[1][19] === 'Beschaffung', 'Company and person departments were mixed');
check(!str_contains(serialize($combinedRows), 'Legacy') && !str_contains(serialize($combinedRows), 'Internal note') && !str_contains(serialize($combinedRows), 'DE123'), 'Combined export exposes legacy names or confidential data');
check(array_column($combinedList->getColumns(), 'name') === ['contactid', 'name1', 'name2', 'department', 'street', 'postcode', 'city', 'country', 'phone', 'email', 'internet', 'category', 'tags', 'roles', 'salutation', 'title', 'firstname', 'lastname', 'position', 'contactperson_department', 'contactperson_email'], 'Combined export exposes unexpected fields or internal IDs');
check($service->loadedClientId === 70, 'Combined export did not use the authorized contact tenant');

$service->related['contactpersonEmails'][501][] = ['email' => 'max@example.test'];
$service->related['contactpersons'][11][0]['position'] = "@SUM(1;2)\n\"Einkauf\"";
$specialRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, $contacts));
check($specialRows[1][18] === "'@SUM(1;2)\n\"Einkauf\"", 'Combined person fields lost formula protection, quotes or newlines');
check($specialRows[1][20] === 'max@example.test, max.work@example.test', 'Repeated person email caused duplicate values or rows');
check(count($specialRows) === 4, 'Multiple email addresses caused additional person rows');
$service->related['contactpersons'][11][0]['position'] = 'Einkauf';

$service->related['contactpersons'][12] = [['id' => 503, 'name1' => 'Legacy only', 'name2' => 'Ignored']];
$legacyRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, [$contacts[1]]));
check($legacyRows[1][16] === '' && $legacyRows[1][17] === '', 'Combined export fell back to legacy person names');
unset($service->related['contactpersons'][12]);
check(count(csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, []))) === 1, 'Empty combined export must contain only headers');

$manyContacts = $contacts;
for ($i = 0; $i < 60; $i++) $manyContacts[] = array_replace($contacts[1], ['id' => 100 + $i, 'contactid' => 2000 + $i]);
$manyRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, $manyContacts));
check(count($manyRows) === 64 && $manyRows[63][0] === '2059', 'Combined export lost contacts beyond the first page');

foreach (['de_DE', 'en_US'] as $locale) {
	$translator = new ContactExportTestTranslator($locale);
	Zend_Registry::set('DEEC_Translate', $translator);
	$translatedRows = csvRows($service->createList(Contacts_Service_ContactExportService::PROFILE_COMBINED, $contacts));
	check(!str_contains(implode(';', $translatedRows[0]), 'CONTACTS_'), $locale . ' combined headers are not fully translated');
	check($translatedRows[0][9] === $translator->t('CONTACTS_EXPORT_COMPANY_EMAIL') && $translatedRows[0][20] === $translator->t('CONTACTS_EXPORT_CONTACTPERSON_EMAIL'), $locale . ' email headers are ambiguous');
}
Zend_Registry::set('DEEC_Translate', new ContactExportTestTranslator());

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('export');
$controller = new ContactExportTestController($request, new Zend_Controller_Response_Http());
$profileMethod = new ReflectionMethod(Contacts_ContactController::class, 'getExportProfile');
check($profileMethod->invoke($controller) === 'companies', 'Export without explicit profile lost backward compatibility');
foreach (['combined', 'companies', 'contactpersons'] as $profile) {
	$request->setParam('profile', $profile);
	check($profileMethod->invoke($controller) === $profile, 'Controller rejected a valid export profile');
}
$request->setParam('profile', 'invalid');
try {
	$profileMethod->invoke($controller);
	throw new RuntimeException('Controller accepted an invalid export profile');
} catch (InvalidArgumentException $expected) {
	check(true, 'Controller rejects invalid export profiles');
}

$file = $service->export(sys_get_temp_dir() . '/', $contacts, ['profile' => Contacts_Service_ContactExportService::PROFILE_COMBINED]);
try {
	check((bool)preg_match('/^contacts-combined-\d{8}-\d{6}\.csv$/', $file['name']), 'Combined export filename is incorrect');
	check(str_starts_with(file_get_contents($file['path']), "\xEF\xBB\xBF"), 'Combined file export bypassed the CSV writer');
	$controller->contacts = $contacts;
	$preview = $controller->previewFile($file['path']);
	check(!array_diff(array_slice($combinedRows[0], 14), $preview['unknownHeaders']), 'Company importer unexpectedly recognizes combined person columns');
	check(!array_intersect(['firstname', 'lastname', 'salutation', 'title', 'position', 'department', 'email'], $preview['mappedHeaders']), 'Combined person data can be mapped into company import fields');
	check($preview['items'][0]['emails'] === [] && !isset($preview['items'][0]['contactData']['firstname']), 'Combined import preview treats person details as company data');
	check(in_array('CONTACTS_IMPORT_DUPLICATE_CONTACT_ID_CSV', $preview['items'][0]['errors'], true) && in_array('CONTACTS_IMPORT_DUPLICATE_CONTACT_ID_CSV', $preview['items'][1]['errors'], true), 'Company importer accepts repeated combined company rows as a full reimport');
} finally {
	unlink($file['path']);
}

$params = (new ContactExportTestQuery())->exportParams([
	'_export' => true,
	'page' => 4,
	'limit' => 25,
	'tagid' => 21,
	'role' => 'supplier',
	'catid' => 3,
	'country' => 'DE',
	'keyword' => 'Acme',
	'order' => 'name1',
	'sort' => 'ASC',
]);
check($params['limit'] === 0 && $params['offset'] === 0, 'Export is restricted to the visible list page');
check($params['tagid'] === 21 && $params['catid'] === 3 && $params['country'] === 'DE' && $params['keyword'] === 'Acme', 'Export normalization lost active filters');
check($params['role'] === 'supplier', 'Export normalization lost the role filter');
check($params['order'] === 'name1' && $params['sort'] === 'ASC', 'Export normalization lost list sorting');

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('export');
Zend_Controller_Front::getInstance()->setRequest($request);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
$options = ['catid' => [3 => ['childs' => [4]], 4 => ['childs' => []]], 'country' => ['DE' => 'Germany']];
$select = (new ContactExportTestQuery())->build($params, $options);
$where = implode(' ', $select->getPart(Zend_Db_Select::WHERE));
check(str_contains($where, 'c.catid IN (3, 4)') && str_contains($where, "a.country = 'DE'") && str_contains($where, 'LIKE'), 'Export query lost category, subcategory, country or search filters');
check(str_contains($where, 'EXISTS (SELECT 1 FROM `contactrole`') && str_contains($where, "'supplier'") && str_contains($where, ' = 70'), 'Export query lost the tenant-bound role filter');
check(str_contains($where, 'c.clientid = 70') && str_contains($where, 'c.deleted = 0'), 'Export query lost authorized parent tenant or active contacts');
$from = $select->getPart(Zend_Db_Select::FROM);
check(str_contains($from['tag_filter']['joinCondition'], 'tag_filter.tagid = 21') && str_contains($from['tag_filter']['joinCondition'], 'tag_filter.clientid = 70') && str_contains($from['tag_filter_definition']['joinCondition'], 'tag_filter_definition.deleted = 0'), 'Export query lost the active tenant-bound tag filter');
check($select->getPart(Zend_Db_Select::GROUP) === ['c.id'], 'Export query can duplicate contacts through joins');
check(in_array(['c.name1', 'ASC'], $select->getPart(Zend_Db_Select::ORDER), true), 'Export query lost sorting');
check(!$select->getPart(Zend_Db_Select::LIMIT_COUNT) && !$select->getPart(Zend_Db_Select::LIMIT_OFFSET), 'Export SQL still contains paging');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
$ownWhere = implode(' ', (new ContactExportTestQuery())->build($params, $options)->getPart(Zend_Db_Select::WHERE));
check(str_contains($ownWhere, 'c.clientid = 7') && !str_contains($ownWhere, 'c.clientid = 70'), 'Own-tenant export query uses a foreign tenant');

$contactsJs = file_get_contents($root . '/js/contacts.js');
$globalJs = file_get_contents($root . '/js/global.js');
$indexView = file_get_contents($root . '/application/modules/contacts/views/scripts/contact/index.phtml');
check(str_contains($contactsJs, "profile !== 'combined'") && str_contains($contactsJs, "profile !== 'companies'") && str_contains($contactsJs, "profile !== 'contactpersons'"), 'Contact toolbar does not offer all export profiles');
check(str_contains($globalJs, 'submitExport: function (extraData)') && str_contains($globalJs, 'collectToolbarData()'), 'Contact export bypasses the active toolbar filters');
check(str_contains($indexView, 'data-contact-export-profile="companies"') && str_contains($indexView, 'data-contact-export-profile="contactpersons"'), 'Contact export profile dialog is incomplete');
check(strpos($indexView, 'data-contact-export-profile="combined"') !== false && strpos($indexView, 'data-contact-export-profile="combined"') < strpos($indexView, 'data-contact-export-profile="companies"'), 'Combined export is not the first dialog option');
check(str_contains($indexView, 'CONTACTS_EXPORT_COMBINED_DESCRIPTION'), 'Combined export lacks the import limitation notice');
check((new DEEC_Permission([]))->getPermissionForAction('export') === 'view', 'Contact export is not protected by view permission');

$german = require $root . '/languages/de_DE/contacts/contact.php';
$english = require $root . '/languages/en_US/contacts/contact.php';
check(($german['CONTACTS_EXPORT_COMPANIES'] ?? '') !== '' && ($english['CONTACTS_EXPORT_COMPANIES'] ?? '') !== '', 'Contact export translations are incomplete');
check($german['CONTACTS_EXPORT_COMBINED'] === 'Kontakte komplett (CSV)' && $english['CONTACTS_EXPORT_COMBINED'] !== '', 'Combined export option is not translated');

foreach (['companies', 'contactpersons', 'combined'] as $profile) {
	try {
		$service->createList($profile, [$contacts[0], array_replace($contacts[1], ['clientid' => 71])]);
		throw new RuntimeException('Mixed tenant export was accepted');
	} catch (RuntimeException $expected) {
		check(str_contains($expected->getMessage(), 'multiple client'), 'Mixed tenant export failed for an unexpected reason');
	}
	try {
		$service->createList($profile, [array_replace($contacts[0], ['clientid' => 0])]);
		throw new RuntimeException('Export without tenant context was accepted');
	} catch (RuntimeException $expected) {
		check(str_contains($expected->getMessage(), 'authorized client'), 'Missing tenant export failed for an unexpected reason');
	}
}

try {
	$service->createList('invalid', $contacts);
	throw new RuntimeException('Invalid export profile was accepted');
} catch (InvalidArgumentException $expected) {
	check(true, 'Invalid export profile was rejected');
}

echo 'PASS: ' . $checks . " contact export cases\n";
