<?php

class Contacts_InquiryController extends DEEC_Controller_Action
{
	protected function shouldPrepareAttachmentDirectory(): bool
	{
		return !in_array($this->getRequest()->getActionName(), ['select', 'save'], true);
	}

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

	public function selectAction()
	{
		$request = $this->getRequest();
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		$input = $request->isPost() ? (array)$request->getPost() : (array)$request->getQuery();
		$this->view->technical = null;
		$this->view->technicalComparisonRequested = isset($input['compare']) || isset($input['candidates']);
		$this->view->technicalComparison = null;
		$this->view->technicalCanCompare = false;
		$this->view->technicalError = null;
		$this->view->technicalInput = $input;
		$this->view->technicalReview = null;
		$this->view->technicalHistory = [];
		$this->view->technicalCanSave = false;
		$this->view->technicalReviewEnabled = false;
		$this->view->toolbar = null;
		try {
			if(strlen(json_encode($input, JSON_THROW_ON_ERROR)) > 65536) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$integer = static function($value): int {
				if($value === null || $value === '') return 0;
				if(!is_scalar($value) || !filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
				return (int)$value;
			};
			foreach(['q', 'hash', 'calculation'] as $name) if(isset($input[$name]) && !is_string($input[$name])) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$id = $integer($this->_getParam('id'));
			if($this->view->technicalComparisonRequested) {
				if(!in_array($request->getMethod(), ['GET', 'HEAD'], true)) throw new RuntimeException('CONTACT_TECH_DENIED', 405);
				if(($input['compare'] ?? null) !== '1' || !is_array($input['candidates'] ?? [])) throw new InvalidArgumentException('CONTACT_COMPARE_INPUT');
				$this->view->technicalComparison = (new Contacts_Service_InquiryTechnicalComparison())->compare($id, $input['candidates'] ?? [], $input['q'] ?? '');
				return;
			}
			$service = new Contacts_Service_InquiryTechnical();
			$reviews = new Contacts_Service_InquiryTechnicalReview();
			$reviewId = $integer($input['reviewid'] ?? null);
			$previousId = $integer($input['fromreview'] ?? null);
			if($previousId && !$request->isPost()) {
				$input += $reviews->startingInput($id, $previousId);
				$this->view->technicalInput = $input;
			}
			if($reviewId) $this->view->technicalReview = $reviews->open($id, $reviewId);
			$this->view->technical = $service->open($id, (string)($input['q'] ?? ''), $integer($input['itemid'] ?? null), $integer($input['datasetid'] ?? null), $integer($input['revision'] ?? null), (string)($input['hash'] ?? ''));
			$this->view->technicalReviewEnabled = $reviews->internalEnabled();
			$this->view->technicalCanSave = $reviews->canSave();
			$this->view->technicalCanCompare = $reviews->canCompare();
			if($reviews->internalEnabled()) $this->view->technicalHistory = $reviews->history($id, $integer($input['beforeversion'] ?? null));
			$session = new Zend_Session_Namespace('InquiryTechnical');
			if(!isset($session->token)) $session->token = bin2hex(random_bytes(32));
			$this->view->technicalToken = $session->token;
			if($request->isPost()) {
				if(!is_string($input['technical_csrf'] ?? null) || !hash_equals($session->token, $input['technical_csrf'])) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
				$this->view->technical = $reviews->canSave() ? $reviews->calculate($this->view->technical, $input, $previousId) : $service->calculate($this->view->technical, $input);
			} elseif(!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
				throw new RuntimeException('CONTACT_TECH_DENIED', 405);
			}
			if(!$request->isPost() && !$reviewId && $reviews->canSave() && $this->view->technical['machine']) $this->view->technical = $reviews->inspect($this->view->technical, $previousId, $input['calculation'] ?? null);
		} catch(InvalidArgumentException|JsonException $e) {
			$this->getResponse()->setHttpResponseCode(422);
			$this->view->technicalError = $e->getMessage() === 'CONTACT_TECH_SYNTHETIC_REQUIRED' ? $e->getMessage() : 'CONTACT_TECH_INPUT';
		} catch(RuntimeException $e) {
			$code = in_array($e->getCode(), [403, 405, 409], true) ? $e->getCode() : 403;
			$this->getResponse()->setHttpResponseCode($code);
			$this->view->technicalError = in_array($e->getMessage(), ['CONTACT_TECH_REVIEW_ROLLOUT', 'CONTACT_TECH_REVIEW_INTEGRITY'], true) ? $e->getMessage() : ($code === 409 ? 'CONTACT_TECH_REVISION' : 'CONTACT_TECH_DENIED');
		}
	}

	public function saveAction()
	{
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		$this->view->technicalSaveError = null;
		$this->view->technicalSavedId = null;
		$this->view->toolbar = null;
		try {
			if(!$this->getRequest()->isPost()) throw new RuntimeException('CONTACT_TECH_DENIED', 405);
			$id = filter_var($this->_getParam('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			$post = (array)$this->getRequest()->getPost();
			if(!$id || array_diff(array_keys($post), ['technical_csrf', 'review_token']) || !is_string($post['review_token'] ?? null) || strlen($post['review_token']) !== 64) throw new InvalidArgumentException('CONTACT_TECH_INPUT');
			$session = new Zend_Session_Namespace('InquiryTechnical');
			if(!isset($session->token) || !is_string($post['technical_csrf'] ?? null) || !hash_equals($session->token, $post['technical_csrf'])) throw new RuntimeException('CONTACT_TECH_DENIED', 403);
			$this->view->technicalSavedId = (new Contacts_Service_InquiryTechnicalReview())->save((int)$id, $post['review_token']);
			$this->view->technicalInquiryId = (int)$id;
		} catch(InvalidArgumentException $e) {
			$this->getResponse()->setHttpResponseCode(422);
			$this->view->technicalSaveError = 'CONTACT_TECH_INPUT';
		} catch(RuntimeException $e) {
			$this->getResponse()->setHttpResponseCode(in_array($e->getCode(), [403, 405, 409], true) ? $e->getCode() : 409);
			$this->view->technicalSaveError = in_array($e->getMessage(), ['CONTACT_TECH_REVIEW_ROLLOUT', 'CONTACT_TECH_REVIEW_STALE', 'CONTACT_TECH_REVIEW_EXPIRED', 'CONTACT_TECH_REVIEW_INTEGRITY'], true) ? $e->getMessage() : 'CONTACT_TECH_DENIED';
		}
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
