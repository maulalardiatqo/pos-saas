<?php

namespace App\Filament\Tenant\Resources\SalesInvoices\Pages;

use Filament\Resources\Pages\ViewRecord;
use App\Filament\Tenant\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;     
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Support\RawJs;
use App\Models\TransactionPayment;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ==============================================
            // TOMBOL BARU: CETAK INVOICE
            // ==============================================
            Action::make('print')
                ->label('Cetak Invoice')
                ->icon('heroicon-o-printer')
                ->color('info')
                // Mengarahkan ke route cetak di tab baru
                ->url(fn () => route('sales-invoice.print', $this->record->id))
                ->openUrlInNewTab(),

            Action::make('terima_pembayaran')
                ->label('Terima Pembayaran')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->hidden(fn () => $this->record->status === 'completed')
                ->form([
                    // ==============================================================
                    // PERBAIKAN: Format uang dan Disabled
                    // ==============================================================
                    TextInput::make('sisa_tagihan')
                        ->label('Sisa Tagihan Saat Ini')
                        ->prefix('Rp')
                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                        ->default(fn () => abs($this->record->amount_change))
                        ->disabled()
                        ->dehydrated(false), // Mencegah field ini terkirim saat submit
                        
                    TextInput::make('amount')
                        ->label('Nominal Dibayar (Cicilan/Lunas)')
                        ->prefix('Rp')
                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                        ->stripCharacters('.')
                        ->required()
                        ->default(fn () => abs($this->record->amount_change)),
                        
                    DatePicker::make('payment_date')
                        ->label('Tanggal Pembayaran')
                        ->default(now())
                        ->required(),
                        
                    Select::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->options(['cash' => 'Cash / Tunai', 'transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'ewallet' => 'E-Wallet'])
                        ->default('transfer')
                        ->required(),
                        
                    Select::make('account_id')
                        ->label('Masuk Ke Rekening / Kas')
                        ->options(Account::where('company_id', filament()->getTenant()->id)->where('is_active', true)->pluck('name', 'id'))
                        ->required(),
                        
                    TextInput::make('notes')
                        ->label('Catatan (Opsional)')
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $amountPaid = (float) $data['amount'];
                        
                        TransactionPayment::create([
                            'company_id'     => $this->record->company_id,
                            'outlet_id'      => $this->record->outlet_id,
                            'transaction_id' => $this->record->id,
                            'account_id'     => $data['account_id'],
                            'user_id'        => auth()->id(),
                            'amount'         => $amountPaid,
                            'payment_date'   => $data['payment_date'],
                            'payment_method' => $data['payment_method'],
                            'notes'          => $data['notes'],
                            'payment_status' => 'success',
                        ]);

                        Account::where('id', $data['account_id'])->increment('balance', $amountPaid);

                        $newTotalPaid = $this->record->amount_paid + $amountPaid;
                        $newChange = $newTotalPaid - $this->record->grand_total;
                        $status = $newTotalPaid >= $this->record->grand_total ? 'completed' : 'pending';

                        $this->record->update([
                            'amount_paid'   => $newTotalPaid,
                            'amount_change' => $newChange,
                            'status'        => $status
                        ]);
                    });

                    Notification::make()->title('Pembayaran berhasil dicatat!')->success()->send();
                })
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Invoice')
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema([
                        TextEntry::make('transaction_number')->label('No. Invoice')->weight('bold')->color('primary'),
                        TextEntry::make('created_at')->label('Tanggal')->dateTime('d M Y'),
                        TextEntry::make('customer.name')->label('Pelanggan')->weight('bold'),
                        TextEntry::make('status')->label('Status Pembayaran')
                            ->badge()
                            ->color(fn ($state) => $state === 'completed' ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state) => $state === 'completed' ? 'LUNAS' : 'BELUM LUNAS'),
                        TextEntry::make('notes')->label('Catatan / Jatuh Tempo'),
                        
                        // Menambahkan info poin ke tampilan detail
                        TextEntry::make('points_used')->label('Poin Ditukar')->badge()->color('info')->visible(fn($record) => $record->points_used > 0),
                        TextEntry::make('point_discount_amount')->label('Diskon Poin')->money('IDR', locale: 'id')->color('success')->visible(fn($record) => $record->point_discount_amount > 0),
                        
                        TextEntry::make('grand_total')->label('Total Tagihan')->money('IDR', locale: 'id')->weight('bold'),
                        TextEntry::make('amount_paid')->label('Sudah Dibayar')->money('IDR', locale: 'id')->color('success'),
                        TextEntry::make('amount_change')->label('Sisa Tagihan (Hutang)')
                            ->getStateUsing(fn ($record) => abs($record->amount_change))
                            ->money('IDR', locale: 'id')->color('danger')->weight('bold'),
                    ]),
                
                Tabs::make('InvoiceDetails')
                    ->tabs([
                        Tab::make('Rincian Barang (Item)')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('item_name')
                                            ->label('Nama Barang')
                                            ->weight('bold')
                                            ->columnSpan(3),
                                        
                                        TextEntry::make('qty')
                                            ->label('Qty')
                                            ->columnSpan(1),
                                        
                                        TextEntry::make('uom.name')
                                            ->label('Satuan')
                                            ->default('-')
                                            ->columnSpan(2),
                                        
                                        TextEntry::make('selling_price')
                                            ->label('Harga Satuan')
                                            ->money('IDR', locale: 'id')
                                            ->columnSpan(2),
                                        
                                        TextEntry::make('discount')
                                            ->label('Diskon Item')
                                            ->getStateUsing(fn ($record) => ($record->qty * $record->selling_price) - $record->subtotal)
                                            ->money('IDR', locale: 'id')
                                            ->color('danger')
                                            ->columnSpan(2),
                                        
                                        TextEntry::make('subtotal')
                                            ->label('Total Bersih')
                                            ->money('IDR', locale: 'id')
                                            ->weight('bold')
                                            ->color('primary')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(12)
                            ]),

                        Tab::make('Riwayat Pembayaran (Billing)')
                            ->icon('heroicon-o-credit-card')
                            ->badge(fn () => $this->record->payments()->count()) 
                            ->badgeColor('success')
                            ->schema([
                                RepeatableEntry::make('payments')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('payment_date')->label('Tanggal Bayar')->dateTime('d M Y - H:i')->columnSpan(3),
                                        TextEntry::make('amount')->label('Nominal Dibayar')->money('IDR', locale: 'id')->weight('bold')->color('success')->columnSpan(3),
                                        TextEntry::make('payment_method')->label('Metode')->badge()->columnSpan(2),
                                        TextEntry::make('account.name')->label('Masuk Rekening/Kas')->columnSpan(2),
                                        TextEntry::make('user.name')->label('Penerima (Kasir)')->columnSpan(2),
                                    ])
                                    ->columns(12)
                            ]),
                    ])
            ]);
    }
}