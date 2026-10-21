<?php

namespace App\Http\Controllers;

use App\Services\PengingatService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Daftar pengingat laporan rutin Senin: siapa yang belum melapor pekan ini, dengan tombol WhatsApp. */
class PengingatController extends Controller
{
    public function __invoke(Request $request, PengingatService $svc)
    {
        abort_unless($request->user()->can('lihat-petugas'), 403);
        $senin = rescue(fn () => Carbon::parse((string) $request->input('pekan'))->startOfWeek(), today()->startOfWeek(), false);
        if ($senin->gt(today())) {
            $senin = today()->startOfWeek();
        }

        return view('pengingat.index', $svc->pekan($senin, $request->user()->subIdsTerjangkau()) + [
            'hanyaBelum' => $request->boolean('belum', true),
        ]);
    }
}
