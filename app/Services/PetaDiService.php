<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\PetaDi;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengolahan geometri batas D.I.: validasi, luas geodesik, titik tengah,
 * penyederhanaan, dan pembangunan ulang file public/geojson/{lapisan}.geojson.
 */
class PetaDiService
{
    /** Batas wajar wilayah kerja (NTT + sedikit kelonggaran). */
    private const BATAS = ['lng' => [117.5, 126.5], 'lat' => [-12.5, -7.0]];

    private const R = 6378137.0;

    /** Toleransi penyederhanaan untuk file tampilan (derajat; ±15 m). */
    public const TOLERANSI = 0.00015;

    // ------------------------------------------------------------------ baca & validasi

    /**
     * Baca file unggahan (.geojson/.json, atau .zip berisi salah satunya) menjadi daftar feature.
     *
     * @return array<int, array{properties: array, geometry: array}>
     */
    public function bacaFile(string $path, string $namaAsli): array
    {
        $isi = null;
        if (Str::endsWith(Str::lower($namaAsli), '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages(['berkas' => 'File ZIP tidak bisa dibuka.']);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('/\.(geo)?json$/i', $n)) {
                    $isi = $zip->getFromIndex($i);
                    break;
                }
                if (preg_match('/\.shp$/i', $n)) {
                    $zip->close();
                    throw ValidationException::withMessages(['berkas' => 'ZIP berisi shapefile (.shp). Ekspor dulu ke GeoJSON dengan sistem koordinat WGS84 (EPSG:4326), lalu unggah file .geojson-nya.']);
                }
            }
            $zip->close();
            if ($isi === null) {
                throw ValidationException::withMessages(['berkas' => 'Tidak ada file .geojson/.json di dalam ZIP.']);
            }
        } else {
            $isi = file_get_contents($path);
        }

        $json = json_decode($isi, true);
        if (! is_array($json)) {
            throw ValidationException::withMessages(['berkas' => 'Isi file bukan GeoJSON yang valid.']);
        }
        $crs = data_get($json, 'crs.properties.name');
        if ($crs && ! preg_match('/(CRS84|4326)/i', $crs)) {
            throw ValidationException::withMessages(['berkas' => "Sistem koordinat file adalah \"$crs\". Ekspor ulang dengan WGS84 (EPSG:4326)."]);
        }

        $features = match ($json['type'] ?? null) {
            'FeatureCollection' => $json['features'] ?? [],
            'Feature' => [$json],
            'Polygon', 'MultiPolygon' => [['type' => 'Feature', 'properties' => [], 'geometry' => $json]],
            default => throw ValidationException::withMessages(['berkas' => 'GeoJSON harus berupa FeatureCollection, Feature, atau Polygon/MultiPolygon.']),
        };

        $hasil = [];
        foreach ($features as $i => $f) {
            $g = $f['geometry'] ?? null;
            if (! $g || ! in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                continue; // titik/garis diabaikan
            }
            $this->validasiGeometri($g, 'berkas', 'Fitur ke-'.($i + 1).': ');
            $hasil[] = ['properties' => $f['properties'] ?? [], 'geometry' => $this->bersihkan($g)];
        }
        if (! $hasil) {
            throw ValidationException::withMessages(['berkas' => 'Tidak ada poligon di dalam file.']);
        }

        return $hasil;
    }

    /** Pastikan geometri Polygon/MultiPolygon dengan koordinat derajat di sekitar NTT. */
    public function validasiGeometri(array $g, string $field = 'geometri', string $awalan = ''): void
    {
        if (! in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || empty($g['coordinates'])) {
            throw ValidationException::withMessages([$field => $awalan.'Geometri harus berupa poligon.']);
        }
        foreach ($this->semuaRing($g) as $ring) {
            if (count($ring) < 4) {
                throw ValidationException::withMessages([$field => $awalan.'Ada poligon dengan kurang dari 3 titik.']);
            }
            foreach ($ring as $p) {
                [$lng, $lat] = [$p[0] ?? null, $p[1] ?? null];
                if (! is_numeric($lng) || ! is_numeric($lat)) {
                    throw ValidationException::withMessages([$field => $awalan.'Koordinat tidak valid.']);
                }
                if (abs($lng) > 180 || abs($lat) > 90) {
                    throw ValidationException::withMessages([$field => $awalan.'Koordinat masih dalam meter (proyeksi), bukan derajat. Ekspor ulang dengan WGS84 (EPSG:4326).']);
                }
                if ($lng < self::BATAS['lng'][0] || $lng > self::BATAS['lng'][1] || $lat < self::BATAS['lat'][0] || $lat > self::BATAS['lat'][1]) {
                    throw ValidationException::withMessages([$field => $awalan."Koordinat ($lat, $lng) berada di luar wilayah NTT. Periksa urutan bujur/lintang atau sistem koordinat."]);
                }
            }
        }
    }

    /** Buang dimensi Z, bulatkan 7 desimal, tutup ring yang belum tertutup. */
    public function bersihkan(array $g): array
    {
        $ring = function (array $r) {
            $r = array_map(fn ($p) => [round((float) $p[0], 7), round((float) $p[1], 7)], $r);
            if ($r && $r[0] !== end($r)) {
                $r[] = $r[0];
            }

            return $r;
        };
        $poly = fn (array $rings) => array_map($ring, $rings);

        return $g['type'] === 'Polygon'
            ? ['type' => 'Polygon', 'coordinates' => $poly($g['coordinates'])]
            : ['type' => 'MultiPolygon', 'coordinates' => array_map($poly, $g['coordinates'])];
    }

    // ------------------------------------------------------------------ ukuran

    /** Luas geodesik (Ha), metode yang sama dengan Turf.js / Leaflet. */
    public function luasHa(array $g): float
    {
        $total = 0.0;
        foreach ($this->poligon($g) as $rings) {
            foreach ($rings as $i => $r) {
                $a = abs($this->luasRing($r));
                $total += $i === 0 ? $a : -$a; // ring berikutnya = lubang
            }
        }

        return round(max(0, $total) / 10000, 2);
    }

    private function luasRing(array $r): float
    {
        $n = count($r);
        if ($n < 3) {
            return 0;
        }
        $sum = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $r[$i];
            [$x2, $y2] = $r[$i + 1];
            $sum += deg2rad($x2 - $x1) * (2 + sin(deg2rad($y1)) + sin(deg2rad($y2)));
        }

        return $sum * self::R * self::R / 2;
    }

    /** Titik tengah: centroid ring luar terbesar (cukup untuk penanda). */
    public function titikTengah(array $g): array
    {
        $terbesar = null;
        $maks = -1;
        foreach ($this->poligon($g) as $rings) {
            $a = abs($this->luasRing($rings[0]));
            if ($a > $maks) {
                [$maks, $terbesar] = [$a, $rings[0]];
            }
        }
        $cx = $cy = $ar = 0.0;
        $n = count($terbesar);
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $terbesar[$i];
            [$x2, $y2] = $terbesar[$i + 1];
            $f = $x1 * $y2 - $x2 * $y1;
            $cx += ($x1 + $x2) * $f;
            $cy += ($y1 + $y2) * $f;
            $ar += $f;
        }
        if (abs($ar) < 1e-12) {
            return [round($terbesar[0][1], 7), round($terbesar[0][0], 7)];
        }

        return [round($cy / (3 * $ar), 7), round($cx / (3 * $ar), 7)]; // [lat, lng]
    }

    // ------------------------------------------------------------------ simpan & sinkron

    /** Isi luas & titik tengah lalu simpan; sinkronkan D.I. dan file tampilan. */
    public function simpan(PetaDi $peta, array $geometri): PetaDi
    {
        $geometri = $this->bersihkan($geometri);
        [$lat, $lng] = $this->titikTengah($geometri);
        $diLama = $peta->getOriginal('daerah_irigasi_id');
        $peta->fill(['geometri' => $geometri, 'luas_ha' => $this->luasHa($geometri), 'lat' => $lat, 'lng' => $lng])->save();

        $this->sinkronDi($peta->daerah_irigasi_id, $peta->lapisan);
        if ($diLama && $diLama !== $peta->daerah_irigasi_id) {
            $this->sinkronDi($diLama, $peta->lapisan);
        }
        $this->bangunFile($peta->lapisan);

        return $peta;
    }

    /** Perbarui luas_{lapisan}_ha D.I. (dan titik tengahnya dari lapisan fungsional). */
    public function sinkronDi(?int $diId, string $lapisan): void
    {
        $di = $diId ? DaerahIrigasi::find($diId) : null;
        if (! $di) {
            return;
        }
        $daftar = PetaDi::where('daerah_irigasi_id', $di->id)->where('lapisan', $lapisan)->get(['luas_ha', 'lat', 'lng']);
        $di->{"luas_{$lapisan}_ha"} = $daftar->isEmpty() ? null : round($daftar->sum('luas_ha'), 2);
        if ($lapisan === 'fungsional' && $daftar->isNotEmpty()) {
            $utama = $daftar->sortByDesc('luas_ha')->first();
            $di->lat = $utama->lat;
            $di->lng = $utama->lng;
        }
        $di->save();
    }

    /** Bangun ulang file GeoJSON ringan untuk peta (disederhanakan ±15 m). */
    public function bangunFile(string $lapisan): void
    {
        $fitur = PetaDi::with('daerahIrigasi:id,kode,keterangan')->where('lapisan', $lapisan)->orderBy('nama')->get()
            ->map(fn (PetaDi $p) => [
                'type' => 'Feature',
                'properties' => [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'kode' => $p->daerahIrigasi?->kode,
                    'luas_ha' => $p->luas_ha,
                    'keterangan' => $p->daerahIrigasi?->keterangan,
                    'lat' => $p->lat,
                    'lng' => $p->lng,
                ],
                'geometry' => $this->sederhanakan($p->geometri, self::TOLERANSI, 5),
            ])->values()->all();

        File::ensureDirectoryExists(public_path('geojson'));
        File::put(public_path("geojson/$lapisan.geojson"), json_encode(
            ['type' => 'FeatureCollection', 'name' => $lapisan, 'features' => $fitur],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    /** Cocokkan nama dari file ke D.I. (nama, kode, atau alias). */
    public function cariDi(?string $nama): ?DaerahIrigasi
    {
        $k = self::kunci($nama);
        if ($k === '') {
            return null;
        }
        static $semua = null;
        $semua ??= DaerahIrigasi::all(['id', 'kode', 'nama', 'alias']);

        return $semua->first(function ($d) use ($k, $nama) {
            if (Str::lower((string) $nama) === Str::lower($d->kode)) {
                return true;
            }

            return collect($d->alias ?? [])->push($d->nama)
                ->flatMap(fn ($a) => array_merge([$a], preg_split('#\s*/\s*#', $a)))
                ->contains(fn ($a) => self::kunci($a) === $k);
        });
    }

    public static function kunci(?string $nama): string
    {
        $n = Str::lower((string) $nama);
        $n = str_replace(['d.i.', 'd.i', 'di.'], '', $n);
        $n = preg_replace('/^(daerah irigasi|di)\s/', '', trim($n));

        return preg_replace('/[^a-z0-9]/', '', $n);
    }

    // ------------------------------------------------------------------ geometri dasar

    /** @return array<int, array<int, array>> daftar poligon (tiap poligon = daftar ring) */
    public function poligon(array $g): array
    {
        return $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
    }

    private function semuaRing(array $g): array
    {
        return array_merge(...array_map(fn ($p) => $p, $this->poligon($g)));
    }

    /** Douglas–Peucker per ring; ring yang terlalu kecil dibuang (kecuali satu-satunya). */
    public function sederhanakan(array $g, float $tol, int $desimal = 6): array
    {
        $bulat = fn ($r) => array_map(fn ($p) => [round($p[0], $desimal), round($p[1], $desimal)], $r);
        $polys = [];
        foreach ($this->poligon($g) as $rings) {
            $hasil = [];
            foreach ($rings as $i => $r) {
                $s = $this->dp($r, $tol);
                if (count($s) < 4) {
                    if ($i === 0) {
                        $s = $r; // ring luar kecil: pertahankan aslinya
                    } else {
                        continue;
                    }
                }
                $hasil[] = $bulat($s);
            }
            $polys[] = $hasil;
        }

        return count($polys) === 1 ? ['type' => 'Polygon', 'coordinates' => $polys[0]] : ['type' => 'MultiPolygon', 'coordinates' => $polys];
    }

    private function dp(array $pts, float $tol): array
    {
        $n = count($pts);
        if ($n < 3) {
            return $pts;
        }
        $simpan = array_fill(0, $n, false);
        $simpan[0] = $simpan[$n - 1] = true;
        $tumpuk = [[0, $n - 1]];
        $tol2 = $tol * $tol;
        while ($tumpuk) {
            [$a, $b] = array_pop($tumpuk);
            $maks = 0;
            $idx = -1;
            for ($i = $a + 1; $i < $b; $i++) {
                $d = $this->jarak2($pts[$i], $pts[$a], $pts[$b]);
                if ($d > $maks) {
                    [$maks, $idx] = [$d, $i];
                }
            }
            if ($idx >= 0 && $maks > $tol2) {
                $simpan[$idx] = true;
                $tumpuk[] = [$a, $idx];
                $tumpuk[] = [$idx, $b];
            }
        }
        $out = [];
        foreach ($pts as $i => $p) {
            if ($simpan[$i]) {
                $out[] = $p;
            }
        }

        return $out;
    }

    private function jarak2(array $p, array $a, array $b): float
    {
        [$x, $y] = $p;
        [$x1, $y1] = $a;
        [$x2, $y2] = $b;
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        if ($dx == 0 && $dy == 0) {
            return ($x - $x1) ** 2 + ($y - $y1) ** 2;
        }
        $t = max(0, min(1, (($x - $x1) * $dx + ($y - $y1) * $dy) / ($dx * $dx + $dy * $dy)));

        return ($x - ($x1 + $t * $dx)) ** 2 + ($y - ($y1 + $t * $dy)) ** 2;
    }
}
