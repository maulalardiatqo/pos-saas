<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Account;

class Outlet extends Model
{
    use HasUlids, SoftDeletes;


    protected $guarded = ['id'];


    protected $casts = [
        'is_active' => 'boolean',
    ];


    protected static function booted()
    {
        static::created(function ($outlet) {
            Account::create([
                'company_id' => $outlet->company_id,
                'outlet_id' => $outlet->id,
                'name' => 'Cash ' . $outlet->name, 
                'payment_methods' => ['cash'], 
                'balance' => 0,
            ]);
        });
    }
    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */


    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */


    public function isActive(): bool
    {
        return $this->is_active;
    }
}