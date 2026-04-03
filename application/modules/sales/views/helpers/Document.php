<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Document extends Zend_View_Helper_Abstract
{
	public function Document()
	{
		$url = $this->view->url([
			'module' => 'sales',
			'controller' => 'quote',
			'action' => 'download',
			'id' => $this->view->quote['id'],
		]);

		$fileUrl = $this->view->baseUrl()
			. '/files/contacts/'
			. $this->view->contactUrl . '/'
			. $this->view->quote['filename']
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
