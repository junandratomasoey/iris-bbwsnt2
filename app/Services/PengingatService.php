<?php

namespace App\Services;

use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PintuAir;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Support\Role;
use Carbon\Carbon;

/**
 * Siapa yang belum mengirim laporan rutin pekan ini (Senin–Minggu):
 * PPA per Sub-D.I. tugasnya, dan Manager per D.I.
 */
class PengingatService
{
    /**
     * @return array{senin: Carbon, akhir: Carbon, di: array, ringkas: array}
     */
    public function pekan(Carbon $senin, ?array $subIds): array
    {
        $senin = $senin->copy()->startOfWeek();
        $akhir = $senin->copy()->addDays(6)->min(today());
        $rentang = [$senin->toDateString(), $senin->copy()->addDays(6)->toDateString()];

        $subs = SubDaerahIrigasi::with([
            'daerahIrigasi:id,nama',
            'petugas' => fn ($q) => $q->where('role', Role::PETUGAS)->where('is_active', true)->orderBy('name'),
        ])->when($subIds !== null, fn ($q) => $q->whereIn('id', $subIds))->orderBy('nama')->get();
        $diIds = $subs->pluck('daerah_irigasi_id')->unique()->values();

        // laporan PPA pekan ini: per petugas per Sub-D.I.
        $lapPpa = LaporanHarian::whereBetween('tanggal', $rentang)->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))
            ->orderBy('tanggal')->get(['id', 'tanggal', 'sub_daerah_irigasi_id', 'user_id', 'jenis'])
            ->groupBy(fn ($l) => $l->sub_daerah_irigasi_id.':'.$l->user_id);
        // petak tersier tugas utama tiap petugas
        $petak = PintuAir::tersier()->where('aktif', true)->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))
            ->with(['petugas' => fn ($q) => $q->wherePivot('peran', 'utama')])->get(['id', 'kode', 'sub_daerah_irigasi_id']);
        $petakSaya = [];
        foreach ($petak as $p) {
            foreach ($p->petugas as $u) {
                $petakSaya[$p->sub_daerah_irigasi_id.':'.$u->id][] = $p->kode;
            }
        }

        $manager = User::where('role', Role::MANAGER)->where('is_active', true)->whereIn('daerah_irigasi_id', $diIds)->orderBy('name')->get();
        $lapAir = LaporanAir::whereBetween('tanggal', $rentang)->whereIn('daerah_irigasi_id', $diIds)->orderBy('tanggal')
            ->get(['id', 'tanggal', 'daerah_irigasi_id'])->groupBy('daerah_irigasi_id');

        $di = [];
        foreach ($subs->groupBy('daerah_irigasi_id') as $diId => $daftarSub) {
            $nama = $daftarSub->first()->daerahIrigasi->nama;
            $ppa = [];
            foreach ($daftarSub as $s) {
                foreach ($s->petugas as $u) {
                    $kunci = $s->id.':'.$u->id;
                    $l = $lapPpa->get($kunci, collect());
                    $ppa[] = [
                        'user' => $u, 'sub' => $s, 'petak' => $petakSaya[$kunci] ?? [],
                        'laporan' => $l, 'sudah' => $l->isNotEmpty(),
                        'wa' => $l->isEmpty() ? $u->waLink($this->pesanPpa($u, $s, $nama, $senin)) : null,
                    ];
                }
                if ($s->petugas->isEmpty()) {
                    $ppa[] = ['user' => null, 'sub' => $s, 'petak' => [], 'laporan' => collect(), 'sudah' => false, 'wa' => null];
                }
            }
            $air = $lapAir->get($diId, collect());
            $mgr = $manager->where('daerah_irigasi_id', $diId)->values();
            $di[] = [
                'id' => (int) $diId, 'nama' => $nama, 'ppa' => $ppa,
                'manager' => $mgr->map(fn ($m) => ['user' => $m, 'wa' => $air->isEmpty() ? $m->waLink($this->pesanManager($m, (int) $diId, $nama, $senin)) : null])->all(),
                'air' => $air, 'air_sudah' => $air->isNotEmpty(),
                'belum' => collect($ppa)->where('sudah', false)->whereNotNull('user')->count() + ($air->isEmpty() ? 1 : 0),
                'pesan_grup' => $this->pesanGrup($nama, $senin, $ppa, $air->isEmpty(), $mgr),
            ];
        }
        usort($di, fn ($a, $b) => [$b['belum'] > 0, $a['nama']] <=> [$a['belum'] > 0, $b['nama']]);

        $semuaPpa = collect($di)->flatMap(fn ($d) => $d['ppa'])->whereNotNull('user');

        return [
            'senin' => $senin, 'akhir' => $akhir, 'di' => $di,
            'ringkas' => [
                'ppa_total' => $semuaPpa->count(), 'ppa_belum' => $semuaPpa->where('sudah', false)->count(),
                'di_total' => count($di), 'air_belum' => collect($di)->where('air_sudah', false)->count(),
                'sub_tanpa_ppa' => collect($di)->flatMap(fn ($d) => $d['ppa'])->whereNull('user')->count(),
            ],
        ];
    }

    private function tanggal(Carbon $senin): string
    {
        return 'Senin, '.$senin->translatedFormat('d F Y');
    }

    public function pesanPpa(User $u, SubDaerahIrigasi $s, string $di, Carbon $senin): string
    {
        return "Selamat pagi Bapak/Ibu {$u->name}.\n"
            ."Mengingatkan laporan PPA rutin pekan {$this->tanggal($senin)} untuk Sub-D.I. {$s->nama} (D.I. {$di}) belum masuk di IRIS.\n"
            .'Mohon dibaca semua pintu tugas dan diisi kondisi tanaman tiap petak tersier, pukul 07.00–09.00 WITA: '
            .route('laporan.create', ['sub' => $s->id])."\nTerima kasih.";
    }

    public function pesanManager(User $m, int $diId, string $di, Carbon $senin): string
    {
        return "Selamat pagi Bapak/Ibu {$m->name}.\n"
            ."Mengingatkan laporan Manager Air rutin pekan {$this->tanggal($senin)} untuk D.I. {$di} belum masuk di IRIS.\n"
            .'Mohon diisi bangunan utama dan titik ukur saluran primer: '.route('laporan-air.create', ['di' => $diId])
            ."\nTerima kasih.";
    }

    /** Pesan untuk ditempel di grup WhatsApp petugas D.I. */
    private function pesanGrup(string $di, Carbon $senin, array $ppa, bool $airBelum, $manager): string
    {
        $belum = collect($ppa)->where('sudah', false)->whereNotNull('user');
        if ($belum->isEmpty() && ! $airBelum) {
            return "Laporan IRIS D.I. {$di} pekan {$this->tanggal($senin)}: semua sudah masuk. Terima kasih.";
        }
        $baris = $belum->map(fn ($r) => '- '.$r['user']->name.' (Sub-D.I. '.$r['sub']->nama.($r['petak'] ? ', petak '.implode(', ', $r['petak']) : '').')')->values()->all();
        if ($airBelum) {
            $baris[] = '- Laporan Manager Air'.($manager->isNotEmpty() ? ' ('.$manager->pluck('name')->implode(', ').')' : '');
        }

        return "Pengingat laporan IRIS D.I. {$di}, pekan {$this->tanggal($senin)}.\nBelum masuk:\n".implode("\n", $baris)
            ."\nMohon diisi hari ini pukul 07.00–09.00 WITA di ".url('/login').'. Terima kasih.';
    }
}
