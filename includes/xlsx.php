<?php
if (!defined('ABSPATH')) exit;

/**
 * Pieni Excel (.xlsx) -kirjoitin ilman ulkoisia kirjastoja tai PHP:n zip-laajennusta.
 * Tukee useita välilehtiä, tekstiä, lukuja, päivämääriä, euroja, kaavoja, värejä,
 * kiinnitettyä otsikkoriviä ja suodattimia.
 *
 * Solu voi olla tavallinen arvo (merkkijono → teksti, int/float → luku) tai taulukko
 * array('v' => arvo, 't' => 's|n|d|f', 's' => tyyli), ks. apufunktiot alla.
 */
class JASREK_Xlsx {

    // Tyylit (cellXfs-indeksit styles.xml:ssä)
    const S_DEFAULT = 0, S_HEADER = 1, S_MONEY = 2, S_DATE = 3, S_OK = 4, S_ERR = 5,
          S_BOLD = 6, S_BOLD_MONEY = 7, S_WARN = 8, S_TEXT = 9;

    private $sheets = array();

    public static function money($v)          { return array('v' => (float) $v, 't' => 'n', 's' => self::S_MONEY); }
    public static function date($ymd)         { return array('v' => $ymd, 't' => 'd', 's' => self::S_DATE); }
    public static function text($v, $s = 9)   { return array('v' => (string) $v, 't' => 's', 's' => $s); }
    public static function formula($f, $s = 0) { return array('v' => $f, 't' => 'f', 's' => $s); }

    /**
     * @param string $name   Välilehden nimi (lyhennetään ja siistitään Excelin sääntöjen mukaan)
     * @param array  $header Otsikkorivi
     * @param array  $rows   Rivit
     * @param array  $widths Sarakeleveydet merkkeinä
     * @param array|null $totals Viimeinen (yhteensä)rivi, tai null
     */
    public function add_sheet($name, $header, $rows, $widths = array(), $totals = null) {
        $this->sheets[] = array(
            'name'   => $this->unique_name($name),
            'header' => $header,
            'rows'   => $rows,
            'widths' => $widths,
            'totals' => $totals,
        );
    }

    private function unique_name($name) {
        $name = trim(preg_replace('/[\[\]\:\*\?\/\\\\]/u', ' ', (string) $name));
        if ($name === '') $name = 'Taulukko';
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
        $base = $name; $i = 2;
        $lower = 'jasrek_lower';
        $taken = array_map(function ($s) use ($lower) { return $lower($s['name']); }, $this->sheets);
        while (in_array($lower($name), $taken, true)) {
            $suffix = ' (' . $i++ . ')';
            $name = (function_exists('mb_substr') ? mb_substr($base, 0, 31 - strlen($suffix)) : substr($base, 0, 31 - strlen($suffix))) . $suffix;
        }
        return $name;
    }

    private static function esc($s) {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function col($i) {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private static function cell($ref, $c, $default_style = 0) {
        if (!is_array($c)) {
            if ($c === null || $c === '') return '';
            $c = (is_int($c) || is_float($c)) ? array('v' => $c, 't' => 'n') : array('v' => (string) $c, 't' => 's');
        }
        $s = isset($c['s']) ? (int) $c['s'] : $default_style;
        $sa = $s ? ' s="' . $s . '"' : '';
        $v = $c['v'];
        switch ($c['t']) {
            case 'n':
                if ($v === null || $v === '') return $s ? '<c r="' . $ref . '"' . $sa . '/>' : '';
                return '<c r="' . $ref . '"' . $sa . '><v>' . (0 + $v) . '</v></c>';
            case 'd':
                $ts = $v ? strtotime(substr($v, 0, 10) . ' 00:00:00 UTC') : false;
                if (!$ts) return '';
                return '<c r="' . $ref . '"' . $sa . '><v>' . (intdiv($ts, 86400) + 25569) . '</v></c>';
            case 'f':
                return '<c r="' . $ref . '"' . $sa . '><f>' . self::esc($v) . '</f></c>';
            default:
                if ((string) $v === '') return $s ? '<c r="' . $ref . '"' . $sa . '/>' : '';
                return '<c r="' . $ref . '"' . $sa . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc($v) . '</t></is></c>';
        }
    }

    private function sheet_xml($sh) {
        $ncol = count($sh['header']);
        $last = self::col(max(0, $ncol - 1));
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
           . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
           . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
           . '<sheetFormatPr defaultRowHeight="15"/>';
        if ($sh['widths']) {
            $x .= '<cols>';
            foreach ($sh['widths'] as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        $r = 1;
        $x .= '<row r="1">';
        foreach ($sh['header'] as $i => $h) $x .= self::cell(self::col($i) . '1', self::text($h, self::S_HEADER));
        $x .= '</row>';
        foreach ($sh['rows'] as $row) {
            $r++;
            $x .= '<row r="' . $r . '">';
            foreach (array_values($row) as $i => $c) $x .= self::cell(self::col($i) . $r, $c);
            $x .= '</row>';
        }
        $data_last = $r;
        if ($sh['totals'] !== null && $data_last > 1) {
            $r++;
            $x .= '<row r="' . $r . '">';
            foreach (array_values($sh['totals']) as $i => $c) {
                if (is_array($c) && $c['t'] === 'f') $c['v'] = str_replace('{last}', (string) $data_last, $c['v']);
                $x .= self::cell(self::col($i) . $r, $c, self::S_BOLD);
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($data_last > 1) $x .= '<autoFilter ref="A1:' . $last . $data_last . '"/>';
        $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
        return $x;
    }

    private function styles_xml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00\ &quot;€&quot;"/><numFmt numFmtId="165" formatCode="d.m.yyyy"/></numFmts>'
            . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D3557"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4EA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFDECEA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFDEBD8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                   // 0 oletus
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'        // 1 otsikko
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'            // 2 euro
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'            // 3 päivämäärä
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'                      // 4 vihreä
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"/>'                      // 5 punainen
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                      // 6 lihavoitu
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>' // 7 lihavoitu euro
            . '<xf numFmtId="0" fontId="0" fillId="5" borderId="0" xfId="0" applyFill="1"/>'                      // 8 oranssi
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'             // 9 teksti
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Palauttaa .xlsx-tiedoston sisällön. */
    public function build() {
        if (!$this->sheets) $this->add_sheet('Tyhjä', array(''), array());
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb_sheets = ''; $wb_rels = '';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb_sheets .= '<sheet name="' . self::esc($this->sheets[$i - 1]['name']) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $wb_rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $ct .= '</Types>';
        $wb_rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $files = array(
            '[Content_Types].xml' => $ct,
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<bookViews><workbookView/></bookViews><sheets>' . $wb_sheets . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wb_rels . '</Relationships>',
            'xl/styles.xml' => $this->styles_xml(),
        );
        foreach ($this->sheets as $i => $sh) $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->sheet_xml($sh);
        return self::zip($files);
    }

    /** Yksinkertainen ZIP-kirjoitin (deflate), ei vaadi ZipArchive-laajennusta. */
    private static function zip($files) {
        $data = ''; $central = ''; $offset = 0; $count = 0;
        $dostime = 0; $dosdate = (2026 - 1980) << 9 | 1 << 5 | 1;
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $comp = gzdeflate($content, 6);
            $method = 8;
            if ($comp === false || strlen($comp) >= strlen($content)) { $comp = $content; $method = 0; }
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $dostime, $dosdate, $crc, strlen($comp), strlen($content), strlen($name), 0) . $name;
            $data .= $local . $comp;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $dostime, $dosdate, $crc, strlen($comp), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local) + strlen($comp);
            $count++;
        }
        return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
    }
}
