<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Filament\Facades\Filament;

class LoyaltyReward extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $casts = [
        'points_required' => 'integer',
        'discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}