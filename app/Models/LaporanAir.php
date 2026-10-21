<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use App\Support\KodeIsian;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Laporan Manager Air (Format Pengisian Manager Air): bangunan utama (bendung/intake) dan titik ukur saluran primer.
 * Rutin tiap Senin atau insidental; satu per D.I. per tanggal. Titik ukur primer disimpan sebagai pembacaan pintu (sesiPintu).
 */
class LaporanAir extends Model
{
    use LogsActivity;

    protected $table = 'laporan_air';

    public const MAKS_FOTO = 5;

    /** Laporan boleh diubah pengisinya sampai sekian hari setelah tanggal laporan. */
    public const BATAS_UBAH_HARI = 7;

    protected $fillable = [
        'tanggal', 'jam', 'jenis', 'alasan', 'alasan_lain', 'daerah_irigasi_id', 'user_id', 'mt', 'cuaca', 'curah_hujan_mm',
        'debit_sungai_m3s', 'tma_sungai_cm', 'melimpas', 'tinggi_limpasan_cm', 'bukaan_intake_cm', 'tma_intake_cm', 'debit_intake_lps',
        'bukaan_penguras_cm', 'kondisi_bendung', 'kondisi_pintu_intake', 'kondisi_kantong_lumpur', 'status_pemberian', 'atas_perintah',
        'kondisi_saluran', 'bukaan_pintu_persen', 'catatan', 'usulan', 'dokumentasi', 'status', 'lat', 'lng',
    ];

    protected $casts = [
        'tanggal' => Tanggal::class, 'lat' => 'float', 'lng' => 'float', 'melimpas' => 'boolean', 'dokumentasi' => 'array',
        'debit_sungai_m3s' => 'float', 'tma_sungai_cm' => 'float', 'debit_intake_lps' => 'float', 'tma_intake_cm' => 'float',
        'tinggi_limpasan_cm' => 'float', 'bukaan_intake_cm' => 'float', 'bukaan_penguras_cm' => 'float', 'curah_hujan_mm' => 'float',
    ];

    protected static function booted(): void
    {
        static::deleting(function (LaporanAir $l) {
            $l->foto()->get()->each->delete();
            $l->sesiPintu()->get()->each->delete();
        });
    }

    /** Pembacaan intake & titik ukur saluran primer (Bagian B–C). */
    public function sesiPintu(): HasOne
    {
        return $this->hasOne(SesiPintu::class);
    }

    /**
     * Status dari kondisi tiap titik ukur primer (kriteria sementara): K → Siaga; S/Sp/Bc → Waspada; N → Aman.
     * Laporan lama tanpa titik ukur memakai kondisi saluran umum.
     */
    public function hitungStatus(): string
    {
        $kode = $this->sesiPintu()->first()?->pembacaan()->pluck('kondisi_saluran')->filter() ?? collect();
        if ($kode->isNotEmpty()) {
            return $kode->reduce(fn ($s, $k) => StatusKondisi::maks($s, KodeIsian::STATUS_SALURAN[$k] ?? StatusKondisi::AMAN), StatusKondisi::AMAN);
        }

        return $this->kondisi_saluran ? StatusKondisi::dariSaluran($this->kondisi_saluran) : StatusKondisi::AMAN;
    }

    /** "Saluran: 1 kering, 2 sedimentasi · Pemberian rotasi" */
    public function ringkasKondisi(): ?string
    {
        if (! $this->getAttributeValue('status') && $this->kondisi_saluran) {
            return LaporanHarian::KONDISI[$this->kondisi_saluran] ?? $this->kondisi_saluran;
        }
        $hitung = $this->sesiPintu?->pembacaan->pluck('kondisi_saluran')->filter()->countBy() ?? collect();
        $teks = $hitung->except('N')->map(fn ($n, $k) => $n.' '.mb_strtolower(KodeIsian::KONDISI_SALURAN[$k] ?? $k))->implode(', ');
        $bagian = array_filter([
            $hitung->isEmpty() ? null : 'Saluran primer: '.($teks ?: 'normal'),
            KodeIsian::PEMBERIAN_AIR[$this->status_pemberian] ?? null,
        ]);

        return $bagian ? implode(' · ', $bagian) : null;
    }

    public function getJenisLabelAttribute(): string
    {
        $alasan = $this->alasan === 'lainnya' ? $this->alasan_lain : (KodeIsian::ALASAN_MANAGER[$this->alasan] ?? null);

        return (KodeIsian::JENIS_LAPOR[$this->jenis] ?? 'Rutin').($this->jenis === 'insidental' && $alasan ? ' · '.$alasan : '');
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function status(): string
    {
        return $this->getAttributeValue('status') ?: ($this->kondisi_saluran ? StatusKondisi::dariSaluran($this->kondisi_saluran) : StatusKondisi::AMAN);
    }

    public function terkunci(): bool
    {
        return $this->tanggal->copy()->addDays(self::BATAS_UBAH_HARI)->lt(today());
    }
}
