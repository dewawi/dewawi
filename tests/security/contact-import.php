<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/contact-import.php (no database required).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
define('BASE_PATH', $root);
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

class ContactImportTestController extends Contacts_ContactController
{
	public array $contacts = [];
	public array $categories = [];

	public function init() {}

	public function previewFile(string $file, string $separator = 'auto'): array
	{
		return $this->buildContactImportPreview($file, $separator, 7);
	}

	protected function getContactImportReferenceData(int $clientId): array
	{
		return ['contacts' => $this->contacts, 'categories' => $this->categories];
	}
}

function importFixture(string $contents): string
{
	$file = tempnam(sys_get_temp_dir(), 'dewawi-contact-import-');
	file_put_contents($file, $contents);
	return $file;
}

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('import');
$controller = new ContactImportTestController($request, new Zend_Controller_Response_Http());
$controller->contacts = [
	['id' => 1, 'contactid' => 100, 'catid' => 3, 'name1' => 'Acme GmbH', 'name2' => '', 'department' => 'Alt'],
	['id' => 2, 'contactid' => 123, 'catid' => 3, 'name1' => 'Other Company', 'name2' => '', 'department' => ''],
	['id' => 3, 'contactid' => 300, 'catid' => 3, 'name1' => 'Ambiguous A', 'name2' => '', 'department' => ''],
	['id' => 4, 'contactid' => 300, 'catid' => 4, 'name1' => 'Ambiguous B', 'name2' => '', 'department' => ''],
];
$controller->categories = [
	3 => ['id' => 3, 'title' => 'Kunden', 'breadcrumb' => 'Geschäftspartner > Kunden'],
	4 => ['id' => 4, 'title' => 'Partner', 'breadcrumb' => 'Geschäftspartner > Partner'],
];

$file = importFixture("\xEF\xBB\xBFKontaktnr.;Firmenname;Namenszusatz;Kategorie;Straße;PLZ;Ort;Land;Telefon;E-Mail;Internet;Ignoriert\n"
	. "100;Acme Neu;Nord;Kunden;Neue Straße 1;40210;Düsseldorf;DE;+49 1;one@example.test, two@example.test;https://example.test;x\n"
	. ";New GmbH;;Partner;Weg 2;10115;Berlin;DE;+49 2;new@example.test;https://new.test;x\n"
	. ";Acme GmbH;;;;;;;;;;x\n"
	. "abc;Invalid GmbH;;;;;;;;;;x\n"
	. "999;Unknown Category;;Missing;;;;;;;;x\n"
	. ";Repeated GmbH;;;;;;;;;;x\n"
	. ";Repeated GmbH;;;;;;;;;;x\n"
	. ";123 Company;;;;;;;;;;x\n"
	. "300;Ambiguous Number;;;;;;;;;;x\n");
$preview = $controller->previewFile($file);
unlink($file);

check($preview['separator'] === ';', 'Semicolon separator was not detected');
check($preview['headers'][0] === 'Kontaktnr.', 'UTF-8 BOM was not removed from the first header');
check($preview['unknownHeaders'] === ['Ignoriert'], 'Unsupported headers were not reported');
check($preview['counts'] === ['new' => 2, 'update' => 1, 'duplicate' => 3, 'error' => 3], 'Import preview status counts are incorrect: ' . json_encode($preview['counts']));
check($preview['items'][0]['status'] === 'update' && $preview['items'][0]['existingId'] === 1, 'Existing contact was not matched by contactid');
check($preview['items'][0]['contactData']['catid'] === 3, 'Category title was not mapped');
check($preview['items'][0]['emails'] === ['one@example.test', 'two@example.test'], 'Multiple email addresses were not parsed');
check($preview['items'][1]['status'] === 'new', 'New contact with no contactid was not classified as new');
check($preview['items'][2]['status'] === 'duplicate' && $preview['items'][2]['duplicates'][0] === '100 · Acme GmbH', 'Existing company name duplicate was not shown');
check(in_array('CONTACTS_IMPORT_INVALID_CONTACT_ID', $preview['items'][3]['errors'], true), 'Invalid contactid was accepted');
check(in_array('CONTACTS_IMPORT_INVALID_CATEGORY', $preview['items'][4]['errors'], true), 'Invalid category was accepted');
check($preview['items'][5]['csvDuplicate'] && $preview['items'][6]['csvDuplicate'], 'Potential duplicates inside the CSV were not shown');
check($preview['items'][7]['status'] === 'new', 'Company name was interpreted as a numeric contactid');
check(in_array('CONTACTS_IMPORT_AMBIGUOUS_CONTACT_ID', $preview['items'][8]['errors'], true), 'Non-unique existing contactid was accepted');

$file = importFixture("contactid,phone\n100,+49 99\n");
$missingColumns = $controller->previewFile($file, 'comma');
unlink($file);
check($missingColumns['items'][0]['status'] === 'update', 'Minimal contactid update was rejected');
check($missingColumns['items'][0]['contactData'] === [], 'Missing company columns would overwrite existing data');
check($missingColumns['items'][0]['phones'] === ['+49 99'], 'Imported phone was lost');

$file = importFixture("contactid,name1\n100,First\n100,Second\n");
$duplicateNumbers = $controller->previewFile($file, 'comma');
unlink($file);
check($duplicateNumbers['counts']['error'] === 2, 'Repeated contactid inside CSV was not rejected');
check(in_array('CONTACTS_IMPORT_DUPLICATE_CONTACT_ID_CSV', $duplicateNumbers['items'][0]['errors'], true), 'Repeated CSV contactid has no useful error');

$file = importFixture("company,email\n\"Comma, Company\",invalid-address\n");
$invalidEmail = $controller->previewFile($file, 'comma');
unlink($file);
check($invalidEmail['items'][0]['name1'] === 'Comma, Company', 'Quoted comma value was parsed incorrectly');
check(in_array('CONTACTS_IMPORT_INVALID_EMAIL', $invalidEmail['items'][0]['errors'], true), 'Invalid email address was accepted');

$controllerSource = file_get_contents($root . '/application/modules/contacts/controllers/ContactController.php');
$formSource = file_get_contents($root . '/application/modules/contacts/forms/Import.php');
check(!str_contains($controllerSource, 'getContactWithID($datacsv[$map[\'name1\']])'), 'Legacy company-name contact lookup remains active');
check(!str_contains($controllerSource, 'addPhone(') && !str_contains($controllerSource, 'addEmail(') && !str_contains($controllerSource, 'addInternet('), 'Legacy contact information methods remain in the importer');
check(str_contains($controllerSource, 'beginTransaction()') && str_contains($controllerSource, 'rollBack()'), 'Confirmed contact import is not transactional');
check(str_contains($formSource, "addValidator('Extension', false, 'csv')"), 'Contact import accepts non-CSV extensions');
check(str_contains($controllerSource, 'assignToContacts('), 'Optional import tag does not use existing tag assignment');

echo 'PASS: ' . $checks . " contact import cases\n";
