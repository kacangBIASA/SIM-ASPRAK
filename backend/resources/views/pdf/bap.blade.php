<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>BAP {{ $bap['nama'] }} - {{ $bap['nama_mk'] }}</title>
<style>
    @page { margin: 1.6cm 1.4cm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5pt; color: #000; }
    table { border-collapse: collapse; }
    .kepala { width: 100%; }
    .kepala td { vertical-align: top; padding: 0; }
    .judul { font-size: 11pt; font-weight: bold; line-height: 1.25; margin-bottom: 8px; }
    .identitas td { padding: 0 4px 0 0; line-height: 1.3; }
    .identitas .label { font-weight: bold; width: 70px; }
    .lab { width: 40%; text-align: right; font-weight: bold; font-size: 9pt; line-height: 1.3; }
    .logo { height: 58px; margin: 0 22px 4px 0; }
    .data { width: 100%; margin-top: 12px; }
    .data th, .data td { border: 1px solid #000; padding: 2px 4px; }
    .data th { background: #bdd7ee; text-align: center; }
    .c { text-align: center; }
    .r { text-align: right; }
    .ttd { width: 100%; margin-top: 18px; page-break-inside: avoid; }
    .blok-ttd { width: 40%; text-align: center; }
    .ruang-ttd { height: 70px; }
    .nama-ttd { border-bottom: 1px solid #000; padding-bottom: 1px; }
</style>
</head>
<body>
    <table class="kepala">
        <tr>
            <td>
                <div class="judul">BERITA ACARA PEKERJAAN DAN KEHADIRAN<br>ASISTEN PRAKTIKUM</div>
                <table class="identitas">
                    <tr><td class="label">NAMA</td><td>: {{ $bap['nama'] }}</td></tr>
                    <tr><td class="label">NIM</td><td>: {{ $bap['nim'] ?? '-' }}</td></tr>
                    <tr><td class="label">BULAN</td><td>: {{ $bap['bulan'] }}</td></tr>
                    <tr><td class="label">PRODI</td><td>: {{ $bap['prodi'] }}</td></tr>
                    <tr><td class="label">KODE MK</td><td>: {{ $bap['kode_mk'] }}</td></tr>
                    <tr><td class="label">NAMA MK</td><td>: {{ $bap['nama_mk'] }}</td></tr>
                    <tr><td class="label">TAHUN</td><td>: {{ $bap['tahun'] }}</td></tr>
                    <tr><td class="label">TOTAL JAM</td><td>: {{ $bap['total_jam'] }}</td></tr>
                </table>
            </td>
            {{-- Logo disematkan sebagai data URI karena dompdf tidak mengambil gambar lewat URL. --}}
            <td class="lab">
                <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('images/logo-telkom.png'))) }}" alt="Logo Telkom University"><br>
                Laboratorium<br>Universitas Telkom Surabaya
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:7%">Hari</th>
                <th style="width:16%">Tanggal</th>
                <th style="width:11%">Kelas</th>
                <th style="width:9%">Jam Masuk</th>
                <th style="width:8%">Jam Keluar</th>
                <th style="width:8%">Jumlah Jam</th>
                <th>Modul Praktikum</th>
                <th style="width:9%">Paraf Asprak</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bap['baris'] as $i => $b)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $b['hari'] }}</td>
                    <td>{{ $b['tanggal'] }}</td>
                    <td class="c">{{ $b['kelas'] }}</td>
                    <td class="r">{{ $b['jam_masuk'] }}</td>
                    <td class="r">{{ $b['jam_keluar'] }}</td>
                    <td>{{ $b['jumlah_jam'] }}</td>
                    <td>{{ $b['modul'] }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td></td>
            <td class="blok-ttd">
                Surabaya, {{ $bap['tanggal_dibuat'] }}<br>
                Koordinator Dosen Mata Kuliah
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $bap['dosen'] }}</span>
            </td>
        </tr>
    </table>
</body>
</html>
