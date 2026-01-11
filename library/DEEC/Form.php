<?php

class DEEC_Form
{
	protected $method = 'post';
	protected $locale = null;
	protected $elements = [];
	protected $errors = [];
	protected $translator = null;

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
		if (empty($cfg['name']) || empty($cfg['type'])) return;

		$name = $cfg['name'];

		$el = [
			'type' => $cfg['type'],
			'name' => $name,
			'label' => $cfg['label'] ?? $cfg['name'],
			'value' => $cfg['value'] ?? null,
			'default' => $cfg['default'] ?? null,
			'options' => $cfg['options'] ?? [],
			'source' => $cfg['source'] ?? null,
			'description' => $cfg['description'] ?? '',
			'info' => $cfg['info'] ?? '',
			'unit' => $cfg['unit'] ?? '',
			'attribs' => (!empty($cfg['attribs']) && is_array($cfg['attribs'])) ? $cfg['attribs'] : [],
			'format' => $cfg['format'] ?? null,
			'col' => isset($cfg['col']) ? (int)$cfg['col'] : null,
			'tab' => $cfg['tab'] ?? 'overview',
			'section' => $cfg['section'] ?? null,
			'order' => isset($cfg['order']) ? (int)$cfg['order'] : 1,
		];

		// HTML attributes go into attribs (so the view or renderer can output them)
		if (!empty($cfg['required'])) $el['attribs']['required'] = 'required';
		if (isset($cfg['min'])) $el['attribs']['min'] = $cfg['min'];
		if (isset($cfg['max'])) $el['attribs']['max'] = $cfg['max'];
		if (isset($cfg['step'])) $el['attribs']['step'] = $cfg['step'];
		if (!empty($cfg['pattern'])) $el['attribs']['pattern'] = $cfg['pattern'];
		if (!empty($cfg['class'])) $el['attribs']['class'] = $cfg['class'];

		// conditional visibility
		if (!empty($cfg['depends_on'])) $el['attribs']['data-depends-on'] = $cfg['depends_on'];
		if (!empty($cfg['depends_value'])) $el['attribs']['data-depends-value'] = $cfg['depends_value'];

		$this->elements[$name] = $el;
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
		$h = "<div class=\"row g-3\">\n"; // one grid row for all fields
		foreach ($this->elements as $el) {
			$h .= $this->renderElementHtml($el) . "\n";
		}
		$h .= "</div>\n"; // close .row
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
		$nav = '<ul class="tabs">';
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

			$nav .= '<li'.($isActive ? ' class="active"' : '').'>'
				. '<a href="#tab'.htmlspecialchars($key).'"'.$aAttrs.'>'.$title.'</a>'
				. '</li>';
		}
		$nav .= '</ul>';

		// CONTENT
		$content = '<div class="tab_container">';
		foreach ($tabs as $key => $t) {
			$isActive = ($key === $activeKey);
			$content .= '<div id="tab'.htmlspecialchars($key).'" class="tab_content'.($isActive ? ' active' : '').'">';

			// Wenn html gesetzt => direkt ausgeben (Attributes/Options/Ledger/Images/Files)
			if (isset($t['html'])) {
				$content .= (string)$t['html'];
				$content .= '</div>';
				continue;
			}

			// Standard: Form + Felder, die tab == $key haben
			$content .= '<form id="'.htmlspecialchars($formId).'-form" enctype="application/x-www-form-urlencoded" action="" method="'.htmlspecialchars($this->method).'">';
			$content .= '<div class="row">';

			// Optional: Spaltenkonfig
			// tabs['overview']['cols'] = [ ['class'=>'col-lg-7','fields'=>[...] ], ... ]
			if (!empty($t['cols']) && is_array($t['cols'])) {
				foreach ($t['cols'] as $col) {
					$colClass = $col['class'] ?? 'col-sm-12 col-lg-12';
					$content .= '<div class="'.htmlspecialchars($colClass).'">';

					// dl wrapper wie bisher
					if (!empty($col['dl'])) {
						$content .= '<dl class="form">';
					}

					// explizite fields liste
					if (!empty($col['fields']) && is_array($col['fields'])) {
						foreach ($col['fields'] as $item) {
							$content .= $this->renderTabItem($item);
						}
					} else {
						// fallback: alle Elemente des Tabs in dieser Spalte
						foreach (($elementsByTab[$key] ?? []) as $el) {
							$content .= $this->renderElementHtml($el);
						}
					}

					if (!empty($col['dl'])) {
						$content .= '</dl>';
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
					$content .= '<div class="row g-3">';

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
		$name = htmlspecialchars($nameRaw);
		$label = htmlspecialchars($this->translate((string)($el['label'] ?? $nameRaw)));
		$type = $el['type'] ?? 'text';
		$unit = $el['unit'] ? ' (' . htmlspecialchars($el['unit']) . ')' : '';

		$val = isset($el['value']) && $el['value'] !== ''
			? $el['value']
			: (isset($el['default']) ? $el['default'] : '');

		$hasError = !empty($this->errors[$nameRaw]);

		// attribs + class handling (wichtig: class nicht überschreiben)
		$base = array_merge(['id' => $nameRaw, 'name' => $nameRaw, 'type' => $type], $el['attribs']);

		$cls = trim((string)($base['class'] ?? ''));
		if ($type !== 'checkbox') {
			// bootstrap forms
			if ($cls === '') $cls = 'form-control';
			if (strpos($cls, 'form-control') === false && $type !== 'hidden') $cls .= ' form-control';
			if ($hasError && strpos($cls, 'is-invalid') === false) $cls .= ' is-invalid';
		} else {
			if ($hasError && strpos($cls, 'is-invalid') === false) $cls .= ' is-invalid';
		}
		$base['class'] = trim($cls);

		$attrs = '';
		foreach ($base as $k => $v) {
			if ($v === true) $v = $k;
			if ($v === false) continue;
			$attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
		}

		$desc = $el['description'] ? '<small class="form-text text-muted">'.htmlspecialchars($el['description']).'</small>' : '';
		$info = $el['info'] ? '<div class="form-text">'.htmlspecialchars($el['info']).'</div>' : '';

		$errorHtml = '';
		if ($hasError) {
			$msgs = [];
			foreach ($this->errors[$nameRaw] as $code) {
				$msgs[] = $this->translateError($code);
			}
			$errorHtml = '<div class="invalid-feedback d-block">'.htmlspecialchars(implode(' ', $msgs)).'</div>';
		}

		// wrapper
		$wrapperClasses = 'form-group';
		$colClass = ' col-md-12';
		if (!empty($el['col']) && $el['col'] >= 1 && $el['col'] <= 12) $colClass = ' col-md-' . (int)$el['col'];

		if ($type === 'hidden') {
			return '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars((string)$val).'">';
		}

		if ($type === 'textarea') {
			return '<div class="'.$wrapperClasses.$colClass.'">'.
				"<label for=\"$name\">$label$unit</label>".
				"<textarea$attrs>".htmlspecialchars((string)$val)."</textarea>".
				$errorHtml . $desc . $info .
				"</div>";
		}

		if ($type === 'select') {
			$opts = '';
			foreach ((array)$el['options'] as $optValue => $optLabel) {
				$valueEsc = htmlspecialchars((string)$optValue);
				$labelEsc = htmlspecialchars($this->translate((string)$optLabel));
				$sel = ((string)$val === (string)$optValue) ? ' selected' : '';
				$opts .= "<option value=\"$valueEsc\"$sel>$labelEsc</option>";
			}
			return '<div class="'.$wrapperClasses.$colClass.'">'.
				"<label for=\"$name\">$label$unit</label>".
				"<select$attrs>$opts</select>".
				$errorHtml . $desc . $info .
				"</div>";
		}

		if ($type === 'checkbox') {
			$isChecked = in_array((string)$el['value'], ['1','on','true'], true)
				|| in_array((string)($el['default'] ?? ''), ['1','on','true'], true);

			return '<div class="'.$wrapperClasses.$colClass.' form-check" style="margin-left:20px;">'
				. '<input type="hidden" name="'.$name.'" value="0">'
				. '<input'.$attrs.' value="1"'.($isChecked ? ' checked' : '').' class="form-check-input">'
				. '<label class="form-check-label" for="'.$name.'">'.$label.$unit.'</label>'
				. $errorHtml . $desc . $info
				. '</div>';
		}

		// text/email/number...
		$valueAttr = ' value="'.htmlspecialchars((string)$val).'"';
		return '<div class="'.$wrapperClasses.$colClass.'">'.
			"<label for=\"$name\">$label$unit</label>".
			"<input$attrs$valueAttr>".
			$errorHtml . $desc . $info .
			"</div>";
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
