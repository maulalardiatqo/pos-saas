<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Filament\Facades\Filament;

class TransactionItem extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $casts = [
        'qty'               => 'float',
        'conversion_factor' => 'float',
        'base_qty'          => 'float',
        'cost_price'        => 'float',
        'selling_price'     => 'float',
        'discount_rate'     => 'float',
        'discount_amount'   => 'float',
        'tax_rate'          => 'float',
        'tax_amount'        => 'float',
        'subtotal'          => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }

    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}