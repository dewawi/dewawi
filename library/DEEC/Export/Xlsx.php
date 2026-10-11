
<?php

class DEEC_Export_Xlsx
{
    public function create(array $sheets, string $period): string
    {
        if(!$sheets) throw new InvalidArgumentException('No worksheets provided.');

        $file = tempnam(sys_get_temp_dir(), 'dewawi-xlsx-');
        if($file === false) throw new RuntimeException('Unable to create XLSX file.');

        try {
            $zip = new ZipArchive();
            if($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to open XLSX archive.');

            try {
                $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
                $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
                $relations = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

                foreach($sheets as $index => $sheet) {
                    $i = $index + 1;
                    $name = (string)$sheet['name'];
                    if($name === '' || strlen($name) > 31 || preg_match('/[\[\]:*?\/\\\\]/', $name)) throw new InvalidArgumentException('Invalid worksheet name.');

                    $types .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                    $workbook .= '<sheet name="'.$this->escape($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
                    $relations .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
                    if(!$zip->addFromString('xl/worksheets/sheet'.$i.'.xml', $this->worksheet($sheet, $period))) throw new RuntimeException('Unable to write worksheet.');
                }

                $types .= '</Types>';
                $workbook .= '</sheets></workbook>';
                $relations .= '<Relationship Id="rId'.(count($sheets)+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

                $zip->addFromString('[Content_Types].xml', $types);
                $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
                $zip->addFromString('xl/workbook.xml', $workbook);
                $zip->addFromString('xl/_rels/workbook.xml.rels', $relations);
                $zip->addFromString('xl/styles.xml', $this->styles());
            } finally {
                $closed = $zip->close();
            }

            if(!$closed) throw new RuntimeException('Unable to finalize XLSX archive.');

            $content = file_get_contents($file);
            if($content === false) throw new RuntimeException('Unable to read XLSX file.');

            return $content;
        } finally {
            unlink($file);
        }
    }

    private function worksheet(array $sheet, string $period): string
    {
        $headers = $sheet['headers'];
        $rows = $sheet['rows'];
        $formats = $sheet['formats'] ?? [];
        $widths = $sheet['widths'] ?? [];
        $lastColumn = $this->column(count($headers));
        $lastRow = count($rows) + 4;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:'.$lastColumn.$lastRow.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="17"/><cols>';

        foreach($headers as $i => $header) $xml .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.($widths[$i] ?? min(46, max(14, strlen((string)$header) + 4))).'" customWidth="1"/>';

        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1" ht="29" customHeight="1">'.$this->cell('A1', $sheet['name'], 5).'</row>';
        $xml .= '<row r="2">'.$this->cell('A2', $period, 6).'</row>';
        $xml .= '<row r="4" ht="22" customHeight="1">';

        foreach($headers as $i => $header) $xml .= $this->cell($this->column($i+1).'4', $header, 1);
        $xml .= '</row>';

        foreach($rows as $index => $row) {
            if(count($row) !== count($headers)) throw new InvalidArgumentException('Worksheet row width mismatch.');

            $r = $index + 5;
            $xml .= '<row r="'.$r.'">';

            foreach(array_values($row) as $i => $value) {
                $style = ['money' => 2, 'integer' => 3, 'percent' => 4][$formats[$i] ?? ''] ?? 0;
                $xml .= $this->cell($this->column($i+1).$r, $value, $style);
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData><autoFilter ref="A4:'.$lastColumn.$lastRow.'"/>';

        if(count($headers) > 1) $xml .= '<mergeCells count="2"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/></mergeCells>';

        return $xml.'</worksheet>';
    }

    private function cell(string $reference, $value, int $style = 0): string
    {
        $cell = '<c r="'.$reference.'" s="'.$style.'"';

        if($value === null || $value === '') return $cell.'/>';
        if(is_int($value) || is_float($value)) return $cell.'><v>'.(is_int($value) ? $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.')).'</v></c>';

        return $cell.' t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string)$value).'</t></is></c>';
    }

    private function column(int $number): string
    {
        $label = '';

        while($number > 0) {
            $number--;
            $label = chr(65 + $number % 26).$label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00 &quot;€&quot;"/><numFmt numFmtId="165" formatCode="0.00%"/></numFmts>'
            .'<fonts count="4"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FF17324D"/><sz val="16"/><name val="Aptos"/></font><font><color rgb="FF64748B"/><sz val="10"/><name val="Aptos"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF17324D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}

