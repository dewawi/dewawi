<?php

class Admin_Model_List_Shipping extends DEEC_List
{
	protected function buildColumns()
	{
		return [
			['name' => 'title', 'label' => 'SHIPPING_TITLE', 'type' => 'callback', 'callback' => [$this, 'link']],
			['name' => 'details', 'label' => 'SHIPPING_DETAILS', 'type' => 'callback', 'callback' => [$this, 'details']],
			['name' => 'active', 'label' => 'SHIPPING_ACTIVE', 'type' => 'callback', 'callback' => [$this, 'active']],
			['name' => 'ordering', 'label' => 'SHIPPING_ORDERING', 'type' => 'text'],
			['name' => 'id', 'label' => 'ADMIN_ID', 'type' => 'text'],
		];
	}
	public function link($row): string
	{
		$url = $this->view->url(['module' => 'admin', 'controller' => 'shipping', 'action' => 'edit', 'shopid' => $this->context['shopid'], 'kind' => $this->context['kind'], 'id' => (int)$row['id']], null, true);
		$title = $row['title'] ?? ('#' . ($row['itemid'] ?? $row['id']));
		if (trim($title) === '') $title = $this->translate('SHIPPING_UNNAMED') . ' #' . $row['id'];
		return '<a href="' . $this->escape($url) . '">' . $this->escape($title) . '</a>';
	}
	public function details($row): string
	{
		$choices = $this->context['choices'] ?? [];
		$class = fn($id): string => $id ? ($choices['classid'][$id] ?? ('#' . $id)) : $this->translate('SHIPPING_SELECT');
		switch ($this->context['kind']) {
			case 'classes': $text = $this->translate($row['combine_same_class'] ? 'SHIPPING_COMBINE_SAME_CLASS' : 'SHIPPING_SEPARATE'); break;
			case 'zones': $countries = json_decode($row['countries'] ?: '[]', true); $text = is_array($countries) ? implode(', ', array_filter($countries, 'is_string')) : ''; break;
			case 'tariffs': $text = $class($row['classid']) . ' · ' . (!empty($row['zoneid']) ? ($choices['zoneid'][$row['zoneid']] ?? ('#' . $row['zoneid'])) : $this->translate('SHIPPING_SELECT')) . ' · ' . $this->translate('SHIPPING_' . strtoupper($row['model'] ?? '')) . ' · ' . ($row['currency'] ?? ''); break;
			case 'assignments': $text = '#' . $row['itemid'] . ' → ' . $class($row['classid']) . ' · ' . $row['packages_per_unit'] . ' ' . $this->translate('SHIPPING_PACKAGES_SHORT'); break;
			case 'combinations': $text = $class($row['hostclassid']) . ' ← ' . $class($row['guestclassid']) . ' · ' . $this->translate('SHIPPING_PRIORITY_SHORT') . ' ' . $row['priority']; break;
			default: $text = '';
		}
		return $this->escape($text);
	}

	public function active($row): string { return $this->escape($this->translate($row['active'] ? 'SHIPPING_ENABLED' : 'SHIPPING_DISABLED')); }
}
