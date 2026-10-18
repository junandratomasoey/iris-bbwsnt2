<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LaporanHarian extends Model
{
    use LogsActivity;

    protected $table = 'laporan_harian';

    public const KONDISI = ['penuh' => 'Penuh', 'normal' => 'Normal', 'kurang' => 'Kurang', 'kering' => 'Kering'];

    public const FASE = [
        'bera' => 'Bera / tidak ditanam', 'persiapan' => 'Persiapan lahan', 'vegetatif' => 'Vegetatif',
        'generatif' => 'Generatif (bunting–berbunga)', 'pemasakan' => 'Pemasakan', 'panen' => 'Panen',
    ];

    public const VERIFIKASI = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'perbaikan' => 'Perlu perbaikan'];

    protected $fillable = [
        'tanggal', 'sub_daerah_irigasi_id', 'user_id', 'mt', 'debit_intake_lps', 'tma_cm', 'kondisi_saluran',
        'bukaan_pintu_persen', 'fase_tanaman', 'luas_tanam_ha', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha',
        'luas_puso_ha', 'kendala', 'lat', 'lng', 'status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected $casts = [
        'tanggal' => Tanggal::class,
        'diverifikasi_pada' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function status(): string
    {
        return StatusKondisi::dariLaporan($this);
    }

    public function terkunci(): bool
    {
        return $this->status_verifikasi === 'disetujui';
    }
}
