<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id ?? $user->tenant_id ?? $user->company->id;

        $query = StockMovement::with(['product:id,name', 'outlet:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner && $user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $movements = $query->limit(200)->get();

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }
}