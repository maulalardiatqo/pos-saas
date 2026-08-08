<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;

use App\Models\StockMovement;
use App\Models\StockTransferItem;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;

class StockTransfer extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && Filament::getTenant()) {
                $model->company_id = Filament::getTenant()->id;
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // --- RELATIONS DENGAN RETURN TYPE EKSPLISIT ---
    
    public function company(): BelongsTo 
    { 
        return $this->belongsTo(Company::class); 
    }
    
    public function fromOutlet(): BelongsTo 
    { 
        return $this->belongsTo(Outlet::class, 'from_outlet_id'); 
    }
    
    public function toOutlet(): BelongsTo 
    { 
        return $this->belongsTo(Outlet::class, 'to_outlet_id'); 
    }
    
    public function creator(): BelongsTo 
    { 
        return $this->belongsTo(User::class, 'created_by'); 
    }
    
    public function items(): HasMany 
    { 
        // Menambahkan parameter relasi secara eksplisit agar Filament tidak bingung
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id', 'id'); 
    }

    // --- FUNGSI INTI: Eksekusi Pindah Stok ---
    public function markAsCompleted()
    {
        if ($this->status === 'completed') return;

        DB::transaction(function () {
            foreach ($this->items as $item) {
                // =========================================================
                // 1. Barang KELUAR dari Gudang Asal (from_outlet)
                // =========================================================
                $lastStockFrom = StockMovement::where('product_id', $item->product_id)
                    ->where('outlet_id', $this->from_outlet_id)
                    ->latest()
                    ->first();
                
                // Hitung balance_before & balance_after untuk Outlet Asal
                $balanceBeforeFrom = $lastStockFrom ? $lastStockFrom->balance_after : 0;
                $balanceAfterFrom  = $balanceBeforeFrom - $item->quantity;

                StockMovement::create([
                    'company_id'     => $this->company_id,
                    'outlet_id'      => $this->from_outlet_id,
                    'product_id'     => $item->product_id,
                    'type'           => 'out',
                    'quantity'       => $item->quantity,
                    'balance_before' => $balanceBeforeFrom, // <-- TAMBAHKAN KODE INI
                    'balance_after'  => $balanceAfterFrom,
                    'reference_type' => StockTransfer::class, // Morph Class
                    'reference_id'   => $this->id,
                    'remarks'        => "Mutasi Keluar ke " . $this->toOutlet->name,
                ]);

                // =========================================================
                // 2. Barang MASUK ke Gudang Tujuan (to_outlet)
                // =========================================================
                $lastStockTo = StockMovement::where('product_id', $item->product_id)
                    ->where('outlet_id', $this->to_outlet_id)
                    ->latest()
                    ->first();

                // Hitung balance_before & balance_after untuk Outlet Tujuan
                $balanceBeforeTo = $lastStockTo ? $lastStockTo->balance_after : 0;
                $balanceAfterTo  = $balanceBeforeTo + $item->quantity;

                StockMovement::create([
                    'company_id'     => $this->company_id,
                    'outlet_id'      => $this->to_outlet_id,
                    'product_id'     => $item->product_id,
                    'type'           => 'in',
                    'quantity'       => $item->quantity,
                    'balance_before' => $balanceBeforeTo, // <-- TAMBAHKAN KODE INI
                    'balance_after'  => $balanceAfterTo,
                    'reference_type' => StockTransfer::class, // Morph Class
                    'reference_id'   => $this->id,
                    'remarks'        => "Mutasi Masuk dari " . $this->fromOutlet->name,
                ]);
            }

            $this->update(['status' => 'completed']);
        });
    }
}