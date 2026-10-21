<?php

namespace App\Models;

use App\Support\KodeIsian;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kondisi tanaman satu petak tersier dalam laporan PPA (Bagian C format PPA). */
class LaporanPetak extends Model
{
    protected $table = 'laporan_petak';

    protected $fillable = [
        'laporan_harian_id', 'pintu_air_id', 'luas_baku_ha', 'luas_tanam_ha', 'jenis_tanaman', 'fase', 'umur_hst',
        'kecukupan_air', 'luas_kering_ha', 'luas_panen_ha', 'luas_puso_ha', 'keterangan', 'status',
    ];

    protected $casts = [
        'luas_baku_ha' => 'float', 'luas_tanam_ha' => 'float', 'luas_kering_ha' => 'float', 'luas_panen_ha' => 'float',
        'luas_puso_ha' => 'float', 'fase' => 'integer', 'umur_hst' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(fn (LaporanPetak $p) => $p->status = $p->hitungStatus());
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class, 'laporan_harian_id');
    }

    /** Pintu pelayan petak (pintu tersier). */
    public function pintu(): BelongsTo
    {
        return $this->belongsTo(PintuAir::class, 'pintu_air_id');
    }

    public function bera(): bool
    {
        return $this->jenis_tanaman === 'B';
    }

    /** Status dari kode kecukupan air; laporan lama (tanpa kode) memakai kriteria sementara luas kering/puso. */
    public function hitungStatus(): string
    {
        if ($this->kecukupan_air && isset(KodeIsian::STATUS_KECUKUPAN[$this->kecukupan_air])) {
            return KodeIsian::STATUS_KECUKUPAN[$this->kecukupan_air];
        }
        if ($this->bera()) {
            return StatusKondisi::AMAN;
        }

        return StatusKondisi::dariNilai(null, (float) $this->luas_tanam_ha, (float) $this->luas_kering_ha, (float) $this->luas_puso_ha);
    }

    public function label(): string
    {
        return $this->pintu ? $this->pintu->kode.($this->pintu->nama ? ' · '.$this->pintu->nama : '') : 'Seluruh Sub-D.I.';
    }
}
