<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Schemas;

use App\Models\SubscriptionPlan;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Informasi Paket')
                    ->schema([

                        TextEntry::make('name')
                            ->label('Nama Paket'),

                        TextEntry::make('code')
                            ->label('Kode Paket'),

                        TextEntry::make('price')
                            ->label('Harga')
                            ->money('IDR'),

                        TextEntry::make('billing_cycle')
                            ->label('Siklus')
                            ->formatStateUsing(
                                fn($state) => ucfirst($state)
                            ),

                        TextEntry::make('is_active')
                            ->label('Status')
                            ->formatStateUsing(
                                fn($state) =>
                                $state ? 'Aktif' : 'Nonaktif'
                            ),

                    ])
                    ->columns(2),


                Section::make('Limits')
                    ->schema([

                        TextEntry::make('features.limits.outlets')
                            ->label('Maks Outlet'),

                        TextEntry::make('features.limits.users')
                            ->label('Maks User'),

                        TextEntry::make('features.limits.products')
                            ->label('Maks Produk'),

                        TextEntry::make('features.limits.warehouses')
                            ->label('Maks Warehouse'),

                    ])
                    ->columns(4),


                Section::make('Features JSON')
                    ->schema([

                        TextEntry::make('features')
                            ->label('')
                            ->formatStateUsing(
                                fn($state) =>
                                json_encode(
                                    $state,
                                    JSON_PRETTY_PRINT
                                )
                            ),

                    ]),

                TextEntry::make('created_at')
                    ->dateTime(),

                TextEntry::make('updated_at')
                    ->dateTime(),

                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(
                        fn(SubscriptionPlan $record) =>
                        $record->trashed()
                    ),

            ]);
    }
}