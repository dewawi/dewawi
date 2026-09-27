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

	protected function afterView(array $row, $form): void
	{
		if((int)$this->_getParam('feedback', 0) === 1) $this->view->activeTab = 'messages';
	}
}
