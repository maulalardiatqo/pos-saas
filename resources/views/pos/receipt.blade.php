<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $transaction->transaction_number }}</title>
    
    <!-- LOGIKA DINAMIS UKURAN KERTAS -->
    @php
        $notaSize = $company->nota_size ?? '58mm';
        
        // Default pengaturan untuk 58mm
        $bodyWidth = '58mm';
        $fontSize = '12px';
        $padding = '10px';
        
        // Penyesuaian jika 80mm
        if ($notaSize === '80mm') {
            $bodyWidth = '80mm';
            $fontSize = '14px';
            $padding = '15px';
        } 
        // Penyesuaian jika A4 (Invoice Style)
        elseif ($notaSize === 'A4') {
            $bodyWidth = '210mm';
            $fontSize = '14px';
            $padding = '30px 40px'; // Padding lebih lega untuk A4
        }
    @endphp

    <style>
        /* CSS DINAMIS MENGGUNAKAN VARIABEL BLADE */
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
        
        /* Penyesuaian Garis Putus-Putus */
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 2px 0; }
        
        /* Area Header */
        .header { margin-bottom: 15px; }
        .logo-container { margin-bottom: 8px; text-align: center; }
        
        /* PERBAIKAN 1: Logo dipaksa maksimal 60px saja untuk kertas struk agar terlihat proporsional */
        .logo-container img { max-width: {{ $notaSize === 'A4' ? '120px' : '60px' }}; height: auto; } 
        
        .header h2 { margin: 0 0 5px 0; font-size: 1.2em; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 0.9em; line-height: 1.2; }
        
        /* Area Item */
        .item-name { display: block; font-size: 0.95em; font-weight: bold; }
        .item-detail { font-size: 0.9em; }
        
        /* Area Ringkasan (Subtotal, Diskon, Total) */
        .summary-table { margin-top: 8px; font-size: 0.9em; }
        .summary-table td { padding: 3px 0; }
        
        .footer { margin-top: 20px; font-size: 0.85em; line-height: 1.4; }
    </style>
</head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">

    <div class="header text-center">
        <!-- 1. LOGO PERUSAHAAN -->
        @if($company->is_nota_logo && $company->logo)
            <div class="logo-container">
                <!-- Pastikan path storage sudah dilink (php artisan storage:link) -->
                <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo Perusahaan">
            </div>
        @endif

        <!-- PERBAIKAN 2: Menggunakan Nama, Alamat, dan Telepon dari Outlet -->
        <h2>{{ $transaction->outlet->name ?? $company->name ?? 'TOKO KITA' }}</h2>
        
        <!-- Cek jika outlet punya alamat, jika tidak fallback ke alamat company -->
        @if(isset($transaction->outlet) && $transaction->outlet->address)
            <p>{{ $transaction->outlet->address }}</p>
        @elseif($company->address)
            <p>{{ $company->address }}</p>
        @endif
        
        <!-- Cek jika outlet punya telepon, jika tidak fallback ke telepon company -->
        @if(isset($transaction->outlet) && $transaction->outlet->phone)
            <p>Telp: {{ $transaction->outlet->phone }}</p>
        @elseif($company->phone)
            <p>Telp: {{ $company->phone }}</p>
        @endif
        
        <div class="divider"></div>
        
        <!-- 2. INFO TRANSAKSI & CUSTOMER -->
        <table style="font-size: 0.9em; text-align: left; margin-bottom: 5px;">
            <tr>
                <td width="35%">Tanggal</td>
                <td width="5%">:</td>
                <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>No. Nota</td>
                <td>:</td>
                <td>{{ $transaction->transaction_number }}</td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td>:</td>
                <td>{{ $transaction->user->name ?? 'Admin' }}</td>
            </tr>
            
            <!-- BLOK INFO CUSTOMER -->
            @if($transaction->customer)
            <tr>
                <td>Pelanggan</td>
                <td>:</td>
                <td class="font-bold">{{ $transaction->customer->name }}</td>
            </tr>
                <!-- Tampilkan nomor HP jika ada -->
                @if($transaction->customer->phone)
                <tr>
                    <td>No. HP</td>
                    <td>:</td>
                    <td>{{ $transaction->customer->phone }}</td>
                </tr>
                @endif
                <!-- Tampilkan sisa poin jika program loyalitas aktif -->
                @if($company->is_loyalty_enabled)
                <tr>
                    <td>Sisa Poin</td>
                    <td>:</td>
                    <td>{{ number_format($transaction->customer->points_balance ?? 0, 0, ',', '.') }} Pts</td>
                </tr>
                @endif
            @else
            <tr>
                <td>Pelanggan</td>
                <td>:</td>
                <td>Umum (Walk-in)</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="divider"></div>

    <div class="divider"></div>

    <!-- 3. RINCIAN PRODUK -->
    <table>
        @foreach($transaction->items as $item)
            <tr>
                <td colspan="3"><span class="item-name">{{ $item->product->name ?? 'Produk' }}</span></td>
            </tr>
            <tr class="item-detail">
                <td class="text-left" width="30%">{{ $item->qty }} {{ $item->uom->name ?? 'Pcs' }}</td>
                <td class="text-left" width="35%">x {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                <td class="text-right" width="35%">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <!-- 4. RINGKASAN BAYAR -->
    <table class="summary-table">
        <tr>
            <td class="text-left">Subtotal</td>
            <td class="text-right">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
        </tr>
        
        @if($transaction->discount > 0)
        <tr>
            <td class="text-left">Diskon Manual</td>
            <td class="text-right">- Rp {{ number_format($transaction->discount, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($transaction->point_discount_amount > 0)
        <tr>
            <td class="text-left">Potongan Poin</td>
            <td class="text-right">- Rp {{ number_format($transaction->point_discount_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
        
        <tr>
            <td class="text-left font-bold" style="font-size: 1.1em; padding-top: 5px;">TOTAL</td>
            <td class="text-right font-bold" style="font-size: 1.1em; padding-top: 5px;">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
        </tr>
        
        <tr>
            <td class="text-left">Bayar ({{ strtoupper($transaction->payment_method ?? 'TUNAI') }})</td>
            <td class="text-right">Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="text-left">Kembali</td>
            <td class="text-right">Rp {{ number_format($transaction->amount_change, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer text-center">
        <p class="font-bold" style="font-size: 1.1em; margin-bottom: 5px;">Terima Kasih!</p>
        <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
    </div>

</body>
</html>