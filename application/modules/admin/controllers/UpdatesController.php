<?php

class Admin_UpdatesController extends DEEC_Controller_AdminAction
{
    public function init()
    {
        if (!DEEC_SystemOperator::allowed()) throw new Zend_Controller_Action_Exception('Forbidden', 403);
        $action = $this->getRequest()->getActionName();
        if (!in_array($action, ['index', 'approve'], true)) throw new Zend_Controller_Action_Exception('Not found', 404);
        $methods = $action === 'approve' ? ['POST'] : ['GET', 'HEAD'];
        if (!in_array($this->getRequest()->getMethod(), $methods, true)) {
            $this->getResponse()->setHeader('Allow', implode(', ', $methods), true);
            throw new Zend_Controller_Action_Exception('Method not allowed', 405);
        }
        parent::init();
        $this->view->toolbar = null;
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
    }

    protected function shouldPrepareAttachmentDirectory(): bool { return false; }

    protected function queue(): ?Admin_Service_SystemUpdates
    {
        $options = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getOption('systemUpdates');
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
        $state = $confirmation = null;
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_queue_unavailable');
        });
        try {
            $queue = $this->queue();
            if ($queue && isset($inspector)) { $state = $queue->state($inspector); $confirmation = $queue->confirmation($state); }
        } catch (Throwable $error) { $state = null; }
        finally { restore_error_handler(); }
        $this->view->assign(['applicationVersion' => $version, 'updateReport' => $report, 'workerState' => $state, 'updateConfirmation' => $confirmation,
            'submittedUpdate' => Admin_Service_SystemUpdates::session()->submitted]);
        $this->assignMessages();
    }

    public function approveAction()
    {
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
}
