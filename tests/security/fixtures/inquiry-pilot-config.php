<?php

// Synthetic public-form data, independent of production form references.
function inquiryPilotConfig(): array
{
	return [
		1 => [
			['name' => 'einsatzbereich', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', '0' => 'Kühlung', '1' => 'Klima', '2' => 'Heizung', '3' => 'Warmwasser']],
			['name' => 'kuehlleistung', 'type' => 'number', 'depends_on' => 'einsatzbereich', 'depends_value' => ['0', '1'], 'attribs' => ['min' => 0, 'step' => 'any', 'data-min-exclusive' => 0]],
			['name' => 'heizleistung', 'type' => 'number', 'depends_on' => 'einsatzbereich', 'depends_value' => ['2', '3'], 'attribs' => ['min' => 0, 'step' => 'any', 'data-min-exclusive' => 0]],
			['name' => 'vorlauf_temp', 'type' => 'number', 'attribs' => ['step' => 'any']],
			['name' => 'aufstellung', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'innen' => 'Innen', 'aussen' => 'Außen', 'unbekannt' => 'Noch zu klären']],
			['name' => 'medium', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', '0' => 'Wasser', '1' => 'Wasser/Glykol'], 'depends' => [
				'1' => [
					['name' => 'glykol_typ', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', '0' => 'EG', '1' => 'PG', '2' => 'Sonstiges', 'unbekannt' => 'Noch zu klären'], 'depends' => [
						'2' => [['name' => 'glykol_typ_sonstiges', 'type' => 'text']],
					]],
					['name' => 'glykol_anteil', 'type' => 'number', 'attribs' => ['min' => 0, 'max' => 100, 'step' => 'any']],
					['name' => 'glykol_bezugsart', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'masse' => 'Masse', 'volumen' => 'Volumen', 'unbekannt' => 'Noch zu klären'], 'depends' => [
						'volumen' => [['name' => 'glykol_bezugstemperatur', 'type' => 'number', 'section' => 'Weitere Fluidangaben', 'tab' => 'advanced', 'attribs' => ['step' => 'any']]],
					]],
				],
			]],
		],
		2 => [
			['name' => 'pumpe', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', '0' => 'Ja', '1' => 'Nein', '2' => 'Extern', 'unbekannt' => 'Noch zu klären']],
			['name' => 'tank', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'kein' => 'Keiner', 'unbekannt' => 'Noch zu klären']],
			['name' => 'freitext_optionen', 'type' => 'textarea'],
			['name' => 'netzspannung', 'type' => 'number', 'section' => 'Elektrische Angaben', 'tab' => 'advanced', 'attribs' => ['min' => 0, 'step' => 'any', 'data-min-exclusive' => 0]],
			['name' => 'netzfrequenz', 'type' => 'number', 'section' => 'Elektrische Angaben', 'tab' => 'advanced', 'attribs' => ['min' => 0, 'step' => 'any', 'data-min-exclusive' => 0]],
		],
		3 => [
			['name' => 'firma', 'type' => 'text', 'required' => true],
			['name' => 'ansprechpartner', 'type' => 'text', 'required' => true],
			['name' => 'adresse', 'type' => 'text', 'required' => true],
			['name' => 'plz', 'type' => 'text', 'required' => true],
			['name' => 'stadt', 'type' => 'text', 'required' => true],
			['name' => 'country', 'type' => 'select', 'source' => 'country', 'options' => ['' => 'Bitte wählen'], 'required' => true],
			['name' => 'lieferadresse_abweichend', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', 'nein' => 'Nein', 'ja' => 'Ja', 'unbekannt' => 'Noch zu klären'], 'depends' => [
				'ja' => [
					['name' => 'lieferempfaenger', 'type' => 'text', 'required' => true],
					['name' => 'lieferansprechpartner', 'type' => 'text'],
					['name' => 'lieferstrasse', 'type' => 'textarea', 'required' => true],
					['name' => 'lieferplz', 'type' => 'text', 'required' => true],
					['name' => 'lieferstadt', 'type' => 'text', 'required' => true],
					['name' => 'lieferland', 'type' => 'select', 'source' => 'country', 'options' => ['' => 'Bitte wählen'], 'required' => true],
				],
			]],
			['name' => 'email', 'type' => 'email', 'required' => true],
			['name' => 'privacy', 'type' => 'checkbox', 'required' => true],
		],
		'_settings' => ['selection' => ['method' => 'manual', 'automatic_quote' => false]],
	];
}
