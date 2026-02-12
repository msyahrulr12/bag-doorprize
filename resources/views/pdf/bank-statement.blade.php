@php
    $type = pathinfo(public_path('images/background-image.png'), PATHINFO_EXTENSION);
    $data = file_get_contents(public_path('images/background-image.png'));
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Perolehan Kupon Undian Bagi Hoki</title>
    <style>
        @page {
            margin: 0cm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 30px;
            background-image: url({{ 'data:image/' . $type . ';base64,' . base64_encode($data) }});
            background-size: 400px;
            background-position: center;
            background-repeat: no-repeat;
        }

        .container {
            width: 100%;
        }

        /* Header Section menggunakan Table */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
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

        /* Customer Info */
        .customer-info {
            margin-bottom: 25px;
            font-size: 13px;
            line-height: 1.6;
        }

        .info-row {
            margin-bottom: 2px;
        }

        /* Main Title */
        .main-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            font-style: italic;
            color: #2d7a8e;
        }

        .subtitle {
            text-align: center;
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Table */
        .coupon-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            border-radius: 50%;

        }

        .coupon-table thead th {
            /* background-color: #2d7a8e; */
            background-color: #FBBA38;
            color: #ffffff;
            padding: 12px 5px;
            font-size: 8.5px;
            text-align: center;
            border: 1px solid #FBBA38;
        }

        .coupon-table tbody td {
            padding: 10px 5px;
            text-align: center;
            /* border: 1px solid #eeeeee; */
            border: transparent;
            font-size: 8px;
        }

        .bg-gray {
            background-color: #f9f9f9;
        }

        /* Success Message */
        .success-message {
            background-color: #FBBA38;
            color: #ffffff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            font-size: 13px;
        }

        /* Footer Section menggunakan Table */
        .footer-table {
            width: 100%;
            margin-top: 0;
            padding: 5px 20px;
        }

        .qr-box {
            width: 75px;
            height: 75px;
            /* border: 1px solid #ddd; */
            padding: 5px;
        }

        .qr-box img {
            width: 100%;
            height: 100%;
        }

        .hadiah-box {
            width: 180px;
            float: right;
        }

        .hadiah-box img {
            height: auto;
            max-width: 100%;
        }

        .prize-amount {
            font-size: 40px;
            font-weight: bold;
            color: #f7a628;
            margin: 0;
        }

        .prize-label {
            font-size: 28px;
            font-weight: bold;
            color: #f7a628;
        }

        /* App Links */
        .app-links {
            margin-top: 0;
            font-size: 10px;
            margin-left: 5px;
        }

        .app-links img {
            height: 50px;
            vertical-align: middle;
            margin: 0 5px;
        }

        .bank-info {
            margin-top: 15px;
            font-size: 9px;
            color: #666;
        }

        /* Contact Box di Bottom Right */
        .contact-container {
            text-align: right;
            margin-top: 0;
        }

        .contact-box {
            border: 1px solid #2d7a8e;
            padding: 10px;
            display: inline-block;
            border-radius: 10px;
            text-align: right;
            width: 150px;
            margin-right: 30px;
        }

        .contact-box div {
            font-size: 10px;
            font-weight: bold;
            color: #2d7a8e;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <table class="header-table" style="height: 100px;">
            <tr>
                <td style=" width: 60%; vertical-align: middle;">
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

        <!-- Customer Info -->
        <div class=" customer-info">
            <div class="info-row"><strong>{{ $branch ?? 'CABANG KPO SUDIRMAN' }}</strong></div>
            <div class="info-row"><strong>KEPADA</strong></div>
            <div class="info-row"><strong>{{ $customer_name ?? 'HABIB PRASETYA' }}</strong></div>
            <div class="info-row" style="margin-top: 10px;">
                <strong>Periode Statement</strong> : {{ $period ?? '01 Jan s/d 31 jan 2026' }}
            </div>
            <div class="info-row">
                <strong>Nomor CIF</strong> : {{ $cif_number ?? '12345678' }}
            </div>
        </div>

        <!-- Main Title -->
        <div class="main-title">PEROLEHAN KUPON UNDIAN BAGI HOKI</div>
        <div class="subtitle">Terus tingkatkan saldo bulanan Anda & menangkan hadiah hingga miliaran rupiah</div>

        <!-- Coupon Table -->
        <table class="coupon-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Periode</th>
                    <th>Penambahan Kupon</th>
                    <th>Pengurangan Kupon</th>
                    <th>Nomor Kupon</th>
                    <th>Saldo Kupon</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @if ($coupons)
                    @foreach($coupons ?? [] as $index => $coupon)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $coupon['periode'] }}</td>
                            <td>{{ $coupon['penambahan'] }}</td>
                            <td>{{ $coupon['pengurangan'] }}</td>
                            <td>{!! $coupon['nomor'] !!}</td>
                            <td>{{ $coupon['saldo'] }}</td>
                            <td style="text-align: left;">{!! $coupon['keterangan'] !!}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="4"></td>
                        <td style="text-align: center;">
                            <strong>Total</strong>
                        </td>
                        <td>
                            <strong>{{ $totalPoints ?? 0 }}</strong>
                        </td>
                        <td style="text-align: left;">
                            <strong>{!! $totalPointDescriptions ?? 'N/A' !!}</strong>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- Success Message -->
        @if ($showSuccessMessage)
            <div class="success-message" style="text-align: center;">
                <strong>Selamat! Kupon undian Anda periode {{ ($monthName ?? 'N/A') . ' ' . ($year ?? 'N/A') }} telah
                    tercatat atas nama Anda.</strong><br>
                Tingkatkan saldo rata-rata bulanan Anda. Menangkan Tabungan Rp1 miliar & ribuan hadiah lainnya
            </div>
        @endif

        <!-- Footer -->
        <table class="footer-table" style="margin-bottom: 20px;">
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <!-- <table>
                        <tr>
                            <td>
                                <div class="qr-box">
                                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/qr.png'))) }}"
                                        width="100px" height="auto">
                                </div>
                            </td>
                            <td style="padding-left: 10px; font-size: 12px;">
                                <strong>SCAN DI SINI</strong><br>
                                untuk informasi<br>lebih lanjut
                            </td>
                        </tr>
                    </table> -->
                </td>
                <td style="width: 30%; text-align: right; vertical-align: bottom;">
                    <div class="hadiah-box">
                        <img
                            src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/hadiah.png'))) }}">
                    </div>
                </td>
            </tr>
        </table>
        <div style="width: 100%; background-color: #2d7a8e; bottom: 0; left:0; position: fixed;">
            <table width="90%" style="padding: 6px 8px; margin: 0 auto;">
                <tr>
                    <td>
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/footer.png'))) }}"
                            width="100%">
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>