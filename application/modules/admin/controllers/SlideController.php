<?php

class Admin_SlideController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'slides',
			'list' => 'Admin_Model_List_Slides',
			'entity' => Admin_Model_Entity_Slide::listConfig(),
		]);
	}

	public function addAction()
	{
		header('Content-type: application/json');
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$request = $this->getRequest();
		if($request->isPost()) {
			$form = new Admin_Form_Slide();
			$options = $this->_helper->Options->getOptions($form);
			$params = $this->_helper->Params->getParams($form, $options);
			$data = $request->getPost();
			//if($form->isValid($data)) {
				$data['ordering'] = $this->getLatestOrdering($params['clientid'], $params['type'], $data['parentid']) + 1;
				if(!isset($data['shopid'])) $data['shopid'] = 0;
				//$data['parentid'] = $params['parentid'];

				$slideDb = new Admin_Model_DbTable_Slide();
				$id = $slideDb->addSlide($data);

				if($data['shopid']) {
					$slugDb = new Admin_Model_DbTable_Slug();
					$slugDb->addSlug('shops', 'slide', $data['shopid'], $data['parentid'], $id, $id);
				}

				//echo Zend_Json::encode($data);
				echo Zend_Json::encode($slideDb->getSlide($id));
			//} else {
			//	echo Zend_Json::encode($data);
				//echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
			//}
		}
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$slideDb = new Admin_Model_DbTable_Slide();
		$data = $slideDb->getSlide($id);
		unset($data['id']);

		$slidesDb = new Admin_Model_DbTable_Slide();
		$slides = $slidesDb->getSlides($data['type'], $data['parentid']);
		foreach($slides as $slide) {
			if(isset($slide['ordering'])) {
				if($slide['ordering'] > $data['ordering']) {
					if(!isset($slidesDb)) $slidesDb = new Admin_Model_DbTable_Slide();
					$slidesDb->sortSlide($slide['id'], $slide['ordering'] + 1);
				}
			}
		}

		$data['title'] = $data['title'].' 2';
		$data['ordering'] = $data['ordering'] + 1;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = NULL;
		$newId = $slideDb->addSlide($data);
		//print_r($data);

		if($data['shopid']) {
			$slugDb = new Admin_Model_DbTable_Slug();
			$slugDb->addSlug('shops', 'slide', $data['shopid'], $data['parentid'], $newId, $newId);
		}

		$childSlides = $slidesDb->getSlides($data['type'], $id);
		if(isset($childSlides[$id]['childs'])) $this->copyChilds($id, $childSlides, $newId);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function deleteAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if($this->getRequest()->isPost()) {
			$id = $this->_getParam('id', 0);
			$slideDb = new Admin_Model_DbTable_Slide();
			$slide = $slideDb->getSlide($id);
			$slideDb->deleteSlide($id);
			$this->setOrdering($slide['clientid'], $slide['type'], $slide['parentid']);
			$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
		}
	}
}
