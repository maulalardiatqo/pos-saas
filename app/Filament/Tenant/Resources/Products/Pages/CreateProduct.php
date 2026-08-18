<?php

namespace App\Filament\Tenant\Resources\Products\Pages;

use App\Filament\Tenant\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = filament()->getTenant()->id;
        
        $data['product_type'] = $data['product_type'] ?? 'standard';
        
        if (in_array($data['product_type'], ['bundle', 'recipe'])) {
            $data['item_type'] = 'bundle';
            $data['base_uom_id']  = null;
            $data['has_variants'] = false;
        } elseif (($data['item_type'] ?? 'goods') === 'service') {
            // Jika Jasa / Service
            $data['base_uom_id']  = null;
            $data['has_variants'] = false;
        } else {
            // Jika kosong (karena error UI dll), pastikan fallback ke goods
            $data['item_type'] = $data['item_type'] ?? 'goods';
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        // Blok ini sudah aman karena ada pengecekan if ($product->base_uom_id)
        if ($product->base_uom_id) {
            
            $baseUomRecord = DB::table('product_uoms')
                ->where('product_id', $product->id)
                ->where('uom_id', $product->base_uom_id)
                ->whereNull('deleted_at')
                ->first();

            $hasDefault = DB::table('product_uoms')
                ->where('product_id', $product->id)
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->exists();

            if (!$baseUomRecord) {
                DB::table('product_uoms')->insert([
                    'id'                => (string) Str::ulid(),
                    'product_id'        => $product->id,
                    'uom_id'            => $product->base_uom_id,
                    'conversion_factor' => 1, 
                    'selling_price'     => $product->base_price,
                    'barcode'           => $product->barcode,
                    'is_default'        => !$hasDefault, 
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            } else {
                DB::table('product_uoms')
                    ->where('id', $baseUomRecord->id)
                    ->update([
                        'conversion_factor' => 1,
                        'is_default'        => $hasDefault ? $baseUomRecord->is_default : true,
                        'updated_at'        => now(),
                    ]);
            }
        }
    }
}