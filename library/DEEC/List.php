<?php

class DEEC_List
{
	protected $view = null;
	protected $translator = null;
	protected $id = null;
	protected $module = '';
	protected $controller = '';
	protected $columns = [];
	protected $items = [];
	protected $options = [];
	protected $context = [];
	protected $toolbarInline = null;
	protected $selectable = true;
	protected $tableClass = '';
	protected $total = null;
	protected $rowClassCallback = null;
	protected $readonlyStates = ['105', '106'];
	protected $partial = 'list/list.phtml';
	protected $emptyText = 'NO_ENTRIES_FOUND';

	public function __construct(array $config = [])
	{
		$this->setColumns($this->buildColumns());

		if ($config) {
			$this->configure($config);
		}
	}

	public function configure(array $config)
	{
		if (array_key_exists('id', $config)) {
			$this->setId($config['id']);
		}

		if (array_key_exists('items', $config)) {
			$this->setItems($config['items']);
		}

		if (array_key_exists('options', $config)) {
			$this->setOptions($config['options']);
		}

		if (array_key_exists('view', $config)) {
			$this->setView($config['view']);
		}

		if (array_key_exists('module', $config)) {
			$this->setModule($config['module']);
		}

		if (array_key_exists('controller', $config)) {
			$this->setController($config['controller']);
		}

		if (array_key_exists('toolbarInline', $config)) {
			$this->setToolbarInline($config['toolbarInline']);
		}

		if (array_key_exists('context', $config)) {
			$this->setContext($config['context']);
		}

		if (array_key_exists('selectable', $config)) {
			$this->selectable = (bool)$config['selectable'];
		}

		if (isset($config['rowClassCallback'])) {
			$this->rowClassCallback = $config['rowClassCallback'];
		}

		if (isset($config['columns'])) {
			$this->setColumns($config['columns']);
		}

		return $this;
	}

	public function setContext(array $context)
	{
		$this->context = $context;
		return $this;
	}

	public function getContext($key = null, $default = null)
	{
		if ($key === null) {
			return $this->context;
		}

		return array_key_exists($key, $this->context) ? $this->context[$key] : $default;
	}

	public function hasPermission(string $key): bool
	{
		$user = $this->getContext('user', []);

		if ($key === 'admin') {
			return !empty($user['admin']);
		}

		return false;
	}

	public function setView($view)
	{
		$this->view = $view;
		return $this;
	}

	public function getView()
	{
		return $this->view;
	}

	public function getTranslator()
	{
		if ($this->translator) return $this->translator;
		if (class_exists('Zend_Registry') && Zend_Registry::isRegistered('DEEC_Translate')) {
			$this->translator = Zend_Registry::get('DEEC_Translate');
		}

		return $this->translator;
	}

	public function setTranslator(DEEC_Translate $translator): self
	{
		$this->translator = $translator;
		return $this;
	}

	public function translate(string $key, array $args = []): string
	{
		$t = $this->getTranslator();
		if ($t && method_exists($t, 't')) {
			return $t->t($key, $args);
		}
		return $key;
	}

	public function getId(): string
	{
		if ($this->id) {
			return $this->id;
		}

		$module = $this->getModule();
		$controller = $this->getController();

		if ($module && $controller) {
			return $module . '-' . $controller;
		}

		return 'list';
	}

	public function setId(string $id)
	{
		$this->id = $id;
		return $this;
	}

	public function setModule($module)
	{
		$this->module = (string)$module;
		return $this;
	}

	public function getModule()
	{
		return $this->module;
	}

	public function setController($controller)
	{
		$this->controller = (string)$controller;
		return $this;
	}

	public function getController()
	{
		return $this->controller;
	}

	public function setItems($items)
	{
		$this->items = $items;
		return $this;
	}

	public function getItems()
	{
		return $this->items;
	}

	public function hasItems()
	{
		$items = $this->getItems();

		if (is_array($items)) {
			return !empty($items);
		}

		if ($items instanceof Countable) {
			return count($items) > 0;
		}

		foreach ($items as $item) {
			return true;
		}

		return false;
	}

	public function isReadonly($item, string $field = 'state'): bool
	{
		$value = (string)$this->getFieldValue($item, $field);

		return in_array($value, $this->readonlyStates, true);
	}

	public function setOptions(array $options)
	{
		$this->options = $options;
		return $this;
	}

	public function getOptions()
	{
		return $this->options;
	}

	public function getOption($key, $default = null)
	{
		return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
	}

	public function setToolbarInline($toolbarInline)
	{
		$this->toolbarInline = $toolbarInline;
		return $this;
	}

	public function getToolbarInline()
	{
		return $this->toolbarInline;
	}

	public function setSelectable($selectable)
	{
		$this->selectable = (bool)$selectable;
		return $this;
	}

	public function isSelectable()
	{
		return $this->selectable;
	}

	public function setTableClass($tableClass)
	{
		$this->tableClass = trim((string)$tableClass);
		return $this;
	}

	public function getTableClass()
	{
		if ($this->tableClass !== '') {
			return $this->tableClass;
		}

		return 'dw-table dw-table--' . $this->getId();
	}

	public function setTotal($total)
	{
		$this->total = (int)$total;
		return $this;
	}

	public function getTotal()
	{
		if ($this->total !== null) {
			return $this->total;
		}

		$items = $this->getItems();

		if (is_object($items) && isset($items->total)) {
			return (int)$items->total;
		}

		if (is_array($items)) {
			return count($items);
		}

		if ($items instanceof Countable) {
			return count($items);
		}

		return 0;
	}

	public function setRowClassCallback($callback)
	{
		$this->rowClassCallback = $callback;
		return $this;
	}

	public function getRowClassCallback()
	{
		return $this->rowClassCallback;
	}

	public function setColumns(array $columns)
	{
		$this->columns = [];

		foreach ($columns as $column) {
			$this->addColumn($column);
		}

		return $this;
	}

	public function addColumn(array $column)
	{
		$this->columns[] = $this->normalizeColumn($column);
		return $this;
	}

	protected function normalizeColumn(array $column): array
	{
		$name = isset($column['name']) ? (string)$column['name'] : '';
		$type = isset($column['type']) ? (string)$column['type'] : 'text';

		$column['type'] = $type;

		if (!isset($column['field']) && $name !== '' && !in_array($type, ['actions', 'address', 'contact', 'pin'], true)) {
			$column['field'] = $name;
		}

		if (!isset($column['editable_name']) && isset($column['field'])) {
			$column['editable_name'] = $column['field'];
		}

		if (!isset($column['class']) && $name !== '') {
			$column['class'] = 'dw-col-' . str_replace('_', '-', $name);
		}

		if ($type === 'link' && !isset($column['url'])) {
			$column['url'] = [
				'action' => 'edit',
				'id_field' => 'id',
			];
		}

		return $column;
	}

	public function getColumns()
	{
		return $this->columns;
	}

	public function setPartial($partial)
	{
		$this->partial = (string)$partial;
		return $this;
	}

	public function getPartial()
	{
		return $this->partial;
	}

	public function setEmptyText($emptyText)
	{
		$this->emptyText = (string)$emptyText;
		return $this;
	}

	public function getEmptyText()
	{
		return $this->emptyText;
	}

	public function render()
	{
		if (!$this->view) {
			throw new RuntimeException('DEEC_List requires a view instance.');
		}

		return (string)$this->view->partial($this->partial, null, ['table' => $this]);
	}

	public function getHeaderColspan()
	{
		$colspan = count($this->getColumns());

		if ($this->isSelectable()) {
			$colspan++;
		}

		return $colspan > 0 ? $colspan : 1;
	}

	public function getColumnLabel(array $column)
	{
		$label = isset($column['label']) ? (string)$column['label'] : '';

		if ($label === '') {
			return '';
		}

		return $this->translate($label);
	}

	public function getColumnDataLabel(array $column)
	{
		$key = isset($column['data_label']) ? (string)$column['data_label'] : '';

		if ($key === '') {
			$key = isset($column['label']) ? (string)$column['label'] : '';
		}

		if ($key === '') {
			return '';
		}

		return $this->translate($key);
	}

	public function getRowClass($item)
	{
		$classes = ['dw-row'];
		$callback = $this->getRowClassCallback();

		if (is_callable($callback)) {
			$extraClass = trim((string)call_user_func($callback, $item, $this));
			if ($extraClass !== '') {
				$classes[] = $extraClass;
			}
		}

		return implode(' ', $classes);
	}

	public function getCellClass(array $column)
	{
		return trim((string)($column['class'] ?? ''));
	}

	public function getFieldValue($item, $field, $default = null)
	{
		$field = (string)$field;

		if ($field === '') {
			return $default;
		}

		if (is_array($item) && array_key_exists($field, $item)) {
			return $item[$field];
		}

		if (is_object($item) && isset($item->$field)) {
			return $item->$field;
		}

		return $default;
	}

	public function getDisplayValue($item, array $column)
	{
		$field = isset($column['field']) ? $column['field'] : '';
		$value = $this->getFieldValue($item, $field);

		if (($value === null || $value === '') && !empty($column['fallback_field'])) {
			$value = $this->getFieldValue($item, $column['fallback_field']);
		}

		return $value;
	}

	public function renderCell($item, array $column)
	{
		$type = isset($column['type']) ? (string)$column['type'] : 'text';

		switch ($type) {
			case 'link':
				return $this->renderLinkCell($item, $column);
			case 'contact':
				return $this->renderContactCell($item, $column);
			case 'address':
				return $this->renderAddressCell($item, $column);
			case 'editable_note':
				return $this->renderEditableNoteCell($item, $column);
			case 'date':
				return $this->renderDateCell($item, $column);
			case 'state_badge':
				return $this->renderStateBadgeCell($item, $column);
			case 'pin':
				return $this->renderPinCell($item, $column);
			case 'actions':
				return $this->renderActionsCell($item, $column);
			case 'callback':
				return $this->renderCallbackCell($item, $column);
			case 'text':
			default:
				return $this->renderTextCell($item, $column);
		}
	}

	protected function renderTextCell($item, array $column)
	{
		$value = $this->getDisplayValue($item, $column);
		return $this->escape((string)$value);
	}

	protected function renderLinkCell($item, array $column)
	{
		$value = $this->getDisplayValue($item, $column);

		if (!empty($column['empty_hide']) && ($value === null || $value === '')) {
			return '';
		}

		$url = $this->buildUrl($item, $column['url'] ?? []);
		$label = $this->escape((string)$value);

		return '<a href="' . $this->escapeAttr($url) . '">' . $label . '</a>';
	}

	protected function renderContactCell($item, array $column)
	{
		$contactId = $this->getFieldValue($item, 'contactid');

		if (!$contactId) {
			return '';
		}

		$url = $this->buildUrl($item, $column['url'] ?? []);
		$parts = [];
		$parts[] = '<div>' . $this->escape((string)$contactId) . '</div>';
		$parts[] = '<div>' . $this->escape((string)$this->getFieldValue($item, 'billingname1')) . '</div>';

		$billingName2 = $this->getFieldValue($item, 'billingname2');
		if ($billingName2 !== null && $billingName2 !== '') {
			$parts[] = '<div>' . $this->escape((string)$billingName2) . '</div>';
		}

		return '<a href="' . $this->escapeAttr($url) . '">' . implode('', $parts) . '</a>';
	}

	protected function renderAddressCell($item, array $column)
	{
		$fields = isset($column['fields']) && is_array($column['fields']) ? $column['fields'] : [];
		$lines = [];

		foreach ($fields as $field) {
			$value = $this->getFieldValue($item, $field);
			if ($value === null || $value === '') {
				continue;
			}
			$lines[] = '<div>' . $this->escape((string)$value) . '</div>';
		}

		return implode('', $lines);
	}

	protected function renderEditableNoteCell($item, array $column)
	{
		$field = isset($column['field']) ? (string)$column['field'] : 'notes';
		$editableName = isset($column['editable_name']) ? (string)$column['editable_name'] : $field;
		$value = (string)$this->getFieldValue($item, $field, '');
		$itemId = (int)$this->getFieldValue($item, 'id');
		$hasValue = $value !== '';
		$attrs = [
			'class' => 'editable dw-editable-note',
			'data-name' => $editableName,
			'data-module' => $this->getModule(),
			'data-controller' => $this->getController(),
			'data-id' => (string)$itemId,
			'data-value' => $value,
			'data-type' => 'textarea',
		];

		if (!$hasValue) {
			$attrs['data-empty'] = 'true';
		}

		$label = $hasValue ? $value : $this->translate((string)($column['empty_label'] ?? 'TOOLBAR_NEW'));

		return '<div class="editableContainer"><pre' . $this->renderAttributes($attrs) . '>'
			. $this->escape($label)
			. '</pre></div>';
	}

	protected function renderDateCell($item, array $column)
	{
		$value = $this->getDisplayValue($item, $column);

		if (!$value) {
			return '';
		}

		$timestamp = strtotime((string)$value);
		if (!$timestamp) {
			return '';
		}

		$format = isset($column['format']) ? (string)$column['format'] : 'd.m.Y';
		return $this->escape(date($format, $timestamp));
	}

	protected function renderStateBadgeCell($item, array $column)
	{
		$field = isset($column['field']) ? (string)$column['field'] : 'state';
		$value = (string)$this->getFieldValue($item, $field, '');
		$stateLabelKey = '';
		$optionKey = isset($column['option_key']) ? (string)$column['option_key'] : '';
		$options = $optionKey !== '' ? (array)$this->getOption($optionKey, []) : [];

		if (isset($options[$value])) {
			$stateLabelKey = (string)$options[$value];
		}

		$label = $stateLabelKey !== '' ? $this->translate($stateLabelKey) : $value;
		$badgeClass = 'dw-badge--info';
		$badgeMap = isset($column['badge_map']) && is_array($column['badge_map']) ? $column['badge_map'] : [];

		if (isset($badgeMap[$value])) {
			$badgeClass = (string)$badgeMap[$value];
		}

		$editable = true;
		if (isset($column['editable']) && is_callable($column['editable'])) {
			$editable = (bool)call_user_func($column['editable'], $item, $column, $this);
		}

		if (!$editable) {
			return '<span class="dw-badge ' . $this->escapeAttr($badgeClass) . '">' . $this->escape($label) . '</span>';
		}

		$attrs = [
			'class' => 'editable dw-badge ' . $badgeClass,
			'data-name' => $field,
			'data-module' => $this->getModule(),
			'data-controller' => $this->getController(),
			'data-id' => (string)((int)$this->getFieldValue($item, 'id')),
			'data-value' => $value,
			'data-type' => 'select',
		];

		return '<div class="editableContainer"><span' . $this->renderAttributes($attrs) . '>'
			. $this->escape($label)
			. '</span></div>';
	}

	protected function renderPinCell($item, array $column)
	{
		$itemId = (int)$this->getFieldValue($item, 'id');
		$isPinned = (bool)$this->getFieldValue($item, 'pinned');
		$label = $isPinned ? $this->translate('DETACH') : $this->translate('ATTACH');
		$function = isset($column['function']) ? (string)$column['function'] : 'pin';

		return '<button type="button" class="dw-btn dw-btn--secondary" onclick="' . $this->escapeAttr($function) . '(' . $itemId . ')">'
			. $this->escape($label)
			. '</button>';
	}

	protected function renderActionsCell($item, array $column)
	{
		$toolbarInline = $this->getToolbarInline();
		$elements = isset($column['elements']) && is_array($column['elements']) ? $column['elements'] : [];
		$html = [];

		foreach ($elements as $element) {
			$name = isset($element['name']) ? (string)$element['name'] : '';
			if ($name === '') {
				continue;
			}

			$show = true;
			if (isset($element['show']) && is_callable($element['show'])) {
				$show = (bool)call_user_func($element['show'], $item, $element, $this);
			}

			if (!$show) {
				continue;
			}

			if ($toolbarInline && method_exists($toolbarInline, 'renderElement')) {
				$html[] = (string)$toolbarInline->renderElement($name);
			}
		}

		return '<div class="dw-row-actions">' . implode('', $html) . '</div>';
	}

	protected function renderCallbackCell($item, array $column)
	{
		if (empty($column['callback']) || !is_callable($column['callback'])) {
			return '';
		}

		return (string)call_user_func($column['callback'], $item, $column, $this);
	}

	public function buildUrl($item, $config)
	{
		if (is_string($config) && $config !== '') {
			return $this->replacePlaceholders($config, $item);
		}

		if (!is_array($config) || !$this->view) {
			return '#';
		}

		$module = isset($config['module']) ? (string)$config['module'] : $this->getModule();
		$controller = isset($config['controller']) ? (string)$config['controller'] : $this->getController();
		$action = isset($config['action']) ? (string)$config['action'] : 'index';
		$idField = isset($config['id_field']) ? (string)$config['id_field'] : 'id';
		$id = $this->getFieldValue($item, $idField);

		$params = [
			'module' => $module,
			'controller' => $controller,
			'action' => $action,
		];

		if (isset($config['module_field'])) {
			$params['module'] = (string)$this->getFieldValue($item, $config['module_field'], $module);
		}

		if (isset($config['controller_field'])) {
			$params['controller'] = (string)$this->getFieldValue($item, $config['controller_field'], $controller);
		}

		if ($id !== null && $id !== '') {
			$params['id'] = $id;
		}

		return (string)$this->view->url($params);
	}

	protected function replacePlaceholders($template, $item)
	{
		return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($item) {
			$value = $this->getFieldValue($item, $matches[1], '');
			return rawurlencode((string)$value);
		}, (string)$template);
	}

	public function renderAttributes(array $attributes)
	{
		$parts = [];

		foreach ($attributes as $key => $value) {
			$key = (string)$key;

			if ($key === '' || $value === null) {
				continue;
			}

			$parts[] = ' ' . $this->escapeAttr($key) . '="' . $this->escapeAttr((string)$value) . '"';
		}

		return implode('', $parts);
	}

	public function escape($value)
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	public function escapeAttr($value)
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
}
