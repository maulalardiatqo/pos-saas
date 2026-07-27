<?php

namespace App\Filament\Tenant\Pages\Settings;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

// 1. Antarmuka dan Trait Schemas (Standar v4)
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;

// 2. Komponen Struktur/Layout dari Schemas
use Filament\Schemas\Components\Section;

// 3. Komponen Input dari Forms
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

// 4. Action untuk tombol simpan
use Filament\Actions\Action;

class MidtransSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Pengguna';
    protected static ?string $navigationLabel = 'Integrasi Midtrans';
    protected static ?string $title = 'Pengaturan Midtrans (QRIS & Payment)';
    protected static ?string $slug = 'settings/midtrans';
    protected static ?int $navigationSort = 110;

    protected string $view = 'filament.tenant.pages.settings.midtrans-settings';

    public ?array $data = [];

    /*
    |--------------------------------------------------------------------------
    | Keamanan & Otorisasi
    |--------------------------------------------------------------------------
    */
    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Cek apakah user adalah Owner
        $isOwner = $user && $user->isOwner(); 

        // Cek fitur QRIS
        $hasQrisFeature = false;
        if ($tenant) {
            $hasQrisFeature = $tenant->hasFeature('qris'); 
        }

        return $isOwner && $hasQrisFeature;
    }

    /*
    |--------------------------------------------------------------------------
    | Inisialisasi Data
    |--------------------------------------------------------------------------
    */
    public function mount(): void
    {
        $tenant = Filament::getTenant();
        
        $this->form->fill([
            'midtrans_merchant_id' => $tenant->midtrans_merchant_id,
            'midtrans_client_key' => $tenant->midtrans_client_key,
            'midtrans_server_key' => $tenant->midtrans_server_key,
            'midtrans_is_production' => $tenant->midtrans_is_production,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Skema Form (UI)
    |--------------------------------------------------------------------------
    */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kredensial API Midtrans')
                    ->description('Masukkan API Key dari dashboard Midtrans Anda. Gunakan mode Sandbox untuk pengujian.')
                    ->schema([
                        TextInput::make('midtrans_merchant_id')
                            ->label('Merchant ID')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('midtrans_client_key')
                            ->label('Client Key')
                            ->required()
                            ->maxLength(255),
                            
                        TextInput::make('midtrans_server_key')
                            ->label('Server Key')
                            ->required()
                            ->password()
                            ->revealable()
                            ->maxLength(255),

                        Toggle::make('midtrans_is_production')
                            ->label('Mode Produksi (Live)')
                            ->helperText('Aktifkan ini HANYA jika Anda sudah siap menerima pembayaran uang asli (Production). Biarkan nonaktif untuk pengujian (Sandbox).')
                            ->onColor('success')
                            ->offColor('danger')
                            ->default(false),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    /*
    |--------------------------------------------------------------------------
    | Aksi Tombol & Simpan
    |--------------------------------------------------------------------------
    */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();
        
        $tenant->update($data);

        Notification::make()
            ->title('Berhasil Disimpan')
            ->body('Pengaturan integrasi Midtrans telah diperbarui.')
            ->success()
            ->send();
    }
}