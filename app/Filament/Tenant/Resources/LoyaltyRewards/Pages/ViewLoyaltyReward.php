<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Pages;

use App\Filament\Tenant\Resources\LoyaltyRewards\LoyaltyRewardResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLoyaltyReward extends ViewRecord
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
