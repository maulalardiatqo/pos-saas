<?php

namespace App\Filament\Tenant\Resources\Suppliers\Schemas;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select; 

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $isOwnerOrPlatform = $user && ($user->isOwner() || $user->isPlatform());

        return $schema
            ->components([
                Grid::make(3)->schema([
                    // KOLOM KIRI: Identitas Perusahaan (Lebar 2/3)
                    Grid::make(1)->schema([
                        Section::make('Informasi Pemasok')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('code')
                                        ->label('Kode Pemasok')
                                        ->required()
                                        ->maxLength(50)
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                            return $rule->where('company_id', Filament::getTenant()->id);
                                        })
                                        ->default(fn () => 'SUP-' . strtoupper(str()->random(5))),

                                    TextInput::make('name')
                                        ->label('Nama Perusahaan')
                                        ->required()
                                        ->maxLength(150)
                                        ->placeholder('Contoh: PT. ABC Indonesia'),
                                        
                                    // SATU INPUT OUTLET UNTUK SEMUA (Dengan logika Disabled & Dehydrated)
                                    Select::make('outlet_id')
                                        ->label('Lokasi Outlet / Cabang')
                                        ->relationship('outlet', 'name')
                                        ->helperText('Kosongkan jika supplier ini menyuplai barang ke semua cabang (Global).')
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => $user?->outlet_id)
                                        ->disabled(!$isOwnerOrPlatform) // Karyawan biasa tidak bisa mengubah isinya
                                        ->dehydrated(), // Memaksa Filament untuk tetap menyimpan nilainya ke database meskipun disabled
                                ]),

                                Textarea::make('address')
                                    ->label('Alamat Lengkap')
                                    ->rows(3),
                            ]),
                    ])->columnSpan(2),

                    // KOLOM KANAN: Kontak Personal & Status (Lebar 1/3)
                    Grid::make(1)->schema([
                        Section::make('Kontak PIC')
                            ->description('Orang yang bisa dihubungi.')
                            ->schema([
                                TextInput::make('contact_person')
                                    ->label('Nama Kontak (PIC)')
                                    ->maxLength(100)
                                    ->placeholder('Contoh: Budi Santoso'),

                                TextInput::make('phone')
                                    ->label('Nomor Telepon')
                                    ->tel()
                                    ->maxLength(20),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(100),
                            ]),

                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Status Aktif')
                                    ->default(true)
                                    ->helperText('Matikan jika sudah tidak bekerja sama dengan pemasok ini.'),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }
}