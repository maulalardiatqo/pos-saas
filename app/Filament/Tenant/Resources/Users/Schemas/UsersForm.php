<?php

namespace App\Filament\Tenant\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Hash;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class UsersForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Akun')
                    ->description('Data profil dan kredensial login karyawan.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            // Email harus unik di seluruh sistem (karena dipakai login)
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                // Hanya wajib diisi saat membuat user baru
                                ->required(fn (string $context): bool => $context === 'create')
                                // Otomatis enkripsi password sebelum masuk database
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                // Jangan ubah password jika inputnya dibiarkan kosong saat edit
                                ->dehydrated(fn ($state) => filled($state)),

                            TextInput::make('pin')
                                ->label('PIN Kasir (Opsional)')
                                ->password()
                                ->revealable()
                                ->numeric()
                                ->maxLength(10)
                                ->helperText('Digunakan untuk login cepat di aplikasi kasir (POS).'),
                        ]),
                    ])->columns(2),

                Section::make('Penempatan & Hak Akses')
                    ->schema([
                        Select::make('role_id')
                            ->label('Jabatan / Role')
                            ->relationship(
                                name: 'role', 
                                titleAttribute: 'name',
                                // Memaksa query hanya mengambil role milik toko ini
                                modifyQueryUsing: fn (Builder $query) => $query->where('company_id', Filament::getTenant()->id)
                            )
                            ->required()
                            ->preload()
                            ->searchable(),

                        Select::make('outlet_id')
                            ->label('Penempatan Outlet')
                            ->relationship(
                                name: 'outlet', 
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('company_id', Filament::getTenant()->id)
                            )
                            ->helperText('Kosongkan jika karyawan ini bisa mengakses semua cabang/outlet.')
                            ->preload()
                            ->searchable(),
                    ])->columns(2),
            
            ]);
    }
}