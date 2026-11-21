<?php

class Admin_Form_Shipping extends DEEC_Form
{
	public function __construct(string $kind, array $choices = [], bool $itemMode = false)
	{
		$add = function(string $name, string $type = 'text', array $extra = []): void {
			$this->addElement($extra + ['name' => $name, 'type' => $type, 'label' => 'SHIPPING_' . strtoupper($name), 'col' => 6, 'attribs' => ['id' => 'shipping-' . $name]]);
		};
		$select = fn(string $name, string $source) => $add($name, 'select', ['options' => $choices[$source] ?? ['' => 'SHIPPING_SELECT']]);
		if ($kind === 'settings') { $select('currency', 'currency'); $add('pickup', 'checkbox'); return; }
		if (in_array($kind, ['classes', 'zones', 'tariffs'], true)) $add('title');
		$add('active', 'checkbox'); if (!$itemMode) $add('ordering', 'number', ['default' => 0, 'attribs' => ['id' => 'shipping-ordering', 'min' => 0, 'step' => 1]]);
		switch ($kind) {
			case 'classes':
				$add('combine_same_class', 'checkbox');
				foreach (['packweight', 'packlength', 'packwidth', 'packheight'] as $field) $add('require_' . $field, 'checkbox');
				break;
			case 'zones': $add('countries', 'multicheckbox', ['options' => $choices['countries'] ?? [], 'col' => 12]); break;
			case 'tariffs':
				$select('classid', 'classid'); $select('zoneid', 'zoneid'); $select('currency', 'currency');
				$add('model', 'select', ['options' => ['fixed' => 'SHIPPING_FIXED', 'weight' => 'SHIPPING_WEIGHT', 'additional_packages' => 'SHIPPING_ADDITIONAL_PACKAGES', 'percentage' => 'SHIPPING_PERCENTAGE', 'manual' => 'SHIPPING_MANUAL']]);
				foreach (['amount', 'base', 'additional', 'percent', 'min_weight', 'max_weight', 'min_packages', 'max_packages', 'min_value', 'max_value'] as $field) $add($field, 'text', ['attribs' => ['id' => 'shipping-' . $field, 'inputmode' => 'decimal']]);
				break;
			case 'assignments':
				if (!$itemMode) $add('itemid', 'number'); $select('classid', 'classid'); $add('packages_per_unit', 'number', ['default' => 1]); $add('separate', 'checkbox', ['default' => 1]); break;
			case 'combinations':
				$select('hostclassid', 'classid'); $select('guestclassid', 'classid');
				foreach (['priority', 'max_guest_packages', 'max_guest_weight', 'max_packlength', 'max_packwidth', 'max_packheight'] as $field) $add($field, 'text', ['attribs' => ['id' => 'shipping-' . $field, 'inputmode' => 'decimal']]);
				break;
		}
	}
}
