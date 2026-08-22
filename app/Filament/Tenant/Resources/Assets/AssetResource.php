<?php

namespace App\Filament\Tenant\Resources\Assets;

use App\Filament\Tenant\Resources\Assets\RelationManagers;
use App\Filament\Tenant\Resources\Assets\Pages\CreateAsset;
use App\Filament\Tenant\Resources\Assets\Pages\EditAsset;
use App\Filament\Tenant\Resources\Assets\Pages\ListAssets;
use App\Filament\Tenant\Resources\Assets\Pages\ViewAsset;
use App\Filament\Tenant\Resources\Assets\Schemas\AssetForm; // IMPORT SCHEMA
use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Manajemen Aset';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventaris';

    /*
    |--------------------------------------------------------------------------
    | Hak Akses (Role-Based Access Control)
    |--------------------------------------------------------------------------
    */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->isOwner() || $user->hasPermission('inventory.asset'));
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Form & Tabel
    |--------------------------------------------------------------------------
    */
    public static function form(Schema $schema): Schema
    {
        // PERBAIKAN: Gunakan konfigurasi eksternal dari AssetForm agar rapi
        return AssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Kode')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Aset')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Lokasi (Outlet)'),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Harga/Nilai')
                    ->money('idr'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'      => 'success',
                        'maintenance' => 'warning',
                        'broken'      => 'danger',
                        'disposed'    => 'gray',
                        default       => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->label('Filter Outlet'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'      => 'Aktif',
                        'maintenance' => 'Servis',
                        'broken'      => 'Rusak',
                        'disposed'    => 'Dibuang',
                    ]),
            ])
            ->actions([
                Action::make('mutasi')
                    ->label('Mutasi')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Select::make('to_outlet_id')
                            ->label('Pindah ke Outlet')
                            ->options(function (Asset $record) {
                                return \App\Models\Outlet::where('company_id', $record->company_id)
                                    ->where('id', '!=', $record->outlet_id)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Catatan Mutasi')
                            ->placeholder('Contoh: Dipindah karena cabang baru buka / Dipinjam sementara.')
                            ->required(),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        \App\Models\AssetLog::create([
                            'company_id'     => $record->company_id,
                            'asset_id'       => $record->id,
                            'user_id'        => auth()->id(),
                            'action_type'    => 'moved',
                            'from_outlet_id' => $record->outlet_id,
                            'to_outlet_id'   => $data['to_outlet_id'],
                            'remarks'        => $data['remarks'],
                        ]);

                        $record->update([
                            'outlet_id' => $data['to_outlet_id'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Aset berhasil dipindahkan!')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn (Asset $record) => 'Mutasi Aset: ' . $record->name)
                    ->modalDescription('Pilih cabang tujuan untuk memindahkan fisik aset ini.')
                    ->modalSubmitActionLabel('Pindahkan Sekarang'),

                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user && $user->isOwner()) {
            return $query;
        }

        return $query->where('outlet_id', $user?->outlet_id);
    }
    
    public static function getPages(): array
    {
        return [
            'index'  => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit'   => EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\LogsRelationManager::class,
        ];
    }
}