<?php

class Admin_Model_Get
{
	protected $db;

	public function __construct()
	{
		// Initialize your database adapter
		$this->db = Zend_Db_Table::getDefaultAdapter();
	}

	public function tags($module, $controller, $id = null) {
		if($id) {
			$shopid = 100; // TODO
			//$client = Zend_Registry::get('Client');
			$tagEntityDb = new Application_Model_DbTable_Tagentity();
			$tags = $tagEntityDb->fetchAll(
				$tagEntityDb->select()
					->setIntegrityCheck(false)
					->from(array('t' => 'tagentity'))
					->join(array('tag' => 'tag'), 't.tagid = tag.id', array('title as tag', 'module', 'controller'))
					->group('t.id')
					//->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.shopid = "'.$shopid.'") AND (t.deleted = 0)')
					->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.deleted = 0)')
					//->order($order.' '.$params['sort'])
					//->limit($params['limit'], $params['offset'])
			);
			$tags = $tags->toArray();
		} else {
			$tagsDb = new Shops_Model_DbTable_Tag();
			$tags = $tagsDb->getTags($module, $controller);
		}
		//print_r($tags);

		return $tags;
	}
}
