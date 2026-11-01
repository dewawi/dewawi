<?php

class Admin_Model_DbTable_Slugredirect extends DEEC_Model_DbTable_Entity
{
	protected $_name = 'slugredirect';
	protected ?string $orderingField = null;

	public function getAllByShopForUpdate(int $shopId): array
	{
		return $this->fetchAll(
			$this->select()
				->where('clientid = ?', $this->getClientId())
				->where('shopid = ?', $shopId)
				->order('id ASC')
				->forUpdate(true)
		)->toArray();
	}

	public function savePath(int $shopId, string $path, string $controller, int $entityId): int
	{
		if ($shopId <= 0 || $entityId <= 0 || !in_array($controller, ['page', 'category', 'item', 'tag', 'inquiry'], true)
			|| !DEEC_Site_Router::isRedirectablePath($path)) {
			throw new DomainException('MESSAGES_REDIRECT_TARGET_INVALID');
		}

		$hash = hash('sha256', $path);
		$row = $this->fetchRow(
			$this->select()
				->where('clientid = ?', $this->getClientId())
				->where('shopid = ?', $shopId)
				->where('pathhash = ?', $hash)
				->limit(1)
				->forUpdate(true)
		);

		$data = [
			'path' => $path,
			'pathhash' => $hash,
			'module' => 'shops',
			'controller' => $controller,
			'entityid' => $entityId,
			'deleted' => 0,
		];

		if ($row) {
			$existing = $row->toArray();
			if ((string)$existing['path'] !== $path) throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
			if ((string)$existing['controller'] !== $controller || (int)$existing['entityid'] !== $entityId) {
				throw new DomainException('MESSAGES_REDIRECT_CONFLICT');
			}

			$data['modified'] = $this->_date;
			$data['modifiedby'] = $this->getUserId();
			$this->update($data, 'id = ' . (int)$existing['id']);

			return (int)$existing['id'];
		}

		$data['shopid'] = $shopId;
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->getUserId();
		$this->insert($data);

		return (int)$this->getAdapter()->lastInsertId();
	}
}
