<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Facades\Filament;

class Customer extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];
    
    protected $casts = [
        'points_balance'  => 'integer',
        'lifetime_points' => 'integer',
        'is_active' => 'boolean',
        'is_member' => 'boolean',
        'points'    => 'integer',
    ];
    
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // RELASI BARU KE OUTLET
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function pointHistories()
    {
        return $this->hasMany(PointHistory::class);
    }
    public function vehicles()
    {
        return $this->hasMany(CustomerVehicle::class);
    }
}