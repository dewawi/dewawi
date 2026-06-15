<?php

class Zend_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract
{
	public function Breadcrumbs()
	{
		$breadcrumbs = '<span><a href="/">'
			. $this->view->translate('DEWAWI')
			. '</a></span>';

		if ($this->view->module !== 'default') {
			$breadcrumbs .= '<span> &raquo; </span><span><a href="'
				. $this->buildUrl($this->view->module, 'index')
				. '">' . $this->view->translate(strtoupper($this->view->module)) . '</a></span>';
		}

		if ($this->view->controller !== 'index') {
			$breadcrumbs .= '<span> &raquo; </span><span><a href="'
				. $this->buildUrl($this->view->module, $this->view->controller)
				. '">' . $this->view->translate(strtoupper($this->view->controller)) . '</a></span>';
		}

		if ($this->view->action !== 'index') {
			$breadcrumbs .= '<span> &raquo; </span><span>'
				. $this->view->translate(strtoupper($this->view->action))
				. '</span>';
		}

		return $breadcrumbs;
	}

	protected function buildUrl($module, $controller)
	{
		$params = [
			'module' => $module,
			'controller' => $controller,
			'action' => 'index',
		];

		$type = Zend_Controller_Front::getInstance()
			->getRequest()
			->getParam('type');

		if ($type) {
			$params['type'] = $type;
		}

		return $this->view->url($params, 'default', false);
	}
}
