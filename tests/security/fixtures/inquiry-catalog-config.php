<?php

// Two synthetic product groups; IDs refer only to the isolated database fixtures.
function inquiryCatalogConfig(string $group = 'equipment'): array
{
    $trading = $group === 'trading';
    return [
        'requirements' => [
            ['name' => 'demand', 'label' => $trading ? 'Durchmesser' : 'Leistung', 'type' => 'number', 'unit' => $trading ? 'mm' : 'kW', 'required' => true, 'attribs' => ['step' => 'any']],
            ['name' => 'material', 'label' => 'Ausführung', 'type' => 'select', 'required' => true, 'options' => ['' => 'Bitte wählen', '0' => $trading ? 'Stahl' : 'Wasser', '1' => $trading ? 'Messing' : 'Luft']],
        ],
        'contact' => [
            ['name' => 'itemid', 'type' => 'hidden'],
            ['name' => 'sku', 'label' => 'Gewünschte Artikelnummer', 'type' => 'text'],
            ['name' => 'titel', 'label' => 'Gewünschte Bezeichnung', 'type' => 'text'],
            ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'required' => true],
            ['name' => 'privacy', 'label' => 'Datenschutz bestätigt', 'type' => 'checkbox', 'required' => true],
        ],
        '_settings' => ['selection' => [
            'method' => 'catalog', 'automatic_quote' => true,
            'step' => 'requirements', 'item_field' => 'itemid', 'categories' => [$trading ? 40 : 30], 'include_subcategories' => true, 'limit' => 4,
            'filters' => [
                ['field' => 'demand', 'attribute' => $trading ? 'Diameter' : 'Power', 'column' => $trading ? 'description' : 'value', 'type' => 'number', 'operator' => 'gte', 'unit' => $trading ? 'mm' : 'kW'],
                ['field' => 'material', 'attribute' => $trading ? 'Material' : 'Fluid', 'column' => 'description', 'type' => 'text', 'operator' => 'eq', 'value_map' => ['0' => $trading ? 'steel' : 'water', '1' => $trading ? 'brass' : 'air']],
                ['attribute' => 'Grade', 'column' => 'value', 'type' => 'text', 'operator' => 'eq', 'values' => ['A', 'B']],
            ],
        ]],
    ];
}
