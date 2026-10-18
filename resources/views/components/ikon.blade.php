@props(['n'])
@php
$p = [
  'peta' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Zm0 0v14m6-12v14',
  'dashboard' => 'M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-4H4v4Zm10-13h6V4h-6v3Z',
  'laporan' => 'M8 4h8l4 4v12H4V4h4Zm0 0v4h8M8 13h8M8 17h5',
  'kejadian' => 'M12 3 2 20h20L12 3Zm0 6v5m0 3v.5',
  'di' => 'M3 17c2-2 4-2 6 0s4 2 6 0 4-2 6 0M3 12c2-2 4-2 6 0s4 2 6 0 4-2 6 0M12 3v4',
  'bangunan' => 'M3 20h18M5 20V10l7-5 7 5v10M9 20v-5h6v5M3 14h2m14 0h2',
  'jadwal' => 'M5 6h14v14H5zM5 10h14M9 3v4m6-4v4',
  'p3a' => 'M16 19v-1a4 4 0 0 0-8 0v1M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 8v-1a3 3 0 0 0-2-2.8M5 19v-1a3 3 0 0 1 2-2.8',
  'petugas' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
  'pengguna' => 'M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4 20a8 8 0 0 1 16 0M19 8v4m2-2h-4',
  'audit' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  'tambah' => 'M12 5v14M5 12h14',
  'wa' => 'M4 20l1.3-3.9A8 8 0 1 1 8 19L4 20Z',
  'kamera' => 'M4 8h3l2-3h6l2 3h3v11H4V8Zm8 9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
  'lokasi' => 'M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  'unduh' => 'M12 4v11m0 0-4-4m4 4 4-4M5 20h14',
  'keluar' => 'M15 12H4m0 0 4-4m-4 4 4 4M14 4h6v16h-6',
  'menu' => 'M4 6h16M4 12h16M4 18h16',
  'tutup' => 'M6 6l12 12M18 6 6 18',
  'cek' => 'M5 12l5 5 9-10',
  'edit' => 'M4 20h4L19 9l-4-4L4 16v4Z',
  'hapus' => 'M5 7h14M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3',
  'kunci' => 'M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5V11Z',
  'filter' => 'M4 5h16l-6 8v6l-4 1v-7L4 5Z',
  'luar' => 'M14 4h6v6m0-6-9 9M18 14v6H4V6h6',
];
@endphp
<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $p[$n] ?? '' }}"/></svg>
