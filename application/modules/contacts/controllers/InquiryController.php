<?php

class Contacts_InquiryController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$list = $this->buildListView([
			'viewKey' => 'inquiries',
			'list' => 'Contacts_Model_List_Inquiries',
			'entity' => Contacts_Model_Entity_Inquiry::listConfig(),
			'toolbar' => 'Contacts_Form_InquiryToolbar',
			'toolbarInline' => 'Contacts_Form_InquiryToolbarInline',
		]);
		$list->setSelectable(false);
	}

	protected function getDb(): DEEC_Model_DbTable_Entity
	{
		return new Contacts_Model_DbTable_Inquiry();
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();
		$db = $this->getDb();
		$row = $db->getById($id);

		if (!$row) {
			if ($isAjax) {
				$this->disableView();
				return $this->_helper->json(['ok' => false, 'message' => 'not_found']);
			}

			$this->_flashMessenger->addMessage($this->getNotFoundMessage());
			return $this->_helper->redirector->gotoSimple('index', 'inquiry');
		}

		$formData = $this->getEditForm();
		$form = $formData['form'];

		if ($request->isPost()) {
			if ($isAjax) return $this->handleEditAjaxSave($form, $db, $id, $row);

			$post = (array)$request->getPost();

			if ($form->isValid($post)) {
				$values = $this->beforeEditSave($form->getFilteredValues(), $row);
				$adapter = $db->getAdapter();

				try {
					$adapter->beginTransaction();
					$db->updateById($id, $values);
					$adapter->commit();
				} catch (Exception $e) {
					$adapter->rollBack();
					throw $e;
				}

				$this->_flashMessenger->addMessage('MESSAGES_SAVED');
				return $this->_helper->redirector->gotoSimple('edit', 'inquiry', null, ['id' => $id]);
			}
		} else {
			$form->setValues(['status' => (string)$row['status']]);
		}

		$this->view->assign([
			'id' => $id,
			'form' => $form,
			'toolbar' => null,
			'inquiry' => $this->getInquiryView($id),
		]);
		$this->assignMessages();
	}

	protected function getEditToolbar()
	{
		return null;
	}

	protected function getViewAssigns(array $row, $form): array
	{
		return ['inquiry' => $this->getInquiryView((int)$row['id'])];
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$status = (string)($values['status'] ?? '');

		if (!isset(Contacts_Model_DbTable_Inquiry::STATUSES[$status])) {
			throw new InvalidArgumentException('Invalid inquiry status');
		}

		return ['status' => $status];
	}

	protected function getNotFoundMessage(): string
	{
		return 'MESSAGES_INQUIRY_NOT_FOUND';
	}

	private function getInquiryView(int $id): array
	{
		$detail = $this->getDb()->getDetail($id);

		if (!$detail) throw new RuntimeException('Inquiry not found');

		return (new Contacts_Service_InquiryView())->build($detail);
	}
}
