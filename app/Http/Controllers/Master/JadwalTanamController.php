<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\JadwalTanam;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Jadwal MT I/II/III resmi per D.I. dari Bidang OP. */
class JadwalTanamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('lihat-master');
        $tahun = $request->integer('tahun') ?: (int) date('Y');
        $di = DaerahIrigasi::with(['kabupaten:id,nama', 'jadwalTanam' => fn ($q) => $q->where('tahun', $tahun)])->orderBy('kode')->get();
        $tahunList = JadwalTanam::distinct()->orderByDesc('tahun')->pluck('tahun')->push((int) date('Y'))->push((int) date('Y') + 1)->unique()->sortDesc()->values();

        return view('master.jadwal.index', compact('di', 'tahun', 'tahunList'));
    }

    public function create(Request $request)
    {
        $this->authorize('kelola-master');
        $jadwal = new JadwalTanam([
            'daerah_irigasi_id' => $request->integer('di') ?: null,
            'tahun' => $request->integer('tahun') ?: (int) date('Y'),
            'mt' => $request->integer('mt') ?: 1,
            'komoditas' => 'Padi',
            'sumber' => 'Bidang OP',
        ]);

        return view('master.jadwal.form', ['jadwal' => $jadwal, 'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('kelola-master');
        $j = JadwalTanam::create($this->validasi($request));

        return redirect()->route('master.jadwal-tanam.index', ['tahun' => $j->tahun])->with('ok', 'Jadwal tanam disimpan.');
    }

    public function edit(JadwalTanam $jadwal)
    {
        $this->authorize('kelola-master');

        return view('master.jadwal.form', ['jadwal' => $jadwal, 'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama'])]);
    }

    public function update(Request $request, JadwalTanam $jadwal)
    {
        $this->authorize('kelola-master');
        $jadwal->update($this->validasi($request, $jadwal));

        return redirect()->route('master.jadwal-tanam.index', ['tahun' => $jadwal->tahun])->with('ok', 'Jadwal tanam diperbarui.');
    }

    public function destroy(JadwalTanam $jadwal)
    {
        $this->authorize('kelola-master');
        $jadwal->delete();

        return back()->with('ok', 'Jadwal tanam dihapus.');
    }

    private function validasi(Request $request, ?JadwalTanam $j = null): array
    {
        $data = $request->validate([
            'daerah_irigasi_id' => ['required', 'exists:daerah_irigasi,id'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'mt' => ['required', 'integer', 'between:1,3',
                Rule::unique('jadwal_tanam')->where(fn ($q) => $q->where('daerah_irigasi_id', $request->daerah_irigasi_id)->where('tahun', $request->tahun))->ignore($j?->id)],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after:mulai'],
            'komoditas' => ['required', 'string', 'max:50'],
            'rencana_tanam_ha' => ['nullable', 'numeric', 'min:0'],
            'realisasi_tanam_ha' => ['nullable', 'numeric', 'min:0'],
            'puso_ha' => ['nullable', 'numeric', 'min:0'],
            'panen_ha' => ['nullable', 'numeric', 'min:0'],
            'sumber' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], ['mt.unique' => 'Jadwal MT ini untuk D.I. dan tahun yang sama sudah ada.']);

        // cegah tumpang-tindih antar MT dalam satu D.I.
        $tabrakan = JadwalTanam::where('daerah_irigasi_id', $data['daerah_irigasi_id'])
            ->when($j, fn ($q) => $q->where('id', '!=', $j->id))
            ->where('mulai', '<=', $data['selesai'])->where('selesai', '>=', $data['mulai'])->first();
        if ($tabrakan) {
            throw ValidationException::withMessages(['mulai' => "Rentang tanggal bertabrakan dengan {$tabrakan->label} ({$tabrakan->mulai->format('d/m/Y')}–{$tabrakan->selesai->format('d/m/Y')})."]);
        }

        return $data;
    }
}
