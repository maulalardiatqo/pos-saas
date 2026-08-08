<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Pages;

use App\Filament\Tenant\Resources\LoyaltyRewards\LoyaltyRewardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRewards extends ListRecords
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
