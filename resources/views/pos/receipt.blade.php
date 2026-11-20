<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $transaction->transaction_number }}</title>
    
    <!-- LOGIKA DINAMIS UKURAN KERTAS -->
    @php
        $notaSize = $company->nota_size ?? '58mm';
        
        // Default pengaturan untuk 58mm (seperti di foto)
        $bodyWidth = '58mm';
        $fontSize = '12px';
        $padding = '5px 10px';
        
        // Penyesuaian jika 80mm
        if ($notaSize === '80mm') {
            $bodyWidth = '80mm';
            $fontSize = '14px';
            $padding = '10px 15px';
        } 
        // Penyesuaian jika A4 (Invoice Style)
        elseif ($notaSize === 'A4') {
            $bodyWidth = '210mm';
            $fontSize = '14px';
            $padding = '30px 40px';
        }
    @endphp

    <style>
        @page { margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $fontSize }};
            color: #000;
            margin: 0 auto;
            padding: {{ $padding }};
            width: {{ $bodyWidth }};
            box-sizing: border-box;
            background: #fff;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        
        /* Garis Putus-Putus Khas Struk Kasir */
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 2px 0; }
        
        /* Area Header */
        .header { margin-bottom: 8px; }
        .logo-container { margin-bottom: 5px; text-align: center; }
        .logo-container img { max-width: {{ $notaSize === 'A4' ? '120px' : '50px' }}; height: auto; } 
        
        .header h2 { margin: 0 0 2px 0; font-size: 1.15em; font-weight: bold; }
        .header p { margin: 2px 0; font-size: 0.9em; line-height: 1.2; }
        
        /* Area Info Nota */
        .info-area { text-align: center; font-size: 0.85em; margin-bottom: 5px; }
        .info-area p { margin: 2px 0; }
        
        /* Area Item */
        .item-table th { font-weight: normal; font-size: 0.9em; padding-bottom: 5px; }
        .item-table td { font-size: 0.85em; }
        .item-name { font-weight: normal; text-transform: uppercase; }
        
        /* Area Ringkasan (Kasir Kiri, Subtotal Kanan) */
        .summary-area { font-size: 0.85em; width: 100%; margin-top: 2px; }
        .summary-area td { padding: 3px 0; }
        
        /* Area History Cicilan */
        .history-table { font-size: 0.8em; margin-top: 5px; color: #333; }
        
        .footer { margin-top: 10px; font-size: 0.8em; line-height: 1.3; text-align: center; }
    </style>
</head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">

    <div class="header text-center">
        @if($company->is_nota_logo && $company->logo)
            <div class="logo-container">
                <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo">
            </div>
        @endif

        <!-- NAMA TOKO, ALAMAT, TELP DI TENGAH -->
        <h2>{{ $transaction->outlet->name ?? $company->name ?? 'Toko' }}</h2>
        
        @if(isset($transaction->outlet) && $transaction->outlet->address)
            <p>{{ $transaction->outlet->address }}</p>
        @elseif($company->address)
            <p>{{ $company->address }}</p>
        @endif
        
        @if(isset($transaction->outlet) && $transaction->outlet->phone)
            <p>({{ $transaction->outlet->phone }})</p>
        @elseif($company->phone)
            <p>({{ $company->phone }})</p>
        @endif
    </div>

    <!-- INFO NOTA & TANGGAL (DI TENGAH) -->
    <div class="info-area">
        <p>No. Nota : {{ $transaction->transaction_number }}</p>
        <p>Tgl : {{ $transaction->created_at->format('d-m-Y \J\a\m \: H:i') }}</p>
        @if($transaction->customer)
            <p>Cust : {{ $transaction->customer->name }}</p>
        @endif
    </div>

    <div class="divider"></div>

    <!-- TABEL RINCIAN BARANG -->
    <table class="item-table">
        <thead>
            <tr>
                <th class="text-left" width="50%">Item</th>
                <th class="text-center" width="20%">Qty</th>
                <th class="text-right" width="30%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <!-- Nama Barang -->
                    <td colspan="3" style="padding-top: 5px;">
                        <span class="item-name">{{ $item->item_name ?? $item->product->name ?? 'Item Custom' }}</span>
                    </td>
                </tr>
                <tr>
                    <!-- Satuan x Harga | Qty | Subtotal -->
                    <td style="padding-bottom: 5px;">
                        {{ $item->uom ? $item->uom->name . ' x ' : 'x ' }}{{ number_format($item->selling_price, 0, ',', '.') }}
                    </td>
                    <td class="text-center" style="padding-bottom: 5px;">{{ $item->qty }}</td>
                    <td class="text-right" style="padding-bottom: 5px;">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- AREA RINGKASAN: KASIR/MEKANIK (KIRI) & ANGKA (KANAN) -->
    <table class="summary-area">
        <tr>
            <!-- Sisi Kiri -->
            <td width="45%" style="vertical-align: top;">
                Kasir : {{ $transaction->user->name ?? 'Admin' }}<br><br>
                Mekanik :<br>
                {{ strtoupper($transaction->mechanic_name ?? '-') }}
            </td>
            <!-- Sisi Kanan -->
            <td width="55%" style="vertical-align: top;">
                <table width="100%">
                    <tr>
                        <td class="text-left">Subtotal</td>
                        <td width="5%">:</td>
                        <td class="text-right">{{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    
                    @php
                        $totalDiscount = $transaction->discount + $transaction->point_discount_amount;
                    @endphp
                    <tr>
                        <td class="text-left">Diskon</td>
                        <td>:</td>
                        <td class="text-right">{{ $totalDiscount > 0 ? number_format($totalDiscount, 0, ',', '.') : '0' }}</td>
                    </tr>
                    
                    <tr>
                        <td class="text-left">Total</td>
                        <td>:</td>
                        <td class="text-right">{{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                    </tr>
                    
                    <tr>
                        <td class="text-left">Bayar</td>
                        <td>:</td>
                        <td class="text-right">{{ number_format($transaction->amount_paid, 0, ',', '.') }}</td>
                    </tr>
                    
                    <!-- Spasi Kosong Sebelum Kembalian Seperti Pada Gambar -->
                    <tr><td colspan="3" style="height: 6px;"></td></tr>

                    <tr>
                        <td class="text-left">Kembalian</td>
                        <td>:</td>
                        <td class="text-right">
                            {{ number_format(max(0, $transaction->amount_paid - $transaction->grand_total), 0, ',', '.') }}
                        </td>
                    </tr>
                    
                    <!-- Munculkan Sisa Hutang jika Invoice belum lunas (Kredit) -->
                    @if($transaction->amount_paid < $transaction->grand_total)
                    <tr>
                        <td class="text-left">Hutang</td>
                        <td>:</td>
                        <td class="text-right font-bold">{{ number_format(abs($transaction->amount_change), 0, ',', '.') }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Riwayat Bayar DP / Cicilan (Tetap Dipertahankan) -->
    @if($transaction->payments && $transaction->payments->count() > 1)
        <div style="font-size: 0.8em; font-weight: bold; margin-bottom: 2px;">Riwayat Cicilan/DP:</div>
        <table class="history-table">
            @foreach($transaction->payments as $payment)
            <tr>
                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/y') }} ({{ strtoupper($payment->payment_method) }})</td>
                <td class="text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
        <div class="divider"></div>
    @endif

    <!-- Info Reward Poin (Tetap Dipertahankan) -->
    @php
        $earnedPoint = \App\Models\PointHistory::where('reference_id', $transaction->transaction_number)
                            ->where('type', 'earn')
                            ->sum('amount');
    @endphp

    @if($earnedPoint > 0 || ($transaction->customer && $transaction->customer->points_balance > 0))
        <div class="text-center" style="font-size: 0.8em; margin-bottom: 5px;">
            @if($earnedPoint > 0)
                Anda mendapat +{{ $earnedPoint }} Poin<br>
            @endif
            Sisa Poin: {{ number_format($transaction->customer->points_balance ?? 0, 0, ',', '.') }} Pts
        </div>
        <div class="divider"></div>
    @endif

    <!-- FOOTER TEXT -->
    <div class="footer">
        Barang yang telah dibeli tidak dapat<br>
        dikembalikan kecuali ada perjanjian.<br>
        Terima Kasih.
    </div>

</body>
</html>