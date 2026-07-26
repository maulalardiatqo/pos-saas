<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; // <-- UBAH IMPORT-NYA KE SINI
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email Login')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    // ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),

                TextInput::make('pin')
                    ->label('PIN Kasir (POS)')
                    ->password()
                    ->numeric()
                    ->maxLength(10)
                    ->placeholder('Contoh: 1234')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state)),

                Select::make('company_id')
                    ->label('Perusahaan / Tenant')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->live() 
                    ->required(),
                Select::make('role_id')
                    ->label('Hak Akses (Role)')
                    ->relationship(
                        name: 'role', 
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query, Get $get) => $query->where('company_id', $get('company_id'))
                    )
                    ->disabled(fn (Get $get): bool => ! $get('company_id'))
                    ->preload()
                    ->searchable()
                    ->required(),

                Select::make('outlet_id')
                    ->label('Penempatan Outlet (Kosongkan jika Owner/Pusat)')
                    ->relationship(
                        name: 'outlet', 
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query, Get $get) => $query->where('company_id', $get('company_id'))
                    )
                    ->disabled(fn (Get $get): bool => ! $get('company_id'))
                    ->preload()
                    ->searchable(),
            ]);
    }
}