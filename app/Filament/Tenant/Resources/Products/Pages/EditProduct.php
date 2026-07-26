<?php

namespace App\Filament\Tenant\Resources\Products\Pages;

use App\Filament\Tenant\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $product = $this->record;

        if ($product->base_uom_id) {
            
            $existsInPivot = DB::table('product_uoms')
                ->where('product_id', $product->id)
                ->where('uom_id', $product->base_uom_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$existsInPivot) {
                DB::table('product_uoms')
                    ->where('product_id', $product->id)
                    ->update(['is_default' => false]);

                DB::table('product_uoms')->insert([
                    'id'                => (string) Str::ulid(),
                    'product_id'        => $product->id,
                    'uom_id'            => $product->base_uom_id,
                    'conversion_factor' => 1,
                    'selling_price'     => $product->base_price,
                    'barcode'           => $product->barcode,
                    'is_default'        => true,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } else {
                DB::table('product_uoms')
                    ->where('product_id', $product->id)
                    ->where('uom_id', $product->base_uom_id)
                    ->update([
                        'selling_price' => $product->base_price,
                        'updated_at'    => now(),
                    ]);
            }
        }
    }
}