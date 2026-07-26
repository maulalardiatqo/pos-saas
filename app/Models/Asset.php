<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use SoftDeletes, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date'  => 'date',
            'purchase_price' => 'float',
        ];
    }

    // --- RELATIONS ---

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class)->latest();
    }
}