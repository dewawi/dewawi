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

		$inquiry = $this->getInquiryView($id);
		$this->view->assign([
			'id' => $id,
			'form' => $form,
			'toolbar' => null,
			'inquiry' => $inquiry,
		]);
		$this->assignWorkflowView($inquiry);
		$this->assignMessages();
	}

	public function assigncontactAction()
	{
		$id = (int)$this->_getParam('id', 0);
		if (!$this->getRequest()->isPost()) return $this->redirectToInquiry($id);

		$contactId = !empty($this->getRequest()->getPost('remove'))
			? 0
			: (int)$this->getRequest()->getPost('contactid', 0);
		if ($contactId <= 0 && empty($this->getRequest()->getPost('remove'))) {
			$this->_flashMessenger->addMessage('MESSAGES_INQUIRY_SELECT_CUSTOMER');
			return $this->redirectToInquiry($id);
		}

		(new Contacts_Service_InquiryWorkflow())->assignContact($id, $contactId);
		$this->_flashMessenger->addMessage('MESSAGES_SAVED');
		return $this->redirectToInquiry($id);
	}

	public function createcontactAction()
	{
		$id = (int)$this->_getParam('id', 0);
		if (!$this->getRequest()->isPost()) return $this->redirectToInquiry($id);
		$permission = new DEEC_Permission($this->_user);
		if (!$permission->hasActionPermission('contacts', 'contact', 'edit')) {
			throw new Zend_Controller_Action_Exception('Forbidden', 403);
		}

		$inquiry = $this->getInquiryView($id);
		$form = new Contacts_Form_InquiryContact();
		$post = (array)$this->getRequest()->getPost();
		$workflow = new Contacts_Service_InquiryWorkflow();

		if (!$form->isValid($post)) {
			$form->setValues($post);
			return $this->renderWorkflowEdit($inquiry, $form);
		}

		$values = $form->getFilteredValues();
		$suggestions = $workflow->findContactSuggestions($values);

		if ($suggestions && empty($values['confirm_duplicate'])) {
			$form->setValues($values);
			$this->view->duplicateWarning = true;
			return $this->renderWorkflowEdit($inquiry, $form, $suggestions);
		}

		$workflow->createContact($id, $values);
		$this->_flashMessenger->addMessage('MESSAGES_INQUIRY_CONTACT_CREATED');

		return $this->redirectToInquiry($id);
	}

	public function createquoteAction()
	{
		$id = (int)$this->_getParam('id', 0);
		if (!$this->getRequest()->isPost()) return $this->redirectToInquiry($id);

		$permission = new DEEC_Permission($this->_user);
		if (
			!$permission->hasActionPermission('sales', 'quote', 'add')
			|| !$permission->hasActionPermission('sales', 'quote', 'edit')
		) {
			throw new Zend_Controller_Action_Exception('Forbidden', 403);
		}

		$result = (new Contacts_Service_InquiryWorkflow())->createQuoteDraft($id);
		$this->_flashMessenger->addMessage($result['created']
			? 'MESSAGES_INQUIRY_QUOTE_CREATED'
			: 'MESSAGES_INQUIRY_QUOTE_EXISTS');

		return $this->_helper->redirector->gotoSimple('edit', 'quote', 'sales', ['id' => (int)$result['id']]);
	}

	protected function getEditToolbar()
	{
		return null;
	}

	protected function getViewAssigns(array $row, $form): array
	{
		$inquiry = $this->getInquiryView((int)$row['id']);
		$permission = new DEEC_Permission($this->_user);
		return [
			'inquiry' => $inquiry,
			'workflow' => (new Contacts_Service_InquiryWorkflow())->buildContext($inquiry),
			'canCreateQuote' => false,
			'canViewQuote' => $permission->hasActionPermission('sales', 'quote', 'view')
				|| $permission->hasActionPermission('sales', 'quote', 'edit'),
		];
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

	private function assignWorkflowView(array $inquiry, ?Contacts_Form_InquiryContact $contactForm = null, ?array $suggestions = null): void
	{
		$workflow = new Contacts_Service_InquiryWorkflow();
		$context = $workflow->buildContext($inquiry);
		if ($suggestions !== null) $context['suggestions'] = $suggestions;

		$assignmentForm = new Contacts_Form_InquiryAssignment();
		if ($context['contact']) {
			$assignmentForm->setValues([
				'contactid' => (int)$context['contact']['id'],
				'contact_search' => trim($context['contact']['contactid'].' · '.$context['contact']['name1'].' '.$context['contact']['name2']),
			]);
		}

		if (!$contactForm) {
			$contactForm = new Contacts_Form_InquiryContact();
			$contactForm->setValues($context['contact_defaults']);
		}

		$permission = new DEEC_Permission($this->_user);
		$canEditQuote = $permission->hasActionPermission('sales', 'quote', 'edit');
		$this->view->assign([
			'assignmentForm' => $assignmentForm,
			'contactForm' => $contactForm,
			'workflow' => $context,
			'canCreateQuote' => $canEditQuote && $permission->hasActionPermission('sales', 'quote', 'add'),
			'canViewQuote' => $canEditQuote || $permission->hasActionPermission('sales', 'quote', 'view'),
		]);
	}

	private function renderWorkflowEdit(array $inquiry, Contacts_Form_InquiryContact $contactForm, ?array $suggestions = null)
	{
		$statusForm = new Contacts_Form_Inquiry();
		$statusForm->setValues(['status' => (string)$inquiry['status']]);
		$this->view->assign([
			'id' => (int)$inquiry['id'],
			'form' => $statusForm,
			'toolbar' => null,
			'inquiry' => $inquiry,
		]);
		$this->assignWorkflowView($inquiry, $contactForm, $suggestions);
		$this->assignMessages();
		$this->_helper->viewRenderer->setRender('edit');
	}

	private function redirectToInquiry(int $id)
	{
		return $this->_helper->redirector->gotoSimple('edit', 'inquiry', null, ['id' => $id]);
	}
}
