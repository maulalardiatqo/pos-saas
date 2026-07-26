<?php

namespace App\Filament\Tenant\Resources\Users\Pages;

use App\Filament\Tenant\Resources\Users\UsersResource;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UsersResource::class;

    protected function getHeaderActions(): array
    {
        // 1. Ambil Data Limit
        $tenant = Filament::getTenant();
        $currentUsers = User::where('company_id', $tenant?->id)->count();
        $maxUsers = data_get($tenant?->subscriptionPlan?->features, 'limits.users');
        
        // 2. Cek apakah limit tercapai
        $isLimitReached = is_numeric($maxUsers) && $currentUsers >= $maxUsers;

        return [
            Actions\CreateAction::make()
                // Jika limit tercapai, matikan tombol (abu-abu)
                ->disabled($isLimitReached)
                // Beri penjelasan ramah saat kursor diarahkan ke tombol
                ->tooltip($isLimitReached ? "Batas maksimal {$maxUsers} karyawan telah tercapai. Silakan upgrade paket Anda." : null),
        ];
    }
}