<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;

class ManagePointSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM & Pelanggan';
    
    protected static ?string $navigationLabel = 'Poin & Loyalitas';

    protected string $view = 'filament.tenant.pages.manage-point-settings';

    public ?array $data = [];

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return 'Pengaturan Poin Pelanggan';
    }

   public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // 2. CEK OTORISASI HALAMAN (GEMBOK PAKET + HAK AKSES USER)
    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        // Cek Gembok Paket: Apakah Tenant ini berlangganan fitur CRM/Poin?
        if (!$tenant || !$tenant->hasFeature('crm.point')) {
            return false;
        }

        $user = auth()->user();

        // Hanya Owner, Platform Admin, atau karyawan yang punya hak akses 'crm.point' yang bisa mengakses
        return $user && ($user->isOwner() || $user->isPlatform() || $user->hasPermission('crm.point'));
    }

    public function mount(): void
    {
        /** @var \App\Models\Company $company */
        $company = filament()->getTenant();

        abort_if(! $company->hasFeature('crm.point'), 403, 'Fitur Poin Pelanggan tidak tersedia dalam paket toko Anda.');

        $this->form->fill([
            'is_loyalty_enabled'   => $company->is_loyalty_enabled,
            'loyalty_spend_amount' => $company->loyalty_spend_amount,
            'loyalty_point_earned' => $company->loyalty_point_earned,
            'loyalty_point_value'  => $company->loyalty_point_value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Skema Loyalitas')
                    ->description('Atur bagaimana pelanggan mendapatkan dan menukarkan poin hadiah.')
                    ->schema([
                        Toggle::make('is_loyalty_enabled')
                            ->label('Aktifkan Fitur Poin Pelanggan')
                            ->helperText('Jika dimatikan, transaksi kasir tidak akan menghasilkan poin.')
                            ->live()
                            ->default(false),

                        Grid::make(3)
                            ->visible(fn (Get $get) => $get('is_loyalty_enabled'))
                            ->schema([
                                TextInput::make('loyalty_spend_amount')
                                    ->label('Syarat Belanja (Kelipatan)')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->default(10000)
                                    ->required(),

                                TextInput::make('loyalty_point_earned')
                                    ->label('Poin yang Didapat')
                                    ->suffix('Poin')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),

                                TextInput::make('loyalty_point_value')
                                    ->label('Nilai 1 Poin (Potongan Langsung)')
                                    ->helperText('Nominal potongan harga di kasir. Ketik 0 jika poin hanya khusus ditukar di Katalog Hadiah.')
                                    ->prefix('Rp')
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->default(0)
                                    ->required(),
                            ]),
                    ])
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (isset($data['loyalty_spend_amount'])) {
            $data['loyalty_spend_amount'] = (float) str_replace('.', '', $data['loyalty_spend_amount']);
        }
        if (isset($data['loyalty_point_value'])) {
            $data['loyalty_point_value'] = (float) str_replace('.', '', $data['loyalty_point_value']);
        }

        $company = filament()->getTenant();
        $company->update($data);

        Notification::make()
            ->success()
            ->title('Berhasil disimpan')
            ->body('Pengaturan poin telah diperbarui.')
            ->send();
    }
}