<?php
/**
 * Class inserts necessary code for finish area
 */
class Zend_View_Helper_Finish extends Zend_View_Helper_Abstract
{
	public function finish()
	{
		$controller = $this->view->controller;
		$module = $this->view->module;
		$id = (int)$this->view->form->getValue('id');

		$saveUrl = $this->view->url([
			'module' => $module,
			'controller' => $controller,
			'action' => 'save',
			'id' => $id,
		]);

		$downloadUrl = $this->view->url([
			'module' => $module,
			'controller' => $controller,
			'action' => 'download',
			'id' => $id,
		]);

		$html = '';

		$html .= '<div class="dw-finish">';
		$html .= '<form id="' . $controller . '" enctype="application/x-www-form-urlencoded" action="" method="post">';
		$html .= '<div class="dw-form-layout">';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('templateid');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('language');
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowprices');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowdiscounts');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowoptions');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowattributes');
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowcover');
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-12">';
		$html .= '<div class="dw-toolbar dw-finish__actions">';
		$html .= '<button type="button" class="dw-btn view" onclick="previewPdf()">';
		$html .= htmlspecialchars($this->view->translate('DOCUMENTS_PREVIEW_PDF'));
		$html .= '</button>';
		$html .= '<a class="dw-btn save" href="' . htmlspecialchars($saveUrl) . '">';
		$html .= htmlspecialchars($this->view->translate('DOCUMENTS_SAVE_AND_BOOK'));
		$html .= '</a>';
		$html .= '<a class="dw-btn pdf" href="' . htmlspecialchars($downloadUrl) . '" target="_blank" rel="noopener">';
		$html .= htmlspecialchars($this->view->translate('DOCUMENTS_DOWNLOAD_PDF'));
		$html .= '</a>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-12">';
		$html .= '<div id="output" class="dw-finish__preview"></div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}
}
