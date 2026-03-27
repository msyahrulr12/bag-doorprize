@php
    $type = pathinfo(public_path('images/background-image.png'), PATHINFO_EXTENSION);
    $data = file_get_contents(public_path('images/background-image.png'));
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Ketentuan Program Undian Bagi Hoki 2026</title>
    <style>
        @page {
            margin: 0cm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px 50px;
            background-color: #ffffff;
            /* background-image: url({{ 'data:image/' . $type . ';base64,' . base64_encode($data) }});
            background-size: 400px;
            background-repeat: no-repeat;
            background-position: center; */
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header Section */
        .header-table {
            width: 100%;
            margin-bottom: 30px;
        }

        .logo-section-img {
            width: 120px;
            height: 60px;
            margin-right: 15px;
        }

        .bagi-hoki-logo-img {
            width: 160px;
            height: 120px;
        }

        /* Main Title */
        .main-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            color: #2d7a8e;
        }

        /* Content Sections */
        .content-section {
            margin-bottom: 12px;
            font-size: 11px;
            line-height: 1.3;
            text-align: justify;
        }

        .section-title {
            font-weight: bold;
            font-size: 10.5px;
            margin-bottom: 6px;
            color: #2d7a8e;
        }

        .content-paragraph {
            margin-bottom: 6px;
        }

        /* Period Info Box */
        .period-box {
            background-color: #f5f5f5;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2d7a8e;
        }

        .period-box p {
            margin: 5px 0;
            font-size: 10px;
        }

        /* Table Styles */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }

        .info-table th {
            background-color: #2d7a8e;
            color: #ffffff;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #2d7a8e;
            font-size: 10px;
        }

        .info-table td {
            padding: 5px 4px;
            border: 1px solid #dddddd;
            text-align: left;
            font-size: 9.5px;
        }

        .info-table td.center {
            text-align: center;
        }

        /* .info-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        } */

        /* Simulation Table */
        .simulation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9.5px;
        }

        .simulation-table th {
            background-color: #FBBA38;
            color: #ffffff;
            padding: 8px 5px;
            text-align: center;
            border: 1px solid #FBBA38;
            font-size: 10px;
        }

        .simulation-table td {
            padding: 8px 5px;
            border: 1px solid #dddddd;
            text-align: center;
        }

        .simulation-table .phase-header {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
            font-size: 10.5px;
        }

        /* Prize Table */
        .prize-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10.5px;
        }

        .prize-table th {
            background-color: #2d7a8e;
            color: #ffffff;
            padding: 5px 4px;
            text-align: center;
            border: 1px solid #2d7a8e;
            font-size: 10px;
        }

        .prize-table td {
            padding: 5px 4px;
            border: 1px solid #dddddd;
            text-align: center;
            font-size: 9.5px;
        }

        .prize-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .prize-table .total-row {
            background-color: #e8f4f8;
            font-weight: bold;
        }

        /* Bullet Lists */
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        ul li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        ul ul {
            margin-top: 5px;
        }

        /* Notes */
        .note {
            font-style: italic;
            color: #666;
            font-size: 10px;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }

        strong {
            color: #2d7a8e;
        }

        /* Footer */
        .footer-section {
            margin-top: 12px;
            padding-top: 6px;
            /* border-top: 2px solid #2d7a8e; */
            font-size: 10px;
            text-align: center;
        }

        .footer-note {
            font-size: 10px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 60%; vertical-align: middle;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/agi-bank-logo.png'))) }}"
                        class="logo-section-img" style="vertical-align: middle;" height="auto">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-agi.png'))) }}"
                        class="logo-section-img" style="vertical-align: middle;" height="auto">
                </td>
                <td style="width: 40%; text-align: right;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-program.png'))) }}"
                        class="bagi-hoki-logo-img" width="200px" height="auto">
                </td>
            </tr>
        </table>

        <!-- Main Title -->
        <div class="main-title">KETENTUAN PROGRAM UNDIAN BAGI HOKI 2026</div>

        <!-- Introduction -->
        <div class="content-section">
            <p class="content-paragraph">
                Program Undian BAGI HOKI 2026 adalah program pemberian hadiah undian untuk Nasabah yang membuka
                rekening dan meningkatkan saldo rata-rata tabungan. Semakin tinggi peningkatan saldo rata-rata, semakin
                besar
                kesempatan untuk memenangkan hadiah.
            </p>
        </div>

        <!-- Period Box -->
        <div class="period-box">
            <p><strong>Periode program:</strong> 3 Februari – 31 Juli 2026.</p>
            <p><strong>Pengundian akan dilakukan 2x selama periode program:</strong> Mei & Agustus 2026.</p>
        </div>

        <!-- How to Get Coupons -->
        <div class="content-section">
            <div class="section-title">Cara mendapatkan kupon undian</div>

            <table class="info-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">AKTIVITAS</th>
                        <th style="width: 60%;">KETERANGAN</th>
                        <th style="width: 15%;">KUPON UNDIAN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Buka Rekening</strong></td>
                        <td>
                            <ul>
                                <li>Pembukaan Tabungan min. Rp500.000</li>
                                <li>Berlaku untuk Tabungan Icon, Artha, Artha Merchant, Artha Mitra dan Payroll
                                    Umum/AGN.</li>
                                <li>Berlaku untuk nasabah baru (new CIF).</li>
                            </ul>
                        </td>
                        <td class="center"><strong>10</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tingkatkan Saldo Rata-Rata</strong></td>
                        <td>
                            <ul>
                                <li>Peningkatan Saldo Rata-Rata Bulan "X" dibandingkan Saldo Rata-Rata Bulan "X-1"
                                    sebesar Rp.100.000. Berlaku kelipatan.</li>
                                <li>Berlaku untuk Tabungan Icon, Artha, Artha Merchant, Artha Mitra dan Payroll
                                    Umum/AGN.</li>
                                <li>Berlaku untuk nasabah baru & lama (new & existing CIF).</li>
                            </ul>
                        </td>
                        <td class="center"><strong>1</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Coupon Terms -->
        <div class="content-section">
            <div class="section-title">Ketentuan kupon undian</div>
            <ol>
                <li>Kupon undian akan diakumulasikan setiap bulan selama periode program.</li>
                <li>Kupon akan <strong class="highlight">HANGUS</strong> bila saldo rata-rata Tabungan mengalami
                    penurunan dibanding bulan sebelumnya.
                    Kupon yang sudah terakumulasi akan ter-reset menjadi 0 dan perhitungan kupon akan dimulai kembali
                    pada
                    bulan berikutnya.</li>
            </ol>
        </div>

        <!-- Simulation Table -->
        <div class="content-section">
            <div class="section-title">Simulasi Perhitungan Kupon</div>
            <table class="simulation-table">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Saldo<br>Rata-Rata</th>
                        <th>Peningkatan<br>Saldo Rata-Rata</th>
                        <th>Kupon Undian</th>
                        <th>Akumulasi<br>Kupon Undian</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Jan</td>
                        <td>0</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Feb</td>
                        <td>2.000.000</td>
                        <td>2.000.000</td>
                        <td>20</td>
                        <td>20</td>
                        <td>Penambahan 20 kupon</td>
                    </tr>
                    <tr>
                        <td>Mar</td>
                        <td>2.000.000</td>
                        <td>0</td>
                        <td>0</td>
                        <td>20</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Apr</td>
                        <td>5.000.000</td>
                        <td>3.000.000</td>
                        <td>30</td>
                        <td>50</td>
                        <td>Penambahan 30 kupon</td>
                    </tr>
                    <tr class="phase-header">
                        <td colspan="6">Undian Tahap 1 (Mei 2026)</td>
                    </tr>
                    <tr>
                        <td>Mei</td>
                        <td>4.500.000</td>
                        <td>-500.000</td>
                        <td>0</td>
                        <td>0</td>
                        <td>Kupon Hangus</td>
                    </tr>
                    <tr>
                        <td>Jun</td>
                        <td>5.000.000</td>
                        <td>500.000</td>
                        <td>5</td>
                        <td>5</td>
                        <td>Penambahan 5 kupon</td>
                    </tr>
                    <tr>
                        <td>Jul</td>
                        <td>8.000.000</td>
                        <td>3.000.000</td>
                        <td>30</td>
                        <td>35</td>
                        <td>Penambahan 30 kupon</td>
                    </tr>
                    <tr class="phase-header">
                        <td colspan="6">Undian Tahap 2 (Agustus 2026)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Drawing Terms -->
        <div class="content-section"
            style="page-break-before: always !important; display: block; border-top: 1px solid transparent;">
            <div class="section-title">Ketentuan Pengundian</div>
            <ul>
                <li>Pengundian dilakukan menggunakan aplikasi pengundian, disaksikan dan disahkan oleh Dinas Sosial,
                    Notaris
                    dan instansi terkait lainnya. Keputusan pemenang undian bersifat mutlak dan mengikat serta tidak
                    dapat
                    diganggu gugat.</li>
                <li>Program Undian BAGI HOKI tidak berlaku untuk:
                    <ul>
                        <li>Karyawan BAGI, Direksi dan Komisaris BAGI, Karyawan Outsourcing BAGI.</li>
                        <li>Rekening Joint OR/AND, Tabungan Non-Perorangan, Tabungan Kerjasama B2B.</li>
                        <li>Rekening tidak aktif/dormant/tutup sebelum pengundian.</li>
                    </ul>
                </li>
                <li>Setiap pemenang hanya berhak mendapatkan 1 hadiah pada setiap tahap pengundian.</li>
                <li>Pajak hadiah dan pajak undian ditanggung oleh Bank Artha Graha Internasional</li>
                <li>Hadiah diberikan kepada Pemenang dalam kondisi <em>off the road</em>.</li>
                <li>Biaya lainnya (biaya pajak surat-surat kendaraan, STNK, plat nomor, biaya balik nama, biaya admin,
                    dan lain-lain)
                    akan ditanggung oleh pemenang.</li>
                <li>Hadiah tidak dapat diuangkan, diganti jadi bentuk lain, atau dipindahtangankan.</li>
                <li>Warna hadiah disesuaikan dengan ketersediaan stok dan pemenang tidak dapat memilih warna hadiah.
                </li>
                <li>BAGI tidak memberikan garansi produk hadiah undian. Pengajuan klaim bila terjadi kerusakan hadiah
                    selama
                    masa garansi dapat diajukan ke service center resmi dari produk.</li>
                <li>Bank Artha Graha Internasional berhak membatalkan perolehan kupon undian atau hadiah undian bila
                    terdapat indikasi kecurangan/fraud, dan/atau aksi lainnya yang menyalahi syarat dan ketentuan
                    program.
                    Bank juga berhak mendiskualifikasi dan/atau menolak partisipasi program baik secara sebagian
                    dan/atau
                    keseluruhan dari para peserta yang terindikasi melakukan kecurangan.</li>
            </ul>
        </div>

        <!-- Prize Table -->
        <div class="content-section">
            <div class="section-title">Bentuk hadiah undian sebagai berikut:</div>

            <table class="prize-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Hadiah Undian (Tahap 1)</th>
                        <th style="width: 10%;">Jumlah<br>Pemenang</th>
                        <th style="width: 40%;">Hadiah Undian (Tahap 2)</th>
                        <th style="width: 10%;">Jumlah<br>Pemenang</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Motor Yamaha Fazzio</td>
                        <td>1</td>
                        <td>Tabungan Rp1.000.000.000</td>
                        <td>1</td>
                    </tr>
                    <tr>
                        <td>Tabungan Rp8.000.000</td>
                        <td>2</td>
                        <td>Motor Yamaha Fazzio</td>
                        <td>2</td>
                    </tr>
                    <tr>
                        <td>Speaker Home Theater Sony</td>
                        <td>5</td>
                        <td>Tabungan Rp8.000.000</td>
                        <td>10</td>
                    </tr>
                    <tr>
                        <td>TV Google 43" Sharp</td>
                        <td>5</td>
                        <td>Speaker Home Theater Sony</td>
                        <td>15</td>
                    </tr>
                    <tr>
                        <td>Washing Machine LG</td>
                        <td>3</td>
                        <td>TV Google 43" Sharp</td>
                        <td>15</td>
                    </tr>
                    <tr>
                        <td>Kulkas 2 Pintu Sharp</td>
                        <td>5</td>
                        <td>Washing Machine LG</td>
                        <td>10</td>
                    </tr>
                    <tr>
                        <td>Airfryer Digital Arra</td>
                        <td>34</td>
                        <td>Kulkas 2 Pintu Sharp</td>
                        <td>15</td>
                    </tr>
                    <tr>
                        <td>Microwave Toshiba</td>
                        <td>25</td>
                        <td>Airfryer Digital Arra</td>
                        <td>102</td>
                    </tr>
                    <tr>
                        <td>Water Dispenser Polytron</td>
                        <td>10</td>
                        <td>Microwave Toshiba</td>
                        <td>90</td>
                    </tr>
                    <tr>
                        <td>Tabungan Rp500.000</td>
                        <td>510</td>
                        <td>Water Dispenser Polytron</td>
                        <td>40</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td>Tabungan Rp500.000</td>
                        <td>1.500</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong>600</strong></td>
                        <td><strong>Total</strong></td>
                        <td><strong>1.800</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Winner Announcement -->
        <div class="content-section">
            <div class="section-title">Pengumuman Pemenang dan Penyerahan Hadiah</div>
            <ol>
                <li>Proses pengundian akan diumumkan melalui <a href="https://www.arthagraha.com"
                        style="color: #2d7a8e; text-decoration: none;"><strong>www.arthagraha.com</strong></a> pada
                    bulan Mei & Agustus 2026.</li>
                <li>Pemenang akan dihubungi oleh BAGI selambat-lambatnya 7 hari sejak tanggal pengumuman pengundian.
                </li>
                <li>Pemenang wajib mengambil secara langsung hadiah dalam jangka waktu selambat-lambatnya 30 hari sejak
                    tanggal pelaksanaan penarikan hadiah di Kantor Cabang yang ditentukan oleh BAGI dengan dokumen yang
                    dipersyaratkan untuk keperluan pengambilan hadiah.</li>
            </ol>
        </div>

        <!-- Footer -->
        <div class="footer-section" style="position: fixed; bottom: 0; left:0; width: 100%;">
            <div style="width: 100%; background-color: #2d7a8e; padding: 15px; margin-top: 30px;">
                <table width="100%" style="margin: 0 auto;">
                    <tr>
                        <td style="text-align: center;">
                            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/footer.png'))) }}"
                                style="max-width: 100%; height: auto;">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer-note">
                <p><strong>Bank Artha Graha Internasional</strong></p>
                <p>Untuk informasi lebih lanjut, hubungi customer service kami</p>
            </div>
        </div>
    </div>
</body>

</html>