<?php

class Sales_QuoteController extends DEEC_Controller_DocumentAction
{
	protected function buildIndexView(): void
	{
		$list = $this->buildListView([
			'viewKey' => 'quotes',
			'list' => 'Sales_Model_List_Quotes',
			'entity' => Sales_Model_Entity_Quote::listConfig(),
			'toolbar' => 'Sales_Form_QuoteToolbar',
		]);

		$monitoring = new Sales_Service_QuoteMonitoring();
		$items = $monitoring->enrich($list->getItems());

		$list->setItems($items);
		$this->view->quotesItems = $items;
	}

	protected function getCreateData(): array
	{
		$contactId = (int)$this->_getParam('contactid', 0);
		$controller = $this->getRequest()->getControllerName();

		$factory = new Sales_Service_CreateDataFactory();

		return $factory->build($controller, $contactId);
	}

	protected function beforeEdit(array $row)
	{
		if ($this->isReadonlyState($row)) {
			return $this->_helper->redirector->gotoSimple(
				'view',
				'quote',
				null,
				['id' => (int)$row['id']]
			);
		}

		return null;
	}

	protected function getViewAssigns(array $row, $form): array
	{
		$assign = parent::getViewAssigns($row, $form);
		$feedbackDb = new Application_Model_DbTable_Feedback();
		$feedbackDb->setClientId((int)$row['clientid']);
		$fields = array_flip(['id', 'created', 'responded', 'status', 'reason', 'message']);
		$assign['feedbackHistory'] = array_map(static fn($entry) => array_intersect_key($entry, $fields),
			$feedbackDb->getForEntity((int)$row['id'], 'sales', 'quote', DEEC_Feedback::TYPE_QUOTE));

		if((int)$this->_getParam('feedback', 0) !== 1 || empty($assign['emailForm']) || empty($row['quoteid'])) return $assign;

		$email = (new DEEC_Feedback())->buildQuoteFollowupEmail(
			(int)$row['quoteid'],
			(string)($row['language'] ?? '')
		);

		$assign['emailForm']->addElement([
			'name' => 'feedback',
			'type' => 'hidden',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$assign['emailForm']->setValue('feedback', 1);
		$assign['emailForm']->setValue('subject', $email['subject']);
		$assign['emailForm']->setValue('body', $email['body']);

		return $assign;
	}

	protected function getViewContact(array $row): array
	{
		if(empty($row['contactid'])) return [];
		$client = Zend_Registry::get('Client');
		$clientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$this->_user['clientid'];
		$db = new Contacts_Model_DbTable_Contact();
		$contact = $db->fetchRow($db->select()->where('contactid = ?', (int)$row['contactid'])
			->where('clientid = ?', $clientId)->where('deleted = ?', 0));
		return $contact && $clientId > 0 ? $contact->toArray() : [];
	}

	protected function afterView(array $row, $form): void
	{
		if((int)$this->_getParam('feedback', 0) === 1 || (int)$this->_getParam('history', 0) === 1) $this->view->activeTab = 'messages';
	}
}
