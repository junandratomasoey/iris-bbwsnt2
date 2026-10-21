<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Penulis & pembaca file Excel (.xlsx) sederhana tanpa pustaka tambahan (cukup ekstensi zip PHP).
 * Penulis: beberapa lembar, baris judul tebal, lebar kolom, kunci baris judul, daftar pilihan (data validation).
 * Pembaca: satu lembar (berdasarkan nama atau lembar pertama), nilai teks/angka.
 */
final class Xlsx
{
    /**
     * @param  array<int, array{nama: string, baris: array<int, array<int, mixed>>, lebar?: array<int, float>, judul?: bool, pilihan?: array<string, string>}>  $lembar
     *                                                                                                                                                                   pilihan: ['C2:C500' => "'Daftar'!$A$2:$A$5"] atau ['O2:O500' => '"Ya,Tidak"']
     */
    public static function tulis(string $path, array $lembar): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat file Excel.');
        }
        $n = count($lembar);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $sheets = '';
        $rels = '';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheets .= '<sheet name="'.self::e($lembar[$i - 1]['nama']).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('[Content_Types].xml', $ct.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        // gaya: 0 biasa, 1 judul tebal berlatar, 2 teks dibungkus
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F5E6E"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        foreach ($lembar as $i => $l) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', self::lembar($l));
        }
        $zip->close();
    }

    private static function lembar(array $l): string
    {
        $judul = $l['judul'] ?? true;
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if ($judul) {
            $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }
        if (! empty($l['lebar'])) {
            $x .= '<cols>';
            foreach ($l['lebar'] as $c => $w) {
                $x .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$w.'" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach (array_values($l['baris']) as $r => $baris) {
            $x .= '<row r="'.($r + 1).'"'.($judul && $r === 0 ? ' ht="32" customHeight="1"' : '').'>';
            foreach (array_values($baris) as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $ref = self::kolom($c).($r + 1);
                $s = $judul && $r === 0 ? ' s="1"' : (($l['bungkus'] ?? false) ? ' s="2"' : '');
                if (is_int($v) || is_float($v)) {
                    $x .= '<c r="'.$ref.'"'.$s.'><v>'.$v.'</v></c>';
                } else {
                    $x .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::e((string) $v).'</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (! empty($l['pilihan'])) {
            $x .= '<dataValidations count="'.count($l['pilihan']).'">';
            foreach ($l['pilihan'] as $rentang => $rumus) {
                $x .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="'.$rentang.'"><formula1>'.self::e($rumus).'</formula1></dataValidation>';
            }
            $x .= '</dataValidations>';
        }

        return $x.'</worksheet>';
    }

    /**
     * Baca satu lembar. @return array<int, array<int, string>> baris (mulai 1) → kolom (mulai 0) → nilai teks
     */
    public static function baca(string $path, ?string $namaLembar = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan Excel (.xlsx) yang sah.');
        }
        $wb = $zip->getFromName('xl/workbook.xml');
        $wbRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $wbRels === false) {
            throw new RuntimeException('File bukan Excel (.xlsx) yang sah.');
        }
        $wbX = self::xml($wb);
        $relX = self::xml($wbRels);
        $target = null;
        $pertama = null;
        foreach ($wbX->sheets->sheet as $sh) {
            $rid = (string) $sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($relX->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $t = ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
                    $pertama ??= $t;
                    if ($namaLembar && strcasecmp(trim((string) $sh['name']), $namaLembar) === 0) {
                        $target = $t;
                    }
                }
            }
        }
        $target ??= $pertama;
        $isi = $target ? $zip->getFromName('xl/'.$target) : false;
        if ($isi === false) {
            throw new RuntimeException('Lembar data tidak ditemukan di file Excel.');
        }
        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($ss)->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $zip->close();

        $hasil = [];
        foreach (self::xml($isi)->sheetData->row as $row) {
            $r = (int) $row['r'];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m);
                $kol = self::indeks($m[1] ?? 'A');
                $t = (string) $c['t'];
                $v = match ($t) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => isset($c->is->t) ? (string) $c->is->t : implode('', array_map(fn ($x) => (string) $x->t, iterator_to_array($c->is->r ?? [], false))),
                    'b' => ((string) $c->v) === '1' ? 'Ya' : 'Tidak',
                    default => (string) $c->v,
                };
                if ($t === '' && is_numeric($v)) { // angka dari Excel: buang ekor biner (mis. 12.300000000000001)
                    $v = (string) round((float) $v, 6);
                }
                $hasil[$r][$kol] = trim($v);
            }
        }

        return $hasil;
    }

    public static function kolom(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function indeks(string $huruf): int
    {
        $n = 0;
        foreach (str_split($huruf) as $h) {
            $n = $n * 26 + (ord($h) - 64);
        }

        return $n - 1;
    }

    private static function xml(string $s): \SimpleXMLElement
    {
        $x = simplexml_load_string($s, \SimpleXMLElement::class, LIBXML_NONET);
        if ($x === false) {
            throw new RuntimeException('Isi file Excel tidak bisa dibaca.');
        }

        return $x;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
