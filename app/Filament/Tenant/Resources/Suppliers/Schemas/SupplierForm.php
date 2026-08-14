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
use Illuminate\Database\Eloquent\Builder; // <-- Tambahan untuk query builder

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
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
                                        
                                    // INPUT OUTLET UNTUK SEMUA USER (Owner & Staf)
                                    Select::make('outlet_id')
                                        ->label('Lokasi Outlet / Cabang')
                                        ->relationship('outlet', 'name', function (Builder $query) use ($isOwnerOrPlatform, $user) {
                                            // Jika BUKAN owner/platform, filter agar hanya muncul outlet user tersebut
                                            if (!$isOwnerOrPlatform) {
                                                $query->where('id', $user->outlet_id);
                                            }
                                            return $query;
                                        })
                                        // Placeholder ini otomatis bernilai `null` jika dipilih dan dikirim ke database
                                        ->placeholder('Supplier Umum (Semua Cabang)')
                                        ->helperText('kosongkan jika supplier ini menyuplai barang ke semua cabang (Global).')
                                        ->searchable()
                                        ->preload(),
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