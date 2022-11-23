<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Button extends Zend_View_Helper_Abstract{

	public function Button($class, $onclick = '', $title = '', $value = '', $style = '', $rel = '', $id = '', $params = NULL) {
		$html = '<button type="button"';
		if($class) {
			if(!$title) $html .= ' class="'.$class.' nolabel"';
			else $html .= ' class="'.$class.'"';
		}
		if($style) $html .= ' style="'.$style.'"';
		if($onclick) $html .= ' onclick="'.$onclick.'"';
		if($title) $html .= ' title="'.$title.'"';
		if($rel) $html .= ' rel="'.$rel.'"';
		if($id) $html .= ' data-id="'.$id.'"';
		if($params) {
			foreach($params as $key => $val) {
				$html .= ' data-'.$key.'="'.$val.'"';
			}
		}
		if($value) $html .= '><span>'.$value.'</span></button>';
		else $html .= '>'.$value.'</button>';
		return $html;
	}
}
