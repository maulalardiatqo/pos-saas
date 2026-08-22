<?php

namespace App\Filament\Tenant\Resources\Assets\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\RawJs;
use Filament\Facades\Filament;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Utama')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Aset')
                            ->placeholder('Contoh: Printer Epson L3110 / Meja Kasir')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('asset_code')
                            ->label('Kode Aset / Serial Number')
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id))
                            ->maxLength(255),
                            
                        Select::make('category')
                            ->label('Kategori')
                            ->options([
                                'Elektronik' => 'Elektronik',
                                'Furnitur'   => 'Furnitur',
                                'Mesin'      => 'Mesin',
                                'Kendaraan'  => 'Kendaraan',
                                'Lainnya'    => 'Lainnya',
                            ])
                            ->required(),
                            
                        Select::make('outlet_id')
                            ->label('Penempatan (Outlet)')
                            ->relationship('outlet', 'name', fn($query) => $query->where('company_id', Filament::getTenant()->id))
                            ->default(fn() => auth()->user()->outlet_id)
                            ->required(),
                    ])->columns(2),

                Section::make('Detail Perolehan & Keuangan')
                    ->schema([
                        Select::make('acquisition_type')
                            ->label('Jenis Perolehan')
                            ->options([
                                'opening'  => 'Aset Bawaan / Saldo Awal (Tidak memotong Kas)',
                                'purchase' => 'Pembelian Baru (Otomatis Memotong Kas)',
                            ])
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->helperText('Pilih "Pembelian Baru" jika aset dibeli dengan uang toko saat ini.'),

                        Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash'     => 'Tunai (Cash)',
                                'qris'     => 'QRIS',
                                'transfer' => 'Transfer Bank',
                            ])
                            ->visible(fn (Get $get) => $get('acquisition_type') === 'purchase')
                            ->required(fn (Get $get) => $get('acquisition_type') === 'purchase')
                            ->dehydrated(false),

                        // ==============================================================
                        // PERBAIKAN: Membatasi Sumber Dana hanya untuk Tenant saat ini
                        // ==============================================================
                        Select::make('account_id')
                            ->label('Sumber Dana (Rekening/Kas)')
                            ->options(fn () => \App\Models\Account::where('company_id', Filament::getTenant()->id)
                                ->where('is_active', true)
                                ->pluck('name', 'id')
                            )
                            ->visible(fn (Get $get) => $get('acquisition_type') === 'purchase')
                            ->required(fn (Get $get) => $get('acquisition_type') === 'purchase')
                            ->searchable()
                            ->preload()
                            ->dehydrated(false),

                        DatePicker::make('purchase_date')
                            ->label('Tanggal Perolehan/Pembelian')
                            ->default(now())
                            ->required(),
                            
                        TextInput::make('purchase_price')
                            ->label('Harga Beli / Nilai Aset')
                            ->required()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters('.')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Section::make('Kondisi')
                    ->schema([
                        Select::make('status')
                            ->label('Status Fisik')
                            ->options([
                                'active'      => 'Aktif / Digunakan',
                                'maintenance' => 'Dalam Perbaikan (Servis)',
                                'broken'      => 'Rusak',
                                'disposed'    => 'Dijual / Dibuang',
                            ])
                            ->default('active')
                            ->required(),
                            
                        Textarea::make('notes')
                            ->label('Catatan Fisik (Opsional)')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}