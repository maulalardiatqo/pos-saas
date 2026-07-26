<?php

namespace App\Filament\Tenant\Resources\Outlets\Pages;

use App\Filament\Tenant\Resources\Outlets\OutletResource;
use App\Models\Outlet;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListOutlets extends ListRecords
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = Filament::getTenant();
        $currentOutlets = Outlet::where('company_id', $tenant?->id)->count();
        
        // Baca limit dari JSON Plan
        $maxOutlets = data_get($tenant?->subscriptionPlan?->features, 'limits.outlets');
        
        $isLimitReached = is_numeric($maxOutlets) && $currentOutlets >= $maxOutlets;

        return [
            Actions\CreateAction::make()
                ->disabled($isLimitReached)
                ->tooltip($isLimitReached ? "Batas maksimal {$maxOutlets} cabang telah tercapai. Silakan upgrade paket." : null),
        ];
    }
}