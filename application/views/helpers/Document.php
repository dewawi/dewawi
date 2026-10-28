<?php

class Zend_View_Helper_Document extends Zend_View_Helper_Abstract
{
	public function Document()
	{
		$module = $this->view->module;
		$controller = $this->view->controller;
		$document = $this->view->$controller;

		$url = $this->view->url([
			'module' => $module,
			'controller' => $controller,
			'action' => 'download',
			'id' => $document['id'],
		]);

		$fileUrl = $this->view->url(['module' => $module, 'controller' => $controller, 'action' => 'preview', 'id' => $document['id']]);

		$html  = '<p>';
		$html .= '<a href="' . $url . '" target="_blank">';
		$html .= $this->view->translate('DOCUMENTS_DOWNLOAD_PDF');
		$html .= '</a>';
		$html .= '</p>';

		$html .= '<iframe src="' . $this->view->escape($fileUrl) . '" width="900px" height="500px"></iframe>';

		return $html;
	}
}
