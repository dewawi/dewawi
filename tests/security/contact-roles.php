<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/security/contact-roles.php (in-memory database).
ob_start();
require __DIR__ . '/contact-tags.php';
ob_end_clean();

class ContactRoleTestTranslator
{
	public function t(string $key): string
	{
		return [
			'CONTACTS_ROLE_CUSTOMER' => 'Customer',
			'CONTACTS_ROLE_SUPPLIER' => 'Supplier',
			'CONTACTS_ROLE_PROSPECT' => 'Prospect',
			'CONTACTS_ROLE_PARTNER' => 'Partner',
		][$key] ?? $key;
	}
}

$checks = 0;
contactTagFixtures();
Zend_Registry::set('User', ['id' => 42, 'admin' => 0, 'clientid' => 7]);
Zend_Registry::set('Client', ['id' => 7, 'parentid' => 0]);
Zend_Registry::set('DEEC_Translate', new ContactRoleTestTranslator());

Zend_Db_Table_Abstract::$rows['contactrole'] = [
	41 => ['id' => 41, 'parentid' => 11, 'role' => 'customer', 'clientid' => 7, 'created' => null, 'createdby' => 42, 'modified' => null, 'modifiedby' => 0, 'deleted' => 0],
	42 => ['id' => 42, 'parentid' => 11, 'role' => 'supplier', 'clientid' => 7, 'created' => null, 'createdby' => 42, 'modified' => null, 'modifiedby' => 0, 'deleted' => 1],
	43 => ['id' => 43, 'parentid' => 11, 'role' => 'partner', 'clientid' => 8, 'created' => null, 'createdby' => 9, 'modified' => null, 'modifiedby' => 0, 'deleted' => 0],
	44 => ['id' => 44, 'parentid' => 70, 'role' => 'prospect', 'clientid' => 70, 'created' => null, 'createdby' => 70, 'modified' => null, 'modifiedby' => 0, 'deleted' => 0],
];

$roleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId(7);
$beforeSelects = count(Zend_Db_Table_Abstract::$selects);
$roles = $roleDb->getForContacts([11, 12]);
check(count(Zend_Db_Table_Abstract::$selects) === $beforeSelects + 1, 'Contact roles were not loaded in one query');
check(($roles[11] ?? []) === ['customer'] && !isset($roles[12]), 'Role loading included deleted or foreign assignments');

$saved = $roleDb->syncForContact(11, ['supplier', 'customer', 'supplier']);
check($saved === ['customer', 'supplier'], 'Role normalization is not stable or unique');
check((int)Zend_Db_Table_Abstract::$rows['contactrole'][42]['deleted'] === 0, 'Soft-deleted role assignment was not restored');
check($roleDb->getForContact(11) === ['customer', 'supplier'], 'Multiple roles were not saved and loaded again');

$rowCount = count(Zend_Db_Table_Abstract::$rows['contactrole']);
$roleDb->syncForContact(11, ['customer', 'supplier']);
check(count(Zend_Db_Table_Abstract::$rows['contactrole']) === $rowCount, 'Repeated role save created duplicate assignments');

$saved = $roleDb->syncForContact(11, ['supplier', 'partner']);
check($saved === ['supplier', 'partner'], 'Role replacement returned unexpected roles');
check((int)Zend_Db_Table_Abstract::$rows['contactrole'][41]['deleted'] === 1, 'Removed role assignment was not soft-deleted');
check($roleDb->getForContact(11) === ['supplier', 'partner'], 'Added and removed roles were not persisted');
check((int)Zend_Db_Table_Abstract::$rows['contact'][11]['catid'] === 3, 'Role updates changed the contact category');

$roleDb->syncForContact(11, []);
check($roleDb->getForContact(11) === [], 'All roles could not be removed');
$roleDb->syncForContact(11, []);
check($roleDb->getForContact(11) === [], 'Repeated empty role save is not idempotent');

try {
	Contacts_Model_DbTable_Contactrole::normalizeRoleCodes(['customer', 'invalid']);
	throw new RuntimeException('Invalid role was accepted');
} catch (InvalidArgumentException $expected) {
	check(true, 'Invalid role was rejected');
}

$parentRoleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId(70);
check($parentRoleDb->getForContact(70) === ['prospect'], 'Shared parent tenant role was not loaded');
check($roleDb->getForContact(70) === [], 'Foreign tenant role was exposed');

$list = new Contacts_Model_List_Contacts(['options' => ['contactRoles' => [11 => ['customer', 'supplier']]]]);
$html = $list->renderRoles((object)['id' => 11]);
check(str_contains($html, 'Customer') && str_contains($html, 'Supplier'), 'Contact list role badges are missing');

$request = new Zend_Controller_Request_Http();
$request->setModuleName('contacts')->setControllerName('contact')->setActionName('index');
Zend_Controller_Front::getInstance()->setRequest($request);
$params = ['keyword' => 'Own', 'catid' => '3', 'tagid' => 21, 'role' => 'supplier', 'country' => '0'];
$options = ['catid' => [3 => ['childs' => [4]], 4 => ['childs' => []]]];
$select = (new ContactTagTestListQuery())->build($params, $options, Contacts_Model_Entity_Contact::listConfig());
$where = implode(' ', $select->getPart(Zend_Db_Select::WHERE));
check(str_contains($where, 'EXISTS (SELECT 1 FROM `contactrole`') && str_contains($where, "'supplier'"), 'Role filter does not use the role relation');
check(str_contains($where, 'c.catid IN') && str_contains($where, 'LIKE'), 'Role filter lost category or search filters');
check(isset($select->getPart(Zend_Db_Select::FROM)['tag_filter']), 'Role filter lost the tag filter');
check($select->getPart(Zend_Db_Select::GROUP) === ['c.id'], 'Combined role and tag filter can duplicate contacts');

$form = new Contacts_Form_Contact();
check(($form->getElement('roles')['type'] ?? '') === 'multicheckbox', 'Contact role multi-selection is missing');
$form->setValue('roles', ['customer', 'supplier']);
$formHtml = $form->renderElement('roles');
check(str_contains($formHtml, 'name="roles[]"') && substr_count($formHtml, ' checked') === 2, 'Saved roles are not selected in the contact form');
check(str_contains($formHtml, 'type="hidden"'), 'Empty multi-selection cannot be submitted');
$toolbar = new Contacts_Form_Toolbar();
check(($toolbar->getElement('role')['options']['supplier'] ?? '') === 'CONTACTS_ROLE_SUPPLIER', 'Contact role filter is missing');

$controllerSource = file_get_contents($root . '/application/modules/contacts/controllers/ContactController.php');
$globalSource = file_get_contents($root . '/js/global.js');
$migration = file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql');
check(str_contains($controllerSource, "unset(\$post['roles'])") && str_contains($controllerSource, "unset(\$values['roles'])"), 'Roles can leak into contact table updates');
check(substr_count($controllerSource, 'getByIdForUpdate($id)') >= 2, 'Role updates do not lock and revalidate the authorized contact');
check(str_contains($globalSource, "fieldName.slice(-2) === '[]'") && str_contains($globalSource, "selected.length ? selected : ['']"), 'Multi-checkbox auto-save cannot add and remove roles');
check(str_contains($migration, 'UNIQUE KEY `clientid_parentid_role`') && str_contains($migration, "IN ('customer', 'supplier', 'prospect', 'partner')"), 'Role migration lacks uniqueness or explicit legacy mapping');
check(!str_contains($migration, 'category') && !str_contains($migration, 'invoice') && !str_contains($migration, 'purchaseorder'), 'Role migration infers roles from business usage');
check(!str_contains($migration, 'UPDATE `contact`') && !str_contains($migration, 'ALTER TABLE `contact`'), 'Role migration modifies the legacy contact.type field');
$germanMessages = require $root . '/languages/de_DE/default/messages.php';
$englishMessages = require $root . '/languages/en_US/default/messages.php';
check(($germanMessages['MESSAGES_INVALID_CONTACT_ROLES'] ?? '') !== '' && ($englishMessages['MESSAGES_INVALID_CONTACT_ROLES'] ?? '') !== '', 'Invalid role feedback is not translated');

$salesFiles = file_get_contents($root . '/application/modules/sales/services/CreateDataFactory.php');
$purchaseFiles = file_get_contents($root . '/application/modules/purchases/services/CreateDataFactory.php');
check(!str_contains($salesFiles, 'contactrole') && !str_contains($purchaseFiles, 'contactrole'), 'Document creation was restricted by contact roles');

echo 'PASS: ' . $checks . " contact role cases\n";
