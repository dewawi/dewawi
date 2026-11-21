<?php

class Admin_UpdatesController extends DEEC_Controller_AdminAction
{
    public function init()
    {
        if (!DEEC_SystemOperator::allowed()) throw new Zend_Controller_Action_Exception('Forbidden', 403);
        if ($this->getRequest()->getActionName() !== 'index') throw new Zend_Controller_Action_Exception('Not found', 404);
        if (!in_array($this->getRequest()->getMethod(), ['GET', 'HEAD'], true)) {
            $this->getResponse()->setHeader('Allow', 'GET, HEAD', true);
            throw new Zend_Controller_Action_Exception('Method not allowed', 405);
        }
        parent::init();
        $this->view->toolbar = null;
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
    }

    protected function shouldPrepareAttachmentDirectory(): bool { return false; }

    public function indexAction()
    {
        require_once dirname(APPLICATION_PATH) . '/library/DEEC/Version.php';
        set_error_handler(static function(int $severity): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new ErrorException('update_inspection_failed');
        });
        try {
            require_once dirname(APPLICATION_PATH) . '/tools/migrations/Inspector.php';
            $inspector = new DewawiMigrationInspector(Zend_Db_Table::getDefaultAdapter(), dirname(APPLICATION_PATH) . '/install/config/migrations');
            $report = $inspector->inspect();
        } catch (Throwable $error) {
            $report = ['baseline' => 'unknown', 'last' => null, 'pending' => null, 'issues' => ['catalog_invalid'], 'migrations' => []];
        } finally {
            restore_error_handler();
        }
        $version = dewawiVersion();
        if ($version === null) $report['issues'][] = 'version_invalid';
        $this->view->assign(['applicationVersion' => $version, 'updateReport' => $report]);
        $this->assignMessages();
    }
}
