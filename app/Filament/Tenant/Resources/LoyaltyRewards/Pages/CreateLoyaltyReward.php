<?php

namespace App\Filament\Tenant\Resources\LoyaltyRewards\Pages;

use App\Filament\Tenant\Resources\LoyaltyRewards\LoyaltyRewardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoyaltyReward extends CreateRecord
{
    protected static string $resource = LoyaltyRewardResource::class;
}
