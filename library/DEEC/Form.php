<?php

class DEEC_Form
{
    protected $method = 'post';
    protected $elements = [];
    protected $errors  = [];

    public function setMethod($method) { $this->method = $method ?: 'post'; }

    public function addElement(array $cfg)
    {
        if (empty($cfg['name']) || empty($cfg['type'])) return;

        $el = [
            'type'        => $cfg['type'],
            'name'        => $cfg['name'],
            'label'       => $cfg['label'] ?? $cfg['name'],
            'value'       => $cfg['value'] ?? null,
        	'default'     => $cfg['default'] ?? null,
            'options'     => $cfg['options'] ?? [],
            'description' => $cfg['description'] ?? '',
            'unit'        => $cfg['unit'] ?? '',
            'attribs'     => [],
		    'col'         => isset($cfg['col']) ? (int)$cfg['col'] : null,
        ];

        // HTML attributes go into attribs (so the view or renderer can output them)
        if (!empty($cfg['required']))      $el['attribs']['required'] = 'required';
        if (isset($cfg['min']))            $el['attribs']['min']      = $cfg['min'];
        if (isset($cfg['max']))            $el['attribs']['max']      = $cfg['max'];
        if (isset($cfg['step']))           $el['attribs']['step']     = $cfg['step'];
        if (!empty($cfg['pattern']))       $el['attribs']['pattern']  = $cfg['pattern'];
        if (!empty($cfg['class']))         $el['attribs']['class']    = $cfg['class'];

        // conditional visibility
        if (!empty($cfg['depends_on']))    $el['attribs']['data-depends-on']    = $cfg['depends_on'];
        if (!empty($cfg['depends_value'])) $el['attribs']['data-depends-value'] = $cfg['depends_value'];

        $this->elements[] = $el;
    }

    public function populate(array $values)
    {
        foreach ($this->elements as &$el) {
            if (array_key_exists($el['name'], $values)) {
                $el['value'] = $values[$el['name']];
            }
        }
        unset($el);
    }

    public function isValid(array $data)
    {
        $this->errors = [];
        $this->populate($data);

        foreach ($this->elements as $el) {
            $name  = $el['name'];
            $type  = $el['type'];
            $val   = $data[$name] ?? null;

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
                $pat   = $el['attribs']['pattern'];
                // escape delimiter and anchor the regex
                $delim = '~';
                $safe  = $delim . '^' . str_replace($delim, '\\' . $delim, $pat) . '$' . $delim . 'u';
                if (@preg_match($safe, '') !== false) {
                    if (!preg_match($safe, (string)$val)) {
                        $this->errors[$name][] = 'pattern';
                    }
                }
            }
        }
        return empty($this->errors);
    }

    public function getErrors()  { return $this->errors; }

    public function getElements(){ return $this->elements; }

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

	public function render()
	{
	    $h = "<div class=\"row g-3\">\n"; // one grid row for all fields
		foreach ($this->elements as $el) {
		    $h .= $this->renderElement($el) . "\n";
		}
	    $h .= "</div>\n"; // close .row
		return $h;
	}

	protected function renderElement(array $el)
	{
		$name  = htmlspecialchars($el['name']);
		$label = $el['label'] ?? $name;
		$type  = $el['type'] ?? 'text';
		$unit  = $el['unit'] ? ' (' . htmlspecialchars($el['unit']) . ')' : '';

		// If no value is set from session/post, use the default if provided
		$val = isset($el['value']) && $el['value'] !== ''
			? $el['value']
			: (isset($el['default']) ? $el['default'] : '');

		// build attribute string
		$attrs = '';
		$base  = array_merge(['id' => $name, 'name' => $name, 'type' => $type], $el['attribs']);
		foreach ($base as $k => $v) {
		    if ($v === true)  $v = $k;
		    if ($v === false) continue;
		    $attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
		}

		// wrapper classes + wrapper data attributes for dependency
		$wrapperClasses = 'form-group';
		$wrapperData = '';
		if (!empty($el['attribs']['data-depends-on'])) {
		    $wrapperClasses .= ' dependent-field';
		    $wrapperData .= ' data-depends-on="' . htmlspecialchars($el['attribs']['data-depends-on']) . '"';
		}
		if (!empty($el['attribs']['data-depends-value'])) {
		    $wrapperData .= ' data-depends-value="' . htmlspecialchars($el['attribs']['data-depends-value']) . '"';
		}

		// Column width: default full width, or col-md-{col} if provided
		$colClass = ' col-md-12';
		if (!empty($el['col']) && $el['col'] >= 1 && $el['col'] <= 12) {
			$colClass = ' col-md-' . (int)$el['col'];
		}

		$desc  = $el['description'] ? '<small class="form-text text-muted">'.htmlspecialchars($el['description']).'</small>' : '';
		$errorHtml = '';
		if (!empty($this->errors[$el['name']])) {
		    $errorHtml .= '<div class="text-danger small mt-1">';
		    foreach ($this->errors[$el['name']] as $code) {
		        $errorHtml .= '<div>' . $this->translateError($code) . '</div>';
		    }
		    $errorHtml .= '</div>';
		}

		if ($type === 'hidden') {
		    return '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars((string)$val).'">';
		}

		if ($type === 'submit') {
			$btnText = $label ?: 'Weiter';
			$class   = $el['attribs']['class'] ?? 'btn btn-primary';

			// Build the submit button (right-aligned column)
			$submitCol =
				'<div class="form-group col-md-6 text-end">'.
				    '<button type="submit" name="'.htmlspecialchars($name).'" class="'.htmlspecialchars($class).'">'.
				        htmlspecialchars($btnText).
				    '</button>'.
				'</div>';

			// If not step 1, also output a back button on the left
			$stepStr = (string)($this->currentStep ?? '');
			if ($stepStr !== '' && $stepStr !== '1') {
				$backCol =
				    '<div class="form-group col-md-6">'.
				        '<button type="button" class="btn btn-secondary" onclick="goBackStep(\''.
				            htmlspecialchars($stepStr).'\');">Zurück</button>'.
				    '</div>';

				// Return both columns on the same row (the outer .row already exists in render())
				return $backCol . $submitCol;
			}

			// Step 1: no back button — make submit span full width and right-align
			return
				'<div class="form-group col-md-12 text-end">'.
				    '<button type="submit" name="'.htmlspecialchars($name).'" class="'.htmlspecialchars($class).'">'.
				        htmlspecialchars($btnText).
				    '</button>'.
				'</div>';
		}

		if ($type === 'textarea') {
        	return '<div class="'.$wrapperClasses.$colClass.'"'.$wrapperData.'>'.
		           "<label for=\"$name\">$label$unit</label>".
		           "<textarea$attrs>".htmlspecialchars((string)$val)."</textarea>".
		           $errorHtml . $desc .
		           "</div>";
		}

		if ($type === 'select') {
		    $opts = '';
		    foreach ((array)$el['options'] as $opt) {
		        $optEsc = htmlspecialchars((string)$opt);
		        $sel = ((string)$val === (string)$opt) ? ' selected' : '';
		        $opts .= "<option value=\"$optEsc\"$sel>$optEsc</option>";
		    }
        	return '<div class="'.$wrapperClasses.$colClass.'"'.$wrapperData.'>'.
		           "<label for=\"$name\">$label$unit</label>".
		           "<select$attrs>$opts</select>".
		           $errorHtml . $desc .
		           "</div>";
		}

		if ($type === 'checkbox') {
			// "wahr" erkennen
			$isChecked = in_array((string)$el['value'], ['1','on','true'], true)
				      || in_array((string)($el['default'] ?? ''), ['1','on','true'], true);

			// Hidden-Fallback zuerst -> liefert 0 wenn nicht angekreuzt
			return '<div class="'.$wrapperClasses.$colClass.'"'.$wrapperData.' style="margin-left: 20px;">'
				 . '<input type="hidden" name="'.$name.'" value="0">'
				 . '<input'.$attrs.' value="1"'.($isChecked ? ' checked' : '').' class="form-check-input">'
				 . '<label class="form-check-label" for="'.$name.'">'.$label.$unit.'</label>'
				 . $errorHtml . $desc
				 . '</div>';
		}

		// text, email, number, etc.
		$valueAttr = ' value="'.htmlspecialchars((string)$val).'"';
    	return '<div class="'.$wrapperClasses.$colClass.'"'.$wrapperData.'>'.
		       "<label for=\"$name\">$label$unit</label>".
		       "<input$attrs$valueAttr>".
		       $errorHtml . $desc .
		       "</div>";
	}

	protected function translateError($code)
	{
		switch ($code) {
		    case 'required': return 'Dieses Feld ist erforderlich.';
		    case 'email':    return 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
		    case 'number':   return 'Bitte geben Sie eine Zahl ein.';
		    case 'min':      return 'Der eingegebene Wert ist zu klein.';
		    case 'max':      return 'Der eingegebene Wert ist zu groß.';
		    case 'pattern':  return 'Das Format ist ungültig.';
		    default:         return 'Ungültige Eingabe.';
		}
	}
}
