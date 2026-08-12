<?php

namespace App\Filament\Tenant\Resources\Roles\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Facades\Filament;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        // 1. Ambil data fitur aktif dari paket langganan Tenant
        $tenant = Filament::getTenant();
        $features = $tenant?->subscriptionPlan?->features ?? [];

        // 2. Ambil seluruh master permission & saring berdasarkan isi JSON plan secara ketat
        $filteredPermissions = Permission::all()->filter(function ($permission) use ($features) {
            $module = $permission->module;
            $code = $permission->code;

            // --- FILTER: MODUL INTI (SELALU MUNCUL) ---
            $coreModules = ['users', 'roles', 'outlets', 'sales', 'customers', 'suppliers', 'settings', 'reports', 'finance'];
            if (in_array($module, $coreModules)) {
                return true;
            }

            // --- FILTER: MODUL PRODUK ---
            if ($module === 'products') {
                // Fitur dasar produk selalu muncul
                $coreProductFeatures = ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.category', 'products.brand', 'products.multi_uom', 'products.barcode'];
                if (in_array($code, $coreProductFeatures)) {
                    return true;
                }
                
                // Fitur tambahan (bundle, recipe) dicek dari langganan. Default true jika tidak diatur.
                $subKey = str_replace('products.', '', $code); 
                return data_get($features, "products.{$subKey}", true) === true;
            }

            // --- FILTER: MODUL INVENTORY ---
            if ($module === 'inventory') {
                // Fitur history, transfer, adjusment, dan asset
                $subKey = str_replace('inventory.', '', $code);
                return data_get($features, "inventory.{$subKey}", true) === true;
            }

            // --- FILTER: MODUL PEMBELIAN (PURCHASE) ---
            if ($module === 'purchase') {
                return data_get($features, 'modules.purchase', true) === true;
            }

            // --- FILTER: MODUL CRM ---
            if ($module === 'crm') {
                return data_get($features, 'modules.crm', true) === true;
            }

            return true;
        });

        // 3. Kelompokkan permission yang lolos seleksi berdasarkan nama Modul untuk kerapian UI
        $grouped = $filteredPermissions->groupBy('module');

        // 4. Susun komponen UI Checkbox secara horizontal per kelompok modul
        $permissionComponents = [];
        foreach ($grouped as $moduleName => $permissions) {
            $checkboxes = [];
            foreach ($permissions as $permission) {
                $checkboxes[] = Checkbox::make("permission_states.{$permission->id}")
                    ->label($permission->name)
                    ->afterStateHydrated(function (Checkbox $component, $record) use ($permission) {
                        if (! $record) {
                            $component->state(false);
                            return;
                        }
                        $component->state($record->permissions->contains($permission->id));
                    })
                    ->dehydrated(false);
            }
            $permissionComponents[] = Fieldset::make(strtoupper($moduleName))
                ->schema($checkboxes)
                ->columns(4); 
        }

        $permissionComponents[] = Hidden::make('permissions_sync')
            ->dehydrated(false) 
            ->saveRelationshipsUsing(function (Model $record, $livewire) { 
                $formState = $livewire->form->getRawState();
                $checkedIds = [];
                
                if (isset($formState['permission_states'])) {
                    foreach ($formState['permission_states'] as $id => $checked) {
                        if ($checked) {
                            $checkedIds[] = $id;
                        }
                    }
                }
                
                $record->permissions()->sync($checkedIds);
            });
            
        // 5. Render Form
        return $schema
            ->components([
                Section::make('Informasi Jabatan')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Jabatan / Role')
                            ->placeholder('Cth: Kasir Utama, Supervisor Toko')
                            ->required()
                            ->maxLength(100),
                    ]),

                Section::make('Hak Akses (Permissions)')
                    ->description('Pilih menu dan aksi apa saja yang boleh dilakukan oleh jabatan ini. Pilihan otomatis disaring berdasarkan paket langganan aktif toko.')
                    ->schema($permissionComponents),
            ]);
    }
}