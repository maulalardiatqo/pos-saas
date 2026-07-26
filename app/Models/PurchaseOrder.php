<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Transaction
{
    // 1. Arahkan model ini agar tetap membaca dan menyimpan ke tabel 'transactions'
    protected $table = 'transactions';

    // 2. Terapkan Global Scope dan Event Hooks
    protected static function booted(): void
    {
        parent::booted();

        // A. Filter Otomatis: Saat query PurchaseOrder::all(), hanya ambil yang type-nya 'purchaseorder'
        static::addGlobalScope('po_scope', function (Builder $builder) {
            $builder->where('type', 'purchaseorder');
        });

        // B. Isi Otomatis: Saat PurchaseOrder::create(), paksa type menjadi 'purchaseorder'
        static::creating(function ($model) {
            $model->type = 'purchaseorder';
            
            // Beri nilai default payment_method jika kosong (PO umumnya bersifat tempo/kredit)
            if (empty($model->payment_method)) {
                $model->payment_method = 'credit';
            }
        });
    }

    // --- RELASI MULTI-TENANT & ENTITAS UTAMA ---

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function childrenDocuments(): HasMany
    {
        return $this->hasMany(Transaction::class, 'reference_id', 'id');
    }


    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reference_id', 'id');
    }
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }
}