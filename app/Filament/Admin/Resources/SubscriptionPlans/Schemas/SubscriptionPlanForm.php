<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Informasi Paket')
                ->schema([

                    TextInput::make('name')
                        ->label('Nama Paket')
                        ->placeholder('Contoh: Standard Package')
                        ->required()
                        ->maxLength(100),


                    TextInput::make('code')
                        ->label('Kode Paket')
                        ->placeholder('Contoh: standard')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),


                    TextInput::make('description')
                        ->label('Deskripsi')
                        ->placeholder('Deskripsi paket')
                        ->columnSpanFull(),


                    TextInput::make('price')
                        ->label('Harga Bulanan')
                        ->numeric()
                        ->prefix('Rp')
                        ->required(),


                    Select::make('billing_cycle')
                        ->label('Siklus Tagihan')
                        ->options([
                            'monthly' => 'Bulanan',
                            'yearly' => 'Tahunan',
                        ])
                        ->default('monthly')
                        ->required(),


                    Toggle::make('is_active')
                        ->label('Status Aktif')
                        ->default(true),


                    TextInput::make('sort_order')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0),

                ])
                ->columns(2),

                Section::make('Modules')
                    ->schema([

                        Toggle::make('features.modules.sales')->label('Sales')->default(true),
                        Toggle::make('features.modules.products')->label('Products')->default(true),
                        Toggle::make('features.modules.customers')->label('Customers')->default(true),
                        Toggle::make('features.modules.suppliers')->label('Suppliers')->default(false),

                        Toggle::make('features.modules.purchase')->label('Purchase')->default(false),
                        Toggle::make('features.modules.inventory')->label('Inventory')->default(false),
                        Toggle::make('features.modules.finance')->label('Finance')->default(false),
                        Toggle::make('features.modules.crm')->label('CRM')->default(false),

                        Toggle::make('features.modules.reports')->label('Reports')->default(true),
                        Toggle::make('features.modules.users')->label('Users')->default(true),
                        Toggle::make('features.modules.settings')->label('Settings')->default(true),

                        Toggle::make('features.modules.promotions')->label('Promotions')->default(false),
                        Toggle::make('features.modules.kitchen')->label('Kitchen Display')->default(false),
                        Toggle::make('features.modules.ecommerce')->label('E-Commerce')->default(false),
                        Toggle::make('features.modules.accounting')->label('Accounting')->default(false),
                        Toggle::make('features.modules.mobilepos')->label('Mobile POS')->default(false),
                    ])
                    ->columns(3),
                Section::make('Products')
                    ->schema([
                        Toggle::make('features.products.category')->label('Category'),
                        Toggle::make('features.products.brand')->label('Brand'),
                        Toggle::make('features.products.variant')->label('Variant'),
                        Toggle::make('features.products.bundle')->label('Bundle Product'),
                        Toggle::make('features.products.recipe')->label('Recipe / BOM'),
                        Toggle::make('features.products.barcode')->label('Barcode'),
                        Toggle::make('features.products.multi_uom')->label('Multiple UOM'),
                    ])
                    ->columns(3),
                Section::make('Inventory')
                    ->schema([
                    Toggle::make('features.inventory.adjustment')->label('Stock Adjustment'),
                    Toggle::make('features.inventory.transfer')->label('Stock Transfer'),
                    Toggle::make('features.inventory.opname')->label('Stock Opname'),
                    Toggle::make('features.inventory.history')->label('Stock History'),
                    Toggle::make('features.inventory.stock_card')->label('Stock Card'),
                    Toggle::make('features.inventory.warehouse')->label('Multiple Warehouse'),
                    Toggle::make('features.inventory.batch')->label('Batch Number'),
                    Toggle::make('features.inventory.expiry')->label('Expiry Date'),
                    Toggle::make('features.inventory.serial')->label('Serial Number'),
                    Toggle::make('features.inventory.reorder')->label('Reorder Level'),
                    ])
                    ->columns(2),

                Section::make('Finance')
                    ->schema([

                        Toggle::make('features.finance.cash_in')->label('Cash In'),
                        Toggle::make('features.finance.cash_out')->label('Cash Out'),
                        Toggle::make('features.finance.expense')->label('Expense'),
                        Toggle::make('features.finance.revenue')->label('Revenue'),
                        Toggle::make('features.finance.closing_shift')->label('Closing Shift'),
                        Toggle::make('features.finance.closing_day')->label('Closing Day'),
                        Toggle::make('features.finance.bank')->label('Bank Account'),
                        Toggle::make('features.finance.payment_method')->label('Payment Method'),
                        Toggle::make('features.finance.tax')->label('Tax'),
                        Toggle::make('features.finance.journal')->label('Journal'),
                    ])
                    ->columns(3),

                Section::make('Purchase')
                    ->schema([

                        Toggle::make('features.purchase.po')->label('Purchase Order'),
                        Toggle::make('features.purchase.goods_receive')->label('Goods Receive'),
                        Toggle::make('features.purchase.return_supplier')->label('Return Supplier'),
                        Toggle::make('features.purchase.request')->label('Purchase Request'),
                        Toggle::make('features.purchase.invoice')->label('Supplier Invoice'),
                    ])
                    ->columns(3),

                Section::make('CRM')
                    ->schema([

                        Toggle::make('features.crm.member')->label('Member'),
                        Toggle::make('features.crm.point')->label('Point'),
                        Toggle::make('features.crm.loyalty')->label('Loyalty'),
                        Toggle::make('features.crm.voucher')->label('Voucher'),
                        Toggle::make('features.crm.membership')->label('Membership'),
                        Toggle::make('features.crm.gift_card')->label('Gift Card'),
                        Toggle::make('features.crm.coupon')->label('Coupon'),
                    ])
                    ->columns(3),
                    Section::make('Reports')
                    ->schema([
                        Toggle::make('features.reports.sales')->label('Sales'),
                        Toggle::make('features.reports.inventory')->label('Inventory'),
                        Toggle::make('features.reports.finance')->label('Finance'),
                        Toggle::make('features.reports.purchase')->label('Purchase'),
                        Toggle::make('features.reports.customer')->label('Customer'),
                        Toggle::make('features.reports.product')->label('Product'),

                    ])
                    ->columns(3),
                Section::make('Advanced')
                ->schema([
                    Toggle::make('features.advanced.audit_log')
                        ->label('Audit Log'),
                    Toggle::make('features.advanced.activity_log')
                        ->label('Activity Log'),
                    Toggle::make('features.advanced.approval')
                        ->label('Approval Workflow'),
                    Toggle::make('features.advanced.multi_currency')
                        ->label('Multi Currency'),
                    Toggle::make('features.advanced.multi_language')
                        ->label('Multi Language'),
                    Toggle::make('features.advanced.offline_mode')
                        ->label('Offline Mode'),
                    Toggle::make('features.advanced.backup')
                        ->label('Automatic Backup')
                ])
                ->columns(3),
                Section::make('Limits')
                    ->schema([
                        TextInput::make('features.limits.outlets')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('features.limits.users')
                            ->numeric()
                            ->default(3)
                            ->required(),
                        TextInput::make('features.limits.products')
                            ->numeric()
                            ->default(500),
                        TextInput::make('features.limits.warehouses')
                            ->numeric()
                            ->default(1),
                        TextInput::make('features.limits.transactions_per_month')
                            ->numeric()
                            ->default(-1)
                            ->helperText('-1 = Unlimited'),
                    ])
                    ->columns(3),

                Section::make('Integrations')
                    ->schema([
                        Toggle::make('features.integrations.qris')->label('QRIS'),
                        Toggle::make('features.integrations.whatsapp')->label('WhatsApp'),
                        Toggle::make('features.integrations.marketplace')->label('Marketplace'),
                        Toggle::make('features.integrations.api')->label('Public API'),
                        Toggle::make('features.integrations.webhook')->label('Webhook'),
                        Toggle::make('features.integrations.accounting')->label('Accounting Integration'),
                    ])
                    ->columns(2),

            ]);
    }
}