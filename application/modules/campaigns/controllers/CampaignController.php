<?php

class Campaigns_CampaignController extends DEEC_Controller_Action
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'campaigns',
			'list' => 'Campaigns_Model_List_Campaigns',
			'entity' => Campaigns_Model_Entity_Campaign::listConfig(),
		]);
	}

	public function addAction()
	{
		$campaignDb = new Campaigns_Model_DbTable_Campaign();

		$id = $campaignDb->create([
			'title' => $this->view->translate('CAMPAIGNS_NEW_CAMPAIGN'),
			'state' => 100,
			'responsible' => (int)$this->_user['id'],
			'timezone' => 'Europe/Berlin',
		]);

		$this->_helper->redirector->gotoSimple('edit', 'campaign', null, ['id' => $id]);
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		//$element = $this->_getParam('element', null);
		$activeTab = $request->getCookie('tab', null);

		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);

		if($campaign['completed'] || $campaign['cancelled']) {
			$this->_helper->redirector->gotoSimple('view', 'campaign', null, array('id' => $id));
		} else {
			$this->_helper->Access->lock($id, $this->_user['id'], $campaign['locked'], $campaign['lockedtime']);

			$form = new Campaigns_Form_Campaign();
			$options = $this->_helper->Options->getOptions($form);

			if($request->isPost()) {
				header('Content-type: application/json');
				$this->_helper->viewRenderer->setNoRender();
				$this->_helper->getHelper('layout')->disableLayout();
				$data = $request->getPost();
				$element = key($data);
				if(isset($form->$element) && $form->isValidPartial($data) || true) {
					$data['contactperson'] = $this->_user['name'];
					if(isset($data['currency'])) {
						$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
						/*$positions = $positionsDb->getPositions($id);
						foreach($positions as $position) {
							$positionsDb->updatePosition($position->id, array('currency' => $data['currency']));
						}*/
						//$this->_helper->Currency->convert($id, 'creditnote');
					}
					if(isset($data['expectedrevenue'])) {
						$locale = Zend_Registry::get('Zend_Locale');
						$data['expectedrevenue'] = Zend_Locale_Format::getNumber($data['expectedrevenue'], array('precision' => 2,'locale' => $locale));
					}
					if(isset($data['budgetedcost'])) {
						if($data['budgetedcost']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['budgetedcost'] = Zend_Locale_Format::getNumber($data['budgetedcost'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['budgetedcost'] = NULL;
						}
					}
					if(isset($data['actualcost'])) {
						if($data['actualcost']) {
							$locale = Zend_Registry::get('Zend_Locale');
							$data['actualcost'] = Zend_Locale_Format::getNumber($data['actualcost'], array('precision' => 2,'locale' => $locale));
						} else {
							$data['actualcost'] = NULL;
						}
					}
					if (isset($data['interval'])) {
						$interval = (int)$data['interval'];
						$data['interval'] = $interval > 0 ? $interval : 60;
					}
					if(isset($data['batchsize'])) {
						$batchSize = (int)$data['batchsize'];
						$data['batchsize'] = $batchSize > 0 ? min($batchSize, 1000) : 1;
					}
					// Normalize campaign sending window.
					foreach (['startwindow', 'endwindow'] as $key) {
						if (!isset($data[$key])) {
							continue;
						}

						$value = trim($data[$key]);

						if ($value === '') {
							$data[$key] = null;
							continue;
						}

						if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
							$data[$key] = null;
							continue;
						}

						$data[$key] = $value . ':00';
					}
					if(isset($data['startdate'])) {
						$data['startdate'] = trim($data['startdate']);
						if ($data['startdate']) {
							try {
								// Accept either date or datetime
								$fmt = (strpos($data['startdate'], ':') !== false) ? 'dd.MM.yyyy HH:mm' : 'dd.MM.yyyy';
								$zd = new Zend_Date($data['startdate'], $fmt, 'de');
								// If only a date was given, we start at 00:00
								$data['startdate'] = $zd->toString('yyyy-MM-dd') . (strpos($fmt, 'HH') ? ' ' . $zd->toString('HH:mm:00') : ' 00:00:00');
							} catch (Exception $e) {
								$data['startdate'] = null;
							}
						} else {
							$data['startdate'] = null;
						}
					}
					if(isset($data['duedate'])) {
						$data['duedate'] = trim($data['duedate']);
						if ($data['duedate']) {
							try {
								$fmt = (strpos($data['duedate'], ':') !== false) ? 'dd.MM.yyyy HH:mm' : 'dd.MM.yyyy';
								$zd = new Zend_Date($data['duedate'], $fmt, 'de');
								// If only a date was given, end at 23:59
								$data['duedate'] = $zd->toString('yyyy-MM-dd') . (strpos($fmt, 'HH') ? ' ' . $zd->toString('HH:mm:00') : ' 23:59:59');
							} catch (Exception $e) {
								$data['duedate'] = null;
							}
						} else {
							$data['duedate'] = null;
						}
					}
					if(isset($data['cc'])) {
						$data['emailcc'] = $data['cc'];
						unset($data['cc']);
					}
					if(isset($data['bcc'])) {
						$data['emailbcc'] = $data['bcc'];
						unset($data['bcc']);
					}
					if(isset($data['replyto'])) {
						$data['emailreplyto'] = $data['replyto'];
						unset($data['replyto']);
					}
					if(isset($data['subject'])) {
						$data['emailsubject'] = $data['subject'];
						unset($data['subject']);
					}
					if(isset($data['body'])) {
						$data['emailbody'] = $data['body'];
						unset($data['body']);
					}

					unset($data['id']);

					$campaignDb->updateById($id, $data);
					echo Zend_Json::encode($campaignDb->getCampaign($id));
				} else {
					echo Zend_Json::encode(array('message' => $this->view->translate('MESSAGES_FORM_IS_INVALID')));
				}
			} else {
				if($id > 0) {
					$data = $campaign;

					//Toolbar
					$toolbar = new Campaigns_Form_Toolbar();
					$options = $this->_helper->Options->getOptions($toolbar);
					$toolbar->setValue('state', $data['state']);
					$toolbarPositions = new Campaigns_Form_ToolbarPositions();

					//Get currency
					$currency = $this->_helper->Currency->getCurrency($data['currency']);
					$data['expectedrevenue'] = $currency->toCurrency($data['expectedrevenue']);
					if($data['budgetedcost']) $data['budgetedcost'] = $currency->toCurrency($data['budgetedcost']);
					if($data['actualcost']) $data['actualcost'] = $currency->toCurrency($data['actualcost']);

					// Convert to display formats
					if (!empty($data['startdate'])) {
						$data['startdate'] = date('d.m.Y H:i', strtotime($data['startdate']));
					}
					if (!empty($data['duedate'])) {
						$data['duedate'] = date('d.m.Y H:i', strtotime($data['duedate']));
					}
					if (!empty($data['startwindow'])) {
						$data['startwindow'] = substr($data['startwindow'], 0, 5);
					}

					if (!empty($data['endwindow'])) {
						$data['endwindow'] = substr($data['endwindow'], 0, 5);
					}

					//Get email form
					$emailForm = new Contacts_Form_Emailmessage();

					//Get email templates
					/*$emailtemplateDb = new Contacts_Model_DbTable_Emailtemplate();
					if($emailtemplate = $emailtemplateDb->getEmailtemplate('contacts', 'contact')) {
						if($emailtemplate['cc']) $emailForm->cc->setValue($emailtemplate['cc']);
						if($emailtemplate['bcc']) $emailForm->bcc->setValue($emailtemplate['bcc']);
						if($emailtemplate['replyto']) $emailForm->replyto->setValue($emailtemplate['replyto']);
						$emailForm->subject->setValue($emailtemplate['subject']);
						$emailForm->body->setValue($emailtemplate['body']);
					}*/

					$emailForm->setValues([
						'cc' => $data['emailcc'],
						'bcc' => $data['emailbcc'],
						'replyto' => $data['emailreplyto'],
						'subject' => $data['emailsubject'],
						'body' => $data['emailbody'],
					]);

					$this->view->emailForm = $emailForm;

					//Get email attachments
					$emailattachmentDb = new Contacts_Model_DbTable_Emailattachment();
					$attachments = $emailattachmentDb->getEmailattachments($id, 'campaigns', 'campaign');

					$form->setValues($data);

					$userDb = new Users_Model_DbTable_User();
					$users = $userDb->getUsers();

					$this->view->form = $form;
					$this->view->users = $users;
					$this->view->activeTab = $activeTab;
					$this->view->attachments = $attachments;
					$this->view->toolbar = $toolbar;
					$this->view->toolbarPositions = $toolbarPositions;
				}
			}
		}
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	public function recipientsAction()
	{
		$this->disableView();

		$id = (int)$this->_getParam('id', 0);
		$page = max(1, (int)$this->_getParam('page', 1));
		$limit = max(10, min(100, (int)$this->_getParam('limit', 25)));

		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);

		$recipientService = new Campaigns_Service_CampaignRecipientService();

		$recipientData = $recipientService->getRecipients(
			$id,
			$page,
			$limit,
			(int)$campaign['contactcatid'],
			(bool)$campaign['contactsubcat']
		);

		$recipientStatus = $recipientService->getCampaignRecipientStatus($campaign);

		$records = (int)$recipientData['records'];
		$count = count($recipientData['contacts']);
		$start = $records > 0 ? (($page - 1) * $limit) + 1 : 0;
		$end = $records > 0 ? min($start + $count - 1, $records) : 0;

		$this->view->contacts = $recipientData['contacts'];
		$this->view->contactPersonsByCompany = $recipientData['contactPersonsByCompany'];
		$this->view->emailmessages = $recipientData['emailmessages'];
		$this->view->recipientStatus = $recipientStatus;

		$this->view->pagination = [
			'count' => $count,
			'start' => $start,
			'end' => $end,
			'records' => $records,
			'page' => $page,
			'limit' => $limit,
			'pages' => max(1, (int)ceil($records / $limit)),
		];

		echo $this->view->Contacts();
	}

	public function errorsAction()
	{
		$this->disableView();

		$id = (int)$this->_getParam('id', 0);

		if($id <= 0) return;

		$recipientService = new Campaigns_Service_CampaignRecipientService();
		$userDb = new Users_Model_DbTable_User();

		$this->view->campaignErrors = $recipientService->getErrors($id);
		$this->view->users = $userDb->getUsers();

		echo $this->view->Errors();
	}

	public function copyAction()
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$id = $this->_getParam('id', 0);
		$campaignDb = new Campaigns_Model_DbTable_Campaign();
		$campaign = $campaignDb->getCampaign($id);

		$data = $campaign;
		unset($data['id']);

		$data['title'] = $campaign['title'].' 2';
		$data['state'] = 100;
		$data['completed'] = 0;
		$data['cancelled'] = 0;
		$data['pinned'] = 0;
		$data['activated'] = 0;
		$data['lastsent'] = null;
		$data['modified'] = null;
		$data['modifiedby'] = 0;
		$data['locked'] = 0;
		$data['lockedtime'] = null;

		$newID = $campaignDb->create($data);
		echo $newID;

		$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
		$positions = $positionsDb->getPositions($id);
		foreach($positions as $position) {
			$positionData = $position->toArray();
			unset($positionData['id']);
			$positionData['parentid'] = $newID;
			$positionData['modified'] = NULL;
			$positionData['modifiedby'] = 0;
			$positionsDb->addPosition($positionData);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	protected function getPositions($campaignIDs)
	{
		$positions = array();
		if(!empty($campaignIDs)) {
			$positionsDb = new Campaigns_Model_DbTable_Campaignpos();
			$positionsObject = $positionsDb->getPositions($campaignIDs);

			foreach($positionsObject as $position) {
				if(!isset($previous[$position->parentid])) {
					$previous[$position->parentid] = array();
					$previous[$position->parentid]['ordering'] = 0;
					$previous[$position->parentid]['quantity'] = 1;
					$previous[$position->parentid]['deliverystatus'] = '';
					$previous[$position->parentid]['deliverydate'] = NULL;
					$previous[$position->parentid]['supplierorderstatus'] = '';
				}
				if($previous[$position->parentid]['ordering'] && ($previous[$position->parentid]['deliverystatus'] == $position->deliverystatus) && ($previous[$position->parentid]['deliverydate'] == $position->deliverydate) && ($previous[$position->parentid]['supplierorderstatus'] == $position->supplierorderstatus)) {
					$positions[$position->parentid][$position->ordering] = $positions[$position->parentid][$previous[$position->parentid]['ordering']];
					$positions[$position->parentid][$position->ordering]['quantity'] = ($previous[$position->parentid]['quantity'] + 1);
					unset($positions[$position->parentid][$previous[$position->parentid]['ordering']]);
					$previous[$position->parentid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->parentid]['quantity'] = $positions[$position->parentid][$position->ordering]['quantity'];
					$previous[$position->parentid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->parentid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
					$previous[$position->parentid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				} else {
					$positions[$position->parentid][$position->ordering]['deliverystatus'] = $position->deliverystatus;
					if($position->deliverydate)
						//$deliverydate = new Zend_Date($position->deliverydate);
						//if($position->deliverydate) $position->deliverydate = $deliverydate->get('dd.MM.yyyy');
						$positions[$position->parentid][$position->ordering]['deliverydate'] = $position->deliverydate;
					if($position->itemtype == 'deliveryItem')
						$positions[$position->parentid][$position->ordering]['supplierorderstatus'] = $position->supplierorderstatus;
					$previous[$position->parentid] = array();
					$previous[$position->parentid]['ordering'] = $position->ordering ? $position->ordering : 0;
					$previous[$position->parentid]['quantity'] = 1;
					$previous[$position->parentid]['deliverystatus'] = $position->deliverystatus ? $position->deliverystatus : '';
					$previous[$position->parentid]['deliverydate'] = $position->deliverydate ? $position->deliverydate : NULL;
					$previous[$position->parentid]['supplierorderstatus'] = $position->supplierorderstatus ? $position->supplierorderstatus : '';
				}
			}
		}
		return $positions;
	}
}
