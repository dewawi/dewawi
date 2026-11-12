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

		$html = '';
		try { $candidates = (new DEEC_DocumentReview())->candidates((int)$document['id'], $module, $controller); }
		catch (RuntimeException | InvalidArgumentException | JsonException $error) { $candidates = []; error_log('Historical review list unavailable: ' . $error->getMessage()); }
		$groups = [];
		foreach ($candidates as $sha => $candidate) $groups[$candidate['status']][$sha] = $candidate;
		foreach ($groups as $status => $versions) {
			$title = $status === DEEC_DocumentReview::DIAGNOSTIC_STATUS ? 'DOCUMENTS_DIAGNOSTIC_RECONSTRUCTION' : 'DOCUMENTS_RECONSTRUCTION_PENDING';
			$html .= '<section class="dw-alert dw-alert--warning" role="status" data-document-status="' . $status . '"><div><p><strong>' . $this->view->escape($this->view->translate($title)) . '</strong></p>';
			$html .= '<p>' . $this->view->escape($this->view->translate('DOCUMENTS_RECONSTRUCTION_INTERNAL_ONLY')) . '</p><ul>';
			foreach ($versions as $sha => $candidate) {
				$params = ['module' => $module, 'controller' => $controller, 'action' => 'reviewdownload', 'id' => $document['id'], 'sha256' => $sha];
				$html .= '<li><a target="_blank" rel="noopener" href="' . $this->view->escape($this->view->url($params + ['mode' => 'inline'])) . '">' . $this->view->escape($this->view->translate('DOCUMENTS_RECONSTRUCTION_VIEW')) . '</a> · ';
				$html .= '<a href="' . $this->view->escape($this->view->url($params + ['mode' => 'download'])) . '">' . $this->view->escape($this->view->translate('DOCUMENTS_RECONSTRUCTION_DOWNLOAD')) . '</a><br />';
				$html .= '<small>' . $this->view->escape($candidate['proof']['generated_at']) . ' · SHA-256: ' . $sha . '</small></li>';
			}
			$html .= '</ul></div></section>';
		}
		$html .= '<p>';
		$html .= '<a href="' . $url . '" target="_blank">';
		$html .= $this->view->translate('DOCUMENTS_DOWNLOAD_PDF');
		$html .= '</a>';
		$html .= '</p>';

		$html .= '<iframe src="' . $this->view->escape($fileUrl) . '" width="900px" height="500px"></iframe>';

		return $html;
	}
}
