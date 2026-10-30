<?php

class Shops_SearchController extends Shops_Controller_Action
{
	public function indexAction()
	{
		$this->initSiteLayout();
		$this->getResponse()->setHeader('X-Robots-Tag', 'noindex, follow', true);
		$this->view->headMeta()->setName('robots', 'noindex, follow');
		$this->view->headTitle($this->view->translate('SHOPS_SEARCH'));

		$query = (string)$this->_getParam('q', '');
		$page = filter_var($this->_getParam('page', 1), FILTER_VALIDATE_INT, [
			'options' => ['default' => 1, 'min_range' => 1],
		]);
		$type = (string)$this->_getParam('type', '');

		$search = new Shops_Model_Search($this->_siteContext);
		$this->view->search = $search->search($query, (int)$page, $type);
	}
}
