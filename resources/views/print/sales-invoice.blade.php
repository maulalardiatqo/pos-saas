<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $transaction->transaction_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 14px; margin: 0; padding: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .company-info h2 { margin: 0 0 5px; color: #1e293b; }
        .company-info p { margin: 2px 0; color: #64748b; }
        .invoice-title h1 { margin: 0 0 5px; color: #3b82f6; text-align: right; }
        .invoice-details { text-align: right; }
        .invoice-details p { margin: 2px 0; font-weight: bold; }
        .billing-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .billing-section h3 { margin: 0 0 10px; font-size: 14px; color: #94a3b8; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #cbd5e1; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { width: 50%; float: right; }
        .totals table th, .totals table td { border: none; padding: 8px 12px; }
        .totals table tr.grand-total { border-top: 2px solid #cbd5e1; font-weight: bold; font-size: 16px; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; margin-top: 10px; }
        .status-paid { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-unpaid { background-color: #fef08a; color: #b45309; border: 1px solid #fde047; }
        .clear { clear: both; }
        .footer { margin-top: 50px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px; }
        @media print {
            body { padding: 0; background-color: #fff; }
            .invoice-container { padding: 0; box-shadow: none; width: 100%; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="invoice-container">
    <!-- Header -->
    <div class="header">
        <div class="company-info">
            <h2>{{ $transaction->company->name ?? 'Perusahaan Kami' }}</h2>
            <p>{{ $transaction->outlet->name ?? 'Pusat' }}</p>
            <p>{{ $transaction->outlet->address ?? 'Alamat Outlet' }}</p>
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div class="invoice-details">
                <p>No. Invoice: {{ $transaction->transaction_number }}</p>
                <p style="font-weight: normal;">Tanggal: {{ \Carbon\Carbon::parse($transaction->created_at)->format('d F Y') }}</p>
                
                @if($transaction->status == 'completed')
                    <div class="status-badge status-paid">LUNAS</div>
                @else
                    <div class="status-badge status-unpaid">BELUM LUNAS</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Billing Info -->
    <div class="billing-info">
        <div class="billing-section">
            <h3>Ditagihkan Kepada:</h3>
            <strong>{{ $transaction->customer->name ?? 'Pelanggan Umum' }}</strong>
            <p>{{ $transaction->customer->address ?? '-' }}</p>
            <p>{{ $transaction->customer->phone ?? '-' }}</p>
        </div>
        <div class="billing-section" style="text-align: right;">
            <h3>Catatan Transaksi:</h3>
            <p>{{ $transaction->notes ?? '-' }}</p>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th>Deskripsi Barang</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Diskon</th>
                <th class="text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
            @php
                // Kalkulasi diskon manual dari subtotal
                $itemTotal = $item->qty * $item->selling_price;
                $itemDiscount = $itemTotal - $item->subtotal;
            @endphp
            <tr>
                <td>
                    <strong>{{ $item->item_name }}</strong><br>
                    <span style="font-size: 12px; color: #64748b;">Satuan: {{ $item->uom->name ?? '-' }}</span>
                </td>
                <td class="text-center">{{ $item->qty }}</td>
                <td class="text-right">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                <td class="text-right">{{ $itemDiscount > 0 ? 'Rp ' . number_format($itemDiscount, 0, ',', '.') : '-' }}</td>
                <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <table>
            <tr>
                <th class="text-right">Subtotal</th>
                <td class="text-right">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if($transaction->discount > 0)
            <tr>
                <th class="text-right" style="color: #ef4444;">Diskon Global</th>
                <td class="text-right" style="color: #ef4444;">- Rp {{ number_format($transaction->discount, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if($transaction->tax > 0)
            <tr>
                <th class="text-right">Pajak</th>
                <td class="text-right">Rp {{ number_format($transaction->tax, 0, ',', '.') }}</td>
            </tr>
            @endif

            <!-- TAMBAHAN: DISKON TUKAR POIN (REDEEM) -->
            @if($transaction->point_discount_amount > 0)
            <tr>
                <th class="text-right" style="color: #3b82f6;">Tukar Poin ({{ $transaction->points_used }} Pts)</th>
                <td class="text-right" style="color: #3b82f6;">- Rp {{ number_format($transaction->point_discount_amount, 0, ',', '.') }}</td>
            </tr>
            @endif

            <tr class="grand-total">
                <th class="text-right">Grand Total</th>
                <td class="text-right">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
            </tr>

            <!-- ========================================================================= -->
            <!-- PERBAIKAN: RIWAYAT PEMBAYARAN (DICICIL / LUNAS)                           -->
            <!-- ========================================================================= -->
            @if($transaction->payments && $transaction->payments->count() > 0)
                <tr>
                    <td colspan="2" style="padding: 15px 0 5px 0;">
                        <div style="border-bottom: 1px dashed #cbd5e1; margin-bottom: 5px;"></div>
                        <div style="font-size: 11px; color: #64748b; text-align: right; text-transform: uppercase;">Riwayat Pembayaran:</div>
                    </td>
                </tr>
                @foreach($transaction->payments as $idx => $payment)
                <tr>
                    <th class="text-right" style="font-size: 12px; font-weight: normal; color: #475569; padding: 4px 12px;">
                        Pembayaran {{ $idx + 1 }} 
                        <span style="text-transform: uppercase; font-size: 10px;">({{ $payment->payment_method }})</span><br>
                        <small style="color: #94a3b8;">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y - H:i') }}</small>
                    </th>
                    <td class="text-right" style="color: #15803d; padding: 4px 12px;">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="padding: 0;">
                        <div style="border-bottom: 1px dashed #cbd5e1; margin-top: 5px;"></div>
                    </td>
                </tr>
                <tr>
                    <th class="text-right">Total Dibayar</th>
                    <td class="text-right"><strong>Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</strong></td>
                </tr>
            @else
                <tr>
                    <th class="text-right">Sudah Dibayar</th>
                    <td class="text-right">Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</td>
                </tr>
            @endif
            <!-- ========================================================================= -->

            <tr>
                <th class="text-right">Sisa Tagihan</th>
                <td class="text-right"><strong style="color: #ef4444;">Rp {{ number_format(abs($transaction->amount_change), 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>
    <div class="clear"></div>
    <div class="clear"></div>

    <!-- TAMBAHAN: INFO PENAMBAHAN POIN -->
    @php
        $earnedPoint = \App\Models\PointHistory::where('reference_id', $transaction->transaction_number)
                            ->where('type', 'earn')
                            ->sum('amount');
    @endphp

    @if($earnedPoint > 0)
        <div style="text-align: center; margin-top: 20px; border-top: 1px dashed #cbd5e1; padding-top: 15px; color: #3b82f6; font-size: 15px;">
            Anda mendapatkan <strong>+{{ $earnedPoint }} Poin</strong><br>
            dari transaksi ini. Kumpulkan terus poin Anda! 🎉
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        Terima kasih atas kepercayaan Anda berbisnis dengan kami.<br>
        Dicetak pada: {{ now()->format('d M Y H:i:s') }}
    </div>
</div>

</body>
</html>