<?php

class Campaigns_Model_Get
{
	public function campaigns($params, $options, $flashMessenger)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['campaigns'];
		}

		$campaignsDb = new Campaigns_Model_DbTable_Campaign();

		$columns = array('p.title');

		$query = '';
		$schema = 'p';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryCategory($query, $params['catid'], $options['categories'], 'c');
		if($params['states']) $query = $queryHelper->getQueryStates($query, $params['states'], $schema);
		if($params['country']) $query = $queryHelper->getQueryCountry($query, $params['country'], $options['countries'], $schema);
		if($params['daterange']) {
			$params['from'] = date('Y-m-d', strtotime($params['from']));
			$params['to'] = date('Y-m-d', strtotime($params['to']));
			$query = $queryHelper->getQueryDaterange($query, $params['from'], $params['to'], $schema);
		}
		$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
		$query = $queryHelper->getQueryDeleted($query, $schema);

		$campaigns = $campaignsDb->fetchAll(
			$campaignsDb->select()
				->setIntegrityCheck(false)
				->from(array('p' => 'campaign'))
				->group($schema.'.id')
				->where($query ? $query : 1)
				->order(array('pinned desc', $params['order'].' '.$params['sort']))
				->limit($params['limit'])
		);
		if(!count($campaigns) && $params['keyword']) {
			$query = $queryHelper->getQueryKeyword('', $params['keyword'], $columns);
			$query = $queryHelper->getQueryClient($query, $client['id'], $schema);
			$query = $queryHelper->getQueryDeleted($query, $schema);
			$campaigns = $campaignsDb->fetchAll(
				$campaignsDb->select()
					->setIntegrityCheck(false)
					->from(array('p' => 'campaign'))
					->group($schema.'.id')
					->where($query ? $query : 1)
					->order(array('pinned desc', $params['order'].' '.$params['sort']))
					->limit($params['limit'])
			);
		}
		if(!count($campaigns)) $flashMessenger->addMessage('MESSAGES_SEARCH_RETURNED_NO_RESULTS');

		$campaigns->expectedrevenue = 0;
		$campaigns->budgetedcost = 0;
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($campaigns as $campaign) {
			$campaigns->expectedrevenue += $campaign->expectedrevenue;
			$campaigns->budgetedcost += $campaign->budgetedcost;
			$currency = $currencyHelper->setCurrency($currency, $campaign->currency, 'USE_SYMBOL');
			if($campaign->expectedrevenue == 0.0000) $campaign->expectedrevenue = 0;
			else $campaign->expectedrevenue = $currency->toCurrency($campaign->expectedrevenue);
			if($campaign->budgetedcost == 0.0000) $campaign->budgetedcost = 0;
			else $campaign->budgetedcost = $currency->toCurrency($campaign->budgetedcost);
		}
		$campaigns->budgetedcost = $currency->toCurrency($campaigns->budgetedcost);

		return $campaigns;
	}
}
