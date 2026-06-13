<?php

class Admin_Model_Service_ClientInitializer
{
	public function initialize(int $clientId): void
	{
		$this->createDefaultCategories($clientId);
		$this->createDefaultConfig($clientId);
		$this->createDefaultCurrencies($clientId);
		$this->createDefaultFilenames($clientId);
		$this->createDefaultIncrements($clientId);
		$this->createDefaultLanguages($clientId);
		$this->createDefaultTaxrates($clientId);
		$this->createDefaultTemplate($clientId);
		$this->createDefaultTextblocks($clientId);
		$this->createDefaultUoms($clientId);
		$this->createDefaultWarehouse($clientId);
	}

	private function createDefaultCategories(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Category();

		$items = [
			['shopid' => 0, 'title' => 'Privatkunden', 'type' => 'contact', 'ordering' => 1, 'parentid' => 0],
			['shopid' => 0, 'title' => 'Geschäftskunden', 'type' => 'contact', 'ordering' => 2, 'parentid' => 0],
			['shopid' => 0, 'title' => 'Lieferanten', 'type' => 'contact', 'ordering' => 3, 'parentid' => 0],
		];

		foreach ($items as $item) {
			$db->addCategory($item, $clientId);
		}
	}

	private function createDefaultConfig(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Config();

		$db->addConfig([
			'timezone' => 'Europe/Berlin',
			'language' => 'de_DE',
		], $clientId);
	}

	private function createDefaultCurrencies(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Currency();

		$items = [
			['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'ordering' => 1],
			['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'ordering' => 2],
		];

		foreach ($items as $item) {
			$db->addCurrency($item, $clientId);
		}
	}

	private function createDefaultFilenames(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Filename();

		$db->addFilename([
			'creditnote' => 'Gutschrift-%NUMBER%.pdf',
			'deliveryorder' => 'Lieferschein-%NUMBER%.pdf',
			'invoice' => 'Rechnung-%NUMBER%.pdf',
			'purchaseorder' => 'Bestellung-%NUMBER%.pdf',
			'quote' => 'Angebot-%NUMBER%.pdf',
			'quoterequest' => 'Anfrage-%NUMBER%.pdf',
			'reminder' => 'Mahnung-%NUMBER%.pdf',
			'salesorder' => 'Auftragsbestaetigung-%NUMBER%.pdf',
		], $clientId);
	}

	private function createDefaultIncrements(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Increment();

		$db->addIncrement([
			'clientid' => $clientId,
			'contactid' => 10000,
			'creditnoteid' => 10000,
			'deliveryorderid' => 10000,
			'invoiceid' => 10000,
			'purchaseorderid' => 10000,
			'quoteid' => 10000,
			'quoterequestid' => 10000,
			'reminderid' => 10000,
			'salesorderid' => 10000,
			'shoporderid' => 10000,
		], $clientId);
	}

	private function createDefaultLanguages(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Language();

		$items = [
			['code' => 'de_DE', 'name' => 'Deutsch'],
			['code' => 'en_US', 'name' => 'Englisch'],
		];

		foreach ($items as $item) {
			$db->addLanguage($item, $clientId);
		}
	}

	private function createDefaultTaxrates(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Taxrate();

		$items = [
			['name' => 'MwSt (19%)', 'rate' => 19.0000, 'ordering' => 1],
			['name' => 'MwSt (7%)', 'rate' => 7.0000, 'ordering' => 2],
		];

		foreach ($items as $item) {
			$db->addTaxrate($item, $clientId);
		}
	}

	private function createDefaultTemplate(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Template();

		$db->addTemplate([
			'description' => 'Vorlage',
			'default' => 1,
		], $clientId);
	}

	private function createDefaultTextblocks(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Textblock();

		for ($i = 1; $i <= 16; $i++) {
			$db->addTextblock($db->getTextblock($i), $clientId);
		}
	}

	private function createDefaultUoms(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Uom();

		foreach (['Stück', 'Pack.', 'Std.', 'kg', 'm'] as $title) {
			$db->addUom([
				'title' => $title,
			], $clientId);
		}
	}

	private function createDefaultWarehouse(int $clientId): void
	{
		$db = new Admin_Model_DbTable_Warehouse();

		$db->addWarehouse([
			'title' => 'Hauptlager',
			'description' => 'Hauptlager',
		], $clientId);
	}
}
