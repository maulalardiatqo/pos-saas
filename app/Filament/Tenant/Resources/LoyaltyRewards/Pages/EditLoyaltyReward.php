<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Pages;

use App\Filament\Tenant\Resources\LoyaltyRewards\LoyaltyRewardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyReward extends EditRecord
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
