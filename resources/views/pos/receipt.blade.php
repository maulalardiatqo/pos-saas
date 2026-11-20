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
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 1px 0; }
        
        /* Area Header */
        .header { margin-bottom: 10px; }
        .logo-container { margin-bottom: 5px; text-align: center; }
        .logo-container img { max-width: {{ $notaSize === 'A4' ? '120px' : '50px' }}; height: auto; } 
        
        .header h2 { margin: 0 0 2px 0; font-size: 1.1em; font-weight: normal; }
        .header p { margin: 1px 0; font-size: 0.85em; line-height: 1.2; }
        
        /* Area Info Nota */
        .info-table { font-size: 0.85em; margin-bottom: 2px; }
        .info-table td { padding: 1px 0; }
        
        /* Area Item */
        .item-table th { border-bottom: 1px dashed #000; padding-bottom: 3px; font-weight: normal; font-size: 0.85em; }
        .item-table td { font-size: 0.85em; }
        .item-name { font-weight: bold; }
        
        /* Area Ringkasan (Kasir Kiri, Subtotal Kanan) */
        .summary-area { font-size: 0.85em; width: 100%; margin-top: 4px; }
        .summary-area td { padding: 2px 0; }
        
        /* Area History Cicilan */
        .history-table { font-size: 0.8em; margin-top: 5px; color: #333; }
        
        .footer { margin-top: 15px; font-size: 0.8em; line-height: 1.3; text-align: center; }
    </style>
</head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">

    <div class="header text-center">
        @if($company->is_nota_logo && $company->logo)
            <div class="logo-container">
                <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo">
            </div>
        @endif

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

    <!-- Info Nota & Tanggal (Format Rapi) -->
    <table class="info-table">
        <tr>
            <td class="text-right" width="30%">No. Nota</td>
            <td class="text-center" width="5%">:</td>
            <td>{{ $transaction->transaction_number }}</td>
        </tr>
        <tr>
            <td class="text-right">Tgl</td>
            <td class="text-center">:</td>
            <td>{{ $transaction->created_at->format('d-m-Y \J\a\m \: H:i') }}</td>
        </tr>
        @if($transaction->customer)
        <tr>
            <td class="text-right">Cust</td>
            <td class="text-center">:</td>
            <td>{{ $transaction->customer->name }}</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>

    <!-- Tabel Rincian Barang (Persis Foto) -->
    <table class="item-table">
        <thead>
            <tr>
                <th class="text-left" width="55%">Item</th>
                <th class="text-center" width="15%">Qty</th>
                <th class="text-right" width="30%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <!-- Nama Barang (Full Width) -->
                    <td colspan="3" style="padding-top: 4px;">
                        <span class="item-name">{{ $item->item_name ?? $item->product->name ?? 'Item Custom' }}</span>
                    </td>
                </tr>
                <tr>
                    <!-- Satuan x Harga -->
                    <td>
                        {{ $item->uom ? $item->uom->name . ' x ' : 'x ' }}{{ number_format($item->selling_price, 0, ',', '.') }}
                    </td>
                    <td class="text-center">{{ $item->qty }}</td>
                    <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Area Ringkasan: Mekanik/Kasir (Kiri) & Angka (Kanan) -->
    <table class="summary-area">
        <tr>
            <!-- Sisi Kiri -->
            <td width="40%" style="vertical-align: top;">
                Kasir : {{ $transaction->user->name ?? 'Admin' }}<br><br>
                Mekanik :<br>
                {{ $transaction->mechanic->name ?? '-' }}
            </td>
            <!-- Sisi Kanan -->
            <td width="60%" style="vertical-align: top;">
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
                        <td class="text-right">{{ number_format($totalDiscount, 0, ',', '.') }}</td>
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

    <!-- Riwayat Bayar DP / Cicilan (Muncul Jika Ada Lebih Dari 1 Kali Bayar) -->
    @if($transaction->payments && $transaction->payments->count() > 1)
        <div class="divider"></div>
        <div style="font-size: 0.8em; font-weight: bold; margin-bottom: 2px;">Riwayat Cicilan/DP:</div>
        <table class="history-table">
            @foreach($transaction->payments as $payment)
            <tr>
                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/y') }} ({{ strtoupper($payment->payment_method) }})</td>
                <td class="text-right">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
    @endif

    <!-- Info Reward Poin (Opsional) -->
    @php
        $earnedPoint = \App\Models\PointHistory::where('reference_id', $transaction->transaction_number)
                            ->where('type', 'earn')
                            ->sum('amount');
    @endphp

    @if($earnedPoint > 0 || ($transaction->customer && $transaction->customer->points_balance > 0))
        <div class="divider"></div>
        <div class="text-center" style="font-size: 0.8em; margin-top: 5px;">
            @if($earnedPoint > 0)
                Anda mendapat +{{ $earnedPoint }} Poin<br>
            @endif
            Sisa Poin: {{ number_format($transaction->customer->points_balance ?? 0, 0, ',', '.') }} Pts
        </div>
    @endif

    <div class="divider"></div>

    <div class="footer">
        Barang yang telah dibeli tidak dapat<br>
        dikembalikan kecuali ada perjanjian.<br>
        Terima Kasih.
    </div>

</body>
</html>