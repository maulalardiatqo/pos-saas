<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Facades\Filament;

class StockAdjustment extends Model
{
    use HasUlids, SoftDeletes;
    
    protected $guarded = ['id'];
    protected $casts = ['date' => 'date'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }

    // RELASI TENANT (WAJIB ADA)
    public function company() 
    { 
        return $this->belongsTo(Company::class); 
    }

    public function items() 
    { 
        return $this->hasMany(StockAdjustmentItem::class); 
    }

    public function outlet() 
    { 
        return $this->belongsTo(Outlet::class); 
    }

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function movements() 
    { 
        return $this->morphMany(StockMovement::class, 'reference'); 
    }
}