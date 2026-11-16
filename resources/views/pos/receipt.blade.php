<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $transaction->transaction_number }}</title>
    
    @php
        $notaSize = $company->nota_size ?? '58mm';
        
        // Default pengaturan untuk 58mm
        $bodyWidth = '58mm';
        $fontSize = '12px';
        $padding = '10px';
        
        if ($notaSize === '80mm') {
            $bodyWidth = '80mm';
            $fontSize = '14px';
            $padding = '15px';
        } 
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
        
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 2px 0; }
        
        .header { margin-bottom: 10px; }
        .logo-container { margin-bottom: 8px; text-align: center; }
        .logo-container img { max-width: {{ $notaSize === 'A4' ? '120px' : '60px' }}; height: auto; } 
        
        .header h2 { margin: 0 0 2px 0; font-size: 1.2em; }
        .header p { margin: 2px 0; font-size: 0.9em; line-height: 1.2; }
        
        .transaction-info { text-align: center; font-size: 0.9em; margin-bottom: 5px; }
        .transaction-info p { margin: 2px 0; }

        .item-table th { border-bottom: 1px dashed #000; padding-bottom: 4px; font-weight: normal; }
        .item-name { font-weight: bold; font-size: 0.95em; }
        .item-meta { font-size: 0.85em; }

        .summary-container { display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.9em; }
        .summary-left { width: 40%; }
        .summary-right { width: 60%; }
        .summary-right table { width: 100%; }
        
        .footer { margin-top: 20px; font-size: 0.85em; line-height: 1.4; }
    </style>
</head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">

    <div class="header text-center">
        @if($company->is_nota_logo && $company->logo)
            <div class="logo-container">
                <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo Perusahaan">
            </div>
        @endif

        <h2>{{ $transaction->outlet->name ?? $company->name ?? 'TOKO KITA' }}</h2>
        
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

    <!-- INFO TRANSAKSI -->
    <div class="transaction-info">
        <p>No. Nota : {{ $transaction->transaction_number }}</p>
        <p>Tgl : {{ $transaction->created_at->format('d-m-Y H:i') }}</p>
        
        <!-- BLOK INFO CUSTOMER -->
        @if($transaction->customer)
            <p>Cust : {{ $transaction->customer->name }}</p>
            @if($transaction->customer->phone)
                <p>No. HP : {{ $transaction->customer->phone }}</p>
            @endif
        @endif
    </div>
    
    <div class="divider"></div>

    <!-- RINCIAN PRODUK -->
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
                    <td colspan="3" style="padding-top: 6px;">
                        <span class="item-name">{{ $item->item_name ?? $item->product->name ?? 'Item Custom' }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="item-meta">{{ number_format($item->selling_price, 0, ',', '.') }} x</td>
                    <td class="text-center item-meta">{{ $item->qty }}</td>
                    <td class="text-right item-meta">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- RINGKASAN & KASIR (Layout Kiri-Kanan) -->
    <div class="summary-container">
        <!-- Bagian Kiri: Kasir & Mekanik -->
        <div class="summary-left">
            <p style="margin: 0 0 5px 0;">Kasir: {{ $transaction->user->name ?? 'Admin' }}</p>
            <!-- Opsional: Jika Anda memiliki field mekanik, bisa diletakkan di sini -->
            <!-- <p style="margin: 0;">Mekanik:<br>DEDI</p> -->
        </div>

        <!-- Bagian Kanan: Subtotal, Diskon, Total, Bayar, Kembali -->
        <div class="summary-right">
            <table>
                <tr>
                    <td class="text-left">Subtotal</td>
                    <td class="text-right">: {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                </tr>
                
                @if($transaction->discount > 0)
                <tr>
                    <td class="text-left">Diskon</td>
                    <td class="text-right">: {{ number_format($transaction->discount, 0, ',', '.') }}</td>
                </tr>
                @elseif($transaction->point_discount_amount > 0)
                <tr>
                    <td class="text-left">Diskon</td>
                    <td class="text-right">: {{ number_format($transaction->point_discount_amount, 0, ',', '.') }}</td>
                </tr>
                @else
                <tr>
                    <td class="text-left">Diskon</td>
                    <td class="text-right">: 0</td>
                </tr>
                @endif
                
                <tr>
                    <td class="text-left font-bold" style="padding-top: 5px;">Total</td>
                    <td class="text-right font-bold" style="padding-top: 5px;">: {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                </tr>
                
                <tr>
                    <td class="text-left" style="padding-top: 5px;">Bayar</td>
                    <td class="text-right" style="padding-top: 5px;">: {{ number_format($transaction->amount_paid, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-left" style="padding-top: 5px;">Kembali</td>
                    <td class="text-right" style="padding-top: 5px;">: {{ number_format($transaction->amount_change, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- INFO PENAMBAHAN POIN -->
    @php
        $earnedPoint = \App\Models\PointHistory::where('reference_id', $transaction->transaction_number)
                            ->where('type', 'earn')
                            ->sum('amount');
    @endphp

    @if($earnedPoint > 0)
        <div class="divider"></div>
        <div class="text-center" style="font-size: 0.85em; font-weight: bold; margin-top: 5px;">
            Anda mendapat +{{ $earnedPoint }} Poin<br>
            Total Poin Anda: {{ number_format($transaction->customer->points_balance ?? 0, 0, ',', '.') }} Pts
        </div>
    @endif

    <div class="divider"></div>

    <div class="footer text-center">
        <p style="margin: 0 0 5px 0;">Barang yang telah dibeli tidak dapat dikembalikan kecuali ada perjanjian.</p>
        <p style="margin: 0; font-weight: bold;">Terima Kasih.</p>
    </div>

</body>
</html>