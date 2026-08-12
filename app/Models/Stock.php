<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}