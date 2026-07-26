<?php

namespace App\Observers;

use App\Models\PointHistory;
use App\Models\Membership;

class PointHistoryObserver
{
    /**
     * Handle the PointHistory "created" event.
     * Akan berjalan otomatis saat ada pemasukan / pengeluaran poin baru.
     */
    public function created(PointHistory $pointHistory): void
    {
        $customer = $pointHistory->customer;
        
        // Hentikan jika data pelanggan tidak ditemukan
        if (!$customer) {
            return;
        }

        if ($pointHistory->type === 'earn') {
            $customer->points_balance += $pointHistory->amount;
            $customer->lifetime_points += $pointHistory->amount;
            
        } elseif ($pointHistory->type === 'redeem') {
            $customer->points_balance -= $pointHistory->amount;
            
            if ($customer->points_balance < 0) {
                $customer->points_balance = 0;
            }
        }

        $eligibleMembership = Membership::where('company_id', $customer->company_id)
            ->where('is_active', true)
            ->where('min_points', '<=', $customer->lifetime_points)
            ->orderBy('min_points', 'desc') 
            ->first();

        if ($eligibleMembership && $customer->membership_id !== $eligibleMembership->id) {
            $customer->membership_id = $eligibleMembership->id;
        }

        $customer->save();
    }

    /**
     * Handle the PointHistory "updated" event.
     */
    public function updated(PointHistory $pointHistory): void
    {
    }

    /**
     * Handle the PointHistory "deleted" event.
     * Rollback: Jika history dihapus, kembalikan saldo seperti semula.
     */
    public function deleted(PointHistory $pointHistory): void
    {
        $customer = $pointHistory->customer;
        if (!$customer) {
            return;
        }

        // --- REVERSE (KEMBALIKAN) POIN ---
        if ($pointHistory->type === 'earn') {
            $customer->points_balance -= $pointHistory->amount;
            $customer->lifetime_points -= $pointHistory->amount;
        } elseif ($pointHistory->type === 'redeem') {
            $customer->points_balance += $pointHistory->amount;
        }

        if ($customer->points_balance < 0) $customer->points_balance = 0;
        if ($customer->lifetime_points < 0) $customer->lifetime_points = 0;


        $customer->save();
    }

    /**
     * Handle the PointHistory "restored" event.
     */
    public function restored(PointHistory $pointHistory): void
    {
        $this->created($pointHistory);
    }

    /**
     * Handle the PointHistory "force deleted" event.
     */
    public function forceDeleted(PointHistory $pointHistory): void
    {
        // Sama dengan proses delete biasa
        $this->deleted($pointHistory);
    }
}