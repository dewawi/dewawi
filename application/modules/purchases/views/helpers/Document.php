<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Document extends Zend_View_Helper_Abstract
{
	public function Document()
	{
		$module = $this->view->module;
		$controller = $this->view->controller;
		$document = $this->view->$controller;
		$contactUrl = $this->view->contactUrl;

		$url = $this->view->url([
			'module' => $module,
			'controller' => $controller,
			'action' => 'download',
			'id' => $document['id'],
		]);

		$fileUrl = $this->view->baseUrl()
			. '/files/contacts/'
			. $contactUrl . '/'
			. $document['filename']
			. '?' . time();

		$html  = '<p>';
		$html .= '<a href="' . $url . '" target="_blank">';
		$html .= $this->view->translate('QUOTES_DOWNLOAD_PDF');
		$html .= '</a>';
		$html .= '</p>';

		$html .= '<iframe src="' . $fileUrl . '" width="900px" height="500px"></iframe>';

		return $html;
	}
}
