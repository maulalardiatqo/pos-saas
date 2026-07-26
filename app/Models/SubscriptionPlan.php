<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasUlids;
    use SoftDeletes;


    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'billing_cycle',
        'is_active',
        'sort_order',
        'features',
    ];


    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}