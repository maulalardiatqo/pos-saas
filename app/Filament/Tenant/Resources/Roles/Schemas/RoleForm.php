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

            // --- LANGKAH 1: Validasi Saklar Utama Modul (Master Switch) ---
            // Petakan nama modul seeder ke switch induk di json "modules"
            $mainModuleKey = match($module) {
                'roles'       => 'users',
                'outlets'     => 'settings',
                'promotions'  => 'promotions',
                'kitchen'     => 'kitchen',
                default       => $module
            };

            // Jika saklar utama di JSON bernilai false, potong jalur (sembunyikan seluruh modul)
            if (data_get($features, "modules.{$mainModuleKey}") !== true) {
                return false;
            }

            // --- LANGKAH 2: Validasi Fitur Granular / Sub-Modul ---
            // Cek apakah permission ini memiliki aturan spesifik di dalam objek JSON
            
            // Sub-fitur Produk
            if (str_starts_with($code, 'products.') && ! in_array($code, ['products.view', 'products.create', 'products.edit', 'products.delete'])) {
                $subKey = str_replace('products.', '', $code); // Cth: category, brand
                return data_get($features, "products.{$subKey}") === true;
            }

            // Sub-fitur Inventori
            if (str_starts_with($code, 'inventory.')) {
                $subKey = str_replace('inventory.', '', $code);
                return data_get($features, "inventory.{$subKey}") === true;
            }

            // Sub-fitur Keuangan
            if (str_starts_with($code, 'finance.')) {
                $subKey = str_replace('finance.', '', $code);
                return data_get($features, "finance.{$subKey}") === true;
            }

            // Sub-fitur Pembelian
            if (str_starts_with($code, 'purchase.')) {
                $subKey = str_replace('purchase.', '', $code);
                return data_get($features, "purchase.{$subKey}") === true;
            }

            // Sub-fitur CRM
            if (str_starts_with($code, 'crm.')) {
                $subKey = str_replace('crm.', '', $code);
                return data_get($features, "crm.{$subKey}") === true;
            }

            // Sub-fitur Laporan (Reports)
            if (str_starts_with($code, 'reports.')) {
                $subKey = str_replace('reports.', '', $code);
                return data_get($features, "reports.{$subKey}") === true;
            }

            // Sub-fitur Advanced
            if (str_starts_with($code, 'advanced.')) {
                $subKey = str_replace('advanced.', '', $code);
                return data_get($features, "advanced.{$subKey}") === true;
            }

            // Jika lolos semua pemeriksaan di atas (fitur core bawaan modul yang aktif), maka tampilkan
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
        // 6. Render Form
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