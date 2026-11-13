<?php

// All IDs belong to the isolated test database, never to a production configuration.
function inquiryVariantConfig(): array
{
	return [
		'base' => [
			['name' => 'family', 'label' => 'Produktgruppe', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'equipment' => 'Gerät', 'trading' => 'Handelsware']],
		],
		'configuration' => [
			['name' => 'variantid', 'type' => 'hidden'],
			['name' => 'execution', 'label' => 'Ausführung', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'small' => 'Klein', 'large' => 'Groß']],
			['name' => 'kit', 'label' => 'Zusätzliche Ausstattung', 'type' => 'select', 'required' => true, 'default' => 'none', 'options' => ['none' => 'Keine', 'yes' => 'Mit Ausstattung']],
			['name' => 'quantity', 'label' => 'Menge', 'type' => 'number', 'required' => true, 'default' => 1, 'format' => ['type' => 'decimal'], 'attribs' => ['step' => 'any', 'data-min-exclusive' => 0]],
		],
		'contact' => [
			['name' => 'sku', 'label' => 'Artikelnummer', 'type' => 'text'],
			['name' => 'titel', 'label' => 'Artikelbezeichnung', 'type' => 'text'],
			['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true],
			['name' => 'privacy', 'label' => 'Datenschutz bestätigt', 'type' => 'checkbox', 'required' => true],
		],
		'_settings' => ['selection' => [
			'method' => 'variant_config', 'automatic_quote' => true,
			'base_field' => 'family', 'variant_field' => 'variantid', 'step' => 'configuration', 'quantity_field' => 'quantity',
			'bases' => [
				'equipment' => ['itemid' => 2000, 'included_options' => [203], 'option_fields' => [
					'execution' => ['group_id' => 200, 'role' => 'variant', 'exclusivity' => 'single', 'choices' => ['small' => 201, 'large' => 202]],
					'kit' => ['group_id' => 202, 'role' => 'additional', 'exclusivity' => 'single', 'compatibility_verified' => true, 'choices' => ['none' => null, 'yes' => 204]],
				]],
				'trading' => ['itemid' => 2100, 'option_fields' => [
					'execution' => ['group_id' => 210, 'role' => 'variant', 'exclusivity' => 'single', 'choices' => ['small' => 211, 'large' => 212]],
					'kit' => ['group_id' => 212, 'role' => 'additional', 'exclusivity' => 'single', 'compatibility_verified' => true, 'choices' => ['none' => null, 'yes' => 214]],
				]],
			],
		]],
	];
}
