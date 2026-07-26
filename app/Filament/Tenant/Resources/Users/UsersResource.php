<?php

namespace App\Filament\Tenant\Resources\Users;

use App\Filament\Tenant\Resources\Users\Pages\CreateUser;
use App\Filament\Tenant\Resources\Users\Pages\EditUser;
use App\Filament\Tenant\Resources\Users\Pages\ListUsers;
use App\Filament\Tenant\Resources\Users\Schemas\UserForm;
use App\Filament\Tenant\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $slug = 'karyawan-toko'; 

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pengguna';
    }

    /*
    |--------------------------------------------------------------------------
    | Keamanan (Hak Akses & Limit Kuota SaaS)
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        // Hanya munculkan menu jika user punya izin 'users.view'
        return $user->hasPermission('users.view');
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        return $user->hasPermission('modules.users.create');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('modules.users.edit');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->hasPermission('modules.users.delete');
    }

    /*
    |--------------------------------------------------------------------------
    | Routing UI
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}