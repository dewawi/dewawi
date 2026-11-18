<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Button extends Zend_View_Helper_Abstract{

	public function Button($class, $onclick = '', $title = '', $value = '', $style = '', $rel = '', $id = '', $params = NULL) {
		$attribs = ['type' => 'button'];
		if ($class) $attribs['class'] = $class . (!$title ? ' nolabel' : '');
		if ($style) $attribs['style'] = $style;
		if ($onclick) $attribs['onclick'] = $onclick;
		if ($title) $attribs['title'] = $title;
		if ($rel) $attribs['rel'] = $rel;
		if ($id) $attribs['data-id'] = $id;
		if($params) {
			foreach($params as $key => $val) {
				$attribs['data-' . $key] = $val;
			}
		}
		return $this->renderButton(['text' => (string)$value, 'legacy' => true, 'wrapLabel' => (bool)$value], $attribs);
	}

	public function renderButton(array $presentation, array $attribs = []): string
	{
		return (new DEEC_Form())->renderButtonHtml($presentation, $attribs);
	}
}
