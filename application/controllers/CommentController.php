<?php

class CommentController extends Zend_Controller_Action
{
	public function init()
	{
		$this->view->user = $this->_user = Zend_Registry::get('User');
	}

	public function addAction()
	{
		$request = $this->getRequest();

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Application_Form_Comment();
		$options = $this->_helper->Options->getOptions($form);
		$this->view->options = $options;
		$this->view->action = 'add';

		$client = Zend_Registry::get('Client');

		if($request->isPost()) {
			$data = $request->getPost();
			if($data['parentid']) {
				if($form->isValid($data) || true) {
					$commentDb = new Application_Model_DbTable_Comment();
					$commentDataBefore = $commentDb->getComments($data['parentid'], 'contacts', 'contact');
					$latest = end($commentDataBefore);
					$commentDb->addComment(array('parentid' => $data['parentid'], 'module' => 'contacts', 'controller' => 'contact', 'ordering' => $latest['ordering']+1));
					$commentDataAfter = $commentDb->getComments($data['parentid'], 'contacts', 'contact');
					$comment = end($commentDataAfter);
					echo $this->view->MultiForm('default', 'comment', $comment, array(
																		array('field' => 'comment')
																		));
				}
			} else {
				$timestamp = time();
				$comment = array('id' => $timestamp, 'module' => 'contacts', 'controller' => 'contact', 'comment' => '', 'ordering' => $timestamp);
				echo $this->view->MultiForm('default', 'comment', $comment, array(
																	array('field' => 'street')
																	));
			}
		}
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$form = new Application_Form_Comment();

		if($request->isPost()) {
			$data = $request->getPost();
			if($form->isValid($data) || true) {
				$commentDb = new Application_Model_DbTable_Comment();
				if($id > 0) {
					$commentDb->updateComment($id, $data);
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
			$commentDb = new Application_Model_DbTable_Comment();
			$commentDb->deleteComment($id);
		}
		//$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_DELETED');
	}
}
