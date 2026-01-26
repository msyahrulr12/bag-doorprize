<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bank Statement</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #ffffff; color: #000000;">

    <!-- Main Container -->
    <div style="background-color: #ffffff; padding: 40px; position: relative;">

        <!-- Watermark AGI -->
        <div
            style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200px; font-weight: bold; color: rgba(58, 184, 200, 0.08); z-index: 0; pointer-events: none;">
            agi
        </div>

        <!-- Content Layer -->
        <div style="position: relative; z-index: 1;">

            <!-- Header with Logos -->
            <table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <img src="{{ public_path('images/agi-bank-logo.png') }}" alt="AGI Bank"
                            style="height: 45px; display: inline-block; margin-right: 20px; vertical-align: middle;">
                        <img src="{{ public_path('images/agi-logo.png') }}" alt="AGI"
                            style="height: 45px; display: inline-block; vertical-align: middle;">
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <!-- Empty for now, add logo if needed -->
                    </td>
                </tr>
            </table>

            <!-- Title and Account Info -->
            <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #000000;">9999-CABANG
                            CONFIDENTI</div>
                        <div style="font-size: 14px; font-weight: bold; color: #000000;">KEPADA</div>
                    </td>
                    <td style="width: 50%; text-align: right; vertical-align: top;">
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #000000;">
                            Periode Statement : {{ $period_start ?? '01 Nov' }} s/d {{ $period_end ?? '30 Nov 2025' }}
                        </div>
                        <div style="font-size: 16px; font-weight: bold; color: #000000;">Nomor Rekening</div>
                    </td>
                </tr>
            </table>

            <!-- Account Holder Box -->
            <div style="background-color: #e8e8e8; padding: 20px; border-radius: 5px; margin-bottom: 25px;">
                <div style="font-size: 14px; font-weight: 600; color: #000000;">
                    {{ $account_holder ?? 'Nama & Alamat nasabah' }}
                </div>
            </div>

            <!-- Transaction Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;" cellpadding="0" cellspacing="0">
                <thead>
                    <tr style="background-color: #3ab8c8; color: #ffffff;">
                        <th
                            style="padding: 12px 10px; text-align: left; font-weight: bold; font-size: 11px; border: none;">
                            TANGGAL</th>
                        <th
                            style="padding: 12px 10px; text-align: left; font-weight: bold; font-size: 11px; border: none;">
                            TANGGAL<br />VALUTA</th>
                        <th
                            style="padding: 12px 10px; text-align: left; font-weight: bold; font-size: 11px; border: none;">
                            KETERANGAN</th>
                        <th
                            style="padding: 12px 10px; text-align: left; font-weight: bold; font-size: 11px; border: none;">
                            TRANSACTION<br />REFERENCE</th>
                        <th
                            style="padding: 12px 10px; text-align: right; font-weight: bold; font-size: 11px; border: none;">
                            DEBET</th>
                        <th
                            style="padding: 12px 10px; text-align: right; font-weight: bold; font-size: 11px; border: none;">
                            KREDIT</th>
                        <th
                            style="padding: 12px 10px; text-align: right; font-weight: bold; font-size: 11px; border: none;">
                            SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions ?? [] as $index => $transaction)
                        <tr style="border-bottom: 1px solid #dddddd;">
                            <td style="padding: 10px; font-size: 11px; color: #000000;">{{ $transaction['tanggal'] ?? '' }}
                            </td>
                            <td style="padding: 10px; font-size: 11px; color: #000000;">
                                {{ $transaction['tanggal_valuta'] ?? '' }}
                            </td>
                            <td style="padding: 10px; font-size: 11px; color: #000000;">
                                {{ $transaction['keterangan'] ?? '' }}
                            </td>
                            <td style="padding: 10px; font-size: 11px; color: #000000;">
                                {{ $transaction['reference'] ?? '' }}
                            </td>
                            <td style="padding: 10px; text-align: right; font-size: 11px; color: #000000;">
                                {{ $transaction['debet'] ? number_format($transaction['debet'], 2, ',', '.') : '' }}
                            </td>
                            <td style="padding: 10px; text-align: right; font-size: 11px; color: #000000;">
                                {{ $transaction['kredit'] ? number_format($transaction['kredit'], 2, ',', '.') : '' }}
                            </td>
                            <td style="padding: 10px; text-align: right; font-size: 11px; color: #000000;">
                                {{ number_format($transaction['saldo'] ?? 0, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 30px; text-align: center; font-size: 11px; color: #999999;">
                                &nbsp;
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Tax Amount Section -->
            <div style="border-top: 2px solid #333333; padding-top: 15px; margin-bottom: 10px;">
                <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size: 11px; font-weight: 600; color: #000000;">{{ $tax_date ?? '30/11/2025' }}
                        </td>
                        <td style="font-size: 11px; font-weight: 600; color: #000000; padding-left: 30px;">
                            {{ $tax_value_date ?? '01/12/2025' }}
                        </td>
                        <td style="font-size: 11px; font-weight: bold; color: #000000; padding-left: 30px;">TAX AMOUNT
                            DUE</td>
                    </tr>
                </table>
            </div>

            <!-- Total -->
            <div style="text-align: right; margin-top: 15px; margin-bottom: 40px;">
                <div style="font-size: 12px; font-weight: bold; color: #000000;">Total Akhir</div>
            </div>

            <!-- Perolehan Poin Undian Section dengan Border Kuning -->
            <div style="border-radius: 8px; padding: 20px; margin-top: 30px;">

                <div
                    style="font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #000000; border-bottom: 2px solid #000000; padding-bottom: 10px;">
                    Perolehan Poin Undian
                </div>

                <!-- Coupon Table -->
                <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr style="background-color: #3ab8c8; color: #ffffff;">
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                NO</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                PERIODE</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                PENAMBAHAN KUPON</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                PENGURANGAN KUPON</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                NOMOR KUPON</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                SALDO KUPON</th>
                            <th
                                style="padding: 12px 8px; text-align: center; font-weight: bold; font-size: 11px; border: none;">
                                KETERANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coupons ?? [] as $index => $coupon)
                            <tr style="background-color: {{ $index % 2 == 0 ? '#f9f9f9' : '#ffffff' }};">
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $index + 1 }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['periode'] ?? '' }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['penambahan'] ?? '' }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['pengurangan'] ?? '' }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['nomor'] ?? '' }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: center; font-size: 10px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['saldo'] ?? '' }}
                                </td>
                                <td
                                    style="padding: 10px 8px; text-align: left; font-size: 9px; color: #000000; border-bottom: 1px solid #e0e0e0;">
                                    {{ $coupon['keterangan'] ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>

        </div>

    </div>

</body>

</html>