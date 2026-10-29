<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with: php tests/documents/calculation.php [sales|purchases|processes].
// Reuse the real models, helpers and isolated in-memory adapter from the read regression suite.
ob_start();
require __DIR__ . '/position-read.php';
ob_end_clean();
Zend_Session::$_unitTestEnabled = true;
Zend_Registry::set('User', ['id' => 42, 'name' => 'Fixture user', 'clientid' => 7]);
$adapter->rows['permission'] = [['id' => 1, 'userid' => 42, 'clientid' => 7, 'deleted' => 0]];
foreach ($modules as $module => $documents) $adapter->rows['permission'][0][$module] = json_encode(array_fill_keys($documents, ['view', 'edit', 'add', 'delete']));
require_once $root . '/application/modules/items/controllers/PriceruleposController.php';
foreach (['sales', 'purchases', 'processes', 'items'] as $testModule) require_once $root . '/application/modules/' . $testModule . '/controllers/PositionsetController.php';
$moduleUnderTest = $argv[1] ?? 'sales';
if (!in_array($moduleUnderTest, ['sales', 'purchases', 'processes'], true)) throw new InvalidArgumentException('Invalid test module');
require_once $root . '/application/modules/' . $moduleUnderTest . '/controllers/helpers/Positions.php';

class CalculationTestOptions extends Zend_Controller_Action_Helper_Abstract
{
    public function getName() { return 'Options'; }
    public function applyFormOptions($form): array { return []; }
}
class CalculationTestOrderingSet extends Zend_Controller_Action_Helper_Abstract
{
    public string $name = 'OrderingSet';
    public function getName() { return $this->name; }
    public function getOrdering(...$args) { return [1 => 1]; }
    public function getLatestOrdering($parent, $type, $parentid)
    {
        global $adapter;
        return count(array_filter($adapter->rows[$parent . $type . 'set'], static fn($row) => !$row['deleted'] && $row['parentid'] === $parentid));
    }
    public function pushOrdering(...$args) {}
    public function setOrdering(...$args) {}
}
trait CalculationSetInit
{
    public function init() { $this->_user = Zend_Registry::get('User'); $this->_date = date('Y-m-d H:i:s'); $this->view = new Zend_View(); }
}
class CalculationSalesSetController extends Sales_PositionsetController { use CalculationSetInit; }
class CalculationPurchasesSetController extends Purchases_PositionsetController { use CalculationSetInit; }
class CalculationProcessesSetController extends Processes_PositionsetController { use CalculationSetInit; }
class CalculationPriceRuleController extends Items_PriceruleposController
{
    public function init() { $this->_user = Zend_Registry::get('User'); }
}
class CalculationSaveDocumentController extends CalculationDocumentController
{
    public function changed(array $values): array
    {
        $db = $this->getDb();
        $old = $db->getById(1);
        $db->updateById(1, $values);
        $this->afterEditSave(1, $values, $old);
        return ['calculation' => $this->_saveCalculation, 'reloadPositions' => $this->_reloadPositions];
    }
}
class CalculationFailFinalizeController extends CalculationDocumentController
{
    protected function finalizeDocument(array $row): array { throw new RuntimeException('Simulated finalization failure'); }
}

Zend_Controller_Action_HelperBroker::addHelper(new CalculationTestOptions());
Zend_Controller_Action_HelperBroker::addHelper(new CalculationTestOrderingSet());
$ordering = new CalculationTestOrderingSet();
$ordering->name = 'Ordering';
Zend_Controller_Action_HelperBroker::addHelper($ordering);
Zend_Controller_Front::getInstance()->addControllerDirectory($root . '/application/modules/items/controllers', 'items');
Zend_Controller_Action_HelperBroker::getStaticHelper('json')->suppressExit = true;
$adapter->allowWrites = true;
$helper = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate');
$draft = array_replace($parent, ['state' => 100]);
$position['manufacturerid'] = 0;
$child['manufacturerid'] = 0;

function closeAmount($actual, float $expected, string $message): void
{
    check(abs($actual - $expected) < 0.000001, $message . ': ' . $actual . ' instead of ' . $expected);
}

foreach ($modules as $module => $documents) {
    foreach ($documents as $document) {
        $adapter->rows[$document] = [$draft + [$document . 'id' => null]];
        $adapter->rows[$document . 'pos'] = [$position, $child, array_replace($position, ['id' => 99, 'clientid' => 8]), array_replace($position, ['id' => 100, 'deleted' => 1])];
        $adapter->rows[$document . 'posset'] = [];
        $adapter->rows['pricerulepos'] = [array_replace($rule, ['module' => $module, 'controller' => $document . 'pos'])];
        $result = $helper->direct(1, $module, $document);
        closeAmount($result['row']['subtotal'], 234, 'Inherited discount subtotal');
        closeAmount($result['row']['total'], 278.46, 'Inherited discount total');
        closeAmount($adapter->rows[$document . 'pos'][0]['total'], 214.20, 'Stored master gross total');
        closeAmount($adapter->rows[$document . 'pos'][1]['total'], 64.26, 'Stored child gross total');
        check($adapter->rows[$document . 'pos'][2]['total'] === 999 && $adapter->rows[$document . 'pos'][3]['total'] === 999, 'Calculation changed foreign/deleted positions');

        $adapter->rows[$document][0]['taxfree'] = 1;
        $result = $helper->direct(1, $module, $document);
        closeAmount($result['row']['total'], 234, 'Tax-free total');
        foreach ($result['row']['taxes'] as $tax) closeAmount($tax, 0, 'Tax exemption retained tax');
        closeAmount($adapter->rows[$document . 'pos'][0]['total'], 180, 'Tax-free stored master total');
        closeAmount($adapter->rows[$document . 'pos'][1]['total'], 54, 'Tax-free stored child total');
        $adapter->rows[$document][0]['taxfree'] = 0;
        closeAmount($helper->direct(1, $module, $document)['row']['total'], 278.46, 'Disabling tax exemption');

        $adapter->rows[$document . 'pos'][0]['pricerulemaster'] = 0;
        closeAmount($helper->direct(1, $module, $document)['row']['subtotal'], 240, 'Disabling inherited discount');
        $adapter->rows[$document . 'pos'][0]['pricerulemaster'] = 1;
        foreach (['bypercent' => 234, 'topercent' => 286, 'byfixed' => 210, 'tofixed' => 310] as $action => $expected) {
            $adapter->rows['pricerulepos'][0]['action'] = $action;
            closeAmount($helper->direct(1, $module, $document)['row']['subtotal'], $expected, 'Price rule action ' . $action);
        }
        $adapter->rows['pricerulepos'] = [];
        closeAmount($helper->direct(1, $module, $document)['row']['subtotal'], 260, 'Removing price rule');

        // Round net positions first, then each tax group; fractional rates must remain distinct.
        $adapter->rows[$document . 'pos'] = [
            array_replace($position, ['price' => 0.025, 'quantity' => 1, 'taxrate' => '19.0000']),
            array_replace($position, ['id' => 11, 'price' => 0.025, 'quantity' => 1, 'taxrate' => '19.0000']),
            array_replace($position, ['id' => 12, 'price' => 0.10, 'quantity' => 1, 'taxrate' => '7.0000']),
            array_replace($position, ['id' => 13, 'price' => 100, 'quantity' => 1, 'taxrate' => '7.7000']),
            array_replace($position, ['id' => 14, 'price' => 100, 'quantity' => 1, 'taxrate' => '7.9000']),
        ];
        $result = $helper->direct(1, $module, $document);
        closeAmount($result['row']['subtotal'], 200.16, 'Rounded net subtotal');
        closeAmount($result['row']['taxes'][19], 0.01, 'Rounded 19% tax group');
        closeAmount($result['row']['taxes'][7], 0.01, 'Rounded 7% tax group');
        closeAmount($result['row']['taxes']['7.7'], 7.70, 'Fractional 7.7% rate');
        closeAmount($result['row']['taxes']['7.9'], 7.90, 'Fractional 7.9% rate');
        closeAmount($result['row']['taxes']['total'], 15.62, 'Sum of rounded tax groups');
        closeAmount($result['row']['total'], 215.78, 'Rounded document total');

        // Display tax groups must use the same rounded net bases, even for old tax rates.
        $request = new Zend_Controller_Request_Http();
        $request->setModuleName($module)->setControllerName('position')->setActionName('index')->setParams(['parent' => $document, 'parentid' => 1]);
        $class = 'Read' . ucfirst($module) . 'PositionController';
        $controller = new $class($request, new Zend_Controller_Response_Http());
        $before = $adapter->rows;
        $controller->indexAction();
        check($controller->view->parent['taxes'][19]['value'] === '0,01 €', 'Displayed 19% tax differs from saved value');
        check($controller->view->parent['taxes']['7.7']['value'] === '7,70 €', 'Unlisted tax rate disappeared from display');
        check($adapter->rows === $before, 'Displaying taxes changed data');

        if ($module === $moduleUnderTest) {
            $pdfHelper = new Application_Controller_Action_Helper_Positions();
            [$pdfPositions, $pdfDocument] = $pdfHelper->getPositions(1, $draft + [$document . 'id' => null], Zend_Registry::get('Zend_Locale'), $document);
            check($pdfDocument['total'] === '215,78 €' && $pdfDocument['taxgroups'][19] === '0,01 €', 'PDF preview retained stale totals');
            check(count($pdfDocument['taxgroups']) === 4 && $pdfDocument['taxrate'] === null, 'PDF lost mixed tax rates');
            check($pdfPositions[0]->total === '0,03 €', 'PDF net position rounding differs');
            check($adapter->rows === $before, 'PDF payload changed stored values');
            $serviceClass = ucfirst($module) . '_Service_DocumentPayloadService';
            $paymentMethod = new ReflectionMethod($serviceClass, 'buildPaymentData');
            $paymentMethod->setAccessible(true);
            $payment = $paymentMethod->invoke(new $serviceClass(), $pdfDocument + ['prepayment' => 100]);
            closeAmount($payment['balance_raw'], 115.78, 'PDF balance used stale/formatted total');
            [, $issuedPdf] = $pdfHelper->getPositions(1, $parent + [$document . 'id' => 1001], Zend_Registry::get('Zend_Locale'), $document);
            check($issuedPdf['total'] === '1.198,00 €' && $issuedPdf['taxes'] === '199,00 €', 'Historical PDF totals were changed');
        }

        // Negative credit positions and an empty document must also be rounded and reset.
        $adapter->rows[$document . 'pos'] = [array_replace($position, ['price' => 12.345, 'quantity' => -2])];
        closeAmount($helper->direct(1, $module, $document)['row']['total'], -29.38, 'Negative position total');
        $adapter->rows[$document . 'pos'] = [];
        $result = $helper->direct(1, $module, $document);
        closeAmount($result['row']['total'], 0, 'Deleting last position retained total');
        closeAmount($adapter->rows[$document][0]['subtotal'], 0, 'Empty document retained subtotal');
    }
}

// Real position edit action: multiple fields must all be normalized and recalculated.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$adapter->rows['quote'] = [$draft + ['quoteid' => null]];
$adapter->rows['quotepos'] = [$position, $child];
$adapter->rows['pricerulepos'] = [$rule];
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('position')->setActionName('edit')
    ->setParams(['id' => 10, 'parent' => 'quote', 'parentid' => 1])
    ->setPost(['quantity' => '1,234', 'price' => '12,345', 'taxrate' => 1, 'uom' => 1]);
$response = new Zend_Controller_Response_Http();
Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
$controller = new ReadSalesPositionController($request, $response);
$controller->editAction();
$json = json_decode($response->getBody(), true);
check($json['ok'] && $json['reloadPositions'], 'Position edit did not return calculation/reload');
closeAmount($adapter->rows['quotepos'][0]['quantity'], 1.234, 'Quantity precision lost');
closeAmount($adapter->rows['quotepos'][0]['price'], 12.345, 'Price precision lost');
closeAmount($adapter->rows['quotepos'][0]['taxrate'], 19, 'Tax ID was not mapped in multi-field save');
check($adapter->rows['quotepos'][0]['uom'] === 'Piece', 'Unit ID was not mapped in multi-field save');
closeAmount($adapter->rows['quote'][0]['subtotal'], 67.71, 'Edited position subtotal');

// Real rule edits must recalculate the master and its children, including localized amounts.
foreach ([[['amount' => '20,00'], 60.19], [['action' => 'tofixed'], 159.91]] as [$post, $expected]) {
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('items')->setControllerName('pricerulepos')->setActionName('edit')->setParam('id', 20)->setPost($post);
    $response = new Zend_Controller_Response_Http();
Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $controller = new CalculationPriceRuleController($request, $response);
    $controller->editAction();
    $json = json_decode($response->getBody(), true);
    check($json['ok'] && $json['reloadPositions'], 'Rule edit did not return updated calculations');
    closeAmount($adapter->rows['quote'][0]['subtotal'], $expected, 'Rule edit subtotal');
}

// Every document edit refreshes sums; tax exemption also reloads the tax rows.
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote');
$controller = new CalculationSaveDocumentController($request, new Zend_Controller_Response_Http());
$adapter->rows['quote'][0]['total'] = 999;
$response = $controller->changed(['subject' => 'Adjusted draft']);
closeAmount($adapter->rows['quote'][0]['total'], 190.29, 'Non-price document edit retained stale total');
check($response['reloadPositions'], 'Document edit did not refresh positions');
$controller->changed(['taxfree' => 1]);
closeAmount($adapter->rows['quote'][0]['total'], 159.91, 'Document tax exemption failed');
closeAmount($adapter->rows['quotepos'][0]['total'], 39.91, 'Tax exemption did not update position gross');

// A failed recalculation must roll back the triggering position or rule edit.
foreach (['position', 'pricerulepos'] as $target) {
    $before = $adapter->rows;
    $adapter->failUpdateTable = 'quote';
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($target === 'position' ? 'sales' : 'items')->setControllerName($target)->setActionName('edit')
        ->setParams(['id' => $target === 'position' ? 10 : 20, 'parent' => 'quote', 'parentid' => 1])
        ->setPost($target === 'position' ? ['price' => '99,00'] : ['amount' => '99,00']);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $class = $target === 'position' ? ReadSalesPositionController::class : CalculationPriceRuleController::class;
    (new $class($request, $response))->editAction();
    $json = json_decode($response->getBody(), true);
    check($json['ok'] === false && $json['message'] === 'save_failed', 'Calculation failure was reported as success');
    check($adapter->rows === $before, 'Failed recalculation retained a partial edit');
    $adapter->failUpdateTable = null;
}

// Finalization failures must restore all totals and metadata written in the transaction.
$adapter->rows['quote'][0]['total'] = 999;
$before = $adapter->rows;
$request = new Zend_Controller_Request_Http();
$request->setModuleName('sales')->setControllerName('quote');
try {
    (new CalculationFailFinalizeController($request, new Zend_Controller_Response_Http()))->issue(1);
    throw new LogicException('Failed finalization unexpectedly succeeded');
} catch (RuntimeException $expected) {
    check($expected->getMessage() === 'Simulated finalization failure', 'Unexpected finalization failure');
}
check($adapter->rows === $before, 'Failed finalization retained recalculated totals');

// Recheck the parent under the lock, including a completion since the initial page load.
foreach (['quoteid' => 1001, 'clientid' => 8, 'deleted' => 1] as $field => $value) {
    $adapter->rows['quote'][0] = array_replace($draft, ['quoteid' => null, $field => $value]);
    $before = $adapter->rows;
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName('sales')->setControllerName('position')->setActionName('edit')
        ->setParams(['id' => 10, 'parent' => 'quote', 'parentid' => 1])->setPost(['price' => '99,00']);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    (new ReadSalesPositionController($request, $response))->editAction();
    check(json_decode($response->getBody(), true)['ok'] === false, 'Inaccessible/completed parent accepted an edit');
    check($adapter->rows === $before, 'Rejected edit changed totals or positions');
}

// Group copy must remap children and copy their inherited price rules.
foreach ($modules as $module => $documents) {
    $document = $documents[0];
    $adapter->rows[$document] = [$draft + [$document . 'id' => null]];
    $adapter->rows[$document . 'pos'] = [array_replace($position, ['possetid' => 1]), array_replace($child, ['possetid' => 1])];
    $adapter->rows['pricerulepos'] = [array_replace($rule, ['module' => $module, 'controller' => $document . 'pos'])];
    $class = ucfirst($module) . '_Model_DbTable_' . ucfirst($document) . 'pos';
    $positions = (new $class())->getPositions(1, 1);
    Zend_Controller_Action_HelperBroker::getStaticHelper('Position')->copyPositions($positions, 1, $module, $document, date('Y-m-d H:i:s'), 2);
    $copied = (new $class())->getPositions(1, 2)->toArray();
    check(count($copied) === 2 && $copied[1]['masterid'] === $copied[0]['id'], 'Group copy did not remap child');
    $copiedRules = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule')->getPriceRulePositions($module, $document . 'pos', $copied[0]['id']);
    check(count($copiedRules) === 1 && $copiedRules[0]['amount'] === 10, 'Group copy lost price rule');
    closeAmount($adapter->rows[$document][0]['total'], 556.92, 'Group copy did not recalculate both groups');
}

// Exercise the legacy set controllers with their real models, including purchase/process routing.
foreach ($modules as $module => $documents) {
    $document = $documents[0];
    $adapter->rows[$document] = [$draft + [$document . 'id' => null]];
    $adapter->rows[$document . 'posset'] = [['id' => 1, 'clientid' => 7, 'parentid' => 1, 'deleted' => 0, 'ordering' => 1, 'title' => 'Original group', 'image' => '', 'description' => '', 'modified' => null, 'modifiedby' => 0]];
    $adapter->rows[$document . 'pos'] = [array_replace($position, ['possetid' => 1]), array_replace($child, ['possetid' => 1])];
    $adapter->rows['pricerulepos'] = [array_replace($rule, ['module' => $module, 'controller' => $document . 'pos'])];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($module)->setControllerName('positionset')->setActionName('copy')
        ->setParams(['module' => $module, 'controller' => 'positionset', 'action' => 'copy', 'parent' => $document, 'parentid' => 1, 'type' => 'pos', 'id' => 1])->setPost([]);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $class = 'Calculation' . ucfirst($module) . 'SetController';
    $controller = new $class($request, $response);
    ob_start();
    $controller->copyAction();
    check(ob_get_clean() === '', 'Set copy emitted a second JSON document');
    $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    check($result['ok'] === true && $result['reloadPositions'] === true && $result['calculation']['total'] === $result['total'], 'Set copy lacks confirmed success or legacy calculation keys');
    check(isset($result[10]) && !isset($result[0]), 'Numeric position calculation keys were reindexed');
    check($result['total'] === '556,92 €', 'Set copy action returned stale/malformed totals');
    $newSet = $adapter->rows[$document . 'posset'][1]['id'];
    check($newSet !== 1 && count($adapter->rows[$document . 'pos']) === 4, 'Set action copied to the wrong table');

    $request->setActionName('delete')->setPost(['delete' => 'Yes', 'id' => $newSet, 'parentid' => 1]);
    ob_start();
    $controller->deleteAction();
    check(ob_get_clean() === '', 'Set delete emitted a second JSON document');
    $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    check($result['ok'] === true && $result['reloadPositions'] === true && $result['calculation']['total'] === $result['total'], 'Set delete lacks confirmed success or legacy totals');
    check($result['total'] === '278,46 €', 'Set delete action did not recalculate remaining group');

    $before = $adapter->rows;
    $adapter->failUpdateTable = $document;
    $request->setActionName('copy')->setPost([]);
    ob_start();
    try {
        $controller->copyAction();
        throw new LogicException('Failed group calculation succeeded');
    } catch (RuntimeException $expected) {
        check($expected->getMessage() === 'Simulated calculation write failure', 'Unexpected group failure');
    } finally {
        ob_end_clean();
        $adapter->failUpdateTable = null;
    }
    check($adapter->rows === $before, 'Failed group copy retained partial positions/rules');
}

// Validate the single-JSON group contracts against real controllers and in-memory writes.
foreach ([$moduleUnderTest, 'items'] as $groupModule) {
    $document = $groupModule === 'items' ? 'item' : $modules[$groupModule][0];
    $type = $groupModule === 'items' ? 'opt' : 'pos';
    $setRow = ['id' => 1, 'clientid' => 7, 'parentid' => 1, 'deleted' => 0, 'ordering' => 1, 'title' => 'Group', 'image' => '', 'description' => '', 'price' => 10, 'quantity' => 1, 'taxrate' => 19, 'modified' => null, 'modifiedby' => 0];
    $adapter->rows[$document] = [$draft + [$document . 'id' => null]];
    $adapter->rows[$document . $type . 'set'] = [$setRow];
    $adapter->rows[$document . $type] = [array_replace($position, [$type . 'setid' => 1])];
    $adapter->rows['pricerulepos'] = [];
    $request = new Zend_Controller_Request_Http();
    $request->setModuleName($groupModule)->setControllerName('positionset')->setActionName('edit')
        ->setParams(['module' => $groupModule, 'controller' => 'positionset', 'action' => 'edit', 'parent' => $document, 'parentid' => 1, 'type' => $type, 'id' => 1]);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    $class = $groupModule === 'items' ? Items_PositionsetController::class : 'Calculation' . ucfirst($groupModule) . 'SetController';
    $controller = new $class($request, $response);
    foreach (['title' => 'Changed group', 'price' => '12,50'] as $field => $value) {
        $response->clearBody();
        $_POST = [$field => $value];
        ob_start();
        $controller->editAction();
        check(ob_get_clean() === '', 'Group edit emitted concatenated JSON');
        $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        check($result['ok'] === true && $result['id'] === 1, 'Group edit did not confirm its own row');
        check($field === 'title' ? $result['title'] === $value : (float)$result['price'] === 12.5, 'Group edit lost locale or row values');
        if ($groupModule !== 'items' && $field === 'price') check($result['calculation']['total'] === $result['total'] && isset($result[10]), 'Group edit lost nested/legacy calculation or numeric position keys');
        else check($result['reloadPositions'] === false, 'Text/item group edit requested an unnecessary calculation refresh: ' . $groupModule . '/' . $field);
    }
    $before = $adapter->rows;
    $response->clearBody();
    $_POST = ['nonexistent' => 'Rejected'];
    try {
        $controller->editAction();
        throw new LogicException('Invalid group edit succeeded');
    } catch (Exception $expected) {
        check($expected->getMessage() === 'Form is invalid', 'Unexpected group validation error');
    }
    check($response->getBody() === '' && $adapter->rows === $before, 'Invalid group edit confirmed success or changed data');

    foreach ([true, false] as $existing) {
        $adapter->rows[$document . $type . 'set'] = $existing ? [$setRow] : [];
        $adapter->rows[$document . $type] = [array_replace($position, [$type . 'setid' => $existing ? 1 : 0])];
        $_POST = [];
        $request->setActionName('add');
        $response->clearBody();
        ob_start();
        $controller->addAction();
        check(ob_get_clean() === '', 'Group add emitted a second response');
        $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        check($result['ok'] === true && $result['id'] > 1 && $result['reloadPositions'] === true, 'Group add lacks explicit success/id/refresh');
        check(count($adapter->rows[$document . $type . 'set']) === ($existing ? 2 : 1), 'Group add wrote the wrong set table');
        check($adapter->rows[$document . $type][0][$type . 'setid'] === ($existing ? 1 : $result['id']), 'First group no longer adopts existing positions');
    }
    if ($groupModule === 'items') {
        $_POST = [];
        $request->setParam('id', $result['id'])->setActionName('copy');
        $response->clearBody();
        $controller->copyAction();
        $copied = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        check($copied['ok'] === true && $copied['id'] !== $result['id'] && $copied['title'] === '' && $copied['reloadPositions'] === true, 'Item group copy lost row data or success');
        $request->setActionName('delete')->setPost(['delete' => 'Yes', 'id' => $copied['id'], 'parentid' => 1]);
        $response->clearBody();
        $controller->deleteAction();
        $deleted = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        check($deleted['ok'] === true && $deleted['id'] === $copied['id'] && $deleted['reloadPositions'] === true, 'Item group delete lost its legacy data');
    }
}

// Real position writes must reject finalized invoices/credit notes, including stale route checks.
foreach (['invoice', 'creditnote'] as $document) {
    foreach (['add', 'edit', 'sort', 'copy', 'delete'] as $action) {
        foreach ([false, true] as $stale) {
            $issued = array_replace($draft, [$document . 'id' => 1001, 'state' => 105, 'completed' => 1]);
            $adapter->rows[$document] = [$stale ? array_replace($issued, [$document . 'id' => null, 'state' => 100, 'completed' => 0]) : $issued];
            $adapter->rows[$document . 'pos'] = [$position, $child];
            $adapter->rows[$document . 'posset'] = [];
            $adapter->rows['pricerulepos'] = [];
            $expected = $adapter->rows;
            $expected[$document] = [$issued];
            if ($stale) $adapter->beforeTransaction = static function () use ($adapter, $document, $issued): void { $adapter->rows[$document] = [$issued]; };
            $request = new Zend_Controller_Request_Http();
            $request->setModuleName('sales')->setControllerName('position')->setActionName($action)
                ->setParams(['id' => 10, 'parent' => $document, 'parentid' => 1, 'direction' => 'down'])
                ->setPost($action === 'delete' ? ['id' => 10, 'delete' => 'Yes'] : ['price' => '99,00']);
            $response = new Zend_Controller_Response_Http();
            Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
            (new ReadSalesPositionController($request, $response))->{$action . 'Action'}();
            check(json_decode($response->getBody(), true)['ok'] === false, 'Final position action was accepted: ' . $document . '/' . $action);
            check($adapter->rows === $expected, 'Final position action changed document data: ' . $document . '/' . $action);
        }
    }

    // Legacy group add/sort also recheck the parent after the route plugin has run.
    foreach (['add', 'sort', 'copy', 'delete'] as $action) {
        $adapter->rows[$document] = [array_replace($issued, [$document . 'id' => null, 'state' => 100, 'completed' => 0])];
        $adapter->rows[$document . 'posset'] = [['id' => 1, 'clientid' => 7, 'parentid' => 1, 'deleted' => 0, 'ordering' => 1, 'title' => 'Group']];
        $expected = $adapter->rows;
        $expected[$document] = [$issued];
        $adapter->beforeTransaction = static function () use ($adapter, $document, $issued): void { $adapter->rows[$document] = [$issued]; };
        $request = new Zend_Controller_Request_Http();
        $request->setModuleName('sales')->setControllerName('positionset')->setActionName($action)
            ->setParams(['id' => 1, 'parent' => $document, 'parentid' => 1, 'type' => 'pos'])
            ->setPost(['id' => 1, 'parentid' => 1, 'delete' => 'Yes', 'ordering' => 'down']);
        $response = new Zend_Controller_Response_Http();
        Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
        ob_start();
        try {
            (new CalculationSalesSetController($request, $response))->{$action . 'Action'}();
            throw new LogicException('Stale group action was accepted');
        } catch (RuntimeException $expectedError) {
            check($expectedError->getMessage() === 'Position parent is not editable', 'Unexpected stale group error');
        } finally {
            ob_end_clean();
        }
        check($adapter->rows === $expected, 'Stale group action changed the original');
    }

    // An ordinary draft still supports sorting through the same position endpoint.
    $adapter->rows[$document] = [array_replace($issued, [$document . 'id' => null, 'state' => 100, 'completed' => 0])];
    $adapter->rows[$document . 'pos'] = [$position, array_replace($position, ['id' => 12, 'ordering' => 2])];
    $request->setControllerName('position')->setActionName('sort')->setParam('id', 10)->setParam('direction', 'down')->setPost([]);
    $response = new Zend_Controller_Response_Http();
    Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);
    (new ReadSalesPositionController($request, $response))->sortAction();
    check(json_decode($response->getBody(), true)['ok'] && $adapter->rows[$document . 'pos'][0]['ordering'] === 2, 'Draft position sorting stopped working');
}

echo 'PASS: ' . $checks . ' calculation checks including ' . $moduleUnderTest . " PDF payloads and finalized document write protection\n";
