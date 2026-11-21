<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Sertifikat {{ $nama }}</title>
@php
    // A4 mendatar, satuan pt; pratinjau di editor stempel (AturPosisiStempel.jsx) memakai koordinat yang sama.
    $b64 = fn ($f) => 'data:'.mime_content_type($f).';base64,'.base64_encode(file_get_contents($f));
    $font = fn ($f) => str_replace(DIRECTORY_SEPARATOR, '/', resource_path("fonts/$f"));
@endphp
<style>
    @font-face { font-family: 'Archivo Black'; src: url('{{ $font('ArchivoBlack-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Great Vibes'; src: url('{{ $font('GreatVibes-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Open Sans'; font-weight: normal; src: url('{{ $font('OpenSans-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Open Sans'; font-weight: bold; src: url('{{ $font('OpenSans-Bold.ttf') }}') format('truetype'); }
    @page { margin: 0; }
    body { margin: 0; font-family: 'Open Sans', sans-serif; font-size: 13pt; color: #1f2430; }
    .t { position: absolute; margin: 0; line-height: 19pt; white-space: nowrap; }
    .kiri { left: 120pt; }
    .tengah { text-align: center; left: 520pt; width: 270pt; }
    .abu { color: #5b6170; }
</style>
</head>
<body>
    <img src="{{ $b64(resource_path('images/latar-sertifikat.jpg')) }}" alt="" style="position: absolute; left: 0; top: 0; width: 841.89pt; height: 595.28pt;">
    <img src="{{ $b64(resource_path('images/logo-telu-yayasan.png')) }}" alt="" style="position: absolute; left: 120pt; top: 44pt; width: 150pt;">
    <p class="t abu" style="right: 52pt; top: 70pt; font-size: 11pt; letter-spacing: 2pt;">NO: {{ $nomor_surat }}</p>

    <p class="t kiri" style="top: 112pt; line-height: 54pt; font-family: 'Archivo Black'; font-size: 44pt; letter-spacing: 3pt;">SERTIFIKAT</p>
    <div style="position: absolute; left: 120pt; top: 172pt; width: 64pt; height: 4pt; background: #c8102e;"></div>

    <p class="t kiri abu" style="top: 192pt;">Diberikan Kepada:</p>
    <p class="t kiri" style="top: 212pt; line-height: 62pt; font-family: 'Montserrat'; font-size: 46pt; color: #c8102e;">{{ $nama }}</p>
    <div style="position: absolute; left: 120pt; top: 280pt; width: 400pt; height: 1pt; background: #cfd3da;"></div>

    <p class="t kiri abu" style="top: 296pt;">Sebagai Asisten Praktikum</p>
    <p class="t kiri" style="top: 314pt; line-height: 28pt; font-size: 20pt; font-weight: bold;">{{ $praktikum }}</p>
    <p class="t kiri" style="top: 346pt;">Pada Program Studi {{ $prodi }}</p>
    <p class="t kiri" style="top: 364pt;">Universitas Telkom Kampus {{ $kota }}</p>
    <p class="t kiri abu" style="top: 382pt;">Periode Semester {{ $periode }}</p>

    <div style="position: absolute; left: 570pt; top: 296pt; width: 190pt; height: 96pt; border: 1.2pt solid #e2d0d4; border-radius: 10pt; background: #fff8f9;"></div>
    <p class="t abu" style="left: 570pt; width: 190pt; text-align: center; top: 304pt; font-size: 9.5pt; letter-spacing: 3pt;">NILAI</p>
    <p class="t" style="left: 570pt; width: 190pt; text-align: center; top: 316pt; line-height: 52pt; font-family: 'Archivo Black'; font-size: 40pt; color: #c8102e;">{{ $nilai }}</p>
    <p class="t" style="left: 570pt; width: 190pt; text-align: center; top: 368pt; font-weight: bold;">{{ $predikat }}</p>

    <p class="t kiri" style="top: 428pt;">{{ $kota }}, {{ $tanggal }}</p>

    <p class="t tengah" style="top: 428pt;">Direktur Universitas</p>
    <p class="t tengah" style="top: 445pt;">Telkom Kampus {{ $kota }}</p>
    <img src="{{ $tanda_tangan['src'] }}" alt="" style="position: absolute; left: {{ $tanda_tangan['kiri'] }}pt; top: {{ $tanda_tangan['atas'] }}pt; width: {{ $tanda_tangan['lebar'] }}pt; height: {{ $tanda_tangan['tinggi'] }}pt;">
    <img src="{{ $stempel['src'] }}" alt="" style="position: absolute; left: {{ $stempel['kiri'] }}pt; top: {{ $stempel['atas'] }}pt; width: {{ $stempel['lebar'] }}pt; height: {{ $stempel['tinggi'] }}pt;">
    <div style="position: absolute; left: 540pt; top: 524pt; width: 230pt; height: 0.8pt; background: #1f2430;"></div>
    <p class="t tengah" style="top: 529pt; font-weight: bold; font-size: 11.5pt;">{{ $nama_penandatangan }}</p>
    <p class="t tengah abu" style="top: 545pt; font-size: 11pt;">NIP : {{ $nip_penandatangan }}</p>

    <p class="t kiri abu" style="top: 546pt; font-size: 9pt; letter-spacing: 1pt;">{{ $kode }}</p>
</body>
</html>
