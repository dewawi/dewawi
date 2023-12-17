<?php

class Items_PriceruleposController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Items_Form_Pricerulepos();

		if($request->isPost()) {
			$data = $request->getPost();
			$params = $this->_getAllParams();
			if($form->isValid($data) || true) {
				$positionDb = new Items_Model_DbTable_Pricerulepos();
				$positionDataBefore = $positionDb->getPositions($data['module'], $data['controller'], $data['parentid'], 0);
				$latestOrdering = is_array($positionDataBefore) && !empty($positionDataBefore)
					? end($positionDataBefore)['ordering']
					: 0;
				$positionDb->addPosition(array('module' => $data['module'], 'controller' => $data['controller'], 'parentid' => $data['parentid'], 'masterid' => 0, 'possetid' => 0, 'ordering' => $latestOrdering+1));
				$positionDataAfter = $positionDb->getPositions($data['module'], $data['controller'], $data['parentid'], 0);
				$position = end($positionDataAfter);
				echo $this->view->MultiForm($params['module'], $params['controller'], $position, array('amount', 'action'));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$locale = Zend_Registry::get('Zend_Locale');

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Items_Form_Pricerulepos();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$positionDb = new Items_Model_DbTable_Pricerulepos();
				if($id > 0) {
					if((isset($data['amount'])) && $data['amount'])
						$data['amount'] = Zend_Locale_Format::getNumber($data['amount'],array('precision' => 2,'locale' => $locale));
					$positionDb->updatePosition($id, $data);
					echo Zend_Json::encode($data);
				}
			} else {
				echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$positionDb = new Items_Model_DbTable_Pricerulepos();
			$positionDb->deletePosition($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
