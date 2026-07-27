<?php
namespace App\Filament\Tenant\Resources\Roles\Pages;
use App\Filament\Tenant\Resources\Roles\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}