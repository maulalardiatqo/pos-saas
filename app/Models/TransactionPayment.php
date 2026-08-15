<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids; // Tambahkan ini untuk dukungan ULID

class TransactionPayment extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'gateway_response' => 'array', // Otomatis meng-cast JSON ke Array PHP
    ];

    // ==========================================
    // RELATIONS
    // ==========================================
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}