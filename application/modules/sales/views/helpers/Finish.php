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
		$html .= '<div class="dw-field dw-field--col-12">';
		$html .= '<div class="dw-document-validation" aria-live="polite">';
		$html .= $this->renderValidation($this->view->documentValidation ?? []);
		$html .= '</div>';
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
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowimages');
		$html .= '</div>';
		$html .= '<div class="dw-field dw-field--col-3">';
		$html .= $this->view->form->renderElement('pdfshowpositionimages');
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="dw-form-row">';
		$html .= '<div class="dw-field dw-field--col-12">';
		$html .= '<div class="dw-toolbar dw-finish__actions">';
		$html .= '<button type="button" class="dw-btn view" onclick="previewPdf(this)">';
		$html .= htmlspecialchars($this->view->translate('DOCUMENTS_PREVIEW_PDF'));
		$html .= '</button>';
		$html .= '<a class="dw-btn save" data-document-finalize data-error-message="' . htmlspecialchars($this->view->translate('DOCUMENT_VALIDATION_REQUEST_FAILED')) . '" href="' . htmlspecialchars($saveUrl) . '">';
		$html .= htmlspecialchars($this->view->translate('DOCUMENTS_SAVE_AND_BOOK'));
		$html .= '</a>';
		$html .= '<a class="dw-btn pdf" data-document-download href="' . htmlspecialchars($downloadUrl) . '" target="_blank" rel="noopener">';
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

	protected function renderValidation(array $validation): string
	{
		$status = $validation['status'] ?? 'success';
		$classes = ['success' => 'success', 'warning' => 'warning', 'error' => 'danger'];
		$titles = [
			'success' => 'DOCUMENT_VALIDATION_SUCCESS',
			'warning' => 'DOCUMENT_VALIDATION_WARNING',
			'error' => 'DOCUMENT_VALIDATION_ERROR',
		];
		$html = '<div class="dw-alert dw-alert--' . $classes[$status] . ' dw-document-validation__summary">';
		$html .= '<strong>' . htmlspecialchars($this->view->translate($titles[$status])) . '</strong>';
		$html .= '</div>';
		$issues = array_merge($validation['errors'] ?? [], $validation['warnings'] ?? []);
		if (!$issues) return $html;

		$html .= '<ul class="dw-document-validation__issues">';
		foreach ($issues as $issue) {
			$text = $this->view->translate($issue['message'], $issue['args'] ?? []);
			$attrs = ' href="#tab' . htmlspecialchars($issue['tab']) . '" data-document-validation-target';
			if (!empty($issue['field'])) $attrs .= ' data-field="' . htmlspecialchars($issue['field']) . '"';
			if (!empty($issue['positionId'])) $attrs .= ' data-position-id="' . (int)$issue['positionId'] . '"';
			$html .= '<li><a' . $attrs . '>' . htmlspecialchars($text) . '</a></li>';
		}
		$html .= '</ul>';
		return $html;
	}
}
