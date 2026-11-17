<?php

class Calendar_IndexController extends Zend_Controller_Action
{
	private Calendar_Model_DbTable_Event $events;
	private DateTimeZone $zone;
	private Zend_Session_Namespace $calendarSession;

	public function init()
	{
		$this->events = new Calendar_Model_DbTable_Event();
		$config = Zend_Registry::get('Config');
		$this->zone = Calendar_Model_Event::timezone($config['timezone']);
		$user = Zend_Registry::get('User');
		$this->calendarSession = new Zend_Session_Namespace('Calendar_' . $user['id'] . '_' . $user['clientid']);
		if (empty($this->calendarSession->csrf)) $this->calendarSession->csrf = bin2hex(random_bytes(32));
		$this->view->assign(['module' => 'calendar', 'controller' => 'index', 'action' => $this->getRequest()->getActionName(), 'id' => 0, 'user' => $user, 'client' => Zend_Registry::get('Client'), 'title' => $this->view->translate('CALENDAR'), 'calendarZone' => $this->zone->getName(), 'calendarCsrf' => $this->calendarSession->csrf]);
		$this->view->headLink()->appendStylesheet($this->view->baseUrl('/css/calendar.css'));
		$this->view->headScript()->appendFile($this->view->baseUrl('/js/calendar.js'));
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
	}

	private function period(): array
	{
		$date = $this->getRequest()->getQuery('date', (new DateTimeImmutable('now', $this->zone))->format('Y-m-d'));
		$mode = $this->getRequest()->getQuery('mode', 'month');
		if (!is_string($date) || !is_string($mode)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		return Calendar_Model_Event::period($date, $mode, $this->zone);
	}

	private function id(string $name = 'id', bool $optional = false): int
	{
		$value = $this->_getParam($name, $optional ? '0' : '');
		$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $optional ? 0 : 1]]);
		if ($id === false) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		return $id;
	}

	public function indexAction()
	{
		try {
			$this->events->authorize('view');
			$period = $this->period();
			$ownerid = $this->id('ownerid', true);
			$scope = $this->_getParam('scope', 'team');
			if (!is_string($scope)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
			$this->view->assign(['period' => $period, 'ownerid' => $ownerid, 'scope' => $scope, 'members' => $this->events->members(), 'events' => $this->events->events($period, $ownerid, $scope), 'canAdd' => $this->events->can('add')]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function getAction()
	{
		try {
			$this->events->authorize('view');
			$scope = $this->_getParam('scope', 'team');
			if (!is_string($scope)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
			$this->json(['ok' => true, 'events' => $this->_getParam('id') !== null ? [$this->events->getEvent($this->id())] : $this->events->events($this->period(), $this->id('ownerid', true), $scope)]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	public function viewAction()
	{
		try {
			$event = $this->events->getEvent($this->id());
			$canEdit = $event['own'] && $this->events->can('edit');
			$participants = $event['own'] || $event['participation'] !== null ? $this->events->participants($event['id']) : [];
			$this->view->assign(['event' => $event, 'participants' => $participants, 'canEdit' => $canEdit && !$event['cancelled'], 'canManage' => $canEdit, 'canMail' => $canEdit && $this->events->can('add'), 'canDelete' => $event['own'] && $this->events->can('delete')]);
			if (($this->calendarSession->participantNotice['id'] ?? null) === $event['id']) { $this->view->participantNotice = $this->calendarSession->participantNotice['message']; unset($this->calendarSession->participantNotice); }
			if ($canEdit && !$event['cancelled']) {
				$this->view->participantEmails = $this->events->participantEmailAvailability($event['id']);
				foreach (['internal' => true, 'external' => false] as $key => $internal) {
					$form = new Calendar_Form_Participant($internal ? $this->events->participantUsers($event['id']) : [], $internal, $this->events->can('add'));
					$form->setValues(['sequence' => $event['sequence'], 'csrf_token' => $this->calendarSession->csrf]);
					$this->view->{$key . 'ParticipantForm'} = $form;
				}
			}
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function addAction() { $this->editEvent(false); }
	public function editAction() { $this->editEvent(true); }

	public function participantsAction()
	{
		try {
			$this->requirePost(); $this->checkCsrf();
			$id = $this->id();
			$input = $this->getRequest()->getPost();
			$sequence = $this->sequence($input);
			if (array_diff(array_keys($input), ['csrf_token', 'sequence', 'operation', 'kind', 'userid', 'name', 'email', 'contactemailid', 'participantid', 'status', 'sendinvite'])) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
			if (($input['operation'] ?? '') === 'add') {
				if (!in_array($input['sendinvite'] ?? '0', ['0', '1'], true)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
				$send = ($input['sendinvite'] ?? '0') === '1';
				if ($send) $this->events->authorize('add');
				$adding = true;
				$added = $this->events->addParticipant($id, array_intersect_key($input, array_flip(['kind', 'userid', 'name', 'email', 'contactemailid'])), $sequence);
				$message = $this->view->translate('CALENDAR_PARTICIPANT_ADDED'); $mail = null;
				if ($send) {
					try {
						$mail = (new Calendar_Model_Invitation())->send($this->id(), [$added['id']], $added['sequence'], (string)Zend_Registry::get('Zend_Locale'));
						$message .= ' ' . $this->view->translate($mail['failed'] ? 'CALENDAR_MAIL_UNKNOWN' : 'CALENDAR_MAIL_COMPLETE');
					} catch (Throwable $e) {
						$mail = ['sent' => 0, 'skipped' => 0, 'failed' => 1];
						$message .= ' ' . $this->view->translate('CALENDAR_PARTICIPANT_MAIL_FAILED') . ' ' . ($e instanceof Exception ? $this->message($e) : $this->view->translate('CALENDAR_MAIL_FAILED'));
					}
				}
				$this->participantResult(['message' => $message, 'mail' => $mail]); return;
			}
			elseif (($input['operation'] ?? '') === 'remove') $this->events->removeParticipant($this->id(), $this->positive($input['participantid'] ?? null), $sequence);
			elseif (($input['operation'] ?? '') === 'status') {
				if (!is_string($input['status'] ?? null)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
				$this->events->respond($this->id(), $input['status'], $sequence, $this->positive($input['participantid'] ?? null));
			} else throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
			$this->participantResult();
		} catch (InvalidArgumentException | RuntimeException $e) {
			if (!$this->getRequest()->isXmlHttpRequest() && $e instanceof InvalidArgumentException && isset($adding)) {
				$this->viewAction();
				$this->getResponse()->setHttpResponseCode(422); $this->view->participantError = $this->message($e);
				$key = ($input['kind'] ?? '') === 'internal' ? 'internalParticipantForm' : 'externalParticipantForm';
				if ($this->view->$key) $this->view->$key->setValues(array_filter(array_intersect_key($input, array_flip(['userid', 'name', 'email', 'sendinvite'])), 'is_string'));
				$this->view->participantMailChoice = [($input['kind'] ?? '') === 'internal' ? 'internal' : 'external' => ($input['sendinvite'] ?? '0') === '1' ? '1' : '0'];
				$this->_helper->viewRenderer->setRender('view');
			} else $this->failure($e);
		}
	}

	public function endtimeAction()
	{
		try {
			$this->requirePost(); $this->checkCsrf(); $this->events->authorize('view');
			if (!$this->events->can('add') && !$this->events->can('edit')) throw new RuntimeException('CALENDAR_DENIED', 403);
			$input = $this->getRequest()->getPost(); unset($input['csrf_token']);
			$this->json(['ok' => true] + Calendar_Model_Event::shiftEnd($input));
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	public function respondAction()
	{
		try {
			$this->requirePost(); $this->checkCsrf();
			$input = $this->getRequest()->getPost();
			if (array_diff(array_keys($input), ['csrf_token', 'sequence', 'status']) || !is_string($input['status'] ?? null)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
			$this->events->respond($this->id(), $input['status'], $this->sequence($input));
			$this->participantResult();
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function contactsAction()
	{
		try {
			$keyword = $this->getRequest()->getQuery('keyword', '');
			if (!is_string($keyword)) throw new InvalidArgumentException('CALENDAR_CONTACT_SEARCH');
			$this->json(['ok' => true, 'contacts' => $this->events->contactEmails($this->id(), $keyword)]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	public function cancelAction()
	{
		try {
			$this->requirePost(); $this->checkCsrf(); $input = $this->getRequest()->getPost();
			if (array_diff(array_keys($input), ['csrf_token', 'sequence'])) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
			$this->events->cancelEvent($this->id(), $this->sequence($input)); $this->participantResult();
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function sendAction()
	{
		try {
			$this->requirePost(); $this->checkCsrf(); $input = $this->getRequest()->getPost();
			if (array_diff(array_keys($input), ['csrf_token', 'sequence', 'recipients', 'locale']) || !is_array($input['recipients'] ?? null) || !is_string($input['locale'] ?? null)) throw new InvalidArgumentException('CALENDAR_MAIL_SELECTION');
			$result = (new Calendar_Model_Invitation())->send($this->id(), $input['recipients'], $this->sequence($input), $input['locale']);
			$this->json(['ok' => $result['failed'] === 0, 'result' => $result, 'message' => $this->view->translate($result['failed'] ? 'CALENDAR_MAIL_UNKNOWN' : 'CALENDAR_MAIL_COMPLETE')]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	private function positive($value): int
	{
		$id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if (!$id) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		return $id;
	}

	private function sequence(array $input): int
	{
		$sequence = filter_var($input['sequence'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($sequence === false) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		return $sequence;
	}

	private function requirePost(): void
	{
		if (!$this->getRequest()->isPost()) throw new RuntimeException('CALENDAR_POST_REQUIRED', 405);
	}

	private function participantResult(array $extra = []): void
	{
		if ($this->getRequest()->isXmlHttpRequest()) { $this->json(['ok' => true, 'event' => $this->events->getEvent($this->id()), 'participants' => $this->events->participants($this->id())] + $extra); return; }
		if (isset($extra['message'])) $this->calendarSession->participantNotice = ['id' => $this->id(), 'message' => $extra['message']];
		$this->_helper->redirector->gotoSimple('view', 'index', 'calendar', ['id' => $this->id()]);
	}

	public function remindersAction()
	{
		try {
			$this->events->authorize('view');
			$user = Zend_Registry::get('User');
			$context = $user['id'] . ':' . $user['clientid'];
			if ($this->getRequest()->getQuery('context') !== $context) throw new RuntimeException('CALENDAR_REMINDER_CONTEXT', 403);
			if ($this->getRequest()->isPost()) {
				$this->checkCsrf();
				$post = $this->getRequest()->getPost();
				if (array_diff(array_keys($post), ['csrf_token', 'id', 'revision', 'operation', 'minutes']) || !is_string($post['operation'] ?? null) || !in_array($post['operation'], ['done', 'snooze'], true)) throw new InvalidArgumentException('CALENDAR_REMINDER_INVALID');
				$id = filter_var($post['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
				$revision = filter_var($post['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
				$minutes = $post['operation'] === 'snooze' ? filter_var($post['minutes'] ?? null, FILTER_VALIDATE_INT) : null;
				if (!$id || !$revision || $minutes === false || ($post['operation'] === 'done' && isset($post['minutes']))) throw new InvalidArgumentException('CALENDAR_REMINDER_INVALID');
				$this->events->updateReminder($id, $revision, $minutes);
			} elseif (!$this->getRequest()->isGet()) throw new RuntimeException('CALENDAR_POST_REQUIRED', 405);
			$this->json(['ok' => true, 'context' => $context, 'timezone' => $this->zone->getName(), 'csrf_token' => $this->calendarSession->csrf] + $this->events->dueReminders());
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	private function editEvent(bool $existing): void
	{
		try {
			$this->events->authorize($existing ? 'edit' : 'add');
			$event = $existing ? $this->events->getEvent($this->id()) : null;
			if ($event && !$event['own']) throw new RuntimeException('CALENDAR_DENIED', 403);
			$form = new Calendar_Form_Event();
			$start = (new DateTimeImmutable('now', $this->zone))->modify('+1 hour');
			$start = $start->setTime((int)$start->format('H'), 0);
			$values = $event ? array_intersect_key($event, array_flip(['title', 'description', 'location', 'visibility', 'timezone', 'allday', 'startfold', 'endfold', 'reminderminutes'])) + ['start' => $event['startlocal'], 'end' => $event['endlocal']] : ['start' => $start->format('Y-m-d\TH:i'), 'end' => $start->modify('+1 hour')->format('Y-m-d\TH:i'), 'timezone' => $this->zone->getName(), 'visibility' => 'team'];
			if ($this->getRequest()->isPost()) {
				$this->checkCsrf();
				$input = $this->getRequest()->getPost();
				try {
					$id = $this->events->saveEvent($input, $event['id'] ?? 0);
					if ($this->getRequest()->isXmlHttpRequest()) { $this->json(['ok' => true, 'id' => $id]); return; }
					$this->_helper->redirector->gotoSimple('view', 'index', 'calendar', ['id' => $id]);
					return;
				} catch (InvalidArgumentException $e) {
					if ($this->getRequest()->isXmlHttpRequest()) { $this->failure($e, true); return; }
					$this->getResponse()->setHttpResponseCode(422);
					$this->view->calendarError = $this->message($e);
					$values = array_filter(array_intersect_key($input, array_flip(['title', 'description', 'location', 'visibility', 'timezone', 'allday', 'start', 'end', 'startfold', 'endfold', 'reminderminutes'])), 'is_string');
				}
			}
			$values['csrf_token'] = $this->calendarSession->csrf;
			$form->setValues($values);
			if (!empty($values['allday'])) foreach (['start', 'end'] as $field) $form->setElementData($field, ['type' => 'date']);
			$this->view->assign(['eventForm' => $form, 'event' => $event, 'ownerName' => Zend_Registry::get('User')['name']]);
			$this->_helper->viewRenderer->setRender('edit');
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function deleteAction()
	{
		try {
			$this->events->authorize('delete');
			if (!$this->getRequest()->isPost()) throw new RuntimeException('CALENDAR_POST_REQUIRED', 405);
			$this->checkCsrf();
			$this->events->deleteEvent($this->id());
			if ($this->getRequest()->isXmlHttpRequest()) { $this->json(['ok' => true]); return; }
			$this->_helper->redirector->gotoSimple('index', 'index', 'calendar');
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	private function checkCsrf(): void
	{
		$token = $this->getRequest()->getPost('csrf_token');
		if (!is_string($token) || !hash_equals($this->calendarSession->csrf, $token)) throw new RuntimeException('CALENDAR_CSRF', 403);
	}

	private function message(Exception $e): string
	{
		$key = $e->getMessage();
		return $this->view->translate(preg_match('/^CALENDAR_[A-Z_]+$/D', $key) ? $key : 'CALENDAR_FAILED');
	}

	private function failure(Exception $e, bool $json = false): void
	{
		$code = $e instanceof InvalidArgumentException ? 422 : (in_array($e->getCode(), [403, 404, 405, 409], true) ? $e->getCode() : 500);
		$this->getResponse()->setHttpResponseCode($code);
		if ($code === 405) $this->getResponse()->setHeader('Allow', $this->getRequest()->getActionName() === 'reminders' ? 'GET, POST' : 'POST', true);
		if ($json || $this->getRequest()->isXmlHttpRequest()) { $this->json(['ok' => false, 'message' => $this->message($e)]); return; }
		$this->view->calendarError = $this->message($e);
		$this->_helper->viewRenderer->setRender('error');
	}

	private function json(array $data): void
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout->disableLayout();
		$this->getResponse()->setHeader('Content-Type', 'application/json; charset=UTF-8', true)->setBody(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
	}
}
