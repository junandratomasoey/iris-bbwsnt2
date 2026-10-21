<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan disesuaikan dengan Format Pengisian PPA dan Format Pengisian Manager Air.
 *
 * PPA: satu laporan per Sub-D.I. per petugas per tanggal (rutin Senin / insidental), berisi
 *      pembacaan semua pintu (Bagian B) dan kondisi tanaman tiap petak tersier (Bagian C → laporan_petak).
 *      Laporan lama (satu per petak) dipindah menjadi baris petak; laporan lama petugas yang sama
 *      di Sub-D.I. dan tanggal yang sama digabung menjadi satu laporan.
 * Manager: identitas, bangunan utama, titik ukur saluran primer (sebagai pembacaan pintu), permasalahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- PPA ----------
        Schema::table('laporan_harian', function (Blueprint $t) {
            $t->time('jam')->nullable()->after('tanggal');
            $t->string('jenis', 12)->default('rutin')->after('jam');
            $t->string('alasan', 20)->nullable()->after('jenis');
            $t->string('alasan_lain', 150)->nullable()->after('alasan');
            $t->string('cuaca', 15)->nullable()->after('mt');
            $t->string('hujan_7hari', 10)->nullable()->after('cuaca');
            $t->string('tindakan_operasi', 12)->nullable()->after('hujan_7hari');
            $t->string('atas_perintah', 150)->nullable()->after('tindakan_operasi');
            $t->text('usulan')->nullable()->after('kendala');
            $t->json('dokumentasi')->nullable()->after('usulan');
            $t->string('status', 10)->nullable()->after('dokumentasi');
        });

        Schema::create('laporan_petak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('laporan_harian_id')->constrained('laporan_harian')->cascadeOnDelete();
            $t->foreignId('pintu_air_id')->nullable()->constrained('pintu_air')->nullOnDelete(); // null = seluruh Sub-D.I. (belum ada petak)
            $t->decimal('luas_baku_ha', 10, 2)->nullable();
            $t->decimal('luas_tanam_ha', 10, 2)->default(0);
            $t->string('jenis_tanaman', 3)->nullable();   // P, J, K, Pl, B
            $t->unsignedTinyInteger('fase')->nullable();  // 0–6
            $t->unsignedSmallInteger('umur_hst')->nullable();
            $t->string('kecukupan_air', 3)->nullable();   // C, KR, KS, KB, Bj
            $t->decimal('luas_kering_ha', 10, 2)->default(0);
            $t->decimal('luas_panen_ha', 10, 2)->default(0);
            $t->decimal('luas_puso_ha', 10, 2)->default(0); // hanya dari laporan lama
            $t->string('keterangan', 255)->nullable();
            $t->string('status', 10)->nullable();
            $t->timestamps();
            $t->unique(['laporan_harian_id', 'pintu_air_id']);
        });

        Schema::table('pembacaan_pintu', function (Blueprint $t) {
            $t->string('kondisi_pintu', 3)->nullable()->after('bukaan_cm');
            $t->string('kondisi_saluran', 3)->nullable()->after('kondisi_pintu');
            $t->decimal('debit_kebutuhan_lps', 10, 2)->nullable()->after('debit_lps');
        });

        // ---------- Manager ----------
        Schema::table('sesi_pintu', function (Blueprint $t) {
            $t->foreignId('laporan_air_id')->nullable()->after('laporan_harian_id')->constrained('laporan_air')->cascadeOnDelete();
        });
        Schema::table('laporan_air', function (Blueprint $t) {
            $t->time('jam')->nullable()->after('tanggal');
            $t->string('jenis', 12)->default('rutin')->after('jam');
            $t->string('alasan', 20)->nullable()->after('jenis');
            $t->string('alasan_lain', 150)->nullable()->after('alasan');
            $t->string('cuaca', 15)->nullable()->after('mt');
            $t->decimal('curah_hujan_mm', 6, 1)->nullable()->after('cuaca');
            $t->boolean('melimpas')->nullable()->after('tma_sungai_cm');
            $t->decimal('tinggi_limpasan_cm', 8, 2)->nullable()->after('melimpas');
            $t->decimal('bukaan_intake_cm', 8, 2)->nullable()->after('tinggi_limpasan_cm');
            $t->decimal('bukaan_penguras_cm', 8, 2)->nullable()->after('debit_intake_lps');
            $t->string('kondisi_bendung', 3)->nullable()->after('bukaan_penguras_cm');
            $t->string('kondisi_pintu_intake', 3)->nullable()->after('kondisi_bendung');
            $t->string('kondisi_kantong_lumpur', 3)->nullable()->after('kondisi_pintu_intake');
            $t->string('status_pemberian', 12)->nullable()->after('kondisi_kantong_lumpur');
            $t->string('atas_perintah', 150)->nullable()->after('status_pemberian');
            $t->text('usulan')->nullable()->after('catatan');
            $t->json('dokumentasi')->nullable()->after('usulan');
            $t->string('status', 10)->nullable()->after('dokumentasi');
        });
        Schema::table('laporan_air', function (Blueprint $t) {
            $t->decimal('debit_intake_lps', 10, 2)->nullable()->change();
            $t->string('kondisi_saluran', 10)->nullable()->change();
        });
        foreach (['penuh' => 'aman', 'normal' => 'aman', 'kurang' => 'waspada', 'kering' => 'siaga'] as $k => $s) {
            DB::table('laporan_air')->where('kondisi_saluran', $k)->update(['status' => $s]);
        }

        $this->pindahkanLaporanLama();
        // laporan lama dikirim harian: Senin = rutin, hari lain = insidental (format lama)
        foreach (['laporan_harian', 'laporan_air'] as $tabel) {
            DB::table($tabel)->get(['id', 'tanggal'])->each(function ($r) use ($tabel) {
                if (Carbon::parse($r->tanggal)->isMonday()) {
                    return;
                }
                DB::table($tabel)->where('id', $r->id)->update(['jenis' => 'insidental', 'alasan' => 'lainnya', 'alasan_lain' => 'Laporan harian (format lama)']);
            });
        }

        Schema::table('laporan_harian', function (Blueprint $t) {
            $t->dropUnique('laporan_harian_tanggal_sub_petak_unique');
        });
        Schema::table('laporan_harian', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pintu_air_id');
            $t->dropColumn('petak_kunci');
            $t->unique(['tanggal', 'sub_daerah_irigasi_id', 'user_id'], 'laporan_harian_tanggal_sub_user_unique');
        });
    }

    /** Laporan lama (satu per petak) → satu baris laporan_petak; laporan petugas yang sama di Sub-D.I. & tanggal sama digabung. */
    private function pindahkanLaporanLama(): void
    {
        $fase = ['persiapan' => 0, 'vegetatif' => 2, 'generatif' => 4, 'pemasakan' => 5, 'panen' => 6];
        $luasPintu = DB::table('pintu_air')->pluck('luas_layanan_ha', 'id');
        $luasSub = DB::table('sub_daerah_irigasi')->pluck('luas_ha', 'id');
        $morph = 'App\\Models\\LaporanHarian';

        $grup = DB::table('laporan_harian')->orderBy('tanggal')->orderBy('id')->get()
            ->groupBy(fn ($l) => $l->tanggal.'|'.$l->sub_daerah_irigasi_id.'|'.$l->user_id);

        foreach ($grup as $daftar) {
            $induk = $daftar->first();
            $sesiInduk = DB::table('sesi_pintu')->where('laporan_harian_id', $induk->id)->value('id');
            $statusInduk = 'kosong';
            foreach ($daftar as $l) {
                $tanam = (float) $l->luas_tanam_ha;
                $kering = (float) $l->luas_kering_ha;
                $puso = (float) $l->luas_puso_ha;
                $status = $this->statusLama($l->kondisi_saluran, $tanam, $kering, $puso);
                $statusInduk = $this->maks($statusInduk, $status);
                $jenis = $l->fase_tanaman === 'bera' || $tanam <= 0 ? 'B'
                    : ((float) $l->luas_palawija_ha > (float) $l->luas_padi_ha ? 'Pl' : 'P');
                $sudahAda = DB::table('laporan_petak')->where('laporan_harian_id', $induk->id)
                    ->where(fn ($q) => $l->pintu_air_id ? $q->where('pintu_air_id', $l->pintu_air_id) : $q->whereNull('pintu_air_id'))->exists();
                if (! $sudahAda) {
                    DB::table('laporan_petak')->insert([
                        'laporan_harian_id' => $induk->id, 'pintu_air_id' => $l->pintu_air_id,
                        'luas_baku_ha' => $l->pintu_air_id ? ($luasPintu[$l->pintu_air_id] ?? null) : ($luasSub[$l->sub_daerah_irigasi_id] ?? null),
                        'luas_tanam_ha' => $tanam, 'jenis_tanaman' => $jenis, 'fase' => $fase[$l->fase_tanaman] ?? null,
                        'luas_kering_ha' => $kering, 'luas_panen_ha' => (float) $l->luas_panen_ha, 'luas_puso_ha' => $puso,
                        'status' => $status, 'created_at' => $l->created_at, 'updated_at' => $l->updated_at,
                    ]);
                }
                if ($l->id === $induk->id) {
                    continue;
                }
                // gabungkan ke laporan induk: foto, pembacaan pintu, kendala
                DB::table('foto')->where('fotoable_type', $morph)->where('fotoable_id', $l->id)->update(['fotoable_id' => $induk->id]);
                $sesi = DB::table('sesi_pintu')->where('laporan_harian_id', $l->id)->value('id');
                if ($sesi) {
                    if (! $sesiInduk) {
                        DB::table('sesi_pintu')->where('id', $sesi)->update(['laporan_harian_id' => $induk->id]);
                        $sesiInduk = $sesi;
                    } else {
                        $ada = DB::table('pembacaan_pintu')->where('sesi_pintu_id', $sesiInduk)->pluck('pintu_air_id')->all();
                        DB::table('pembacaan_pintu')->where('sesi_pintu_id', $sesi)->whereNotIn('pintu_air_id', $ada)->update(['sesi_pintu_id' => $sesiInduk]);
                        DB::table('foto')->where('fotoable_type', 'App\\Models\\SesiPintu')->where('fotoable_id', $sesi)->update(['fotoable_id' => $sesiInduk]);
                        DB::table('sesi_pintu')->where('id', $sesi)->delete();
                    }
                }
                if ($l->kendala && $l->kendala !== $induk->kendala) {
                    $induk->kendala = trim(($induk->kendala ? $induk->kendala."\n" : '').$l->kendala);
                }
                DB::table('laporan_harian')->where('id', $l->id)->delete();
            }

            // total laporan induk = jumlah semua baris petaknya
            $baris = DB::table('laporan_petak')->where('laporan_harian_id', $induk->id)->get();
            $tanam = $baris->where('jenis_tanaman', '!=', 'B')->sum(fn ($b) => (float) $b->luas_tanam_ha);
            $kering = $baris->sum(fn ($b) => (float) $b->luas_kering_ha);
            $panen = $baris->sum(fn ($b) => (float) $b->luas_panen_ha);
            $ubah = ['status' => $statusInduk, 'kendala' => $induk->kendala];
            if ($daftar->count() > 1) {
                $jumlah = fn ($k) => round($daftar->sum(fn ($l) => (float) $l->$k), 2);
                $ubah += [
                    'luas_tanam_ha' => round($tanam, 2), 'luas_kering_ha' => round($kering, 2), 'luas_panen_ha' => round($panen, 2),
                    'luas_padi_ha' => $jumlah('luas_padi_ha'), 'luas_palawija_ha' => $jumlah('luas_palawija_ha'), 'luas_horti_ha' => $jumlah('luas_horti_ha'),
                    'luas_hijau_ha' => $jumlah('luas_hijau_ha'), 'luas_puso_ha' => $jumlah('luas_puso_ha'),
                ];
            }
            DB::table('laporan_harian')->where('id', $induk->id)->update($ubah);
        }
    }

    /** Kriteria sementara lama (luas kering/puso & kondisi saluran). */
    private function statusLama(?string $kondisi, float $tanam, float $kering, float $puso): string
    {
        $status = match ($kondisi) {
            'kurang' => 'waspada', 'kering' => 'siaga', default => 'aman'
        };
        $rasio = $tanam > 0 ? $kering / $tanam : 0;
        if ($puso > 0 || $rasio >= 0.5) {
            return 'kritis';
        }
        if ($rasio >= 0.25) {
            return $this->maks($status, 'siaga');
        }

        return $rasio >= 0.10 ? $this->maks($status, 'waspada') : $status;
    }

    private function maks(string $a, string $b): string
    {
        $u = ['kosong' => 0, 'aman' => 1, 'waspada' => 2, 'siaga' => 3, 'kritis' => 4];

        return $u[$a] >= $u[$b] ? $a : $b;
    }

    public function down(): void
    {
        // Penggabungan laporan per petak tidak bisa dibalik; kolom dikembalikan tanpa data petak.
        Schema::table('laporan_harian', function (Blueprint $t) {
            $t->dropUnique('laporan_harian_tanggal_sub_user_unique');
        });
        Schema::table('laporan_harian', function (Blueprint $t) {
            $t->foreignId('pintu_air_id')->nullable()->after('sub_daerah_irigasi_id')->constrained('pintu_air')->nullOnDelete();
            $t->unsignedBigInteger('petak_kunci')->default(0)->after('pintu_air_id');
            $t->unique(['tanggal', 'sub_daerah_irigasi_id', 'petak_kunci'], 'laporan_harian_tanggal_sub_petak_unique');
            $t->dropColumn(['jam', 'jenis', 'alasan', 'alasan_lain', 'cuaca', 'hujan_7hari', 'tindakan_operasi', 'atas_perintah', 'usulan', 'dokumentasi', 'status']);
        });
        Schema::dropIfExists('laporan_petak');
        Schema::table('pembacaan_pintu', fn (Blueprint $t) => $t->dropColumn(['kondisi_pintu', 'kondisi_saluran', 'debit_kebutuhan_lps']));
        Schema::table('sesi_pintu', fn (Blueprint $t) => $t->dropConstrainedForeignId('laporan_air_id'));
        Schema::table('laporan_air', function (Blueprint $t) {
            $t->dropColumn(['jam', 'jenis', 'alasan', 'alasan_lain', 'cuaca', 'curah_hujan_mm', 'melimpas', 'tinggi_limpasan_cm', 'bukaan_intake_cm',
                'bukaan_penguras_cm', 'kondisi_bendung', 'kondisi_pintu_intake', 'kondisi_kantong_lumpur', 'status_pemberian', 'atas_perintah', 'usulan', 'dokumentasi', 'status']);
        });
    }
};
