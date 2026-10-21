<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Support\KodeIsian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembacaanPintu extends Model
{
    protected $table = 'pembacaan_pintu';

    protected $fillable = ['sesi_pintu_id', 'pintu_air_id', 'tanggal', 'debit_lps', 'debit_kebutuhan_lps', 'tma_cm', 'bukaan_cm', 'kondisi_pintu', 'kondisi_saluran', 'catatan'];

    protected $casts = ['tanggal' => Tanggal::class, 'debit_lps' => 'float', 'debit_kebutuhan_lps' => 'float', 'tma_cm' => 'float', 'bukaan_cm' => 'float'];

    /** Debit terukur − debit kebutuhan (titik ukur saluran primer). */
    public function selisih(): ?float
    {
        return $this->debit_lps !== null && $this->debit_kebutuhan_lps !== null ? round($this->debit_lps - $this->debit_kebutuhan_lps, 2) : null;
    }

    public function bermasalah(): bool
    {
        return KodeIsian::pintuBermasalah($this->kondisi_pintu, $this->kondisi_saluran);
    }

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SesiPintu::class, 'sesi_pintu_id');
    }

    public function pintu(): BelongsTo
    {
        return $this->belongsTo(PintuAir::class, 'pintu_air_id');
    }
}
