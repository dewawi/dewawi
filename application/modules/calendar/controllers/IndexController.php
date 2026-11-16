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
			$this->view->assign(['period' => $period, 'ownerid' => $ownerid, 'members' => $this->events->members(), 'events' => $this->events->events($period, $ownerid), 'canAdd' => $this->events->can('add')]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function getAction()
	{
		try {
			$this->events->authorize('view');
			$this->json(['ok' => true, 'events' => $this->_getParam('id') !== null ? [$this->events->getEvent($this->id())] : $this->events->events($this->period(), $this->id('ownerid', true))]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e, true); }
	}

	public function viewAction()
	{
		try {
			$event = $this->events->getEvent($this->id());
			$this->view->assign(['event' => $event, 'canEdit' => $event['own'] && $this->events->can('edit'), 'canDelete' => $event['own'] && $this->events->can('delete')]);
		} catch (InvalidArgumentException | RuntimeException $e) { $this->failure($e); }
	}

	public function addAction() { $this->editEvent(false); }
	public function editAction() { $this->editEvent(true); }

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
