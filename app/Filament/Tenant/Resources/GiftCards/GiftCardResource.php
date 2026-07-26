<?php

namespace App\Filament\Tenant\Resources\GiftCards;

use App\Models\GiftCard;
use Filament\Resources\Resource;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Tenant\Resources\GiftCards\Schemas\GiftCardForm;
use App\Filament\Tenant\Resources\GiftCards\Tables\GiftCardsTable;

class GiftCardResource extends Resource
{
    protected static ?string $model = GiftCard::class;

    protected static ?string $slug = 'gift-cards';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-credit-card';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'CRM & Pelanggan';
    }

    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        // Gembok berbasis JSON Plan berlangganan
        $isFeatureEnabled = data_get($tenant?->subscriptionPlan?->features, 'crm.gift_card') === true;
        $hasPermission = $user->hasPermission('crm.gift_card');

        return $isFeatureEnabled && $hasPermission;
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }

    public static function form(Schema $schema): Schema
    {
        return GiftCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GiftCardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Tenant\Resources\GiftCards\Pages\ManageGiftCards::route('/'),
        ];
    }
}