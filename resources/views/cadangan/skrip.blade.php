@verbatim
# Ambil cadangan database IRIS-BBWSNTII terbaru ke laptop / NAS kantor.
# 1. Isi $Token dengan nilai CADANGAN_TOKEN dari file .env di server.
# 2. Ubah $Tujuan ke folder tujuan (mis. folder NAS: \\WDMyCloud\Cadangan\IRIS).
# 3. Jadwalkan lewat Task Scheduler Windows, harian pukul 08.00:
#    Program: powershell.exe   Argumen: -ExecutionPolicy Bypass -File "C:\IRIS\ambil-cadangan-iris.ps1"
#    Tambahkan argumen -Foto untuk mengambil cadangan foto mingguan (jadwalkan tiap Senin).
param([switch]$Foto)

$Token  = "TEMPEL_TOKEN_DI_SINI"
$Tujuan = "D:\Cadangan-IRIS"
$Simpan = 60   # jumlah file yang disimpan di folder tujuan
@endverbatim
$Url    = "{{ $url }}"
@verbatim

$Jenis = if ($Foto) { "foto" } else { "db" }
$Ext   = if ($Foto) { "zip" } else { "sql.gz" }
New-Item -ItemType Directory -Force -Path $Tujuan | Out-Null
$File  = Join-Path $Tujuan ("iris-" + $Jenis + "-" + (Get-Date -Format "yyyyMMdd-HHmm") + "." + $Ext)
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -Uri ($Url + "?jenis=" + $Jenis) -Headers @{ "X-Cadangan-Token" = $Token } -OutFile $File -UseBasicParsing
    Write-Host "Tersimpan: $File"
} catch {
    Write-Error "Gagal mengambil cadangan: $($_.Exception.Message)"
    exit 1
}
Get-ChildItem $Tujuan -Filter ("iris-" + $Jenis + "-*") | Sort-Object LastWriteTime -Descending | Select-Object -Skip $Simpan | Remove-Item
@endverbatim
