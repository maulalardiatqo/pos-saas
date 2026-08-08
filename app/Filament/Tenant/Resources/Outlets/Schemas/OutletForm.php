<?php

namespace App\Filament\Tenant\Resources\Outlets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden; 
use Filament\Forms\Components\Select; // <-- Jangan lupa import Select
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OutletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('code')
                    ->default(fn () => 'OUT-' . date('Ymd-His') . '-' . strtoupper(Str::random(4)))
                    ->dehydrated(),

                TextInput::make('name')
                    ->label('Nama Outlet')
                    ->required()
                    ->placeholder('Contoh: Cabang Bandung Utama')
                    ->maxLength(100),

                // --- TAMBAHAN UNTUK TIPE OUTLET ---
                Select::make('type')
                    ->label('Tipe Outlet')
                    ->options([
                        'store' => 'Toko Fisik (Kasir/POS)',
                        'warehouse' => 'Gudang Utama',
                        'canvas' => 'Mobil Sales / Kanvas',
                    ])
                    ->default('store')
                    ->required()
                    ->helperText('Pilih "Mobil Sales" jika ini adalah kendaraan untuk sales keliling.'),
                // -----------------------------------

                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->tel()
                    ->default(null)
                    ->placeholder('Contoh: 08123456789')
                    ->maxLength(20),

                Textarea::make('address')
                    ->label('Alamat Lengkap')
                    ->default(null)
                    ->columnSpanFull()
                    ->placeholder('Tuliskan alamat lengkap outlet di sini...'),

                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }
}