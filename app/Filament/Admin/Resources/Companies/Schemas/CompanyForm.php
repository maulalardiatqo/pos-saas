<?php

namespace App\Filament\Admin\Resources\Companies\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Nama Perusahaan')
                    ->placeholder('Contoh: PT Maju Jaya')
                    ->required()
                    ->maxLength(255),


                TextInput::make('email')
                    ->label('Email Perusahaan / Owner')
                    ->email()
                    ->placeholder('contoh@email.com')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),


                Select::make('subscription_plan_id')
                    ->label('Paket Berlangganan')
                    ->relationship(
                        'subscriptionPlan',
                        'name'
                    )
                    ->preload()
                    ->required(),


                Select::make('status')
                    ->label('Status Akun')
                    ->options([
                        'active' => 'Aktif',
                        'suspended' => 'Ditangguhkan',
                        'expired' => 'Kedaluwarsa'
                    ])
                    ->default('active')
                    ->required(),


                DatePicker::make('valid_until')
                    ->label('Masa Berlaku Sampai')
                    ->placeholder('Pilih tanggal kadaluwarsa'),

            ])
            ->columns(2);
    }
}