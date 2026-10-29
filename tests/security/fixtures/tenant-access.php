<?php

// Isolated fixtures shared by CLI and Apache object-authorization tests.
$root = dirname(__DIR__, 3);
set_include_path($root . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function (string $class) use ($root): void {
    if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) { require_once $path; return; }
    $parts = explode('_', $class);
    if (count($parts) < 3) return;
    $module = array_shift($parts);
    $base = $module === 'Application' ? '/application/' : '/application/modules/' . strtolower($module) . '/';
    $path = $root . $base . strtolower(array_shift($parts)) . 's/' . implode('/', $parts) . '.php';
    if (is_file($path)) require_once $path;
});
require_once __DIR__ . '/entity.php';
require_once $root . '/application/modules/contacts/controllers/ContactpersonController.php';
require_once $root . '/application/controllers/TrashController.php';
require_once $root . '/application/plugins/Acl.php';
require_once $root . '/application/plugins/State.php';

class TenantAccessFlash extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'FlashMessenger'; }
    public function addMessage($message) { return $this; }
}

class TenantAccessContactpersonController extends Contacts_ContactpersonController
{
    public function init()
    {
        $this->_user = Zend_Registry::get('User');
        $this->_flashMessenger = $this->_helper->FlashMessenger;
    }

    protected function createRowForm(): DEEC_Form
    {
        $form = new Contacts_Form_Contactperson();
        foreach (['firstname', 'lastname', 'parentid', 'clientid', 'module', 'controller'] as $field) $form->addElement(['name' => $field, 'type' => 'text', 'format' => ['type' => 'string']]);
        return $form;
    }
}

function tenantAccessFixture(string $mode = 'full', int $selected = 7): ContactpersonTestAdapter
{
    $adapter = new ContactpersonTestAdapter(['host' => 'unused', 'username' => 'unused', 'password' => 'unused', 'dbname' => 'unused']);
    Zend_Db_Table::setDefaultAdapter($adapter);
    Zend_Registry::set('User', ['id' => 42, 'clientid' => $selected, 'admin' => 0]);
    Zend_Registry::set('Client', ['id' => $selected, 'parentid' => 1, 'modules' => ['contacts' => 1, 'items' => 1, 'sales' => $selected, 'purchases' => $selected, 'processes' => $selected]]);
    Zend_Registry::set('Zend_Locale', new Zend_Locale('en_US'));
    $auth = Zend_Auth::getInstance();
    $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
    if ($mode !== 'guest') $auth->getStorage()->write((object)['id' => 42, 'clientid' => $selected]);
    $rights = $mode === 'full' ? ['view', 'add', 'edit', 'delete'] : ($mode === 'readonly' ? ['view'] : []);
    $adapter->rows = [
        'permission' => [['id' => 1, 'userid' => 42, 'clientid' => 7, 'deleted' => 0, 'contacts' => json_encode(['contact' => $rights]), 'items' => json_encode(['item' => $rights]), 'sales' => json_encode(['quote' => $rights])]],
        'contact' => [
            ['id' => 10, 'contactid' => 100, 'clientid' => 1, 'deleted' => 0],
            ['id' => 20, 'contactid' => 100, 'clientid' => 9, 'deleted' => 0],
            ['id' => 30, 'contactid' => 100, 'clientid' => 1, 'deleted' => 1],
        ],
        'contactperson' => [
            ['id' => 11, 'clientid' => 1, 'parentid' => 10, 'module' => 'contacts', 'controller' => 'contact', 'firstname' => 'Allowed', 'lastname' => 'Person', 'deleted' => 0, 'ordering' => 1],
            ['id' => 21, 'clientid' => 9, 'parentid' => 20, 'module' => 'contacts', 'controller' => 'contact', 'firstname' => 'Foreign secret', 'deleted' => 0, 'ordering' => 1],
            ['id' => 22, 'clientid' => 1, 'parentid' => 20, 'module' => 'contacts', 'controller' => 'contact', 'firstname' => 'Invalid parent secret', 'deleted' => 0, 'ordering' => 1],
        ],
        'item' => [['id' => 101, 'clientid' => 1, 'deleted' => 0, 'parentid' => 0, 'sku' => 'shared'], ['id' => 102, 'clientid' => 9, 'deleted' => 0, 'parentid' => 0]],
        'warehouse' => [['id' => 201, 'clientid' => 1, 'deleted' => 0], ['id' => 202, 'clientid' => 9, 'deleted' => 0]],
        'quote' => [
            ['id' => 301, 'contactid' => 100, 'clientid' => 7, 'deleted' => 0, 'state' => 100, 'quoteid' => 0],
            ['id' => 302, 'contactid' => 100, 'clientid' => 8, 'deleted' => 0, 'state' => 100, 'quoteid' => 0],
        ],
        'quotepos' => [['id' => 401, 'clientid' => 7, 'parentid' => 301, 'deleted' => 0], ['id' => 402, 'clientid' => 8, 'parentid' => 302, 'deleted' => 0]],
    ];
    return $adapter;
}

function tenantAccessDispatch(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response): void
{
    $front = Zend_Controller_Front::getInstance();
    $front->setControllerDirectory(['default' => dirname(__DIR__, 3) . '/application/controllers', 'contacts' => dirname(__DIR__, 3) . '/application/modules/contacts/controllers']);
    $front->setRequest($request)->setResponse($response);
    Zend_Controller_Action_HelperBroker::addHelper(new TenantAccessFlash());
    Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
    Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->setExit(false);
    Zend_Layout::startMvc();
    $acl = new Application_Plugin_Acl();
    $acl->setResponse($response);
    $acl->routeShutdown($request);
    if ($response->getHttpResponseCode() === 403 || $request->getModuleName() === 'users') {
        $response->setHttpResponseCode(403)->setBody(json_encode(['ok' => false, 'message' => 'MESSAGES_ACCESS_DENIED']));
        return;
    }
    $client = Zend_Registry::get('Client');
    $client['id'] = $client['modules'][$request->getModuleName()] ?? $client['id'];
    Zend_Registry::set('Client', $client);
    if ($request->getModuleName() === 'default' && $request->getControllerName() === 'trash') {
        (new TrashController($request, $response))->addAction();
    } elseif ($request->getModuleName() === 'contacts' && $request->getControllerName() === 'contactperson' && in_array($request->getActionName(), ['get', 'edit', 'copy'], true)) {
        $controller = new TenantAccessContactpersonController($request, $response);
        $controller->{$request->getActionName() . 'Action'}();
    } else {
        $response->setHttpResponseCode(404)->setBody(json_encode(['ok' => false]));
    }
}
