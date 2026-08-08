<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftCardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        // Ambil data kartu dan pelanggan
        $giftCards = GiftCard::with('customer:id,name')
            ->where('company_id', $tenantId)
            ->latest()
            ->get();
            
        $customers = Customer::where('company_id', $tenantId)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true, 
            'gift_cards' => $giftCards, 
            'customers' => $customers
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;

        $data = $request->validate([
            'card_number' => [
                'required', 'string', 'max:50',
                Rule::unique('gift_cards')->where('company_id', $tenantId)
            ],
            'customer_id' => 'nullable|exists:customers,id',
            'balance' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $data['company_id'] = $tenantId;

        GiftCard::create($data);

        return response()->json(['success' => true, 'message' => 'Gift Card berhasil ditambahkan']);
    }

    public function update(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $giftCard = GiftCard::where('company_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'card_number' => [
                'required', 'string', 'max:50',
                Rule::unique('gift_cards')->where('company_id', $tenantId)->ignore($giftCard->id)
            ],
            'customer_id' => 'nullable|exists:customers,id',
            'balance' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $giftCard->update($data);

        return response()->json(['success' => true, 'message' => 'Gift Card berhasil diperbarui']);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = ($request->user()->company ?? $request->user()->tenant)->id;
        $giftCard = GiftCard::where('company_id', $tenantId)->findOrFail($id);
        
        $giftCard->delete();

        return response()->json(['success' => true, 'message' => 'Gift Card berhasil dihapus']);
    }
}