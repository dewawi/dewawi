<?php

class DEEC_Pdf
{
    public $quote = [];
    public $template = [];
    public $shop = [];

    /*public function Header()
    {
        if ($this->page <= 1 || empty($this->quote)) return;

        // Top title
        $this->SetFont('freesansb', 'B', 11);
        $this->SetXY(15, 12);
        $this->Cell(120, 6, mb_strtoupper($this->quote['title'] ?? 'ANGEBOT', 'UTF-8'), 0, 1, 'L', false, '', 1);

        // Info line
        $nr   = $this->quote['quoteid']   ?? '';
        $date = isset($this->quote['quotedate']) ? (new DateTime($this->quote['quotedate']))->format('d.m.Y') : '';
        $cid  = $this->quote['contactid'] ?? '';

        $this->SetFont('freesans', '', 9);
        $this->SetXY(15, 18);
        $line = "Angebotsnummer: {$nr}    Angebotsdatum: {$date}    Kundennummer: {$cid}";
        $this->Cell(170, 6, $line, 0, 1, 'L', false, '', 1);

        // Logo
		$website = !empty($template['website']) ? $template['website'] : ($shop['website'] ?? '');
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

        // small separator line
        $this->SetLineWidth(0.1);
        $this->Line(15, 26, 195, 26);
        $this->SetY(30);
    }

    public function Footer()
    {
        // page numbers centered
        $this->SetY(-15);
        $this->SetFont('freesans', '', 8);
        $this->Cell(0, 10, sprintf('Seite %d / %d', $this->getAliasNumPage(), $this->getAliasNbPages()), 0, 0, 'C');
    }*/

    public function generate($quoteId)
    {
        $shop   = Zend_Registry::get('Shop'); // or Shop, depending on your app
        $view     = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

        // --- Load data using SHOPS models only
        $quoteDb  = new Shops_Model_DbTable_Quote();
        $quote    = $quoteDb->getQuote($quoteId);

        $contact  = null;
        if (!empty($quote['contactid'])) {
            $contactDb = new Shops_Model_DbTable_Contact();
            $contact   = $contactDb->getContactWithID($quote['contactid']);
        }

        // Set language (same way you do in Sales view)
        if (!empty($quote['language'])) {
            $translate = new Zend_Translate('array', BASE_PATH.'/languages/'.$quote['language']);
            Zend_Registry::set('Zend_Translate', $translate);
        }

        // Positions + all the stuff you need in the PDF
        list($positions, $quote, $options, $optionSets, $attributesByGroup) = $this->getPositionsForQuote($quoteId, $quote);

        // Pick the template
        $tplDb    = new Shops_Model_DbTable_Template();
        $template = $quote['templateid'] ? $tplDb->getTemplate($quote['templateid']) : $tplDb->getPrimaryTemplate();

        // --- Build TCPDF just like your view
        require_once(BASE_PATH . '/library/Tcpdf/config/tcpdf_config.php');
        require_once(BASE_PATH . '/library/Tcpdf/tcpdf.php');

		$pdf = $this->initPdf($view->translate('QUOTE').' '.($quote['quoteid'] ?: ''), $quote, $template);

        // ---- RENDER CONTENT
		$coverY = $this->renderCoverAndMeta($pdf, $quote, $template); // now returns Y position (see below)
		$pages = []; // collect section page numbers

		$pages['product_start']  = $pdf->getPage(); $this->renderProductDescription($pdf, $positions, $shop);
		$pages['features_start']  = $pdf->getPage(); $this->renderIncludedOptions($pdf, $positions, $options, $optionSets);
		$pages['offer_start']    = $pdf->getPage(); $this->renderPositions($pdf, $quote, $positions, $options, $optionSets, $attributesByGroup);
		$pages['offer_end']      = $pdf->getPage();

		$pages['terms_start']    = $pdf->getPage(); $this->renderTotalsAndFooter($pdf, $quote, $positions, $template);
		$pages['terms_end']      = $pdf->getPage();

		$pages['options_start']  = $pdf->getPage(); $this->renderOptions($pdf, $positions, $options, $optionSets);
		$pages['options_end']    = $pdf->getPage();

		$pages['images_start']   = $pdf->getPage(); $this->renderImages($pdf, $positions);
		$pages['images_end']     = $pdf->getPage();

		// Build the TOC on page 1
		$this->renderTableOfContentsOnCover($pdf, $quote, $template, $coverY, $pages);

		$this->renderCoverImage($pdf, $positions, $shop);

		$website = !empty($template['website']) ? $template['website'] : ($shop['website'] ?? '');
		$this->renderHeaderFooter($pdf, $quote, $template, $website);

        // Decide the target filename/path (same scheme you already use)
        if (!empty($contact)) {
            $contactUrl = $this->contactUrl($contact['id'], $shop['clientid']);
            $dirAbs     = BASE_PATH . '/files/contacts/' . $contactUrl;
            $this->mkdirp($dirAbs);
            $filename   = $quote['filename'] ?: ('Angebot_' . ($contact['name1'] ?: 'Kunde') . '.pdf');
            $fileAbs    = $dirAbs . $filename;
            $fileUrl    = $view->baseUrl() . '/files/contacts/' . $contactUrl . $filename;
        } else {
            $this->mkdirp(BASE_PATH . '/cache/quote/');
            $filename = ($quote['filename'] ?: ('quote_' . $quoteId . '.pdf'));
            $fileAbs  = BASE_PATH . '/cache/quote/' . $quoteId . '.pdf';
            $fileUrl  = $view->baseUrl() . '/cache/quote/' . $quoteId . '.pdf';
        }

        // Save the file
        $pdf->Output($fileAbs, 'F');

        return ['path' => $fileAbs, 'url' => $fileUrl, 'filename' => $filename];
    }

	private function initPdf($title, array $quote = [], array $template = [])
	{
		$pdf = new Tcpdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->quote = $quote;
		$pdf->template = $template;

		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetTitle($title);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(15, 30, 15);
		$pdf->SetAutoPageBreak(true, 20);
		$pdf->SetDisplayMode('real', 'OneColumn');
		$pdf->AddPage();
		return $pdf;
    }

    private function mkdirp($dir)
    {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }

    private function contactUrl($contactId, $clientId)
    {
        // reproduce your “files/contacts” sharding scheme
        $dir1 = substr($clientId, 0, 1);
        $dir2 = (strlen($clientId) > 1) ? substr($clientId, 1, 1) : '0';
        $c1   = substr($contactId, 0, 1);
        $c2   = (strlen((string)$contactId) > 1) ? substr((string)$contactId, 1, 1) : '0';
        return $dir1.'/'.$dir2.'/'.$clientId.'/'.$c1.'/'.$c2.'/'.$contactId.'/';
    }

    private function getPositionsForQuote($quoteId, array $quote)
    {
		$locale = Zend_Registry::isRegistered('Zend_Locale')
		    ? Zend_Registry::get('Zend_Locale')
		    : new Zend_Locale('de_DE');

		// Currency helper (same as Sales)
		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currencyObj    = $currencyHelper->getCurrency($quote['currency'], 'USE_SYMBOL');

		// Load positions
		$posDb     = new Shops_Model_DbTable_Quotepos();
		$positions = $posDb->getPositions($quoteId);

		$options = [];
		$optionSets = [];

		if (count($positions)) {
		    // Apply price rules to all positions
		    $prHelper = new DEEC_PriceRule();

		    // prefer shops/quotepos, fallback to sales/quotepos (keeps older rules working)
		    $ruleModule     = 'shops';
		    $ruleController = 'quotepos';
		    try {
		        $price = $prHelper->usePriceRulesOnPositions($positions, $ruleModule, $ruleController);
		    } catch (Exception $e) {
		        // fallback if your rules are still stored under "sales/quotepos"
		        $price = $prHelper->usePriceRulesOnPositions($positions, 'sales', 'quotepos');
		    }

		    // Format each position
		    foreach ($positions as $p) {
		        // quantity precision like before
		        $precision = (floor($p->quantity) == $p->quantity) ? 0 : 2;

		        $calculatedUnit = isset($price['calculated'][$p->id]) ? $price['calculated'][$p->id] : (float)$p->price;
		        $rawTotal       = $calculatedUnit * (float)$p->quantity;

		        // Keep a raw “calculated” if you need it later (not printed)
		        //$p->calculated_price = $calculatedUnit;
		        //$p->calculated_total = $rawTotal;

		        // Display values
		        $p->price    = $currencyObj->toCurrency($calculatedUnit);
		        $p->total    = $currencyObj->toCurrency($rawTotal);
		        $p->quantity = Zend_Locale_Format::toNumber($p->quantity, ['precision' => $precision, 'locale' => $locale]);
		    }

		    // Quote totals formatting
		    $quote['taxes']    = $currencyObj->toCurrency($quote['taxes']);
		    $quote['subtotal'] = $currencyObj->toCurrency($quote['subtotal']);
		    $quote['total']    = $currencyObj->toCurrency($quote['total']);
		    if (!empty($quote['taxfree'])) {
		        $quote['taxrate'] = Zend_Locale_Format::toNumber(0, ['precision' => 2, 'locale' => $locale]);
		    } else {
		        // pull taxrate from first position if available
		        $first = reset($positions);
		        $quote['taxrate'] = Zend_Locale_Format::toNumber(
		            isset($first->taxrate) ? $first->taxrate : 0,
		            ['precision' => 2, 'locale' => $locale]
		        );
		    }

		    // Options & option sets (Items module)
		    list($options, $optionSets) = $this->getOptionsForPositions($positions, $price, $currencyObj);
			$attributesByGroup = $this->getAttributesForPositions($positions);
		}

		return [$positions, $quote, $options, $optionSets, $attributesByGroup];
    }

	private function getAttributesForPositions($positions)
	{
		$attributesByGroup = [];

		if (!count($positions)) return $attributesByGroup;

		// Use SHOPS tables so we don't touch Sales
		$setDb  = new Shops_Model_DbTable_Itematrset();
		$atrDb  = new Shops_Model_DbTable_Itematr();

		foreach ($positions as $p) {
		    if (empty($p->itemid)) continue;

		    // 1) Sets for this item
		    $sets = $setDb->getPositionSets($p->itemid); // expect array|Traversable of rows (id, title, description)

		    // 2) Attributes per set
		    foreach ($sets as $set) {
		        $setId   = $this->v($set, 'id');
		        $title   = $this->v($set, 'title');
		        $desc    = $this->v($set, 'description');

		        $attrs = $atrDb->getPositions($p->itemid, $setId); // list for that set id
		        $attributesByGroup[$p->id][$setId] = [
		            'title'       => $title,
		            'description' => $desc,
		            'attributes'  => $this->rowsToArray($attrs),
		        ];
		    }

		    // 3) “Other” attributes without a set (id 0)
		    $other = $atrDb->getPositions($p->itemid, 0);
		    if ($other && count($other)) {
		        $attributesByGroup[$p->id][] = [
		            'title'       => 'Sonstiges',
		            'description' => '',
		            'attributes'  => $this->rowsToArray($other),
		        ];
		    }
		}

		return $attributesByGroup;
	}

	// Safely read from array|object
	private function v($row, $key, $default = '')
	{
		if (is_object($row) && isset($row->$key)) return $row->$key;
		if (is_array($row)  && isset($row[$key])) return $row[$key];
		return $default;
	}

	// Normalize a list of rows (array|Traversable of array|object) to arrays with string keys
	private function rowsToArray($rows)
	{
		$out = [];
		if (!$rows) return $out;
		foreach ($rows as $r) {
		    $out[] = [
		        'id'          => $this->v($r, 'id'),
		        'sku'         => $this->v($r, 'sku'),
		        'title'       => $this->v($r, 'title'),
		        'description' => $this->v($r, 'description'),
		    ];
		}
		return $out;
	}

	private function getOptionsForPositions($positions, array $priceMap, $currencyObj)
	{
		$options    = [];
		$optionSets = [];

		$optDb     = new Shops_Model_DbTable_Itemopt();
		$optSetDb  = new Shops_Model_DbTable_Itemoptset();
		$prHelper  = new DEEC_PriceRule();

		foreach ($positions as $p) {
		    // only for top-level positions that point to an item
		    if (empty($p->itemid) || !empty($p->masterid)) {
		        continue;
		    }

		    // load sets for this item
		    $sets = $optSetDb->getPositionSets($p->itemid);
		    $optionSets[$p->id] = $sets;

		    foreach ($sets as $set) {
		        $opts = $optDb->getPositions($p->itemid, $set->id);
		        $options[$p->id][$set->id] = $opts;

		        foreach ($options[$p->id][$set->id] as $k => $opt) {
		            // Compute option price with rules (if position has a pricerule master)
		            $finalPrice = $this->priceForOption($opt, $p, $prHelper);

		            // Format or keep special values
		            $options[$p->id][$set->id][$k]->price =
		                $this->formatOptionPrice($opt->price, $finalPrice, $currencyObj);
		        }
		    }
		}

		return [$options, $optionSets];
	}

	private function priceForOption($opt, $position, $prHelper)
	{
		// Keep 0 / -1 / -2 as-is (included / on request / not available)
		if (in_array((float)$opt->price, [0.0, -1.0, -2.0], true)) {
		    return (float)$opt->price;
		}

		// If the position acts as a price rule “master”, apply its rules to the option price
		if (!empty($position->pricerulemaster)) {
		    try {
		        // Prefer shops/quotepos, fallback to sales/quotepos
		        $rules = $prHelper->getPriceRulePositions('shops', 'quotepos', $position->id);
		        if (!count($rules)) {
		            $rules = $prHelper->getPriceRulePositions('sales', 'quotepos', $position->id);
		        }
		        return (float)$prHelper->usePriceRules($rules, (float)$opt->price);
		    } catch (Exception $e) {
		        // graceful fallback
		        return (float)$opt->price;
		    }
		}

		return (float)$opt->price;
	}

	private function formatOptionPrice($original, $final, $currencyObj)
	{
		// original may be 0 / -1 / -2 → keep the semantic codes
		if (in_array((float)$original, [0.0, -1.0, -2.0], true)) {
		    return (float)$original; // caller renders label (included / on request / not available)
		}
		return $currencyObj->toCurrency($final);
	}

	/**
	 * Renders the first page: headline, meta box (quote id/date/customer/etc),
	 * letterhead, billing/shipping address, subject/reference, intro text.
	 */
	private function renderCoverAndMeta(TCPDF $pdf, array $quote, array $template)
	{
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$t    = [$view, 'translate'];

		$shop = Zend_Registry::get('Shop'); // holds company data in your shops context
		$contact = null;
		if (!empty($quote['contactid'])) {
		    $cDb = new Shops_Model_DbTable_Contact();
		    $contact = $cDb->getContactWithID($quote['contactid']);
		}

		$quoteid     = $quote['quoteid'] ?? '';
		$quotedate   = $this->formatDate($quote['quotedate']);
		$delivery    = $this->formatDate($quote['deliverydate']);
		$vatin       = $quote['vatin'] ?? '';
		$salesPerson = $quote['contactperson'] ?? '';

		// “IMPORTANT INFORMATION” box on top-right
		$pdf->SetY(40);
		$pdf->setCellPaddings(0, 0, 0, 0);
		$pdf->SetFont('freesans', '', 9);

		$pdf->MultiCell(30, 5, $t('DOCUMENTS_QUOTE_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(40, 5, ($quoteid ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $t('DOCUMENTS_QUOTE_DATE'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(28, 5, ($quotedate ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $t('DOCUMENTS_CUSTOMER_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(28, 5, ($quote['contactid'] ?? ''), 0, 'L', false, 1, 160);

		if (!empty($vatin)) {
		    $pdf->MultiCell(30, 5, $t('DOCUMENTS_VATIN'), 0, 'L', false, 0, 130);
		    $pdf->MultiCell(84, 5, $vatin, 0, 'L', false, 1, 160);
		}
		if (!empty($delivery)) {
		    $pdf->MultiCell(30, 5, $t('DOCUMENTS_DELIVERY_DATE'), 0, 'L', false, 0, 130);
		    $pdf->MultiCell(84, 5, $delivery, 0, 'L', false, 1, 160);
		}
		$pdf->MultiCell(30, 5, $t('DOCUMENTS_SALES_PERSON'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(84, 5, $salesPerson, 0, 'L', false, 1, 160);
		$pdf->ln(8);
		$yTop = $pdf->GetY();

		// Headline
		$x = $pdf->GetX()+5;
		$pdf->SetFont('freesansb', 'B', 15);
		$pdf->MultiCell(0, 0, mb_strtoupper($t('QUOTE'), 'UTF-8'), 0, 'L', false, 1, $x, 20);
		$pdf->ln(22);

		// Letterhead (company address line)
		$pdf->SetFont('freesans', '', 7);
		if (!empty($template['company'])) {
		    $address = $template['company'];
		} else {
		    // fallback to Shop company meta
		    $address = $shop['company'] ?? '';
		    if (!empty($shop['address']))  $address .= ', '.$shop['address'];
		    if (!empty($shop['postcode'])) $address .= ', '.$shop['postcode'];
		    if (!empty($shop['city']))     $address .= ' '.$shop['city'];
		}
		$pdf->MultiCell(70, 4, $address, 'B', 'L', false, 1, $x);
		$pdf->ln(4);
		$pdf->SetFont('freesans', '', 10);

		// Billing address block
		if (!empty($contact)) {
		    $pdf->MultiCell(70, 0, ($quote['billingname1'] ?? ''), 0, 'L', false, 1, $x);
		    if (!empty($quote['billingname2']))      $pdf->MultiCell(70, 0, $quote['billingname2'], 0, 'L', false, 1, $x);
		    if (!empty($quote['billingdepartment'])) $pdf->MultiCell(70, 0, $quote['billingdepartment'], 0, 'L', false, 1, $x);
		    if (!empty($quote['billingstreet']))     $pdf->MultiCell(70, 0, $quote['billingstreet'], 0, 'L', false, 1, $x);

		    $line = trim(($quote['billingpostcode'] ?? '').' '.($quote['billingcity'] ?? ''));
		    if ($line !== '') $pdf->MultiCell(70, 0, $line, 0, 'L', false, 1, $x);

		    $country = $quote['billingcountry'] ?? '';
		    if ($country) $country = $view->translate($country);
		    if ($country) $pdf->MultiCell(70, 0, $country, 0, 'L', false, 1, $x);

		    $pdf->ln(20);
		    if ($yTop > $pdf->GetY()) $pdf->SetY($yTop);
		}

		// Subject / reference / intro
		$pdf->SetFont('freesans', 'B', 12);
		if (!empty($quote['subject']))   $pdf->MultiCell(165, 0, $t('QUOTES_SUBJECT') . ': ' . $quote['subject'], 0, 'L', false, 1, 20, '', true, 0);
		if (!empty($quote['reference'])) $pdf->MultiCell(165, 0, $t('QUOTES_REFERENCE') . ': ' . $quote['reference'], 0, 'L', false, 1, 20, '', true, 0);
		$pdf->SetFont('freesans', '', 10);
		$pdf->MultiCell(165, 0, '', 0, 'L', false, 1, 20, '', true, 0);
		$pdf->MultiCell(165, 0, '', 0, 'L', false, 1, 20, '', true, 0);

		// Short intro (optional – keep it brief)
		$intro = !empty($template['intro'])
		       ? $template['intro']
		       : 'Vielen Dank für Ihre Anfrage. Im Anhang finden Sie unser Angebot.';
		$pdf->MultiCell(165, 0, $intro, 0, 'L', false, 1, 20, '', true, 0);
		$pdf->ln(2);

		// Optional cover logo
		$website = !empty($template['website']) ? $template['website'] : ($shop['website'] ?? '');
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

	    // Return a Y coordinate to start the TOC (a bit lower than current Y)
    	return max($pdf->GetY() + 8, 90);
	}

	private function renderCoverImage(TCPDF $pdf, $positions, $shop)
	{
		// Draw on page 1
		$pdf->setPage(1);

    	// --- Display first product image if available
		$mediaDb = new Shops_Model_DbTable_Media();

		// media base path
		$mediaPath = BASE_PATH.'/media/';
		$dir1 = substr($shop['clientid'], 0, 1);
		$dir2 = (strlen($shop['clientid']) > 1) ? substr($shop['clientid'], 1, 1) : '0';
		$mediaPath .= $dir1.'/'.$dir2.'/'.$shop['clientid'].'/images/';

		foreach ($positions as $p) {
		    if ((int)$p->masterid !== 0) continue; // only main items

		    if (!empty($p->itemid)) {
		        $images = $mediaDb->getMedia($p->itemid, 'items', 'item');
		        if (!count($images)) continue;

		        foreach ($images as $img) {
		            $url   = isset($img->url)   ? $img->url   : (isset($img['url']) ? $img['url'] : null);
		            $title = isset($img->title) ? $img->title : (isset($img['title']) ? $img['title'] : '');

		            if (!$url) continue;
		            $file = $mediaPath . $url;
		            if (!is_file($file)) continue;

		            $pdf->Image($file, 120, 110, 85, 0, '', '', 'N');

					// done after the first valid image
					return;
		        }
		    }
		}
	}

	private function renderProductDescription(TCPDF $pdf, $positions, $shop)
	{
		if (!count($positions)) return;

		// find first top-level position
		$first = null;
		foreach ($positions as $p) {
			if ((int)$p->masterid === 0) {
				$first = $p;
				break;
			}
		}
		if (!$first->itemid) return;

		$itemDb = new Shops_Model_DbTable_Item();
		$item   = $itemDb->getItem($first->itemid, $shop['id']);

		if (!$item['shopdescription']) return;

		$pdf->AddPage();
		$pdf->SetFont('freesansb', 'B', 15);
		$title = trim(($first->title ?? ''));
		if ($first->sku) $title = $first->sku.' '.$title;
		$pdf->MultiCell(0, 0, ($title ?: 'Produkt'), 0, 'L', false, 1, '', '');
		$pdf->ln(4);

		$pdf->SetFont('freesans', '', 9);
		// Use the position description as “Produktbeschreibung” (adapt if you pull item->shopdescription elsewhere)
		$desc = trim((string)($item['shopdescription'] ?? ''));
		if ($desc !== '') {
		    $pdf->MultiCell(180, 0, $desc, 0, 'L', false, 1, '', '', true, 0, true);
		}
	}

	/**
	 * Page "Eigenschaften und bereits inkludierte Ausstattung …"
	 * Lists ONLY options whose price == 0 for each top-level position.
	 */
	private function renderIncludedOptions(TCPDF $pdf, $positions, $options, $optionSets)
	{
		if (!count($positions)) return;

		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

		// First: check if there is any included option at all
		$hasIncluded = false;
		foreach ($positions as $p) {
		    if (empty($options[$p->id]) || !empty($p->masterid)) continue;
		    foreach ($optionSets[$p->id] as $set) {
		        foreach ($options[$p->id][$set->id] as $opt) {
		            if ($this->isIncludedOption($opt)) { $hasIncluded = true; break 3; }
		        }
		    }
		}
		if (!$hasIncluded) return;

		// Start a new page
		$pdf->AddPage();

		foreach ($positions as $p) {
		    if (!empty($p->masterid)) continue;              // only main positions
		    if (empty($options[$p->id])) continue;

		    // Collect included options for this position
		    $hasForPos = false;
		    foreach ($optionSets[$p->id] as $set) {
		        foreach ($options[$p->id][$set->id] as $opt) {
		            if ($this->isIncludedOption($opt)) { $hasForPos = true; break 2; }
		        }
		    }
		    if (!$hasForPos) continue;

		    // Section headline per position
		    $pdf->SetFont('freesans', 'B', 13);
		    $pdf->MultiCell(
		        175, 0,
		        'Eigenschaften und bereits inkludierte Ausstattung für ' . ($p->sku ?: '') . ' ' . ($p->title ?: ''),
		        0, 'L', false, 1, '', '', true, 0
		    );

		    // Option sets and included options
		    foreach ($optionSets[$p->id] as $set) {
		        if ($pdf->getY() > 250) $pdf->AddPage();

		        // (optional) set title – original template had this commented out
		        // $pdf->SetFont('freesans', 'B', 10);
		        // $pdf->MultiCell(145, 0, $set->title, 0, 'L', false, 1, '', '', true, 0);

		        $pdf->SetFont('freesans', '', 9);
		        foreach ($options[$p->id][$set->id] as $opt) {
		            if (!$this->isIncludedOption($opt)) continue;

		            // SKU
		            $pdf->MultiCell(15, 0, ($opt->sku ?? ''), 0, 'L', false, 0, '', '', true, 0);
		            // Title (bold)
		            $pdf->SetFont('freesans', 'B', 9);
		            $pdf->MultiCell(130, 0, ($opt->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
		            // Right label
		            $pdf->SetFont('freesans', '', 9);
		            $pdf->MultiCell(30, 0, 'bereits enthalten', 0, 'R', false, 1, 160, '', true, 0);

		            // Description (optional)
		            if (!empty($opt->description)) {
		                $pdf->MultiCell(120, 0, $opt->description, 0, 'L', false, 1, 30, '', true, 0);
		            }
		        }
		    }

		    // Separator
		    $pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY()-2, true, 0);
		    $pdf->setY($pdf->getY() + 2);
		}
	}

	/**
	 * Draws the main positions table (header + rows).
	 * Expects a Traversable/array of positions with fields: id, sku, title, description, price, quantity, uom, image (optional).
	 */
	private function renderPositions(TCPDF $pdf, array $quote, $positions, $options, $optionSets, $attributesByGroup = [])
	{
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$t    = [$view, 'translate'];

		// Settings: show prices, options, etc. (tweak if needed)
		$settings = [
		    'displayPrices'  => 1,
		    'displayDiscounts'  => 0,
		    'displayOptions' => 1,
		];

		if (!count($positions)) return;

		// Start a new page
		$pdf->AddPage();

		$pdf->setCellPaddings(1, 0, 0, 0);
		$pdf->SetFont('freesansb', 'B', 8);

		// Header
		$this->positionsHeader($pdf, $settings, $t);

		$pdf->SetFont('freesans', '', 9);
		$i = 1;

		// Flatten for child handling (if you need parent/child)
		$positionsArray = [];
		$children       = [];
		foreach ($positions as $p) {
		    $positionsArray[$p->id] = $p;
		    $children[$p->masterid][] = $p->id;
		}

		foreach ($positions as $p) {
		    if ((int)$p->masterid !== 0) continue; // only top-level

    		list($descX, $descWidth) = $this->drawPositionRow($pdf, $p, $i, $settings);

		    $i++;

		    // After description: render attributes for this position (if any)
		    if (isset($attributesByGroup[$p->id])) {
        		$this->renderAttributesForPosition($pdf, $attributesByGroup[$p->id], $descX, $descWidth);
		    }

		    // child rows
		    if (!empty($children[$p->id])) {
		        foreach ($children[$p->id] as $childId) {
		            $this->drawPositionRow($pdf, $positionsArray[$childId], $i, $settings, true);
		            $i++;
		        }
		    }

		    // separator
		    $pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY()-2, true, 0);
		    $pdf->ln(1);
		}
	}

	private function renderAttributesForPosition(TCPDF $pdf, array $sets, $xLeft, $width)
	{
		if (!$sets) return;

    	$lineGap = 2;

    	$pdf->setCellPaddings(0, 0, 0, 0); // remove extra padding

		foreach ($sets as $set) {
		    // Top margin before a set
		    $pdf->SetY($pdf->GetY() + 2);

		    $title = $set['title'] ?? '';
		    $desc  = $set['description'] ?? '';
		    $attrs = $set['attributes'] ?? [];

		    if ($title !== '') {
		        $pdf->SetFont('freesans', 'B', 9);
		        $pdf->MultiCell($width, 0, $title, 0, 'L', false, 1, $xLeft, '', true, 0);
		    }

		    if ($desc !== '') {
		        $pdf->SetFont('freesans', '', 9);
		        $pdf->MultiCell($width, 0, $desc, 0, 'L', false, 1, $xLeft, '', true, 0);
		    }

		    if ($attrs) {
		        $pdf->SetFont('freesans', '', 9);
		        foreach ($attrs as $a) {
		            $line = trim(($a['title'] ?? '').($a['description'] ? ': '.$a['description'] : ''));
		            if ($line !== '') {
		                // bullet-like flow inside description column
		                $pdf->MultiCell($width, 4, '– '.$line, 0, 'L', false, 1, $xLeft, '', true, 0);
		            }
		        }
		    }

		    // small gap to next set
		    $pdf->SetY($pdf->GetY() + $lineGap);
		}
	}

	/**
	 * “Optionen” section like your old `options()` function.
	 * Lists ONLY non-included options (price != 0) per position and per option set.
	 */
	private function renderOptions(TCPDF $pdf, $positions, $options, $optionSets)
	{
		if (!count($positions)) return;

		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

		// Make a list of SKUs already present as base positions (to avoid duplicates)
		$baseSkus = [];
		foreach ($positions as $p) $baseSkus[] = $p->sku;

		// Decide if we need a page at all and add the header box
		$needsPage = false;
		foreach ($positions as $p) {
		    if (empty($options[$p->id]) || !empty($p->masterid)) continue;
		    foreach ($optionSets[$p->id] as $set) {
		        foreach ($options[$p->id][$set->id] as $opt) {
		            if (!$this->isIncludedOption($opt) && !$this->isSkuInList($opt->sku ?? '', $baseSkus)) {
		                $needsPage = true; break 3;
		            }
		        }
		    }
		}
		if (!$needsPage) return;

		$pdf->AddPage();
		$pdf->SetFont('freesans', 'B', 9);
		$pdf->setCellPaddings(1, 2, 1, 2);
		$pdf->MultiCell(175, 0, str_repeat('-', 163), 0, 'L', false, 1, '', '', true, 0);
		$pdf->MultiCell(
		    175, 0,
		    "Folgende Ausstattungen und Funktionen die nicht im Angebot enthalten sind können je nach Bedarf und Einsatzzweck der Maschine zusätzlich bestellt werden. Die Basismaschine in der Grundausstattung ist einsatzfähig und betriebsbereit.",
		    0, 'L', false, 1, '', '', true, 0
		);
		$pdf->MultiCell(175, 0, str_repeat('-', 163), 0, 'L', false, 1, '', '', true, 0);
		$pdf->setCellPaddings(1, 1, 1, 1);

		// Now list per position and per set
		foreach ($positions as $p) {
		    if (!empty($p->masterid) || empty($options[$p->id])) continue;

		    $pdf->SetFont('freesans', 'B', 10);
		    $pdf->MultiCell(
		        175, 0,
		        'Optionale Ausstattung für ' . ($p->sku ?: '') . ' ' . ($p->title ?: ''),
		        0, 'L', false, 1, '', '', true, 0
		    );

		    foreach ($optionSets[$p->id] as $set) {
		        if ($pdf->getY() > 250) $pdf->AddPage();

		        // Set heading row: title + “- optional -”
		        $pdf->SetFont('freesans', 'B', 10);
		        $pdf->MultiCell(145, 0, ($set->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
		        $pdf->MultiCell(30, 0, "- optional -", 0, 'R', false, 1, '', '', true, 0);

		        // Set contents
		        $pdf->SetFont('freesans', '', 9);
		        foreach ($options[$p->id][$set->id] as $opt) {
		            // Skip included (0) and duplicates that are actually base positions
		            if ($this->isIncludedOption($opt)) continue;
		            if ($this->isSkuInList($opt->sku ?? '', $baseSkus)) continue;

		            $displayPrice = $this->renderableOptionPrice($opt->price);

		            // SKU
		            $pdf->MultiCell(15, 0, ($opt->sku ?? ''), 0, 'L', false, 0, '', '', true, 0);
		            // Title
		            $pdf->MultiCell(130, 0, ($opt->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
		            // Price (right aligned)
		            $pdf->MultiCell(30, 0, $displayPrice, 0, 'R', false, 1, 160, '', true, 0);

		            // Description
		            if (!empty($opt->description)) {
		                $pdf->MultiCell(120, 0, $opt->description, 0, 'L', false, 1, 30, '', true, 0);
		            }
		        }
		    }

		    // Separator
		    $pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY()-2, true, 0);
		    $pdf->setY($pdf->getY() + 2);
		}
	}

	/**
	 * Totals box + footer page with terms/conditions.
	 * $template may carry a 'footer' HTML block.
	 */
	private function renderTotalsAndFooter(TCPDF $pdf, array $quote, $positions, array $template)
	{
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$t    = [$view, 'translate'];

		// Totals box on the same page as positions end
		$this->totalBox($pdf, $quote, $t);

		// Footer / terms
		$footerHtml = $quote['footer'] ?? ($template['footer'] ?? '');
		if (trim((string)$footerHtml) !== '') {
		    $pdf->AddPage();
		    $pdf->SetFont('freesansb', '', 9);
		    $pdf->MultiCell(165, 0, $footerHtml, 0, 'L', false, 1, '', '', $reseth=true, $stretch=0, $ishtml=true);

		    // Simple section title (like “3. Verkaufsbedingungen” in your sample)
		    $pdf->SetFont('freesansb', 'B', 10);
		    $pdf->MultiCell(0, 0, mb_strtoupper($t('3. Verkaufsbedingungen'), 'UTF-8'), 0, 'L', false, 1, 16, 10);
		    $pdf->ln(4);
		}
	}

	/**
	 * Renders an “images” section where each item image is put on its own page with a caption.
	 * Expects you can fetch media by itemid via Shops model.
	 */
	private function renderImages(TCPDF $pdf, $positions)
	{
		if (!count($positions)) return;

		$shop  = Zend_Registry::get('Shop');
		$view  = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$t     = [$view, 'translate'];

		$mediaDb = new Shops_Model_DbTable_Media();

		// media base path
		$mediaPath = BASE_PATH.'/media/';
		$dir1 = substr($shop['clientid'], 0, 1);
		$dir2 = (strlen($shop['clientid']) > 1) ? substr($shop['clientid'], 1, 1) : '0';
		$mediaPath .= $dir1.'/'.$dir2.'/'.$shop['clientid'].'/images/';

		foreach ($positions as $p) {
		    if ((int)$p->masterid !== 0) continue; // only main items

		    if (!empty($p->itemid)) {
		        $images = $mediaDb->getMedia($p->itemid, 'items', 'item');
		        if (!count($images)) continue;

		        foreach ($images as $img) {
		            $url   = isset($img->url)   ? $img->url   : (isset($img['url']) ? $img['url'] : null);
		            $title = isset($img->title) ? $img->title : (isset($img['title']) ? $img['title'] : '');

		            if (!$url) continue;
		            $file = $mediaPath . $url;
		            if (!is_file($file)) continue;

		            $pdf->AddPage();
		            $pdf->ln(4);
		            $pdf->SetFont('freesansb', 'B', 11);
		            $pdf->MultiCell(108, 0, $title, 0, 'L', false, 1, '', '', true, 0);
		            $pdf->SetFont('freesansb', '', 9);
		            $pdf->MultiCell(108, 0, $t('DOCUMENTS_ILLUSTRATION_SIMILAR'), 0, 'L', false, 1, '', '', true, 0);
		            $pdf->ln(4);
		            $pdf->Image($file, 20, '', 165, 0, '', '', 'N');
		            $pdf->SetFont('freesansb', '', 10);
		        }
		    }
		}
	}

	private function renderTableOfContentsOnCover(TCPDF $pdf, array $quote, array $template, $yStart, array $pages)
	{
		// Compute page numbers (defaults if a section wasn’t added)
		$pProduct  = $pages['product_start']  ?? 2;
		$pOfferS   = $pages['features_start'] ?? 3;
		$pOfferS   = $pages['offer_start']    ?? 4;
		$pOfferE   = $pages['offer_end']      ?? $pOfferS;
		$pTerms    = $pages['terms_start']    ?? ($pOfferE + 1);

		// Options could span included+optional
		$pOptS     = $pages['options_start']  ?? ($pTerms + 1);
		$pOptE     = $pages['options_end']    ?? $pOptS;

		$pImgS     = $pages['images_start']   ?? ($pOptE + 1);
		$pImgE     = $pages['images_end']     ?? $pImgS;

		// Draw on page 1
		$pdf->setPage(1);
		$pdf->SetY($yStart);
		$pdf->SetFont('freesansb', 'B', 12);
		$pdf->MultiCell(100, 0, 'Inhalt des Angebots', 0, 'L', false, 1, 20, '', true, 0);
		$pdf->SetFont('freesans', '', 10);

		$this->tocLine($pdf, '1. Produktbeschreibung',             $pProduct);
		$this->tocLine($pdf, '2. Verkaufsangebot',                  ($pOfferS == $pOfferE) ? $pOfferS : "{$pOfferS}-{$pOfferE}");
		$this->tocLine($pdf, '3. Verkaufsbedingungen',              $pTerms);
		$this->tocLine($pdf, '4. Optionale Ausstattung',            ($pOptS == $pOptE) ? $pOptS : "{$pOptS}-{$pOptE}");
		$this->tocLine($pdf, '5. Bilder und Zeichnungen',           ($pImgS == $pImgE) ? $pImgS : "{$pImgS}-{$pImgE}");
	}

	private function renderHeaderFooter(TCPDF $pdf, $quote, $template, $website)
	{
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$t    = [$view, 'translate'];

		$i = 1;
		$lastPage = $pdf->getNumPages();
		for ($i = 1; $i <= $lastPage; $i++) {
			$pdf->setPage($i);

			// extra info only from page 2 on
			if ($i >= 2) {
				$pdf->SetY(20);
				$this->addPageInfoLine($pdf, $quote, $t);

				// Logo
				$website = !empty($template['website']) ? $template['website'] : ($website ?? '');
				$this->addLogo(150, 10, 45, $website, $template, $pdf);
			}

			// page numbers (manual footer)
			$pdf->SetAutoPageBreak(false);
			$pdf->SetY(284);
			$pdf->SetFont('freesans', 'I', 8);
			$pdf->Cell(0, 10, $t('DOCUMENTS_PAGE').' '.$i.' / '.$lastPage, 0, 0, 'C');
		}
	}

	private function tocLine(TCPDF $pdf, string $label, $pageText)
	{
		$pdf->MultiCell(60, 0, $label, 0, 'L', false, 0, 20, '', true, 0);
		$pdf->MultiCell(30,  0, "(Seite {$pageText})", 0, 'R', false, 1, 80, '', true, 0);
	}

	/* ===========================================================
	 * Small helpers used by the methods above
	 * ===========================================================
	 */

	private function addLogo($x, $y, $width, $website, array $template, TCPDF $pdf)
	{
		if (!empty($template['logo'])) {
		    $logoFile = BASE_PATH.'/files/images/'.$template['logo'];
		    if (is_file($logoFile)) {
		        $pdf->Image($logoFile, $x, $y, $width, 0, '', $website ?: '');
		    }
		}
	}

	private function positionsHeader(TCPDF $pdf, array $settings, callable $t)
	{
		if (!empty($settings['displayPrices'])) {
		    $pdf->Cell(10, 5, $t('DOCUMENTS_POSITION'), 'LTB');
		    $pdf->setCellPaddings(0, 0, 0, 0);
		    $pdf->Cell(98, 5, $t('DOCUMENTS_DESCRIPTION'), 'TB');
		    $pdf->Cell(10, 5, $t('DOCUMENTS_QUANTITY'), 'TB');
		    $pdf->Cell(12, 5, $t('DOCUMENTS_UOM'), 'TB', 0, 'C');
		    $pdf->Cell(25, 5, $t('DOCUMENTS_PRICE_SINGLE'), 'TB', 0, 'C');
		    $pdf->Cell(25, 5, $t('DOCUMENTS_PRICE_TOTAL'), 'TBR', 0, 'C');
		} else {
		    $pdf->Cell(10, 5, $t('DOCUMENTS_POSITION'), 'LTB');
		    $pdf->setCellPaddings(0, 0, 0, 0);
		    $pdf->Cell(25, 5, $t('DOCUMENTS_SKU'), 'TB', 0, 'C');
		    $pdf->Cell(95, 5, $t('DOCUMENTS_DESCRIPTION'), 'TB', 0, 'L');
		    $pdf->Cell(25, 5, $t('DOCUMENTS_QUANTITY'), 'TB', 0, 'C');
		    $pdf->Cell(25, 5, $t('DOCUMENTS_UOM'), 'TBR', 0, 'C');
		}
		$pdf->ln(5);
	}

	private function drawPositionRow(TCPDF $pdf, $p, int $i, array $settings, bool $isChild = false)
	{
		$x = $pdf->GetX();
		$pdf->setCellPaddings(0, 2, 0, 0);
		$pdf->SetFont('freesans', '', 9);

		$title = $p->title ?? '';
		$desc  = $p->description ?? '';
		$sku   = $p->sku ?? '';
		$qty   = $p->quantity ?? 1;
		$uom   = $p->uom ?? '';
		$price = $p->price ?? 0;
		$total = $p->total ?? 0;

		// Defaults used for attributes block
		$descX = $x + 10;
		$descWidth = !empty($settings['displayPrices']) ? 98 : 95;

		if (!empty($settings['displayPrices'])) {
		    // pos / qty / uom / single price / total
		    $pdf->MultiCell(10, 0, $i, 0, 'C', false, 0, $x, '', true, 0);
		    $pdf->MultiCell(10, 0, $qty, 0, 'C', false, 0, $x+108, '', true, 0);
		    if ($uom) $pdf->MultiCell(12, 0, $uom, 0, 'C', false, 0, $x+118, '', true, 0);
		    $pdf->MultiCell(25, 0, $price, 0, 'C', false, 0, $x+130, '', true, 0);
		    $pdf->MultiCell(25, 0, $total, 0, 'C', false, 0, $x+155, '', true, 0);

		    if ($sku)   $pdf->MultiCell(108, 0, $sku,   0, 'L', false, 2, $x+10, '', true, 0);
		    if ($title) $pdf->MultiCell(108, 0, $title, 0, 'L', false, 2, $x+10, '', true, 0);

		    if ($desc)  $pdf->MultiCell(98, 0, $desc, 0, 'L', false, 1, $x+10, '', true, 0);
		} else {
		    // no-price layout
		    $pdf->MultiCell(10, 0, $i, 0, 'C', false, 0, $x, '', true, 0);
		    if ($sku)   $pdf->MultiCell(25, 0, $sku, 0, 'C', false, 0, $x+10, '', true, 0);
		    if ($title) $pdf->MultiCell(95, 0, $title, 0, 'L', false, 0, $x+35, '', true, 0);
		    $pdf->MultiCell(25, 0, $qty, 0, 'C', false, 0, $x+130, '', true, 0);
		    if ($uom)   $pdf->MultiCell(25, 0, $uom, 0, 'C', false, 0, $x+155, '', true, 0);
		    if ($desc) {
		        if ($title) $pdf->ln(4);
		        $pdf->MultiCell(95, 0, $desc, 0, 'L', false, 1, $x+35, $pdf->getY()+2, true, 0);
		    }
		}

		// Return the description column anchor for follow-up content
		return [$descX, $descWidth];
	}

	private function totalBox(TCPDF $pdf, array $quote, callable $t)
	{
		// expects formatted strings already in $quote['subtotal'], ['taxes'], ['total'], ['taxrate']
		$x = 120;
		$y = $pdf->GetY();

		$subtotal = $quote['subtotal'] ?? '';
		$taxrate  = $quote['taxrate']  ?? '';
		$taxes    = $quote['taxes']    ?? '';
		$total    = $quote['total']    ?? '';

		$pdf->SetFont('freesansb', 'B', 10);
		$pdf->setCellPaddings(3, 1, 3, 1);
		$pdf->MultiCell(45, 0, $t('DOCUMENTS_SUBTOTAL'), 'LT', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $subtotal, 'TR', 'R', false, 1);

		$pdf->setCellPaddings(3, 0, 3, 1);
		$pdf->MultiCell(45, 0, $t('DOCUMENTS_TAXES').' ('.$taxrate.'%)', 'L', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $taxes, 'R', 'R', false, 1);

		$pdf->setCellPaddings(3, 1, 3, 1);
		$pdf->MultiCell(45, 0, $t('DOCUMENTS_TOTAL'), 'LBT', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $total, 'RBT', 'R', false, 1);

		// optional prepayment/balance if present
		if (!empty($quote['prepayment']) && !empty($quote['balance'])) {
		    $pdf->MultiCell(45, 0, $t('DOCUMENTS_PREPAYMENT'), 'L', 'L', false, 0 ,$x);
		    $pdf->MultiCell(30, 0, '- '.$quote['prepayment'], 'R', 'R', false, 1);
		    $pdf->MultiCell(45, 0, $t('DOCUMENTS_BALANCE'), 'LB', 'L', false, 0 ,$x);
		    $pdf->MultiCell(30, 0, $quote['balance'], 'RB', 'R', false, 1);
		}

		$pdf->setY($y);
	}

	private function addPageInfoLine(TCPDF $pdf, array $quote, callable $t)
	{
		// Line with quoteId/date/customerId at top of page
		$quoteid   = $quote['quoteid'] ?? '';
		$quotedate = $this->formatDate($quote['quotedate']);

		$pdf->setCellPaddings(1, 1, 0, 0);
		$pdf->SetFont('freesans', '', 8);

		$pdf->Write(0, $t('DOCUMENTS_QUOTE_ID').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($quoteid ?: ' - - - - - ').'   ');

		$pdf->SetFont('freesans', '', 8);
		$pdf->Write(0, $t('DOCUMENTS_QUOTE_DATE').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($quotedate ?: ' - - - - - ').'   ');

		$pdf->SetFont('freesans', '', 8);
		$pdf->Write(0, $t('DOCUMENTS_CUSTOMER_ID').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($quote['contactid'] ?? ''));

		$pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY(), true, 0);
		$pdf->ln();
	}

	private function isIncludedOption($opt): bool
	{
		// In your pipeline, option->price is either:
		//  - float 0/-1/-2, or
		//  - a formatted currency string for positive prices.
		if (!isset($opt->price)) return false;
		if (is_numeric($opt->price)) return ((float)$opt->price === 0.0);
		// just in case someone already mapped label “bereits enthalten”
		$val = trim((string)$opt->price);
		return ($val === '0' || mb_stripos($val, 'bereits enthalten') !== false);
	}

	private function isSkuInList($sku, array $list): bool
	{
		return $sku && array_search($sku, $list, true) !== false;
	}

	private function renderableOptionPrice($val): string
	{
		// Map special codes to German labels like the legacy view
		if (is_numeric($val)) {
		    $f = (float)$val;
		    if ($f === 0.0)  return 'bereits enthalten';
		    if ($f === -1.0) return 'auf Anfrage';
		    if ($f === -2.0) return 'nicht Verfügbar';
		}
		// Already formatted currency string → return as-is
		return (string)$val;
	}

	private function formatDate($date, $format = 'd.m.Y')
	{
		if (empty($date) || $date === '0000-00-00') {
		    return null;
		}
		try {
		    return (new DateTime($date))->format($format);
		} catch (Exception $e) {
		    return null; // fallback for invalid dates
		}
	}
}
