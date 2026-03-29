<?php

/**
 * DEEC_Form – Element configuration
 *
 * Goal:
 * - One consistent schema for all forms:
 *	a) DynamicForm (DB JSON)
 *	b) Forms built directly in PHP
 * - `attribs` contains ONLY real HTML attributes (plus data-* / aria-*)
 * - `unit` / `default` are NOT HTML attributes and must stay top-level
 *
 * ------------------------------------------------------------------
 * A) Allowed top-level keys per element
 * ------------------------------------------------------------------
 * Required:
 * - name (string)		Unique field name
 * - type (string)		e.g. text, number, email, select, textarea, checkbox, hidden, submit, button
 *
 * Optional (meta / rendering):
 * - label (string|null)		Label key/text (passed through translate())
 * - description (string)		Help text under the field
 * - info (string)				Additional info text
 * - unit (string|null)			Unit shown in label (e.g. "kW") -> NOT an HTML attribute
 * - default (mixed|null)		Default value (string|int|float|bool) -> NOT an HTML attribute
 * - value (mixed|null)			Current value (usually set by setValues())
 * - required (bool)			Mapped internally to attribs['required']
 * - options (array)			Only for type=select:
 *								- allowed: ["Label1","Label2"] or ["value"=>"Label"]
 * - col (int|null)				Bootstrap grid width 1..12 (layout only)
 * - wrap (bool)				Render wrapper div or not (default true)
 * - tab (string)				Tab key (default 'overview')
 * - section (string|null)		Section grouping headline
 * - order (int)				Sort order in tab/section
 * - format (array|null)		Filter schema for getFilteredValues()
 *
 * Optional (logic / JS):
 * - depends_on (string)		Mapped internally to attribs['data-depends-on']
 * - depends_value (string)		Mapped internally to attribs['data-depends-value']
 *
 * Optional (HTML attributes):
 * - attribs (array)			ONLY real HTML attributes:
 *								- allowlist (see buildAttribs):
 *								id, class, placeholder, readonly, disabled,
 *								min, max, step, pattern, minlength, maxlength,
 *								autocomplete, autofocus, multiple, size,
 *								rows, cols, accept, inputmode, spellcheck, title,
 *								checked, tabindex
 *								- always allowed: data-* and aria-*
 *
 * ------------------------------------------------------------------
 * B) Rules / conventions
 * ------------------------------------------------------------------
 * 1) `attribs` must be an array. If not array -> ignored.
 * 2) `unit` and `default` must NOT be inside `attribs`. They will be removed if found.
 * 3) `required` is provided as top-level bool and becomes HTML attribute `required`.
 * 4) min/max/step/pattern belong into `attribs` (real HTML attributes).
 * 5) depends_on/depends_value become data-* attributes.
 * 6) Unknown keys are discarded (strict mode) to keep the contract stable.
 *
 * Example:
 * [
 *	'name' => 'kuehlleistung',
 *	'type' => 'number',
 *	'label' => 'Required cooling capacity',
 *	'unit' => 'kW',
 *	'default' => 20,
 *	'required' => true,
 *	'attribs' => ['min'=>1,'max'=>1500,'step'=>0.1,'placeholder'=>'e.g. 20'],
 *	'col' => 6,
 * ]
 */

class DEEC_Form
{
	protected $method = 'post';
	protected $locale = null;
	protected $elements = [];
	protected $errors = [];
	protected $translator = null;

	public function addCsrfToken(string $name = 'csrf_token'): void
	{
		// falls schon vorhanden: nicht doppelt
		if (isset($this->elements[$name])) return;

		$this->addElement([
			'name' => $name,
			'type' => 'hidden',
			'value' => bin2hex(random_bytes(16)),
			'wrap' => false,
		]);
	}

	public function setMethod($method) { $this->method = $method ?: 'post'; }

	public function setLocale($locale): void
	{
		$this->locale = $locale;
	}

	public function getLocale()
	{
		return $this->locale;
	}

	public function addElement(array $cfg)
	{
		$cfg = $this->normalizeElementConfig($cfg);
		if ($cfg === null) return;

		$name = $cfg['name'];

		$this->elements[$name] = [
			'type' => $cfg['type'],
			'name' => $name,
			'label' => $cfg['label'],
			'description' => $cfg['description'],
			'info' => $cfg['info'],
			'unit' => $cfg['unit'],
			'value' => $cfg['value'],
			'default' => $cfg['default'],
			'options' => $cfg['options'],
			'attribs' => $cfg['attribs'],
			'format' => $cfg['format'],
			'col' => $cfg['col'],
			'tab' => $cfg['tab'],
			'section' => $cfg['section'],
			'order' => $cfg['order'],
			'wrap' => $cfg['wrap'],
			'source' => $cfg['source'],
			'module' => $cfg['module'] ?? '',
			'controller' => $cfg['controller'] ?? '',
			'parentid' => $cfg['parentid'] ?? 0,
			'rows' => $cfg['rows'] ?? [],
		];
	}

	/**
	* Normalisiert strikt auf das erlaubte Schema.
	* Gibt null zurück, wenn config unbrauchbar ist.
	*/
	protected function normalizeElementConfig(array $cfg): ?array
	{
		$name = isset($cfg['name']) ? trim((string)$cfg['name']) : '';
		$type = isset($cfg['type']) ? trim((string)$cfg['type']) : '';

		if ($name === '' || $type === '') return null;

		// allowlist: nur diese top-level keys werden akzeptiert
		$allowedKeys = [
			'name','type',
			'label','description','info','unit',
			'default','value',
			'required',
			'options',
			'attribs',
			'depends_on','depends_value',
			'format',
			'col','tab','section','order','wrap',
			'source',
			'module','controller','parentid','rows',
		];

		$clean = [];
		foreach ($allowedKeys as $k) {
			if (array_key_exists($k, $cfg)) {
				$clean[$k] = $cfg[$k];
			}
		}

		// defaults
		$clean['label'] = isset($clean['label']) ? (string)$clean['label'] : null;
		$clean['description'] = isset($clean['description']) ? (string)$clean['description'] : '';
		$clean['info'] = isset($clean['info']) ? (string)$clean['info'] : '';
		$clean['unit'] = isset($clean['unit']) ? (string)$clean['unit'] : '';
		$clean['default'] = array_key_exists('default', $clean) ? $clean['default'] : null;
		$clean['value'] = array_key_exists('value', $clean) ? $clean['value'] : null;
		$clean['required'] = !empty($clean['required']);

		if ($clean['type'] === 'multi') {
			$clean['rows'] = is_array($clean['rows'] ?? null) ? $clean['rows'] : [];
			$clean['parentid'] = (int)($clean['parentid'] ?? 0);
			$clean['module'] = (string)($clean['module'] ?? '');
			$clean['controller'] = (string)($clean['controller'] ?? '');
		}

		$clean['col'] = isset($clean['col']) ? (int)$clean['col'] : null;
		if ($clean['col'] !== null && ($clean['col'] < 1 || $clean['col'] > 12)) {
			$clean['col'] = null;
		}

		$clean['tab'] = isset($clean['tab']) ? (string)$clean['tab'] : 'overview';
		$clean['section'] = isset($clean['section']) ? (string)$clean['section'] : null;
		$clean['order'] = isset($clean['order']) ? (int)$clean['order'] : 1;
		$clean['wrap'] = array_key_exists('wrap', $clean) ? (bool)$clean['wrap'] : true;
		$clean['format'] = (isset($clean['format']) && is_array($clean['format'])) ? $clean['format'] : null;
		$clean['source'] = isset($clean['source']) ? $clean['source'] : null;

		// options nur für select
		if (($clean['type'] ?? '') === 'select') {
			$opts = $clean['options'] ?? [];
			if (!is_array($opts)) $opts = [];
			$clean['options'] = $opts;
		} else {
			$clean['options'] = [];
		}

		// attribs strict bauen (nur HTML / data- / aria-)
		$attribs = $this->buildAttribs($clean['attribs'] ?? []);

		// unit/default gehören nicht in attribs -> sicher entfernen
		unset($attribs['unit'], $attribs['default']);

		// required mapping -> attribs['required']
		if ($clean['required']) {
			$attribs['required'] = 'required';
		}

		// depends -> data-*
		if (!empty($clean['depends_on'])) {
			$attribs['data-depends-on'] = (string)$clean['depends_on'];
		}
		if (array_key_exists('depends_value', $clean) && $clean['depends_value'] !== null && $clean['depends_value'] !== '') {
			$attribs['data-depends-value'] = (string)$clean['depends_value'];
		}

		$clean['attribs'] = $attribs;

		// final
		$clean['name'] = $name;
		$clean['type'] = $type;

		return $clean;
	}

	public function addOptions(string $elementName, array $options, string $mode = 'merge'): void
	{
		if ($elementName === '' || empty($options)) return;
		if (!isset($this->elements[$elementName])) return;

		// ensure element has options array
		if (!isset($this->elements[$elementName]['options']) || !is_array($this->elements[$elementName]['options'])) {
			$this->elements[$elementName]['options'] = [];
		}

		// normalize option keys to string (select values are strings in HTML)
		$normalized = [];
		foreach ($options as $k => $v) {
			$normalized[(string)$k] = $v;
		}

		$current = $this->elements[$elementName]['options'];

		switch ($mode) {
			case 'replace':
				$this->elements[$elementName]['options'] = $normalized;
				break;

			case 'prepend':
				// new options first, keep existing keys if they already exist (existing wins)
				// if you want new to win, swap the + order
				$this->elements[$elementName]['options'] = $normalized + $current;
				break;

			case 'merge':
			default:
				// keep existing keys, add new ones only if key not already present
				// (like zend addMultiOptions behavior)
				$this->elements[$elementName]['options'] = $current + $normalized;
				break;
		}
	}

	public function addMultiOptions(array $optionsByElement, string $mode = 'merge'): void
	{
		if (empty($optionsByElement)) return;

		foreach ($optionsByElement as $elementName => $options) {
			if (!is_string($elementName)) continue;
			if (!is_array($options)) continue;

			// nur wenn das Element existiert
			if (!isset($this->elements[$elementName])) continue;

			$this->addOptions($elementName, $options, $mode);
		}
	}

	public function isValid(array $data)
	{
		$this->errors = [];
		$this->setValues($data);

		foreach ($this->elements as $el) {
			$name = $el['name'];
			$type = $el['type'];
			$val = $data[$name] ?? null;

			// required
			if (isset($el['attribs']['required']) && ($val === null || $val === '')) {
				$this->errors[$name][] = 'required';
				continue;
			}
			if ($val === null || $val === '') continue;

			// email
			if ($type === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
				$this->errors[$name][] = 'email';
			}

			// number + min/max
			if ($type === 'number') {
				if (!is_numeric($val)) {
					$this->errors[$name][] = 'number';
				} else {
					$f = (float)$val;
					if (isset($el['attribs']['min']) && $f < (float)$el['attribs']['min']) $this->errors[$name][] = 'min';
					if (isset($el['attribs']['max']) && $f > (float)$el['attribs']['max']) $this->errors[$name][] = 'max';
				}
			}

			// checkbox
			if ($type === 'checkbox') {
				// was kommt an?
				$valRaw = $data[$name] ?? null;
				$isTrue = in_array((string)$valRaw, ['1','on','true'], true);

				if (isset($el['attribs']['required']) && !$isTrue) {
					$this->errors[$name][] = 'required';
				}
				// Checkbox ist vollständig geprüft – nächste Schleifenrunde
				continue;
			}

			// pattern
			if (!empty($el['attribs']['pattern'])) {
				$pat = $el['attribs']['pattern'];
				// escape delimiter and anchor the regex
				$delim = '~';
				$safe = $delim . '^' . str_replace($delim, '\\' . $delim, $pat) . '$' . $delim . 'u';
				if (@preg_match($safe, '') !== false) {
					if (!preg_match($safe, (string)$val)) {
						$this->errors[$name][] = 'pattern';
					}
				}
			}
		}
		return empty($this->errors);
	}

	public function isValidPartial(array $data): bool
	{
		$this->errors = [];
		$this->setValues($data);

		foreach ($this->elements as $el) {
			$name = $el['name'];

			if (!array_key_exists($name, $data)) continue; // nicht gepostet => skip

			// readonly: nie blockieren (weil kommt evtl. aus js), aber auch nie speichern
			if (!empty($el['attribs']['readonly'])) continue;

			$type = $el['type'];
			$val = $data[$name] ?? null;

			if (isset($el['attribs']['required']) && ($val === null || $val === '')) {
				$this->errors[$name][] = 'required';
				continue;
			}
			if ($val === null || $val === '') continue;

			if ($type === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
				$this->errors[$name][] = 'email';
			}

			if ($type === 'number') {
				if (!is_numeric($val)) {
					$this->errors[$name][] = 'number';
				} else {
					$f = (float)$val;
					if (isset($el['attribs']['min']) && $f < (float)$el['attribs']['min']) $this->errors[$name][] = 'min';
					if (isset($el['attribs']['max']) && $f > (float)$el['attribs']['max']) $this->errors[$name][] = 'max';
				}
			}

			if ($type === 'checkbox') {
				$isTrue = in_array((string)$val, ['1','on','true'], true);
				if (isset($el['attribs']['required']) && !$isTrue) {
					$this->errors[$name][] = 'required';
				}
				continue;
			}

			if (!empty($el['attribs']['pattern'])) {
				$pat = $el['attribs']['pattern'];
				$delim = '~';
				$safe = $delim . '^' . str_replace($delim, '\\' . $delim, $pat) . '$' . $delim . 'u';
				if (@preg_match($safe, '') !== false && !preg_match($safe, (string)$val)) {
					$this->errors[$name][] = 'pattern';
				}
			}
		}

		return empty($this->errors);
	}

	// nur echte HTML-Attribute (plus aria-* / data-*)
	protected function buildAttribs($attribs): array
	{
		if (!is_array($attribs)) return [];

		$allowed = [
			'id','class','placeholder','readonly','disabled',
			'min','max','step','pattern','minlength','maxlength',
			'autocomplete','autofocus','multiple','size',
			'rows','cols','accept','inputmode','spellcheck','title',
			'checked','tabindex',
		];

		$out = [];

		foreach ($attribs as $k => $v) {
			$key = (string)$k;

			if ($key === '') continue;

			// data-* / aria-* immer
			if (strpos($key, 'data-') === 0 || strpos($key, 'aria-') === 0) {
				$out[$key] = $v;
				continue;
			}

			if (in_array($key, $allowed, true)) {
				$out[$key] = $v;
			}
		}

		return $out;
	}

	public function getErrors() { return $this->errors; }

	public function getElement(string $name)
	{
		return $this->elements[$name] ?? null;
	}

	public function getElements(){ return $this->elements; }

	public function getValue(string $name)
	{
		$el = $this->getElement($name);
		return $el['value'] ?? null;
	}

	public function getValues()
	{
		$out = [];
		foreach ($this->elements as $el) {
			if ($el['type'] === 'submit') continue;
			if ($el['name'] === 'csrf_token') continue; // usually skip
			$out[$el['name']] = $el['value'];
		}
		return $out;
	}

	public function setValue(string $name, $value): void
	{
		if (!isset($this->elements[$name])) {
			return;
		}

		$this->elements[$name]['value'] = $value;
	}

	public function setValues(array $values): void
	{
		foreach ($values as $name => $value) {
			$this->setValue($name, $value);
		}
	}

	public function setElementData(string $name, array $patch): self
	{
		if (!isset($this->elements[$name])) {
			// silently ignore
			return $this;
		}

		if (array_key_exists('rows', $patch)) {
			$this->elements[$name]['rows'] = is_array($patch['rows']) ? $patch['rows'] : [];
			unset($patch['rows']);
		}

		$this->elements[$name] = array_replace_recursive($this->elements[$name], $patch);
		return $this;
	}

	public function getDefault(string $name = null)
	{
		// 1) wenn element name gegeben ist -> default dieses elements
		if ($name !== null) {
			$el = $this->getElement($name);
			if (!$el) return null;

			// bevorzugt: eigenes default feld
			if (array_key_exists('default', $el) && $el['default'] !== null && $el['default'] !== '') {
				return $el['default'];
			}

			// fallback: attrib default (falls legacy code sowas setzt)
			if (!empty($el['attribs']) && is_array($el['attribs']) && array_key_exists('default', $el['attribs'])) {
				$d = $el['attribs']['default'];
				if ($d !== null && $d !== '') return $d;
			}

			// optional fallback: bei select erstes option-key
			if (($el['type'] ?? '') === 'select' && !empty($el['options']) && is_array($el['options'])) {
				$firstKey = array_key_first($el['options']);
				return $firstKey !== null ? (string)$firstKey : null;
			}

			return null;
		}

		// 2) ohne name: alle defaults als array (praktisch für debug / bulk)
		$out = [];
		foreach ($this->elements as $elName => $el) {
			$out[$elName] = $this->getDefault((string)$elName);
		}
		return $out;
	}

	public function getElementSources(): array
	{
		$out = [];
		foreach ($this->elements as $name => $el) {
			if (!empty($el['source'])) $out[$name] = $el['source'];
		}
		return $out;
	}

	public function render()
	{
		$h = "<div class=\"dw-form-row\">\n";
		foreach ($this->elements as $el) {
			$h .= $this->renderElementHtml($el) . "\n";
		}
		$h .= "</div>\n";
		return $h;
	}

	public function renderForm(array $cfg = []): string
	{
		$formId = $cfg['id'] ?? 'form';
		$activeTab = $cfg['activeTab'] ?? null; // z.B. "#tabDetails" oder "details"
		$tabsCfg = $cfg['tabs'] ?? [];

		// activeTab normalisieren: "#tabDetails" -> "details"
		$activeKey = null;
		if (is_string($activeTab) && $activeTab !== '') {
			$t = ltrim($activeTab, '#');
			if (stripos($t, 'tab') === 0) {
				$t = substr($t, 3); // "Details"
			}
			$activeKey = strtolower($t); // "details"
		}

		// Tabs ohne show oder show=true
		$tabs = [];
		foreach ($tabsCfg as $key => $t) {
			$show = $t['show'] ?? true;
			if ($show) {
				$tabs[$key] = $t;
			}
		}

		if (!$tabs) {
			// fallback: einfach alle Elemente
			return '<form id="'.htmlspecialchars($formId).'" method="'.htmlspecialchars($this->method).'">'
				. $this->render()
				. '</form>';
		}

		// wenn kein active gesetzt -> erstes tab
		if (!$activeKey || !isset($tabs[$activeKey])) {
			$activeKey = array_key_first($tabs);
		}

		// Elemente pro Tab einsammeln (nur die ohne html-tab)
		$elementsByTab = [];
		foreach ($this->elements as $el) {
			$tab = $el['tab'] ?? 'overview';
			$tab = (string)$tab;
			$elementsByTab[$tab][] = $el;
		}

		// pro Tab sortieren nach 'order' (und stabil nach name)
		/*foreach ($elementsByTab as $tab => &$list) {
			usort($list, function ($a, $b) {
				$oa = (int)($a['order'] ?? 0);
				$ob = (int)($b['order'] ?? 0);
				if ($oa === $ob) {
					return strcmp((string)$a['name'], (string)$b['name']);
				}
				return $oa <=> $ob;
			});
		}
		unset($list);*/

		// NAV
		$nav = '<ul class="dw-tabs">';
		foreach ($tabs as $key => $t) {
			$titleKey = $t['title'] ?? $key;
			$title = htmlspecialchars($this->translate((string)$titleKey));
			$isActive = ($key === $activeKey);

			$aAttrs = '';
			if (!empty($t['a_attrs']) && is_array($t['a_attrs'])) {
				foreach ($t['a_attrs'] as $ak => $av) {
					$aAttrs .= ' ' . htmlspecialchars((string)$ak) . '="' . htmlspecialchars((string)$av) . '"';
				}
			}

			$nav .= '<li class="dw-tabs__item'.($isActive ? ' is-active' : '').'">'
				. '<a class="dw-tabs__link" href="#tab'.htmlspecialchars($key).'"'.$aAttrs.'>'.$title.'</a>'
				. '</li>';
		}
		$nav .= '</ul>';

		// CONTENT
		$content = '<div class="dw-tab-panels">';
		foreach ($tabs as $key => $t) {
			$isActive = ($key === $activeKey);
			$content .= '<div id="tab'.htmlspecialchars($key).'" class="dw-tab-panel'.($isActive ? ' is-active' : '').'">';

			// Wenn html gesetzt => direkt ausgeben (Attributes/Options/Ledger/Images/Files)
			if (isset($t['html'])) {
				$content .= (string)$t['html'];
				$content .= '</div>';
				continue;
			}

			// Standard: Form + Felder, die tab == $key haben
			$content .= '<form id="'.htmlspecialchars($formId).'-form" enctype="application/x-www-form-urlencoded" action="" method="'.htmlspecialchars($this->method).'">';
			$content .= '<div class="dw-form-layout">';

			// Spaltenkonfig
			if (!empty($t['cols']) && is_array($t['cols'])) {
				foreach ($t['cols'] as $col) {
					$colClass = $col['class'] ?? 'dw-form-layout__col';
					$content .= '<div class="'.htmlspecialchars($colClass).'">';

					if (!empty($col['fields']) && is_array($col['fields'])) {
						foreach ($col['fields'] as $item) {
							$content .= $this->renderTabItem($item);
						}
					} else {
						foreach (($elementsByTab[$key] ?? []) as $el) {
							$content .= $this->renderElementHtml($el);
						}
					}

					$content .= '</div>';
				}
			} else {
				// Keine cols: automatisch nach section gruppieren
				$tabEls = $elementsByTab[$key] ?? [];
				$bySection = $this->groupBySection($tabEls);

				foreach ($bySection as $secKey => $list) {

					if ($secKey !== '__none__') {
						$content .= '<h4>' . htmlspecialchars($this->translate((string)$secKey)) . '</h4>';
					}

					// eigene Row pro Section (sauberes Bootstrap Grid)
					$content .= '<div class="dw-form-row">';

					foreach ($list as $el) {
						$content .= $this->renderElementHtml($el);
					}

					$content .= '</div>';
				}
			}

			$content .= '</div></form>';
			$content .= '</div>';
		}
		$content .= '</div>';

		return $nav . $content;
	}

	/**
	 * Helper um in cols['fields'] nicht nur fieldnames, sondern auch heading/hr/html zu erlauben.
	 * items:
	 * - "sku"
	 * - ['heading' => 'ITEMS_PRICES']
	 * - ['hr' => true]
	 * - ['html' => '<div>...</div>']
	 */
	protected function renderTabItem($item): string
	{
		if (is_string($item)) {
			return $this->renderElement($item);
		}
		if (!is_array($item)) {
			return '';
		}

		if (!empty($item['heading'])) {
			$h = htmlspecialchars($this->translate((string)$item['heading']));
			return '<h4>'.$h.'</h4>';
		}
		if (!empty($item['hr'])) {
			return '<hr>';
		}
		if (array_key_exists('html', $item)) {
			return (string)$item['html'];
		}
		if (!empty($item['field'])) {
			return $this->renderElement((string)$item['field']);
		}
		return '';
	}

	public function renderElementRow(string $name, array $row = [], array $ctx = []): string
	{
		$el = $this->getElement($name);
		if (!$el) return '';

		// clone element config (no mutation in $this->elements)
		$tmp = $el;

		// value from row by default
		if (array_key_exists($name, $row)) {
			$tmp['value'] = $row[$name];
		}

		// merge attrib overrides (row-specific)
		$tmp['attribs'] = is_array($tmp['attribs'] ?? null) ? $tmp['attribs'] : [];

		$rowId = isset($row['id']) ? (string)$row['id'] : '';
		$controller = (string)($ctx['controller'] ?? '');
		$module = (string)($ctx['module'] ?? '');
		$ordering = isset($row['ordering']) ? (string)$row['ordering'] : '';

		// unique id per row + field
		// example: address_12_city
		$tmp['attribs']['id'] = ($controller !== '' && $rowId !== '')
			? ($controller . '_' . $rowId . '_' . $name)
			: ($tmp['attribs']['id'] ?? $name);

		// data-* used by your JS (trash/save/sort)
		if ($rowId !== '') $tmp['attribs']['data-id'] = $rowId;
		if ($ordering !== '') $tmp['attribs']['data-ordering'] = $ordering;
		if ($controller !== '') $tmp['attribs']['data-controller'] = $controller;
		if ($module !== '') $tmp['attribs']['data-module'] = $module;

		// render cloned element
		return $this->renderElementHtml($tmp);
	}

	/**
	* convenience: render only a subset in original order
	*/
	public function renderElementsRow(array $names, array $row = [], array $ctx = []): string
	{
		$h = '';
		foreach ($names as $n) {
			$n = (string)$n;
			if ($n === '') continue;
			$h .= $this->renderElementRow($n, $row, $ctx);
		}
		return $h;
	}

	protected function groupBySection(array $elements): array
	{
		// Ergebnis: [sectionKey => [el, el, ...]]
		// sectionKey: '__none__' für keine section
		$out = [];
		foreach ($elements as $el) {
			$sec = $el['section'] ?? null;
			$sec = is_string($sec) ? trim($sec) : $sec;

			$key = ($sec === null || $sec === '') ? '__none__' : $sec;
			if (!isset($out[$key])) $out[$key] = [];
			$out[$key][] = $el;
		}

		// innerhalb jeder section nach order (und name) sortieren
		/*foreach ($out as &$list) {
			usort($list, function ($a, $b) {
				$oa = (int)($a['order'] ?? 0);
				$ob = (int)($b['order'] ?? 0);
				if ($oa === $ob) return strcmp((string)$a['name'], (string)$b['name']);
				return $oa <=> $ob;
			});
		}
		unset($list);*/

		return $out;
	}

	public function getFormatSchema(): array
	{
		$schema = [];
		foreach ($this->elements as $name => $el) {
			if (!empty($el['format']) && is_array($el['format'])) {
				$schema[$name] = $el['format'];
			}
		}
		return $schema;
	}

	public function getFilteredValues(): array
	{
		$values = $this->getValues();
		$schema = $this->getFormatSchema();

		// zentrale engine
		return DEEC_Filter::applyAll($values, $schema, $this->getLocale());
	}

	public function getFilteredValuesPartial(array $data): array
	{
		$values = [];
		foreach ($this->getPostedFieldNames($data) as $name) {
			$el = $this->elements[$name];

			// readonly nie speichern
			if (!empty($el['attribs']['readonly'])) continue;

			$values[$name] = $data[$name];
		}

		$schema = [];
		foreach ($values as $name => $_) {
			$f = $this->elements[$name]['format'] ?? null;
			if ($f) $schema[$name] = $f;
		}

		return DEEC_Filter::applyAll($values, $schema, $this->getLocale());
	}

	public function getPostedFieldNames(array $data): array
	{
		$names = [];
		foreach ($data as $k => $v) {
			if (isset($this->elements[$k])) $names[] = $k;
		}
		return $names;
	}

	public function renderElement(string $name): string
	{
		$el = $this->getElement($name);
		if (!$el) return '';
		return $this->renderElementHtml($el);
	}

	protected function renderElementHtml(array $el)
	{
		$nameRaw = (string)$el['name'];
		if ($nameRaw === '') return '';

		$nameEsc = htmlspecialchars($nameRaw);

		$type = (string)($el['type'] ?? 'text');
		if ($type === '') $type = 'text';

		if ($type === 'multi') {
			return $this->renderMultiElementHtml($el);
		}

		// ------------------------------------------------------------
		// Value resolution ("0" must NOT fallback to default)
		// ------------------------------------------------------------
		$hasExplicitValue = array_key_exists('value', $el) && $el['value'] !== null && $el['value'] !== '';
		if ($hasExplicitValue) {
			$val = $el['value'];
		} else {
			$hasDefault = array_key_exists('default', $el) && $el['default'] !== null && $el['default'] !== '';
			$val = $hasDefault ? $el['default'] : '';
		}

		$hasError = !empty($this->errors[$nameRaw]);

		// ------------------------------------------------------------
		// Build base attributes
		// - use attribs ONLY from element (already filtered by addElement/buildAttribs)
		// - ensure id/name/type exist
		// ------------------------------------------------------------
		$attribs = [];
		if (!empty($el['attribs']) && is_array($el['attribs'])) {
			$attribs = $el['attribs'];
		}

		// enforce correct id/name/type (element contract)
		$attribs['id'] = $attribs['id'] ?? $nameRaw;
		$attribs['name'] = $attribs['name'] ?? $nameRaw;

		// for textarea/select we will remove/ignore 'type' later
		$attribs['type'] = $attribs['type'] ?? $type;

		// CSS class handling
		$cls = trim((string)($attribs['class'] ?? ''));

		// label optional
		$labelKey = $el['label'] ?? null;
		$hasLabel = is_string($labelKey) && trim($labelKey) !== '';

		$labelTxt = $hasLabel ? $this->translate($labelKey) : '';
		$unitTxt = ($hasLabel && !empty($el['unit'])) ? ' (' . $el['unit'] . ')' : '';

		$forId = htmlspecialchars((string)($attribs['id'] ?? $nameRaw));
		$labelHtml = $hasLabel
			? '<label class="dw-label" for="'.$forId.'">'.htmlspecialchars($labelTxt . $unitTxt).'</label>'
			: '';

		$baseClass = '';
		if ($type === 'textarea') {
			$baseClass = 'dw-textarea';
		} elseif ($type === 'select') {
			$baseClass = 'dw-select';
		} elseif (!in_array($type, ['hidden', 'submit', 'button', 'checkbox'], true)) {
			$baseClass = 'dw-input';
		}

		if ($baseClass !== '' && strpos($cls, $baseClass) === false) {
			$cls = trim($cls . ' ' . $baseClass);
		}

		if ($hasError && strpos($cls, 'is-invalid') === false) {
			$cls = trim($cls . ' is-invalid');
		}

		$attribs['class'] = trim($cls);

		// ------------------------------------------------------------
		// Convert attribs to HTML string
		// - boolean true => attribute="attribute"
		// - boolean false => skip attribute
		// ------------------------------------------------------------
		$attrs = '';
		foreach ($attribs as $k => $v) {
			if ($v === false || $v === null) continue;

			$key = (string)$k;

			// allow boolean attributes
			if ($v === true) {
				$attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($key) . '"';
				continue;
			}

			$attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
		}

		// Description / info / errors
		$desc = !empty($el['description'])
			? '<div class="dw-field__description">'.htmlspecialchars((string)$el['description']).'</div>'
			: '';

		$info = !empty($el['info'])
			? '<div class="dw-field__info">'.htmlspecialchars((string)$el['info']).'</div>'
			: '';

		$errorHtml = '';
		if ($hasError) {
			$msgs = [];
			foreach ((array)$this->errors[$nameRaw] as $code) {
				$msgs[] = $this->translateError($code);
			}
			$errorHtml = '<div class="dw-field__error">'
				. htmlspecialchars(implode(' ', $msgs))
				. '</div>';
		}

		// ------------------------------------------------------------
		// Wrapper handling
		// ------------------------------------------------------------
		$wrap = array_key_exists('wrap', $el) ? (bool)$el['wrap'] : true;

		$wrapperClasses = '';
		$colClass = '';

		$wrapperClasses = '';
		if ($wrap) {
			$wrapperClasses = 'dw-field dw-field--col-12';
			if (!empty($el['col']) && (int)$el['col'] >= 1 && (int)$el['col'] <= 12) {
				$wrapperClasses = 'dw-field dw-field--col-' . (int)$el['col'];
			}
		}

		// ------------------------------------------------------------
		// Render by type
		// ------------------------------------------------------------
		if ($type === 'hidden') {
			// IMPORTANT: do not reuse $attrs because it contains class/id/type etc.
			// render minimal hidden input, but keep name/id
			return '<input type="hidden" name="'.$nameEsc.'" value="'.htmlspecialchars((string)$val).'">';
		}

		if ($type === 'button') {
			// remove form-control if it sneaked in
			$btnAttribs = $attribs;
			if (!empty($btnAttribs['class'])) {
				$btnAttribs['class'] = trim(str_replace('form-control', '', (string)$btnAttribs['class']));
				if ($btnAttribs['class'] === '') unset($btnAttribs['class']);
			}

			// enforce type
			$btnAttribs['type'] = 'button';

			$btnAttrs = '';
			foreach ($btnAttribs as $k => $v) {
				if ($v === false || $v === null) continue;
				$key = (string)$k;
				if ($v === true) $v = $key;
				$btnAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
			}

			$btnText = $hasLabel ? htmlspecialchars($labelTxt . $unitTxt) : '';

			// accessibility: if no visible text and no aria-label
			if ($btnText === '' && empty($btnAttribs['aria-label'])) {
				$btnAttrs .= ' aria-label="' . htmlspecialchars($nameRaw) . '"';
			}

			$btn = '<button'.$btnAttrs.'>'.$btnText.'</button>';
			return $wrap ? '<div class="'.$wrapperClasses.$colClass.'">'.$btn.'</div>' : $btn;
		}

		if ($type === 'submit') {
			$btnAttribs = $attribs;

			// remove form-control if it sneaked in
			if (!empty($btnAttribs['class'])) {
				$btnAttribs['class'] = trim(str_replace('form-control', '', (string)$btnAttribs['class']));
				if ($btnAttribs['class'] === '') unset($btnAttribs['class']);
			}

			$btnAttribs['type'] = 'submit';

			$btnAttrs = '';
			foreach ($btnAttribs as $k => $v) {
				if ($v === false || $v === null) continue;
				$key = (string)$k;
				if ($v === true) $v = $key;
				$btnAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
			}

			$btnText = $hasLabel ? htmlspecialchars($labelTxt . $unitTxt) : 'Submit';

			$btn = '<button'.$btnAttrs.'>'.$btnText.'</button>';
			return $wrap ? '<div class="'.$wrapperClasses.$colClass.'">'.$btn.'</div>' : $btn;
		}

		if ($type === 'textarea') {
			// textarea must not have type=""
			// rebuild attrs without type
			$taAttribs = $attribs;
			unset($taAttribs['type']);

			$taAttrs = '';
			foreach ($taAttribs as $k => $v) {
				if ($v === false || $v === null) continue;
				$key = (string)$k;
				if ($v === true) $v = $key;
				$taAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
			}

			$field = $labelHtml
				. "<textarea$taAttrs>".htmlspecialchars((string)$val)."</textarea>"
				. $errorHtml . $desc . $info;

			return $wrap ? '<div class="'.$wrapperClasses.$colClass.'">'.$field.'</div>' : $field;
		}

		if ($type === 'select') {
			// select must not have type=""
			$selAttribs = $attribs;
			unset($selAttribs['type']);

			$selAttrs = '';
			foreach ($selAttribs as $k => $v) {
				if ($v === false || $v === null) continue;
				$key = (string)$k;
				if ($v === true) $v = $key;
				$selAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
			}

			$opts = '';
			foreach ((array)($el['options'] ?? []) as $optValue => $optLabel) {
				// allow list-style options: ["A","B"] -> values 0/1; or associative
				$ov = (string)$optValue;
				$ol = is_string($optLabel) ? $optLabel : (string)$optLabel;

				$valueEsc = htmlspecialchars($ov);
				$labelEsc = htmlspecialchars($this->translate($ol));

				$sel = ((string)$val === (string)$ov) ? ' selected' : '';
				$opts .= "<option value=\"$valueEsc\"$sel>$labelEsc</option>";
			}

			$field = $labelHtml
				. "<select$selAttrs>$opts</select>"
				. $errorHtml . $desc . $info;

			return $wrap ? '<div class="'.$wrapperClasses.$colClass.'">'.$field.'</div>' : $field;
		}

		if ($type === 'checkbox') {
			$raw = $hasExplicitValue ? $el['value'] : ($el['default'] ?? null);
			$isChecked = in_array((string)$raw, ['1','on','true'], true);

			$cbAttribs = $attribs;
			$cbAttribs['type'] = 'checkbox';

			$cbCls = trim((string)($cbAttribs['class'] ?? ''));
			if ($cbCls === '') {
				$cbAttribs['class'] = '';
			}

			$cbAttrs = '';
			foreach ($cbAttribs as $k => $v) {
				if ($v === false || $v === null) continue;
				$key = (string)$k;
				if ($v === true) $v = $key;
				$cbAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$v) . '"';
			}

			$checkbox = '<input type="hidden" name="'.$nameEsc.'" value="0">';
			$checkbox .= '<label class="dw-checkbox">';
			$checkbox .= '<input'.$cbAttrs.' value="1"'.($isChecked ? ' checked' : '').'>';
			if ($hasLabel) {
				$checkbox .= '<span class="dw-checkbox__label">'.htmlspecialchars($labelTxt . $unitTxt).'</span>';
			}
			$checkbox .= '</label>';
			$checkbox .= $errorHtml . $desc . $info;

			return $wrap ? '<div class="'.$wrapperClasses.'">'.$checkbox.'</div>' : $checkbox;
		}

		// ------------------------------------------------------------
		// Default: text/email/number/etc.
		// ------------------------------------------------------------
		$valueAttr = ' value="'.htmlspecialchars((string)$val).'"';

		$field = $labelHtml
			. "<input$attrs$valueAttr>"
			. $errorHtml . $desc . $info;

		return $wrap ? '<div class="'.$wrapperClasses.$colClass.'">'.$field.'</div>' : $field;
	}

	public function getMultiElements(): array
	{
		$out = [];
		foreach ($this->elements as $name => $el) {
			if (($el['type'] ?? '') === 'multi') {
				$out[$name] = $el;
			}
		}
		return $out;
	}

	protected function renderMultiElementHtml(array $el): string
	{
		$name = (string)$el['name'];			// z.B. address
		$labelKey = (string)($el['label'] ?? '');
		$module = (string)($el['module'] ?? '');
		$controller = (string)($el['controller'] ?? '');
		$parentid = (int)($el['parentid'] ?? 0);
		$rows = is_array($el['rows'] ?? null) ? $el['rows'] : [];

		// Überschrift wie bisher
		$html = '';
		if ($labelKey !== '') {
			$html .= '<h4>' . htmlspecialchars($this->translate($labelKey)) . '</h4>';
		}

		// Container
		$html .= '<div id="' . htmlspecialchars($name) . '-container" class="multiformContainer dw-multiform"'
				. ' data-parentid="' . htmlspecialchars((string)$parentid) . '"'
				. ' data-controller="' . htmlspecialchars($controller) . '"'
				. '>';

		$html .= '<div id="' . htmlspecialchars($name) . '" class="multiform dw-multiform__list">';

		// Row-Form automatisch aus module/controller erzeugen: Contacts_Form_Address
		$rowForm = $this->makeRowFormForMulti($module, $controller);

		$this->applyOptionsIfAvailable($rowForm);

		// Felder der Row-Form
		$subElements = method_exists($rowForm, 'getElements') ? $rowForm->getElements() : [];
		$fieldNames = array_keys($subElements);

		$ctx = [
			'module' => $module,
			'controller' => $controller,
		];

		$cnt = count($rows);
		foreach ($rows as $row) {
			if (!is_array($row) || empty($row['id'])) {
				continue;
			}

			$rowId = (string)$row['id'];

			$html .= '<div id="' . htmlspecialchars($name . $rowId) . '" class="dw-multiform__item dw-card">';
			$html .= '<div class="dw-form-row">';

			foreach ($fieldNames as $field) {
				// renderElementRow kommt aus DEEC_Form und setzt id + data-* + value korrekt
				if (method_exists($rowForm, 'renderElementRow')) {
					$html .= $rowForm->renderElementRow($field, $row, $ctx);
				} else {
					// fallback: falls renderElementRow nicht existiert, müsst ihr es im RowForm bereitstellen
					$html .= $this->renderMultiFallbackField($rowForm, $field, $row, $ctx, $name);
				}
			}

			$html .= '</div>';

			// delete button
			$html .= '<div class="dw-multiform__actions">';
			$html .= '<button type="button" class="delete nolabel"'
					. ' onclick="trash(' . (int)$rowId . ', deleteConfirm, \'' . htmlspecialchars($controller) . '\', \'' . htmlspecialchars($module) . '\');"></button>';
			$html .= '</div>';
			$html .= '</div>';
		}

		// add button wie bisher, global.js click handler greift hier
		$html .= '<button type="button" class="addMulti add nolabel"'
				. ' data-module="' . htmlspecialchars($module) . '"'
				. ' data-controller="' . htmlspecialchars($controller) . '"></button>';

		$html .= '</div></div>';

		// col wrapper wie bei normalen elemente
		$html = $this->wrapByColIfNeeded($html, $el);

		return $html;
	}

	protected function makeRowFormForMulti(string $module, string $controller): DEEC_Form
	{
		$class = DEEC_Util::formClassFromModuleController($module, $controller);

		if (!class_exists($class)) {
			throw new RuntimeException('Multi row form class not found: ' . $class);
		}

		$form = new $class();

		if (!$form instanceof DEEC_Form) {
			throw new RuntimeException('Multi row form must extend DEEC_Form: ' . $class);
		}

		return $form;
	}

	protected function applyOptionsIfAvailable(DEEC_Form $form): void
	{
		// Zend Helper "Options" verfügbar?
		if (!class_exists('Zend_Controller_Action_HelperBroker')) return;

		try {
			$helper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		} catch (Exception $e) {
			return;
		}

		if ($helper && method_exists($helper, 'applyFormOptions')) {
			$helper->applyFormOptions($form);
		}
	}

	protected function wrapByColIfNeeded(string $html, array $el): string
	{
		$col = (int)($el['col'] ?? 0);

		if ($col > 0) {
			return '<div class="dw-field dw-field--col-' . (int)$col . '">' . $html . '</div>';
		}

		return '<div class="dw-field dw-field--col-12">' . $html . '</div>';
	}

	public function getTranslator()
	{
		if ($this->translator) return $this->translator;
		if (class_exists('Zend_Registry') && Zend_Registry::isRegistered('DEEC_Translate')) {
			$this->translator = Zend_Registry::get('DEEC_Translate');
		}

		return $this->translator;
	}

	public function translate(string $key, array $args = []): string
	{
		$t = $this->getTranslator();
		if ($t && method_exists($t, 't')) {
			return $t->t($key, $args);
		}
		return $key;
	}

	protected function translateError($code)
	{
		switch ($code) {
			case 'required': return 'Dieses Feld ist erforderlich.';
			case 'email': return 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
			case 'number': return 'Bitte geben Sie eine Zahl ein.';
			case 'min': return 'Der eingegebene Wert ist zu klein.';
			case 'max': return 'Der eingegebene Wert ist zu groß.';
			case 'pattern': return 'Das Format ist ungültig.';
			default: return 'Ungültige Eingabe.';
		}
	}
}
