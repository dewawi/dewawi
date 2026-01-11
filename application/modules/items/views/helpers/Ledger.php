<?php
/**
* Class inserts necessary code for Ledger
*/
class Zend_View_Helper_Ledger extends Zend_View_Helper_Abstract{

	public function Ledger() {
        $v = $this->view;
        $ledgerList = $v->ledger ?? [];

        // no data
        if (!is_array($ledgerList) || count($ledgerList) === 0) {
            return
		    	'<div id="messages"><ul><li>'
		    	. $v->escape($v->translate('Es sind noch keine Buchungen vorhanden.'))
		    	. '</li></ul></div>';
        }

        $h  = '<h4>' . $v->escape($v->translate('Alle Buchungen im Verlauf')) . '</h4>';
        $h .= '<table id="data">';
        $h .= '<thead><tr>';
        $h .= '<th id="id">' . $v->escape($v->translate('ITEMS_LEDGER_ID')) . '</th>';
        $h .= '<th id="document">' . $v->escape($v->translate('ITEMS_LEDGER_DOCUMENT')) . '</th>';
        $h .= '<th id="comment">' . $v->escape($v->translate('ITEMS_LEDGER_COMMENT')) . '</th>';
        $h .= '<th id="quantity">' . $v->escape($v->translate('ITEMS_LEDGER_QUANTITY')) . '</th>';
        $h .= '<th id="type">' . $v->escape($v->translate('ITEMS_LEDGER_TYPE')) . '</th>';
        $h .= '<th id="contactid">' . $v->escape($v->translate('CONTACTS_CONTACT_ID')) . '</th>';
        $h .= '<th id="ledgerdate">'. $v->escape($v->translate('ITEMS_LEDGER_DATE')) . '</th>';
        $h .= '<th id="price">' . $v->escape($v->translate('ITEMS_LEDGER_PRICE')) . '</th>';
        $h .= '<th id="uom">' . $v->escape($v->translate('ITEMS_LEDGER_UOM')) . '</th>';
        $h .= '<th class="buttons"></th>';
        $h .= '</tr></thead>';

        $h .= '<tbody>';

        foreach ($ledgerList as $ledger) {
            $id         = $v->escape($ledger->id ?? '');
            $doctype    = (string)($ledger->doctype ?? '');
            $docid      = $ledger->docid ?? null;

            $comment    = $v->escape($ledger->comment ?? '');
            $quantity   = $v->escape($ledger->quantity ?? '');
            $type       = (string)($ledger->type ?? '');
            $contactid  = $v->escape($ledger->contactid ?? '');
            $ledgerdate = (string)($ledger->ledgerdate ?? '');
            $price      = $v->escape($ledger->price ?? '');
            $uom        = $v->escape($ledger->uom ?? '');

            // document link
            $docHtml = '';
            if ($doctype === 'invoice') {
                $url = $v->url(['module' => 'sales', 'controller' => 'invoice', 'action' => 'view', 'id' => $docid]);
                $invoiceid = $v->escape($ledger->invoiceid ?? '');
                $docHtml = '<a href="' . $v->escape($url) . '">'
                	. $v->escape($v->translate('INVOICE')) . ' ' . $invoiceid
                	. '</a>';
            } elseif ($doctype === 'creditnote') {
                $url = $v->url(['module' => 'sales', 'controller' => 'creditnote', 'action' => 'view', 'id' => $docid]);
                $creditnoteid = $v->escape($ledger->creditnoteid ?? '');
                $docHtml = '<a href="' . $v->escape($url) . '">'
                	. $v->escape($v->translate('CREDIT_NOTE')) . ' ' . $creditnoteid
                	. '</a>';
            }

            // type label
            $typeLabel = '';
            if ($type === 'inflow')  $typeLabel = $v->escape($v->translate('ITEMS_LEDGER_INFLOW'));
            if ($type === 'outflow') $typeLabel = $v->escape($v->translate('ITEMS_LEDGER_OUTFLOW'));

            // contact link
            $contactUrl = $v->url(['module' => 'contacts', 'controller' => 'contact', 'action' => 'edit', 'id' => ($ledger->contactid ?? null)]);
            $contactHtml = '<a href="' . $v->escape($contactUrl) . '">' . $contactid . '</a>';

            // date formatting
            $dateOut = '';
            if ($ledgerdate !== '') {
                $ts = strtotime($ledgerdate);
                if ($ts !== false) $dateOut = $v->escape(date('d.m.Y', $ts));
            }

            $h .= '<tr>';

            $h .= '<td id="id">'
            	. '<input class="id" type="hidden" value="' . $id . '" name="id"/>'
            	. '<input class="controller" type="hidden" value="ledger" name="controller"/>'
            	. '<input class="module" type="hidden" value="items" name="module"/>'
            	. $id
            	. '</td>';

            $h .= '<td id="document">' . $docHtml . '</td>';
            $h .= '<td id="comment">' . $comment . '</td>';
            $h .= '<td id="quantity">' . $quantity . '</td>';
            $h .= '<td id="type">' . $typeLabel . '</td>';
            $h .= '<td id="contactid">' . $contactHtml . '</td>';
            $h .= '<td id="ledgerdate">' . $dateOut . '</td>';
            $h .= '<td id="price">' . $price . '</td>';
            $h .= '<td id="uom">' . ($uom !== '' ? $uom : '') . '</td>';
            $h .= '<td class="buttons"></td>';

            $h .= '</tr>';
        }

        $h .= '</tbody></table>';

        return $h;
	}
}
