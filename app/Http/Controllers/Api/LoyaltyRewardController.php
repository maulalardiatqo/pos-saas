<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyReward;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LoyaltyRewardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $rewards = LoyaltyReward::with('product:id,name')->where('company_id', $tenantId)->latest()->get();
        $products = Product::where('company_id', $tenantId)->where('is_active', 1)->get(['id', 'name']);

        return response()->json(['success' => true, 'rewards' => $rewards, 'products' => $products]);
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points_required' => 'required|integer',
            'reward_type' => 'required|in:product,discount',
            'product_id' => 'nullable|exists:products,id',
            'discount_amount' => 'nullable|numeric',
            'is_active' => 'required|boolean',
        ]);

        $data['company_id'] = $tenantId;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('loyalty-rewards', 'public');
        }

        LoyaltyReward::create($data);
        return response()->json(['success' => true, 'message' => 'Hadiah berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $reward = LoyaltyReward::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'points_required' => 'required|integer',
            'reward_type' => 'required|in:product,discount',
            'product_id' => 'nullable|exists:products,id',
            'discount_amount' => 'nullable|numeric',
            'is_active' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($reward->image) Storage::disk('public')->delete($reward->image);
            $data['image'] = $request->file('image')->store('loyalty-rewards', 'public');
        }

        $reward->update($data);
        return response()->json(['success' => true, 'message' => 'Hadiah berhasil diperbarui']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $reward = LoyaltyReward::where('company_id', $tenantId)->findOrFail($id);

        if ($reward->image) Storage::disk('public')->delete($reward->image);
        $reward->delete();

        return response()->json(['success' => true, 'message' => 'Hadiah berhasil dihapus']);
    }
}