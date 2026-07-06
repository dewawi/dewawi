<?php

class Zend_View_Helper_MultiFormRows extends Zend_View_Helper_Abstract
{
	public function MultiFormRows(string $module, string $controller, array $rows, string $name): string
	{
		$formClass = DEEC_Util::formClassFromModuleController($module, $controller);

		if (!class_exists($formClass)) {
			return '';
		}

		$form = new $formClass();

		if (!$form instanceof DEEC_Form) {
			return '';
		}

		$helper = Zend_Controller_Action_HelperBroker::getStaticHelper('Options');
		$helper->applyFormOptions($form);

		$html = '';

		foreach ($rows as $row) {
			$html .= $form->renderMultiItem($name, $row, [
				'module' => $module,
				'controller' => $controller,
			]);
		}

		return $html;
	}
}
