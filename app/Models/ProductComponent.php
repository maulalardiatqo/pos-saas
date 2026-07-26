<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Filament\Facades\Filament;

class ProductComponent extends Model
{
    use HasUlids;

    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'decimal:3',
    ];
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function childProduct()
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }

    public function childVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'child_variant_id');
    }
}