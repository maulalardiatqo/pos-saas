<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ManageCompanySettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan Perusahaan';
    protected static ?string $title = 'Pengaturan Perusahaan';
    protected static ?string $slug = 'settings';
    protected static ?int $navigationSort = 100; 
    protected static string | \UnitEnum | null $navigationGroup = 'Manajemen Pengguna';

    protected string $view = 'filament.tenant.pages.manage-company-settings';

    public ?array $data = [];
    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        return $user && ($user->isOwner() || $user->isPlatform());
    }
    public function mount(): void
    {
        // Ambil data perusahaan yang sedang login (Tenant)
        $company = filament()->getTenant();

        // Isi form dengan data yang ada di database
        $this->form->fill($company->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        // TAB 1: INFORMASI UMUM
                        Tabs\Tab::make('Profil Bisnis')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                FileUpload::make('logo')
                                    ->label('Logo Perusahaan')
                                    ->image()
                                    ->disk('public') // Sesuai dengan konfigurasi kita sebelumnya
                                    ->directory('logos')
                                    ->maxSize(2048)
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Nama Perusahaan/Toko')
                                        ->required()
                                        ->maxLength(150),

                                    TextInput::make('email')
                                        ->label('Email Bisnis')
                                        ->email()
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('phone')
                                        ->label('Nomor Telepon/WA')
                                        ->tel()
                                        ->maxLength(30),
                                ]),

                                Textarea::make('address')
                                    ->label('Alamat Lengkap')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        // TAB 2: PENGATURAN POS & STRUK
                        Tabs\Tab::make('POS & Struk')
                            ->icon('heroicon-o-printer')
                            ->schema([
                                Section::make('Tampilan Kasir (POS)')->schema([
                                    Toggle::make('pos_with_img')
                                        ->label('Tampilkan Gambar Produk di Layar Kasir')
                                        ->helperText('Matikan opsi ini untuk mode kasir cepat (hanya teks) agar performa lebih ringan.')
                                        ->default(true),
                                ]),

                                Section::make('Pengaturan Cetak Struk (Nota)')->schema([
                                    Select::make('nota_size')
                                        ->label('Ukuran Kertas Printer')
                                        ->options([
                                            '58mm' => 'Printer Thermal 58mm (Kecil)',
                                            '80mm' => 'Printer Thermal 80mm (Sedang)',
                                            'A4' => 'Kertas A4 (Invoice Standard)',
                                        ])
                                        ->default('58mm')
                                        ->required(),

                                    Toggle::make('is_nota_logo')
                                        ->label('Cetak Logo pada Struk')
                                        ->helperText('Pastikan printer thermal Anda mendukung pencetakan grafis/gambar.')
                                        ->default(true),
                                ]),
                            ]),

                        // TAB 3: PROGRAM LOYALITAS
                        Tabs\Tab::make('Program Loyalitas')
                            ->icon('heroicon-o-gift')
                            ->schema([
                                Toggle::make('is_loyalty_enabled')
                                    ->label('Aktifkan Sistem Poin Loyalitas Pelanggan')
                                    ->helperText('Pelanggan akan mendapatkan poin setiap kali berbelanja.')
                                    ->live() // Memicu re-render komponen di bawahnya
                                    ->default(false),

                                // Bagian ini hanya muncul jika is_loyalty_enabled bernilai TRUE
                                Grid::make(3)
                                    ->hidden(fn (Get $get): bool => ! $get('is_loyalty_enabled'))
                                    ->schema([
                                        TextInput::make('loyalty_spend_amount')
                                            ->label('Target Belanja')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(10000),

                                        TextInput::make('loyalty_point_earned')
                                            ->label('Mendapat Poin')
                                            ->numeric()
                                            ->suffix('Poin')
                                            ->default(1),

                                        TextInput::make('loyalty_point_value')
                                            ->label('Nilai 1 Poin')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->default(100)
                                            ->helperText('Contoh: Belanja kelipatan Rp 10.000 dapat 1 Poin. 1 Poin bernilai Rp 100.'),
                                    ]),
                            ]),

                        // TAB 4: STATUS BERLANGGANAN (READ ONLY)
                        Tabs\Tab::make('Langganan')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('status')
                                        ->label('Status Akun')
                                        ->disabled()
                                        ->formatStateUsing(fn ($state) => strtoupper($state)),

                                    TextInput::make('valid_until')
                                        ->label('Berlaku Hingga')
                                        ->disabled()
                                        ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->translatedFormat('d F Y') : '-'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    // Aksi untuk tombol Simpan
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->submit('save')
                ->color('primary'),
        ];
    }

    // Fungsi yang dieksekusi saat tombol simpan ditekan
    public function save(): void
    {
        $data = $this->form->getState();
        $company = filament()->getTenant();

        // Hapus data yang tidak boleh diubah user dari form (seperti status langganan)
        unset($data['status'], $data['valid_until']);

        $company->update($data);

        Notification::make()
            ->title('Pengaturan Disimpan')
            ->body('Pengaturan perusahaan Anda telah berhasil diperbarui.')
            ->success()
            ->send();
    }
}