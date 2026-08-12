<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    /**
     * Helper Keamanan: Validasi Lapis Ganda (Feature & Role Permission)
     */
    private function checkAccess($user, $permissionCode)
    {
        $company = $user->company ?? $user->tenant;
        
        // -------------------------------------------------------------
        // TRANSLASI PERMISSION KE FEATURE KEY
        // Memisahkan 'products.view' -> 'products', atau 'products.brand' -> 'brand'
        // -------------------------------------------------------------
        $parts = explode('.', $permissionCode);
        $featureKey = $parts[1] ?? $parts[0]; 
        
        // Jika action CRUD standar (view, create, edit, delete, manage), kita ambil modul utamanya
        // Contoh: 'products.view' akan menggunakan key fitur 'products'
        if (in_array($featureKey, ['view', 'create', 'edit', 'delete', 'manage'])) {
            $featureKey = $parts[0]; 
        }

        // 1. Cek Langganan Perusahaan (Feature Toggle)
        if (!$company || !$company->hasFeature($featureKey)) {
            abort(403, "Perusahaan Anda tidak berlangganan fitur ini.");
        }

        // 2. Cek Role Permission Karyawan (RBAC) - Lewati jika Owner
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner) {
            $hasPermission = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('role_permissions.role_id', $user->role_id)
                ->where('permissions.code', $permissionCode)
                ->exists();

            if (!$hasPermission) {
                abort(403, "Anda tidak memiliki izin untuk mengakses ini.");
            }
        }
        
        return $company->id;
    }

    // ========================================================================
    // CRUD BRANDS
    // ========================================================================
    
    public function getBrands(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.brand');

        $brands = Brand::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $brands]);
    }

    public function storeBrand(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.brand');

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                // Validasi Unik di level tenant
                Rule::unique('brands')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $brand = Brand::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Brand berhasil ditambahkan.', 'data' => $brand]);
    }

    public function updateBrand(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.brand');

        $brand = Brand::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('brands')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))->ignore($brand->id),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $brand->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Brand berhasil diperbarui.', 'data' => $brand]);
    }

    public function deleteBrand(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.brand');
        
        $brand = Brand::where('company_id', $companyId)->findOrFail($id);
        
        // Pengecekan Relasi: Pastikan brand belum dipakai di tabel products
        $isUsed = DB::table('products')->where('brand_id', $brand->id)->exists();
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal: Brand sedang digunakan oleh Produk.'], 400);
        }

        $brand->delete();

        return response()->json(['success' => true, 'message' => 'Brand berhasil dihapus.']);
    }

    // ========================================================================
    // CRUD CATEGORIES
    // ========================================================================
    
    public function getCategories(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.category');

        $categories = \App\Models\Category::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.category');

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                // Validasi unik di level tenant (tanpa whereNull deleted_at karena tidak pakai SoftDeletes)
                Rule::unique('categories')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        $category = \App\Models\Category::create([
            'company_id' => $companyId,
            'name' => $request->name,
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil ditambahkan.', 'data' => $category]);
    }

    public function updateCategory(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.category');

        $category = \App\Models\Category::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($category->id),
            ],
        ]);

        $category->update([
            'name' => $request->name,
        ]);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui.', 'data' => $category]);
    }

    public function deleteCategory(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.category');
        
        $category = \App\Models\Category::where('company_id', $companyId)->findOrFail($id);
        
        // Pengecekan Relasi: Pastikan Kategori belum dipakai di tabel products
        $isUsed = DB::table('products')->where('category_id', $category->id)->exists();
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal: Kategori sedang digunakan oleh Produk.'], 400);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Kategori berhasil dihapus.']);
    }
    
    // ========================================================================
    // CRUD UOMS (Unit of Measurements / Satuan)
    // ========================================================================
    
    public function getUoms(Request $request)
    {
        // Permission sesuai dengan yang di resource Filament: 'products.multi_uom'
        $companyId = $this->checkAccess($request->user(), 'products.multi_uom');

        $uoms = \App\Models\Uom::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $uoms]);
    }

    public function storeUom(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.multi_uom');

        $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                // Validasi Code unik di level tenant (mempertimbangkan softDeletes)
                Rule::unique('uoms')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'name' => 'required|string|max:100',
            'symbol' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $uom = \App\Models\Uom::create([
            'company_id' => $companyId,
            'code' => $request->code,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Satuan (UOM) berhasil ditambahkan.', 'data' => $uom]);
    }

    public function updateUom(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.multi_uom');
        $uom = \App\Models\Uom::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('uoms')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))->ignore($uom->id),
            ],
            'name' => 'required|string|max:100',
            'symbol' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $uom->update([
            'code' => $request->code,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Satuan (UOM) berhasil diperbarui.', 'data' => $uom]);
    }

    public function deleteUom(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.multi_uom');
        $uom = \App\Models\Uom::where('company_id', $companyId)->findOrFail($id);
        
        // Pengecekan Relasi Ganda: Pastikan UOM belum dipakai di tabel products maupun product_uoms
        $usedInProduct = DB::table('products')->where('base_uom_id', $uom->id)->exists();
        $usedInVariant = DB::table('product_uoms')->where('uom_id', $uom->id)->exists();
        
        if ($usedInProduct || $usedInVariant) {
            return response()->json(['success' => false, 'message' => 'Gagal: Satuan sedang digunakan oleh produk.'], 400);
        }

        $uom->delete();

        return response()->json(['success' => true, 'message' => 'Satuan (UOM) berhasil dihapus.']);
    }

    // ========================================================================
    // CRUD PRODUCTS
    // ========================================================================
    
    public function getProducts(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.view');

        $query = \App\Models\Product::with(['category:id,name', 'baseUom:id,name', 'brand:id,name', 'productUoms.uom'])
            ->where('company_id', $companyId);

        // FILTER: Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        // FILTER: Category, Brand, Types
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id')) $query->where('brand_id', $request->brand_id);
        if ($request->filled('item_type')) $query->where('item_type', $request->item_type);
        if ($request->filled('product_type')) $query->where('product_type', $request->product_type);

        $products = $query->orderBy('name')->get();

        // Menyisipkan permission ke dalam response agar Flutter tahu hak akses user
        $user = $request->user();
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        
        $permissions = [
            'can_create' => $isOwner || DB::table('role_permissions')->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')->where('role_id', $user->role_id)->where('code', 'products.create')->exists(),
            'can_edit' => $isOwner || DB::table('role_permissions')->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')->where('role_id', $user->role_id)->where('code', 'products.edit')->exists(),
            'can_delete' => $isOwner || DB::table('role_permissions')->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')->where('role_id', $user->role_id)->where('code', 'products.delete')->exists(),
        ];

        return response()->json([
            'success' => true, 
            'data' => $products,
            'permissions' => $permissions
        ]);
    }

    public function storeProduct(Request $request)
    {
        $companyId = $this->checkAccess($request->user(), 'products.create');

        $request->validate([
            'name' => 'required|string|max:200',
            'base_uom_id' => 'required|string',
            'category_id' => 'nullable|string',
            'brand_id' => 'nullable|string',
            'item_type' => 'required|in:goods,service',
            'product_type' => 'required|in:standard,bundle,recipe',
            'cost_price' => 'numeric|min:0',
            'base_price' => 'numeric|min:0',
        ]);

        $product = \App\Models\Product::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'base_uom_id' => $request->base_uom_id,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'item_type' => $request->item_type,
            'product_type' => $request->product_type,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'description' => $request->description,
            'cost_price' => $request->cost_price ?? 0,
            'base_price' => $request->base_price ?? 0,
            'is_active' => $request->is_active ?? true,
            'has_variants' => 0, // Varian di-handle terpisah di mobile untuk penyederhanaan
        ]);

        return response()->json(['success' => true, 'message' => 'Produk berhasil ditambahkan.']);
    }

    public function updateProduct(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.edit');
        $product = \App\Models\Product::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:200',
            'base_uom_id' => 'required|string',
            'cost_price' => 'numeric|min:0',
            'base_price' => 'numeric|min:0',
        ]);

        $product->update($request->only([
            'name', 'base_uom_id', 'category_id', 'brand_id', 'item_type', 'product_type',
            'sku', 'barcode', 'description', 'cost_price', 'base_price', 'is_active'
        ]));

        return response()->json(['success' => true, 'message' => 'Produk berhasil diperbarui.']);
    }

    public function deleteProduct(Request $request, $id)
    {
        $companyId = $this->checkAccess($request->user(), 'products.delete');
        $product = \App\Models\Product::where('company_id', $companyId)->findOrFail($id);
        
        // Soft delete
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus.']);
    }
    // ========================================================================
    // CRUD ACCOUNTS (AKUN KEUANGAN) - OWNER ONLY
    // ========================================================================
    
    // Helper khusus untuk validasi Owner Only
    private function checkOwnerAccess($user)
    {
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        if (!$isOwner) {
            abort(403, "Akses ditolak: Menu ini khusus Pemilik Usaha (Owner).");
        }
        return $user->company->id ?? $user->tenant->id;
    }

    public function getAccounts(Request $request)
    {
        $companyId = $this->checkOwnerAccess($request->user());

        $accounts = \App\Models\Account::with('outlet:id,name')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    public function storeAccount(Request $request)
    {
        $companyId = $this->checkOwnerAccess($request->user());

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'outlet_id' => 'nullable|string|exists:outlets,id',
            'payment_methods' => 'required|array|min:1',
            'balance' => 'numeric|min:0',
        ]);

        $account = \App\Models\Account::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'account_number' => $request->account_number,
            'outlet_id' => $request->outlet_id,
            'payment_methods' => $request->payment_methods,
            'balance' => $request->balance ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Akun keuangan berhasil ditambahkan.', 'data' => $account]);
    }

    public function updateAccount(Request $request, $id)
    {
        $companyId = $this->checkOwnerAccess($request->user());
        $account = \App\Models\Account::where('company_id', $companyId)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'outlet_id' => 'nullable|string|exists:outlets,id',
            'payment_methods' => 'required|array|min:1',
        ]);

        // Catatan: Saldo (Balance) tidak boleh diedit dari form ini. (Sesuai Web -> hiddenOn('edit'))
        $account->update([
            'name' => $request->name,
            'account_number' => $request->account_number,
            'outlet_id' => $request->outlet_id,
            'payment_methods' => $request->payment_methods,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Akun keuangan berhasil diperbarui.', 'data' => $account]);
    }

    public function deleteAccount(Request $request, $id)
    {
        $companyId = $this->checkOwnerAccess($request->user());
        $account = \App\Models\Account::where('company_id', $companyId)->findOrFail($id);
        
        // Pengecekan Relasi: Pastikan akun tidak terikat transaksi aktif
        $isUsed = DB::table('transactions')->where('account_id', $account->id)->exists();
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal: Akun ini sudah memiliki riwayat transaksi.'], 400);
        }

        $account->delete();

        return response()->json(['success' => true, 'message' => 'Akun keuangan berhasil dihapus.']);
    }

    // Endpoint tambahan untuk mengambil daftar Outlet di form Account
    public function getOutletsForDropdown(Request $request)
    {
        $companyId = $this->checkOwnerAccess($request->user());
        $outlets = \App\Models\Outlet::where('company_id', $companyId)->select('id', 'name')->get();
        return response()->json(['success' => true, 'data' => $outlets]);
    }

    // ========================================================================
    // CRUD SUPPLIER (PEMASOK)
    // ========================================================================

    public function getSuppliers(Request $request)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'suppliers.view');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $query = \App\Models\Supplier::with('outlet:id,name')->where('company_id', $companyId);
        
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }

        $suppliers = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    public function storeSupplier(Request $request)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'suppliers.manage');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
            'outlet_id' => 'nullable|string|exists:outlets,id',
        ]);

        $outletId = $isOwner ? $request->outlet_id : $user->outlet_id;

        $supplier = \App\Models\Supplier::create([
            'company_id' => $companyId,
            'outlet_id'  => $outletId,
            'code'       => 'SUP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'name'       => $request->name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'address'    => $request->address,
            'is_active'  => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Supplier berhasil ditambahkan.', 'data' => $supplier]);
    }

    public function updateSupplier(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'suppliers.manage');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $query = \App\Models\Supplier::where('company_id', $companyId);
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }
        $supplier = $query->findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
            'outlet_id' => 'nullable|string|exists:outlets,id',
        ]);

        $outletId = $isOwner ? $request->outlet_id : $supplier->outlet_id;

        $supplier->update([
            'outlet_id'  => $outletId,
            'name'       => $request->name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'address'    => $request->address,
            'is_active'  => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Supplier berhasil diperbarui.', 'data' => $supplier]);
    }

    public function deleteSupplier(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'suppliers.manage');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        
        $query = \App\Models\Supplier::where('company_id', $companyId);
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }
        $supplier = $query->findOrFail($id);

        // Pengecekan Relasi: Pastikan Supplier belum dipakai di transaksi PO (Purchase Order)
        $isUsed = DB::table('transactions')
                    ->where('supplier_id', $supplier->id)
                    ->exists();
                    
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal: Supplier sedang memiliki riwayat transaksi / Purchase Order.'], 400);
        }

        $supplier->delete();

        return response()->json(['success' => true, 'message' => 'Supplier berhasil dihapus.']);
    }

    // ========================================================================
    // CRUD CUSTOMER (PELANGGAN)
    // ========================================================================

    public function getCustomers(Request $request)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'customers.view');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $query = \App\Models\Customer::with('outlet:id,name')->where('company_id', $companyId);
        
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }

        $customers = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $customers]);
    }

    public function storeCustomer(Request $request)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'customers.create');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
            'outlet_id' => 'nullable|string|exists:outlets,id',
        ]);

        $outletId = $isOwner ? $request->outlet_id : $user->outlet_id;

        $customer = \App\Models\Customer::create([
            'company_id' => $companyId,
            'outlet_id'  => $outletId,
            'code'       => 'CUS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'name'       => $request->name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'address'    => $request->address,
            'is_active'  => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil ditambahkan.', 'data' => $customer]);
    }

    public function updateCustomer(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'customers.edit');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;

        $query = \App\Models\Customer::where('company_id', $companyId);
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }
        $customer = $query->findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
            'outlet_id' => 'nullable|string|exists:outlets,id',
        ]);

        $outletId = $isOwner ? $request->outlet_id : $customer->outlet_id;

        $customer->update([
            'outlet_id'  => $outletId,
            'name'       => $request->name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'address'    => $request->address,
            'is_active'  => $request->is_active ?? true,
        ]);

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil diperbarui.', 'data' => $customer]);
    }

    public function deleteCustomer(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $this->checkAccess($user, 'customers.delete');
        $isOwner = $user->isOwner() ?? $user->isPlatform() ?? false;
        
        $query = \App\Models\Customer::where('company_id', $companyId);
        if (!$isOwner) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $user->outlet_id);
            });
        }
        $customer = $query->findOrFail($id);

        // Pengecekan Relasi: Pastikan Pelanggan belum dipakai di transaksi penjualan (POS)
        $isUsed = DB::table('transactions')
                    ->where('customer_id', $customer->id)
                    ->exists();
                    
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal: Pelanggan ini sudah memiliki riwayat transaksi penjualan.'], 400);
        }

        $customer->delete();

        return response()->json(['success' => true, 'message' => 'Pelanggan berhasil dihapus.']);
    }
}