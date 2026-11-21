<?php

class Application_Plugin_UpdateMaintenance extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (!DEEC_UpdateMaintenance::dispatchAllowed($request)) {
            $this->getResponse()->setHttpResponseCode(503)->setHeader('Cache-Control', 'no-store')->setHeader('Retry-After', '60')->setBody('Maintenance unavailable')->sendResponse();
            exit;
        }
    }
}
