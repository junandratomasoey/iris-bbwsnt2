<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\User;
use Illuminate\Http\Request;

/** Direktori kontak petugas lapangan dengan tombol WhatsApp. */
class PetugasController extends Controller
{
    public function index(Request $request)
    {
        $petugas = User::lapangan()->aktif()
            ->with(['daerahIrigasi:id,nama,kabupaten_id', 'daerahIrigasi.kabupaten:id,nama', 'subDaerahIrigasi:id,nama'])
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('jabatan'), fn ($q) => $q->where('jabatan', $request->input('jabatan')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('no_hp', 'like', '%'.$request->q.'%')))
            ->orderBy('daerah_irigasi_id')->orderByRaw("CASE jabatan WHEN 'Manager' THEN 0 WHEN 'POB' THEN 1 ELSE 2 END")->orderBy('name')
            ->paginate(50)->withQueryString();

        $diList = DaerahIrigasi::orderBy('nama')->get(['id', 'nama']);
        $jabatanList = User::lapangan()->whereNotNull('jabatan')->distinct()->orderBy('jabatan')->pluck('jabatan');

        return view('petugas.index', compact('petugas', 'diList', 'jabatanList'));
    }
}
