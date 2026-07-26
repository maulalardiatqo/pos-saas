<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Models\Role;
use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; 
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Role')
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan / Tenant')
                            ->relationship('company', 'name')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->live(), 

                        TextInput::make('name')
                            ->label('Nama Jabatan (Role)')
                            ->placeholder('Contoh: Manager Operasional')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('code')
                            ->label('Kode Unik')
                            ->placeholder('Contoh: manager_ops')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            // Kunci agar kode role sistem bawaan tidak bisa diubah
                            ->disabled(fn (?Role $record) => $record?->is_system ?? false),
                    ])
                    ->columns(2),

                Section::make('Hak Akses (Permissions)')
                    ->description('Centang hak akses yang tersedia sesuai paket langganan tenant ini.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('Pilih Hak Akses')
                            ->relationship(
                                name: 'permissions', 
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $companyId = $get('company_id');
                                    
                                    // 1. Jika belum memilih Company
                                    if (!$companyId) {
                                        // TEGASKAN NAMA TABEL: permissions.id
                                        return $query->whereNull('permissions.id'); 
                                    }

                                    $company = Company::with('subscriptionPlan')->find($companyId);

                                    // 2. Jika company tidak punya paket langganan
                                    if (!$company || !$company->subscriptionPlan) {
                                        return $query->whereNull('permissions.id');
                                    }

                                    // 3. Ambil konfigurasi module dari JSON
                                    $modules = $company->subscriptionPlan->features['modules'] ?? [];
                                    
                                    $allowedModules = [];
                                    foreach ($modules as $moduleName => $isAllowed) {
                                        if ($isAllowed === true) {
                                            $allowedModules[] = $moduleName;
                                        }
                                    }

                                    // 4. Jika tidak ada modul yang aktif
                                    if (empty($allowedModules)) {
                                        return $query->whereNull('permissions.id');
                                    }

                                    // 5. Tampilkan hanya permission dari modul yang diizinkan
                                    return $query->whereIn('permissions.module', $allowedModules);
                                }
                            )
                            ->columns(3)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }
}