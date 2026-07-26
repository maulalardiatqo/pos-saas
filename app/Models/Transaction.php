<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Facades\Filament;
use App\Models\Account;

class Transaction extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'subtotal'      => 'float',
        'tax'           => 'float',
        'discount'      => 'float',
        'grand_total'   => 'float',
        'amount_paid'   => 'float',
        'amount_change' => 'float',
        'points_used'   => 'integer',
        'point_discount_amount' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
        });
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function outlet() { return $this->belongsTo(Outlet::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    
    public function items() { return $this->hasMany(TransactionItem::class); }
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
    public function posSession()
    {
        return $this->belongsTo(PosSession::class);
    }

    public function purchasedAssets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}