<?php

namespace App\Http\Controllers;

use App\Services\RekapService;
use App\Support\Periode;
use App\Support\StatusKondisi as SK;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh rekap kondisi per D.I. untuk periode terpilih (CSV, dibuka langsung di Excel). */
class RekapController extends Controller
{
    public function unduh(Request $request, RekapService $rekap): StreamedResponse
    {
        $p = Periode::dariRequest($request);
        if (! $p->rekap()) { // "hari ini" → rekap satu hari
            $p = Periode::dariRequest(new Request(['periode' => 'rentang', 'dari' => $p->sampai->toDateString(), 'sampai' => $p->sampai->toDateString()]));
        }
        $r = $rekap->rekap($p, $request->user()->subIdsTerjangkau());
        $nama = 'rekap-irigasi-'.$p->dari->format('Ymd').'-'.$p->sampai->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($r, $p) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Rekap kondisi irigasi BBWS NT II', $p->label, $p->dari->toDateString().' s.d. '.$p->sampai->toDateString(), $r['jumlah_hari'].' hari'], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Kode', 'D.I.', 'Kabupaten', 'Satgas', 'Luas fungsional (Ha)', 'Status terberat', 'Status tersering',
                'Hari kritis', 'Hari siaga', 'Hari waspada', 'Hari aman', 'Hari tanpa laporan', 'Jumlah Sub-D.I.', 'Jumlah laporan', 'Kepatuhan lapor (%)',
                'Puncak luas tanam (Ha)', 'Puncak tanaman kekeringan (Ha)', 'Puncak puso (Ha)', 'Puncak panen (Ha)', 'Debit rata-rata (L/dt)', 'Debit minimum (L/dt)'], ';');
            foreach (collect($r['di'])->sortBy('kode') as $d) {
                fputcsv($out, [
                    $d['kode'], $d['nama'], $d['kabupaten'], $d['satgas'], $d['luas_ha'], SK::label($d['terberat']), SK::label($d['tersering']),
                    $d['hari']['kritis'], $d['hari']['siaga'], $d['hari']['waspada'], $d['hari']['aman'], $d['hari']['kosong'],
                    $d['jumlah_sub'], $d['laporan'], $d['kepatuhan'],
                    $d['puncak_tanam_ha'], $d['puncak_kering_ha'], $d['puncak_puso_ha'], $d['puncak_panen_ha'], $d['debit_rata_lps'], $d['debit_min_lps'],
                ], ';');
            }
            $t = $r['total'];
            fputcsv($out, ['', 'TOTAL', '', '', collect($r['di'])->sum('luas_ha'), '', '',
                $t['hari_di']['kritis'], $t['hari_di']['siaga'], $t['hari_di']['waspada'], $t['hari_di']['aman'], $t['hari_di']['kosong'],
                $t['jumlah_sub'], $t['laporan'], $t['kepatuhan'], $t['puncak_tanam_ha'], $t['puncak_kering_ha'], $t['puncak_puso_ha'], $t['puncak_panen_ha'], '', ''], ';');

            fputcsv($out, [], ';');
            fputcsv($out, ['Kepatuhan lapor per Satgas'], ';');
            fputcsv($out, ['Satgas', 'Cakupan', 'Kabupaten', 'Jumlah Sub-D.I.', 'Jumlah laporan', 'Kepatuhan (%)'], ';');
            foreach ($r['kepatuhan'] as $k) {
                fputcsv($out, [$k['kode'] ? 'Wilayah '.$k['kode'] : 'Belum masuk Satgas', $k['cakupan'], $k['kabupaten'], $k['jumlah_sub'], $k['laporan'], $k['persen']], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Kejadian kekeringan baru dalam periode'], ';');
            fputcsv($out, ['Status penanganan', 'Jumlah lokasi', 'Luas terdampak (Ha)', 'Luas tertangani (Ha)'], ';');
            foreach ($r['kejadian'] as $st => $k) {
                fputcsv($out, [$st, $k['jumlah'], $k['luas'], $k['tertangani']], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Catatan: luas = nilai tertinggi yang dilaporkan tiap Sub-D.I. selama periode lalu dijumlah; status harian memakai laporan yang berumur maksimal 3 hari.'], ';');
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
