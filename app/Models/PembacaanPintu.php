<?php

namespace App\Models;

use App\Casts\Tanggal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembacaanPintu extends Model
{
    protected $table = 'pembacaan_pintu';

    protected $fillable = ['sesi_pintu_id', 'pintu_air_id', 'tanggal', 'debit_lps', 'tma_cm', 'bukaan_cm', 'catatan'];

    protected $casts = ['tanggal' => Tanggal::class, 'debit_lps' => 'float', 'tma_cm' => 'float', 'bukaan_cm' => 'float'];

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SesiPintu::class, 'sesi_pintu_id');
    }

    public function pintu(): BelongsTo
    {
        return $this->belongsTo(PintuAir::class, 'pintu_air_id');
    }
}
