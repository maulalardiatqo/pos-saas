<?php

namespace App\Filament\Tenant\Resources\Outlets\Pages;

use App\Filament\Tenant\Resources\Outlets\OutletResource;
use App\Models\Outlet;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOutlet extends CreateRecord
{
    protected static string $resource = OutletResource::class;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $currentOutlets = Outlet::where('company_id', $tenant?->id)->count();
        $maxOutlets = data_get($tenant?->subscriptionPlan?->features, 'limits.outlets');

        if (is_numeric($maxOutlets) && $currentOutlets >= $maxOutlets) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body("Batas maksimal {$maxOutlets} cabang telah tercapai. Silakan upgrade paket.")
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
            return;
        }

        parent::mount();
    }
}