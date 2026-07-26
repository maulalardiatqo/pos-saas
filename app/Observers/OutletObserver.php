<?php

namespace App\Observers;

use App\Models\Outlet;
use Illuminate\Support\Str;

class OutletObserver
{

    public function creating(Outlet $outlet): void
    {
        if (empty($outlet->company_id) && function_exists('filament') && filament()->getTenant()) {
            $outlet->company_id = filament()->getTenant()->id;
        }

        if (empty($outlet->code)) {
            $outlet->code = 'OUT-' . date('Ymd-His') . '-' . strtoupper(Str::random(4));
        }
    }

    public function updating(Outlet $outlet): void
    {
        if ($outlet->isDirty('company_id')) {
            $outlet->company_id = $outlet->getOriginal('company_id');
        }
    }
}