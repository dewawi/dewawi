<?php

class Contacts_Model_DbTable_Emailmessage extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'emailmessage';

	public function init()
	{
		parent::init();
	}

	public function getEmailmessage($id)
	{
		$id = (int)$id;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where);
		if(!$data) {
			throw new Exception("Could not find row $id");
		}
		return $data->toArray();
	}

	public function getEmailmessages($contactid = NULL, $campaignid = NULL, $module = NULL, $controller = NULL)
	{
		$where = array();
		if($contactid) $where[] = $this->getAdapter()->quoteInto('contactid = ?', $contactid);
		if($campaignid) $where[] = $this->getAdapter()->quoteInto('campaignid = ?', $campaignid);
		if($module) $where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		if($controller) $where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC');
		if(!$data) throw new Exception("Could not find email messages");
		$messages = $data->toArray();
		$copies = new Contacts_Model_DbTable_Emailmessageattachment();
		foreach($messages as &$message) $message['storedattachments'] = $copies->getMessageAttachments((int)$message['id']);
		unset($message);
		return $messages;
	}

	public function addEmailmessage($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['messagesent'] = null;
		$data['messagesentby'] = 0;
		$data['deliverystatus'] = 'pending';
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function markAccepted(int $id): void
	{
		$this->updateEmailmessage($id, ['messagesent' => $this->_date, 'messagesentby' => (int)$this->_user['id'], 'response' => 'sent', 'deliverystatus' => 'accepted']);
	}

	public function markFailed(int $id, string $error): void
	{
		$this->updateEmailmessage($id, ['messagesent' => null, 'messagesentby' => 0, 'response' => $error, 'deliverystatus' => 'failed']);
	}

	public function updateEmailmessage($id, $data)
	{
		$id = (int)$id;
		$this->update($data, $this->getEntityWhere($id));
	}

	public function deleteEmailmessage($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$this->update($data, $this->getEntityWhere($id));
	}

	public function updateDelivery(int $id, int $clientId, array $data): void
	{
		$allowed = ['providermessageid', 'deliverystatus', 'deliverydate', 'deliveryresponse'];
		$data = array_intersect_key($data, array_flip($allowed));

		if(!$data) return;

		$where = [
			$this->getAdapter()->quoteInto('id = ?', $id),
			$this->getAdapter()->quoteInto('clientid = ?', $clientId),
			$this->getAdapter()->quoteInto('deleted = ?', 0),
		];

		if(($data['deliverystatus'] ?? '') === 'delivered') {
			$where[] = '(deliverystatus IS NULL OR deliverystatus = "" OR deliverystatus = "accepted" OR deliverystatus = "delivered")';
		}

		$this->update($data, $where);
	}
}
