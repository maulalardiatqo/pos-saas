<?php

namespace App\Observers;

use App\Models\Company;
use Illuminate\Support\Str;

class CompanyObserver
{
   public function created(Company $company): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($company) {
            
            /*
            |--------------------------------------------------------------------------
            | 1. Create Default Role (Hanya Owner)
            |--------------------------------------------------------------------------
            */
            $ownerRole = $company->roles()->create([
                'code' => 'owner',
                'name' => 'Owner',
                'is_system' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. Create Default Outlet
            |--------------------------------------------------------------------------
            */
            $outlet = $company->outlets()->create([
                'name' => 'Outlet Utama',
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. Create Owner User
            |--------------------------------------------------------------------------
            */
            $company->users()->create([
                'name' => 'Owner ' . $company->name,
                'email' => $company->email,
                'password' => 'default123', 
                'role_id' => $ownerRole->id,
                'outlet_id' => $outlet->id,
                'user_type' => 'tenant',
                'email_verified_at' => now(),
            ]);

        });
    }


    public function updated(Company $company): void
    {

    }


    public function deleted(Company $company): void
    {

    }


    public function restored(Company $company): void
    {

    }


    public function forceDeleted(Company $company): void
    {

    }
}