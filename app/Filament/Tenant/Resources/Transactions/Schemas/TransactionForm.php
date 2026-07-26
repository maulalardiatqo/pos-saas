<?php

namespace App\Filament\Tenant\Resources\Transactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_id')
                    ->required(),
                TextInput::make('outlet_id')
                    ->required(),
                TextInput::make('user_id')
                    ->required(),
                TextInput::make('pos_session_id')
                    ->default(null),
                TextInput::make('customer_id')
                    ->default(null),
                TextInput::make('supplier_id')
                    ->default(null),
                TextInput::make('transaction_number')
                    ->required(),
                Select::make('type')
                    ->options(['sale' => 'Sale', 'purchase' => 'Purchase', 'refund' => 'Refund'])
                    ->required(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])
                    ->default('completed')
                    ->required(),
                TextInput::make('payment_method')
                    ->required(),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('tax')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('grand_total')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_paid')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('amount_change')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
