<?php

class Admin_UpdatesController extends DEEC_Controller_AdminAction
{
    public function init()
    {
        if (!DEEC_SystemOperator::allowed()) throw new Zend_Controller_Action_Exception('Forbidden', 403);
        $action = $this->getRequest()->getActionName();
        $options = $this->options();
        $mode = DEEC_UpdateMaintenance::mode($options);
        if ($mode !== 'database') {
            require_once dirname(APPLICATION_PATH) . '/library/DEEC/UpdateDatabase.php';
            if ((new DEEC_UpdateDatabase(Zend_Db_Table::getDefaultAdapter()))->exists()) throw new Zend_Controller_Action_Exception('Conflicting update storage', 409);
        }
        $actions = $mode === 'database' ? ['index', 'check', 'run', 'cancel'] : ($mode === 'shared' ? ['index', 'initialize', 'approve', 'maintenance', 'prepare', 'execute', 'finish'] : ['index', 'approve']);
        if (!in_array($action, $actions, true)) throw new Zend_Controller_Action_Exception('Not found', 404);
        $methods = $action !== 'index' ? ['POST'] : ['GET', 'HEAD'];
        if (!in_array($this->getRequest()->getMethod(), $methods, true)) {
            $this->getResponse()->setHeader('Allow', implode(', ', $methods), true);
            throw new Zend_Controller_Action_Exception('Method not allowed', 405);
        }
        parent::init();
        $this->view->toolbar = null;
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
    }

    protected function shouldPrepareAttachmentDirectory(): bool { return false; }

    private function options(): array { return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getOption('systemUpdates') ?? []; }

    private function hosting(bool $initializing = false): ?DewawiHostingUpdates
    {
        $options = $this->options();
        $selection = DEEC_UpdateMaintenance::storage($options, dirname(APPLICATION_PATH), $initializing);
        if (!$selection) return null;
        if (!$initializing && $selection['record'] && !$selection['ready']) throw new RuntimeException('storage_incomplete');
        require_once dirname(APPLICATION_PATH) . '/tools/updates/Hosting.php';
        return new DewawiHostingUpdates($selection['path'], Zend_Db_Table::getDefaultAdapter(), dirname(APPLICATION_PATH) . '/install/config/migrations', $initializing, $selection);
    }

    protected function queue(): ?Admin_Service_SystemUpdates
    {
        $options = $this->options();
        if (DEEC_UpdateMaintenance::mode($options) !== 'worker') return null;
        if (empty($options['queuePath']) || !is_string($options['queuePath'])) return null;
        return new Admin_Service_SystemUpdates(new DewawiUpdateFiles($options['queuePath'], 'web'));
    }

    private function inspector(): DewawiMigrationInspector
    {
        require_once dirname(APPLICATION_PATH) . '/tools/migrations/Inspector.php';
        return new DewawiMigrationInspector(Zend_Db_Table::getDefaultAdapter(), dirname(APPLICATION_PATH) . '/install/config/migrations');
    }

    public function indexAction()
    {
        require_once dirname(APPLICATION_PATH) . '/library/DEEC/Version.php';
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_inspection_failed');
        });
        try {
            $inspector = $this->inspector();
            $report = $inspector->inspect();
        } catch (Throwable $error) {
            $report = ['baseline' => 'unknown', 'last' => null, 'pending' => null, 'issues' => ['catalog_invalid'], 'migrations' => []];
        } finally {
            restore_error_handler();
        }
        $version = dewawiVersion();
        if ($version === null) $report['issues'][] = 'version_invalid';
        $names = [];
        $labels = ['baseline/1.1.8' => 'UPDATES_DB_REGISTER', '1.1.8-1.1.9/001-shop-shipping.sql' => 'UPDATES_DB_SHIPPING', '1.1.8-1.1.9/002-shop-shipping-tax.sql' => 'UPDATES_DB_SHIPPING_TAX'];
        foreach ($report['migrations'] as $migration) $names[$migration['id']] = isset($labels[$migration['id']]) ? $this->view->translate($labels[$migration['id']]) : ucwords(str_replace('-', ' ', preg_replace('/^[0-9]+-/', '', basename($migration['id'], '.sql'))));
        $this->view->migrationNames = $names;
        $databaseMode = DEEC_UpdateMaintenance::mode($this->options()) === 'database';
        $this->view->databaseMode = $databaseMode;
        if ($databaseMode) {
            require_once dirname(APPLICATION_PATH) . '/tools/updates/Database.php';
            $this->view->assign(['applicationVersion' => $version, 'updateReport' => $report, 'databaseCsrf' => DewawiDatabaseUpdates::csrf(), 'databaseState' => null]);
            try { $this->view->databaseState = $this->database()->status(); }
            catch (Throwable $e) { $this->view->updateApprovalError = $this->databaseError($e); }
            $this->assignMessages(); return;
        }
        $state = $confirmation = $hostingState = $hostingCsrf = null;
        $hostingSetup = ['status' => 'unavailable', 'source' => null, 'code' => null];
        $hostingMode = DEEC_UpdateMaintenance::mode($this->options()) === 'shared';
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_queue_unavailable');
        });
        try {
            $queue = $this->queue();
            if ($queue && isset($inspector)) { $state = $queue->state($inspector); $confirmation = $queue->confirmation($state); }
            if ($hostingMode) {
                require_once dirname(APPLICATION_PATH) . '/tools/updates/Hosting.php';
                $selection = DEEC_UpdateMaintenance::storage($this->options(), dirname(APPLICATION_PATH), true);
                if ($selection) {
                    $hostingCsrf = DewawiHostingUpdates::csrf();
                    $hostingSetup = ['status' => 'available', 'source' => $selection['source'], 'code' => null];
                    if (($selection['record'] && !$selection['ready']) || (is_dir($selection['path']) && iterator_count(new FilesystemIterator($selection['path'])) > 0)) $hostingSetup['status'] = 'incomplete';
                    if ((!$selection['record'] || $selection['ready']) && is_file($selection['path'] . '/control.json')) {
                        $hostingState = $this->hosting()->status(); $hostingSetup['status'] = 'initialized';
                    }
                }
            }
        } catch (Throwable $error) {
            $state = null;
            $pinned = @lstat(dirname(APPLICATION_PATH) . '/configs/update-storage.json') !== false || @lstat(dirname(APPLICATION_PATH) . '/configs/update-storage.ready.json') !== false;
            $hostingSetup = ['status' => $hostingSetup['source'] === null && !$pinned ? 'unavailable' : 'blocked', 'source' => $hostingSetup['source'],
                'code' => class_exists('DewawiHostingUpdates', false) && in_array($error->getMessage(), DewawiHostingUpdates::CODES, true) ? $error->getMessage() : 'configuration'];
            $hostingCsrf = null;
        }
        finally { restore_error_handler(); }
        $this->view->assign(['applicationVersion' => $version, 'updateReport' => $report, 'workerState' => $state, 'updateConfirmation' => $confirmation,
            'hostingMode' => $hostingMode, 'hostingState' => $hostingState, 'hostingCsrf' => $hostingCsrf,
            'hostingSetup' => $hostingSetup,
            'submittedUpdate' => Admin_Service_SystemUpdates::session()->submitted]);
        $this->assignMessages();
    }

    public function approveAction()
    {
        if (DEEC_UpdateMaintenance::mode($this->options()) === 'shared') return $this->hostingAction('approve');
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_queue_unavailable');
        });
        try {
            $queue = $this->queue();
            if (!$queue) throw new RuntimeException('worker_unavailable');
            $queue->approve((array)$this->getRequest()->getPost(), $this->inspector());
            return $this->_helper->redirector->gotoSimple('index', 'updates', 'admin');
        } catch (Throwable $error) {
            $forbidden = $error instanceof Zend_Controller_Action_Exception && $error->getCode() === 403;
            $this->getResponse()->setHttpResponseCode($forbidden ? 403 : 409);
            $this->view->updateApprovalError = $forbidden ? 'UPDATES_CONFIRM_INVALID' : 'UPDATES_APPROVAL_FAILED';
            $this->_helper->viewRenderer->setRender('index');
            $this->indexAction();
        } finally { restore_error_handler(); }
    }

    public function maintenanceAction() { return $this->hostingAction('maintenance'); }
    public function initializeAction() { return $this->hostingAction('initialize'); }
    public function prepareAction() { return $this->hostingAction('prepare'); }
    public function executeAction() { return $this->hostingAction('execute'); }
    public function finishAction() { return $this->hostingAction('finish'); }

    private function database(): DewawiDatabaseUpdates
    {
        require_once dirname(APPLICATION_PATH) . '/tools/updates/Database.php';
        return new DewawiDatabaseUpdates(Zend_Db_Table::getDefaultAdapter(), dirname(APPLICATION_PATH), $this->options());
    }

    private function databaseError(Throwable $error): string
    {
        $code = in_array($error->getMessage(), ['authentication', 'confirmation', 'evidence', 'alternative', 'legacy', 'manual_review', 'plan_changed', 'ambiguous'], true) ? $error->getMessage() : 'verification';
        return 'UPDATES_DB_ERROR_' . strtoupper($code);
    }

    public function checkAction() { return $this->databaseAction('check'); }
    public function runAction() { return $this->databaseAction('run'); }
    public function cancelAction() { return $this->databaseAction('cancel'); }

    private function databaseAction(string $action)
    {
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_database_unavailable');
        });
        try {
            $service = $this->database(); $post = (array)$this->getRequest()->getPost();
            $keys = $action === 'run' ? ['csrf', 'job', 'confirm', 'backup', 'restore', 'writers', 'historical', 'historical_reference'] : ['csrf'];
            $service->authorize($post, $keys);
            if ($action === 'check') $service->check();
            elseif ($action === 'cancel') $service->finish();
            elseif (!is_string($post['job'])) throw new RuntimeException('confirmation');
            else $service->execute($post['job'], array_intersect_key($post, array_flip(['confirm', 'backup', 'restore', 'writers', 'historical', 'historical_reference'])));
            return $this->_helper->redirector->gotoSimple('index', 'updates', 'admin');
        } catch (Throwable $error) {
            $this->getResponse()->setHttpResponseCode(in_array($error->getMessage(), ['authentication', 'confirmation'], true) ? 403 : 409);
            $this->view->updateApprovalError = $this->databaseError($error);
            $this->_helper->viewRenderer->setRender('index'); $this->indexAction();
        } finally { restore_error_handler(); }
    }

    private function hostingAction(string $action)
    {
        require_once dirname(APPLICATION_PATH) . '/tools/updates/Hosting.php';
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('configuration');
        });
        try {
            $h = $this->hosting($action === 'initialize');
            if (!$h) throw new RuntimeException('configuration');
            $post = (array)$this->getRequest()->getPost();
            $keys = ['csrf'];
            if (in_array($action, ['initialize', 'maintenance', 'approve', 'execute', 'finish'], true)) $keys[] = 'password';
            if ($action === 'approve') $keys = array_merge($keys, ['nonce', 'plan', 'confirm']);
            if ($action === 'initialize') $keys[] = 'confirm';
            if ($action === 'execute') $keys[] = 'request';
            $h->authorize($post, $keys, in_array('password', $keys, true));
            if ($action === 'initialize') {
                if ($post['confirm'] !== '1') throw new RuntimeException('confirmation');
                $h->initialize();
            }
            elseif ($action === 'maintenance') $h->begin();
            elseif ($action === 'prepare') $h->prepare();
            elseif ($action === 'approve') $h->approve($post);
            elseif ($action === 'execute') $h->execute(is_string($post['request']) ? $post['request'] : '');
            elseif ($action === 'finish') $h->finish();
            return $this->_helper->redirector->gotoSimple('index', 'updates', 'admin');
        } catch (Throwable $error) {
            $code = in_array($error->getMessage(), DewawiHostingUpdates::CODES, true) ? $error->getMessage() : 'verification';
            $this->getResponse()->setHttpResponseCode(in_array($code, ['confirmation', 'authentication'], true) ? 403 : 409);
            $this->view->updateApprovalError = 'UPDATES_HOST_CODE_' . strtoupper($code);
            $this->_helper->viewRenderer->setRender('index');
            $this->indexAction();
        } finally { restore_error_handler(); }
    }
}
