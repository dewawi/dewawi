<?php

class Zend_View_Helper_MultiForm extends Zend_View_Helper_Abstract
{
	public function MultiForm(
		string $module,
		string $controller,
		$data,
		$elements = null,
		$label = null,
		$childs = null
	): string {
		$className = $module === 'default'
			? 'Application_Form_' . ucfirst($controller)
			: ucfirst($module) . '_Form_' . ucfirst($controller);

		if (!class_exists($className)) {
			return '';
		}

		$form = new $className();

		$dataset = $this->normalizeDataset($data);
		$fieldNames = $this->resolveFields(
			$form,
			$controller,
			$elements
		);

		$html = '<div'
			. ' class="dw-multiform"'
			. ' data-module="' . $this->escape($module) . '"'
			. ' data-controller="' . $this->escape($controller) . '"'
			. '>';

		$html .= $this->renderHeader($label);

		$html .= '<div class="dw-multiform__list">';

		foreach ($dataset as $row) {
			if (!is_array($row) || empty($row['id'])) {
				continue;
			}

			$html .= $this->renderItem(
				$form,
				$module,
				$controller,
				$row,
				$fieldNames,
				$childs
			);
		}

		$html .= '</div>';

		if (!$childs || $this->view->action !== 'add') {
			$html .= $this->renderAddButton(
				$module,
				$controller
			);
		}

		$html .= '</div>';

		return $html;
	}

	protected function normalizeDataset($data): array
	{
		if (!is_array($data)) {
			return [];
		}

		if (array_key_exists('id', $data)) {
			return [$data];
		}

		return $data;
	}

	protected function renderHeader($label): string
	{
		if (!$label) {
			return '';
		}

		return '<div class="dw-multiform__header">'
			. '<span class="dw-label">'
			. $this->escape(
				$this->view->translate((string)$label)
			)
			. '</span>'
			. '</div>';
	}

	protected function renderItem(
		$form,
		string $module,
		string $controller,
		array $row,
		array $fieldNames,
		$childs
	): string {
		$rowId = (string)$row['id'];

		$context = [
			'module' => $module,
			'controller' => $controller,
		];

		$html = '<div'
			. ' id="' . $this->escape(
				$controller . '_' . $rowId
			) . '"'
			. ' class="dw-multiform__item"'
			. ' data-id="' . $this->escape($rowId) . '"'
			. ' data-module="' . $this->escape($module) . '"'
			. ' data-controller="' . $this->escape($controller) . '"'
			. '>';

		$html .= '<div class="dw-multiform__fields">';

		foreach ($fieldNames as $fieldName) {
			$html .= $form->renderElementRow(
				$fieldName,
				$row,
				$context
			);
		}

		$html .= '</div>';

		$html .= '<div class="dw-multiform__actions">';

		if (in_array('email', $fieldNames, true)) {
			$html .= $this->renderEmailButton(
				(string)($row['email'] ?? '')
			);
		}

		$html .= $this->renderDeleteButton(
			$module,
			$controller,
			$rowId
		);

		$html .= '</div>';

		if ($childs) {
			$html .= $this->renderChildren(
				$rowId,
				$childs
			);
		}

		$html .= '</div>';

		return $html;
	}

	protected function renderEmailButton(string $email): string
	{
		if ($email === '') {
			return '';
		}

		return '<a'
			. ' class="dw-btn dw-btn--icon email"'
			. ' href="mailto:' . $this->escape($email) . '"'
			. ' aria-label="E-Mail"'
			. '></a>';
	}

	protected function renderDeleteButton(
		string $module,
		string $controller,
		string $rowId
	): string {
		return '<button'
			. ' type="button"'
			. ' class="dw-btn dw-btn--icon delete"'
			. ' data-action="delete"'
			. ' data-id="' . $this->escape($rowId) . '"'
			. ' data-module="' . $this->escape($module) . '"'
			. ' data-controller="' . $this->escape($controller) . '"'
			. ' aria-label="Löschen"'
			. '></button>';
	}

	protected function renderAddButton(
		string $module,
		string $controller
	): string {
		return '<div class="dw-multiform__footer">'
			. '<button'
			. ' type="button"'
			. ' class="dw-btn dw-btn--icon add"'
			. ' data-action="multi-add"'
			. ' data-module="' . $this->escape($module) . '"'
			. ' data-controller="' . $this->escape($controller) . '"'
			. ' aria-label="Hinzufügen"'
			. '></button>'
			. '</div>';
	}

	protected function renderChildren(
		string $parentId,
		array $childs
	): string {
		return '<div'
			. ' class="dw-multiform__children dw-multiform-context"'
			. ' data-parentid="' . $this->escape($parentId) . '"'
			. ' data-controller="contactperson"'
			. '>'
			. $this->view->MultiForm(
				'contacts',
				'email',
				$childs[$parentId] ?? [],
				null,
				'CONTACTS_EMAIL'
			)
			. '</div>';
	}

	protected function resolveFields(
		$form,
		string $controller,
		$elements
	): array {
		if (is_array($elements)) {
			$fields = [];

			foreach ($elements as $field) {
				if (!is_string($field) && !is_int($field)) {
					continue;
				}

				$field = (string)$field;

				if ($field !== '' && $form->getElement($field)) {
					$fields[] = $field;
				}
			}

			if ($fields) {
				return $fields;
			}
		}

		if ($form->getElement($controller)) {
			return [$controller];
		}

		return array_keys($form->getElements());
	}

	protected function escape(string $value): string
	{
		return htmlspecialchars(
			$value,
			ENT_QUOTES,
			'UTF-8'
		);
	}
}
