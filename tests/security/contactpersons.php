<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/security/contactpersons.php (in-memory database).
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__, 2);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
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

require_once __DIR__ . '/fixtures/entity.php';

$adapter = new ContactpersonTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
Zend_Db_Table::setDefaultAdapter($adapter);
Zend_Registry::set('User', ['id' => 42, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'modules' => ['contacts' => 7]]);

$person = [
    'id' => 10,
    'parentid' => 1,
    'module' => 'contacts',
    'controller' => 'contact',
    'clientid' => 7,
    'deleted' => 0,
    'ordering' => 1,
    'name1' => 'Legacy',
    'name2' => 'Name',
    'firstname' => 'Max',
    'lastname' => 'Mustermann',
    'salutation' => 'Herr',
    'title' => 'Dr.',
    'position' => 'Buyer',
    'department' => 'Purchasing',
    'gender' => null,
    'type' => null,
];
$adapter->rows = [
	'contact' => [
		['id' => 1, 'contactid' => 100, 'clientid' => 7, 'deleted' => 0],
		['id' => 2, 'contactid' => 200, 'clientid' => 7, 'deleted' => 0],
		['id' => 3, 'contactid' => 300, 'clientid' => 70, 'deleted' => 0],
	],
    'contactperson' => [
        $person,
        array_replace($person, ['id' => 11, 'ordering' => 2, 'firstname' => 'Erika', 'lastname' => 'Musterfrau', 'salutation' => 'Frau', 'title' => '', 'position' => 'Manager']),
        array_replace($person, ['id' => 12, 'clientid' => 8]),
        array_replace($person, ['id' => 13, 'deleted' => 1]),
        array_replace($person, ['id' => 14, 'parentid' => 2]),
        array_replace($person, ['id' => 15, 'parentid' => 3, 'clientid' => 70]),
    ],
    'email' => [
        ['id' => 20, 'parentid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'company@example.invalid', 'suppressed' => 0],
        ['id' => 21, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'max@example.invalid', 'suppressed' => 0],
        ['id' => 22, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 2, 'email' => 'max.alt@example.invalid', 'suppressed' => 0],
        ['id' => 23, 'parentid' => 11, 'module' => 'contacts', 'controller' => 'contactperson', 'clientid' => 7, 'deleted' => 0, 'ordering' => 1, 'email' => 'erika@example.invalid', 'suppressed' => 0],
    ],
];

$exportService = new Contacts_Service_ContactExportService();
$exportContext = ['tagEntities' => [], 'contactRoles' => []];
$originalRows = $adapter->rows;
$adapter->rows['contactperson'][] = array_replace($person, ['id' => 16, 'parentid' => 4, 'deleted' => 1]);
$adapter->rows['contactperson'][] = array_replace($person, ['id' => 17, 'parentid' => 4, 'clientid' => 8]);
foreach ([['clientid' => 8], ['deleted' => 1], ['module' => 'sales'], ['controller' => 'contact']] as $overrides) {
    $adapter->rows['email'][] = array_replace($adapter->rows['email'][1], $overrides, ['email' => 'excluded@example.invalid']);
}
$beforeSelects = count($adapter->selects);
$exportRows = $exportService->createList('combined', [$adapter->rows['contact'][0], ['id' => 4, 'clientid' => 7, 'contactid' => 400]], $exportContext)->getItems();
check(count($exportRows) === 3 && array_column($exportRows, 'firstname') === ['Max', 'Erika', ''], 'Combined data loading included deleted, foreign or unrelated persons');
check($exportRows[0]['email'] === 'company@example.invalid' && $exportRows[0]['contactperson_email'] === 'max@example.invalid, max.alt@example.invalid', 'Combined data loading mixed email ownership or included excluded emails');
check($exportRows[2]['contactid'] === '400' && $exportRows[2]['contactperson_email'] === '', 'Company with only deleted or foreign persons did not get one empty person row');
check(count($adapter->selects) === $beforeSelects + 7, 'Combined export did not batch related data loading');
$adapter->rows = $originalRows;

Zend_Registry::set('Client', ['id' => 7, 'parentid' => 70, 'modules' => ['contacts' => 70]]);
$sharedExportRows = $exportService->createList('combined', [$adapter->rows['contact'][2]], $exportContext)->getItems();
check(count($sharedExportRows) === 1 && $sharedExportRows[0]['firstname'] === 'Max', 'Combined export did not load the authorized parent tenant person');
check($sharedExportRows[0]['contactperson_email'] === '', 'Combined export leaked child tenant emails into a shared parent contact');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'modules' => ['contacts' => 7]]);

$form = new Contacts_Form_Contactperson();
check(array_keys($form->getElements()) === ['salutation', 'title', 'firstname', 'lastname', 'position', 'department', 'email'], 'Contact-person form fields or order are incorrect');
check($form->isValidPartial(['firstname' => '  Maximilian  ']), 'First-name auto-save validation failed');
check($form->getFilteredValuesPartial(['firstname' => '  Maximilian  '])['firstname'] === 'Maximilian', 'First-name auto-save filtering failed');
check(!$form->isValidPartial(['lastname' => '']), 'Empty last name was accepted');
check(!isset($form->getElements()['name1'], $form->getElements()['name2']), 'Legacy name fields remain editable');

check(DEEC_ContactpersonFormatter::displayName($person) === 'Dr. Max Mustermann', 'Display name is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation($person) === 'Sehr geehrter Herr Dr. Mustermann,', 'Male formal salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['salutation' => 'Frau', 'title' => ''])) === 'Sehr geehrte Frau Mustermann,', 'Female formal salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['salutation' => '0'])) === 'Guten Tag,', 'Neutral salutation is incorrect');
check(DEEC_ContactpersonFormatter::formalSalutation(array_replace($person, ['lastname' => ''])) === 'Guten Tag,', 'Incomplete name did not use neutral salutation');
check(DEEC_ContactpersonFormatter::recipientLabel($person + ['email' => 'max@example.invalid']) === 'max@example.invalid (Dr. Max Mustermann)', 'Recipient label is incorrect');

$campaignEmail = (new ReflectionClass(DEEC_Email::class))->newInstanceWithoutConstructor();
$personalizeBody = new ReflectionMethod(DEEC_Email::class, 'personalizeBody');
$personalizeBody->setAccessible(true);
check($personalizeBody->invoke($campaignEmail, '[SALUTATION] Willkommen', $person) === 'Sehr geehrter Herr Dr. Mustermann, Willkommen', 'Campaign email personalization is inconsistent');
check($personalizeBody->invoke($campaignEmail, '[SALUTATION] Willkommen', ['salutation' => '0']) === 'Guten Tag, Willkommen', 'Campaign email neutral greeting is inconsistent');

$contactpersonDb = new Contacts_Model_DbTable_Contactperson();
$selectOptions = $contactpersonDb->getSelectOptionsForContact(1);
check($selectOptions === ['10' => 'Dr. Max Mustermann', '11' => 'Erika Musterfrau'], 'Purchase contact-person options are incomplete or incorrectly formatted');
check($contactpersonDb->belongsToContact(10, 1), 'Valid contact-person relation was rejected');
check(!$contactpersonDb->belongsToContact(12, 1) && !$contactpersonDb->belongsToContact(13, 1) && !$contactpersonDb->belongsToContact(14, 1), 'Foreign, deleted or unrelated contact person was accepted');
$purchaseorderViewModel = (new Purchases_Service_PurchaseorderEditViewModel())->build(1, Zend_Registry::get('User'), ['contactid' => 100]);
$quoterequestViewModel = (new Purchases_Service_QuoterequestEditViewModel())->build(1, Zend_Registry::get('User'), ['contactid' => 100]);
check($purchaseorderViewModel['contactpersonOptions'] === $selectOptions, 'Purchase order did not reload supplier contact persons');
check($quoterequestViewModel['contactpersonOptions'] === $selectOptions, 'Quote request did not reload supplier contact persons');

class PurchaseContactpersonValidationController extends DEEC_Controller_DocumentAction
{
	public function init() {}
	public function validateSelection(array $values, array $row): array { return $this->validateDocumentContactperson($values, $row); }
	public function viewContact(array $row): array { return $this->getViewContact($row); }
}

$request = new Zend_Controller_Request_Http();
$request->setModuleName('purchases')->setControllerName('purchaseorder')->setActionName('edit');
$validationController = new PurchaseContactpersonValidationController($request, new Zend_Controller_Response_Http());
check((int)$validationController->viewContact(['contactid' => 100])['id'] === 1, 'Purchase document did not load its contact for view and email');
check($validationController->validateSelection(['contactpersonid' => 10], ['contactid' => 100, 'contactpersonid' => null])['contactpersonid'] === 10, 'Valid purchase contact person was rejected');
check($validationController->validateSelection(['contactpersonid' => 0], ['contactid' => 100, 'contactpersonid' => 10])['contactpersonid'] === null, 'Empty purchase contact-person selection was not normalized');
check($validationController->validateSelection(['contactid' => 200], ['contactid' => 100, 'contactpersonid' => 10])['contactpersonid'] === null, 'Supplier change did not clear an unrelated contact person');
try {
	$validationController->validateSelection(['contactpersonid' => 14], ['contactid' => 100, 'contactpersonid' => null]);
	throw new RuntimeException('Unrelated purchase contact person was accepted');
} catch (InvalidArgumentException $expected) {
	check($expected->getMessage() === 'Contact person does not belong to the selected contact.', 'Unexpected contact-person validation error');
}

Zend_Registry::set('Client', ['id' => 7, 'parentid' => 70, 'modules' => ['contacts' => 70, 'purchases' => 7]]);
check((int)$validationController->viewContact(['contactid' => 300])['id'] === 3, 'Purchase document did not load the shared parent contact for email');
check($validationController->validateSelection(['contactpersonid' => 15], ['contactid' => 300, 'contactpersonid' => null])['contactpersonid'] === 15, 'Shared parent contact person was rejected');
$sharedPurchaseViewModel = (new Purchases_Service_PurchaseorderEditViewModel())->build(1, Zend_Registry::get('User'), ['contactid' => 300]);
check($sharedPurchaseViewModel['contactpersonOptions'] === ['15' => 'Dr. Max Mustermann'], 'Shared parent contact persons were not loaded for purchase orders');
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0, 'modules' => ['contacts' => 7]]);

$newId = $contactpersonDb->createForParent(1, 'contacts', 'contact', [
    'firstname' => 'John',
    'lastname' => 'Doe',
    'salutation' => '0',
    'title' => '',
    'position' => 'Engineer',
    'department' => 'Development',
]);
check($newId === 101 && $contactpersonDb->getById($newId)['ordering'] === 3, 'Contact person was not created with the expected ordering');

$edit = ['firstname' => '  Jonathan  ', 'position' => 'Lead Engineer'];
check($form->isValidPartial($edit), 'Contact-person auto-save update was rejected');
$contactpersonDb->updateById($newId, $form->getFilteredValuesPartial($edit));
$updated = $contactpersonDb->getById($newId);
check($updated['firstname'] === 'Jonathan' && $updated['position'] === 'Lead Engineer' && $updated['lastname'] === 'Doe', 'Contact-person auto-save changed the wrong fields');
check(count($contactpersonDb->getByParentId(1, 'contacts', 'contact')) === 3, 'Multiple contact persons were not retained');

$emailDb = new Contacts_Model_DbTable_Email();
$recipients = $emailDb->getContactRecipients(1);
check(count($recipients) === 4, 'Company and contact-person email addresses were not loaded together');
check(count(array_filter($recipients, static fn($row) => (int)($row['contactpersonid'] ?? 0) === 10)) === 2, 'Multiple email addresses for one contact person were lost');
check($recipients[0]['contactid'] === 1 && $recipients[0]['contactpersonid'] === null, 'Company email recipient metadata is incorrect');
check($recipients[1]['contactid'] === 1 && $recipients[1]['firstname'] === 'Max', 'Contact-person recipient metadata is incorrect');

$deletedRow = $contactpersonDb->getById(10);
$contactpersonDb->deleteById(10);
$contactpersonDb->normalizeOrderingByRow($deletedRow);
check($contactpersonDb->getById(10) === null, 'Contact person was not soft-deleted');
check($emailDb->getById(21) === null && $emailDb->getById(22) === null, 'Contact-person email addresses were not soft-deleted');
$remaining = $contactpersonDb->getByParentId(1, 'contacts', 'contact');
check(array_column($remaining, 'ordering') === [1, 2], 'Contact-person ordering was split by the business position field');
check($adapter->rows['email'][0]['deleted'] === 0 && $adapter->rows['email'][3]['deleted'] === 0, 'Deleting one contact person changed unrelated email addresses');

$structure = file_get_contents($root . '/install/config/structure.sql');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
$review = file_get_contents($root . '/install/config/contactperson-name-review.sql');
foreach (['firstname', 'lastname', 'position'] as $field) {
    check(str_contains($structure, '`' . $field . '` varchar(255)'), 'Install schema is missing ' . $field);
    check(str_contains($migration, 'ADD `' . $field . '`'), 'Incremental migration is missing ' . $field);
}
check(!str_contains(strtoupper($migration), 'DROP '), 'Migration removes legacy fields prematurely');
check(str_contains($migration, '`name1`') && str_contains($migration, '`name2`'), 'Migration does not preserve provisional legacy names');
check(str_contains($review, 'verify_name_order') && str_contains($review, 'split_or_verify_single_name') && str_contains($review, 'missing_name'), 'Manual review query does not identify ambiguous names');
check(str_contains($structure, '`gender`') && str_contains($structure, '`type`'), 'Deferred legacy fields were removed prematurely');

foreach (['de_DE', 'en_US'] as $locale) {
    $translations = file_get_contents($root . '/languages/' . $locale . '/contacts/contactperson.php');
    foreach (['FIRSTNAME', 'LASTNAME', 'POSITION', 'SALUTATION_NONE', 'SALUTATION_MR', 'SALUTATION_MS'] as $key) {
        check(str_contains($translations, 'CONTACTS_CONTACT_PERSONS_' . $key), $locale . ' translation is missing ' . $key);
    }
}

echo 'PASS: ' . $checks . " contact-person model, form, migration and email checks\n";
