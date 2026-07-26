<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Facades\Filament;

class GiftCard extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'balance'     => 'decimal:2',
        'expiry_date' => 'datetime',
        'is_active'   => 'boolean',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}