<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Account;

class Company extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'midtrans_is_production' => 'boolean',
            'valid_until'          => 'date',
            'is_loyalty_enabled'   => 'boolean',
            'loyalty_spend_amount' => 'float',
            'loyalty_point_earned' => 'integer',
            'loyalty_point_value'  => 'float',
            'pos_with_img' => 'boolean',
        ];
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) {
                    return null;
                }

                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }
                return asset('storage/' . $value); 
            }
        );
    }
    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    public function subscriptionPlan()
    {
        return $this->belongsTo(
            SubscriptionPlan::class
        );
    }
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasFeature(string $feature): bool
    {
        $features = $this->subscriptionPlan?->features;

        if (!$features) {
            return false;
        }

        // Ubah string JSON menjadi array
        if (is_string($features)) {
            $features = json_decode($features, true);
        }

        if (!is_array($features)) {
            return false;
        }
        $value = data_get($features, $feature);
        if ($value !== null) {
            return (bool) $value;
        }

        foreach ($features as $group) {
            if (is_array($group) && array_key_exists($feature, $group)) {
                return (bool) $group[$feature];
            }
        }

        return false;
    }
}