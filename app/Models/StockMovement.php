<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Filament\Facades\Filament;

class StockMovement extends Model
{
    use HasUlids;
    
    protected $guarded = ['id'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }

    /**
     * Relasi ke Tenant (Company) - INI YANG WAJIB ADA
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function product() 
    { 
        return $this->belongsTo(Product::class); 
    }
    
    public function outlet() 
    { 
        return $this->belongsTo(Outlet::class); 
    }
    
    public function reference() 
    { 
        return $this->morphTo(); 
    }
}