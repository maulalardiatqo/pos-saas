<div>
    <x-filament-panels::page>
        @php
            $tenant = filament()->getTenant();
            $showImage = $tenant->pos_with_img ?? true;
            
            // Konfigurasi URL Midtrans Snap (Sandbox vs Production)
            $clientKey = $tenant->midtrans_client_key;
            $snapUrl = ($tenant->midtrans_is_production ?? false)
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js';
        @endphp

        {{-- Muat SDK Snap jika Tenant memiliki Client Key --}}
        @if($clientKey)
            <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
        @endif

        <!-- CSS FIX: MENYEMPURNAKAN KEKAKUAN GRID & KARTU PRODUK -->
        <style>
            /* ========================================================
               CSS VARIABLES (MENDUKUNG LIGHT & DARK MODE OTOMATIS)
               ======================================================== */
            :root {
                --pos-bg-fullscreen: #f1f5f9;
                --pos-bg-panel: #ffffff;
                --pos-bg-subpanel: #f8fafc;
                --pos-bg-input: #ffffff;
                --pos-bg-hover: #f1f5f9;
                --pos-border: #e2e8f0;
                --pos-border-strong: #cbd5e1;
                --pos-text-main: #0f172a;
                --pos-text-muted: #64748b;
                --pos-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            }

            .dark {
                --pos-bg-fullscreen: #09090b;
                --pos-bg-panel: #1c1c1e;
                --pos-bg-subpanel: #141416;
                --pos-bg-input: #2c2c2e;
                --pos-bg-hover: #3a3a3c;
                --pos-border: #2c2c2e;
                --pos-border-strong: #3a3a3c;
                --pos-text-main: #ffffff;
                --pos-text-muted: #a1a1aa;
                --pos-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }

            /* BASE LAYOUT & FULLSCREEN */
            .pos-layout { display: flex; gap: 1.25rem; height: calc(100vh - 170px); width: 100%; box-sizing: border-box; font-family: ui-sans-serif, system-ui, sans-serif; }
            .pos-layout.is-fullscreen { 
                position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
                height: 100vh !important; width: 100vw !important; padding: 1.5rem; 
                background-color: var(--pos-bg-fullscreen) !important; 
                z-index: 9999; overflow: hidden; box-sizing: border-box !important;
            }

            /* DINAMISASI LEBAR LAYOUT (GRID vs LIST) */
            .pos-left-side { display: flex; flex-direction: column; overflow: hidden; transition: flex 0.3s ease; }
            .pos-right-side { background: var(--pos-bg-panel); border: 1px solid var(--pos-border); border-radius: 16px; display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--pos-shadow); transition: all 0.3s ease; }

            .layout-grid .pos-left-side { flex: 3; }
            .layout-grid .pos-right-side { width: 380px; min-width: 380px; }

            .layout-list .pos-left-side { flex: 5; } 
            .layout-list .pos-right-side { flex: 7; width: auto; min-width: 0; }

            /* AREA KIRI: PENCARIAN & KATEGORI */
            .pos-search-box { display: flex; gap: 1rem; background: var(--pos-bg-panel); padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--pos-border); margin-bottom: 1rem; align-items: center; box-shadow: var(--pos-shadow); }
            .pos-search-input { flex: 1; background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); border-radius: 8px; padding: 0.65rem 1rem; color: var(--pos-text-main); font-size: 0.875rem; outline: none; transition: border-color 0.2s; }
            .pos-search-input:focus { border-color: #3b82f6; }
            
            .pos-scan-btn { background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); border-radius: 8px; color: var(--pos-text-main); padding: 0.65rem 1.25rem; font-size: 0.875rem; cursor: pointer; font-weight: 600; white-space: nowrap; }
            .pos-scan-btn:hover { background: var(--pos-bg-hover); }

            .pos-category-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; overflow-x: auto; padding-bottom: 4px; }
            .pos-category-btn { background: var(--pos-bg-panel); border: 1px solid var(--pos-border); color: var(--pos-text-muted); padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.825rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
            .pos-category-btn:hover { background: var(--pos-bg-hover); color: var(--pos-text-main); }
            .pos-category-btn.active { background: #2563eb; border-color: #2563eb; color: #ffffff; }

            /* TAMPILAN BARANG (GRID / GAMBAR) */
            .pos-products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); grid-auto-rows: max-content; align-items: start; gap: 1rem; overflow-y: auto; flex: 1; padding-right: 4px; }
            .pos-product-card { background: var(--pos-bg-panel); border: 1px solid var(--pos-border); border-radius: 12px; padding: 0.75rem; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; box-shadow: var(--pos-shadow); }
            .pos-product-card:hover { border-color: #3b82f6; transform: translateY(-2px); }
            .pos-img-wrapper { width: 100%; aspect-ratio: 1; background: var(--pos-bg-hover); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 0.5rem; position: relative; }
            .pos-img-fallback { color: var(--pos-text-muted); font-size: 0.75rem; font-weight: 600; }
            .pos-prod-title { font-size: 0.825rem; font-weight: 700; color: var(--pos-text-main); margin: 0 0 0.5rem 0; line-clamp: 2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
            .pos-prod-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
            .pos-prod-price { font-size: 0.825rem; font-weight: 800; color: #3b82f6; }
            .pos-prod-stock { font-size: 0.7rem; color: var(--pos-text-muted); font-weight: 600; }

            /* TAMPILAN BARANG (LIST / TANPA GAMBAR) */
            .pos-product-list-wrapper { display: flex; flex-direction: column; flex: 1; overflow: hidden; background: var(--pos-bg-panel); border: 1px solid var(--pos-border); border-radius: 12px; }
            .pos-list-header { display: grid; grid-template-columns: 6fr 3fr 2fr 1fr; gap: 1rem; padding: 0.75rem 1rem; border-bottom: 2px solid var(--pos-border); font-size: 0.75rem; font-weight: 800; color: var(--pos-text-muted); text-transform: uppercase; }
            .pos-list-body { flex: 1; overflow-y: auto; padding-bottom: 0.5rem; }
            .pos-list-item { display: grid; grid-template-columns: 6fr 3fr 2fr 1fr; gap: 1rem; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--pos-border); transition: background 0.2s; cursor: pointer; }
            .pos-list-item:hover { background: var(--pos-bg-hover); }
            .pos-list-name { font-size: 0.85rem; font-weight: 700; color: var(--pos-text-main); margin-bottom: 0.15rem; }
            .pos-list-sku { font-size: 0.7rem; color: var(--pos-text-muted); }
            .pos-list-price { font-size: 0.85rem; font-weight: 700; color: var(--pos-text-main); text-align: right; }
            .pos-list-stock { font-size: 0.85rem; font-weight: 600; text-align: center; }
            .pos-list-add-btn { background: var(--pos-bg-hover); border: 1px solid var(--pos-border-strong); color: var(--pos-text-main); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.25rem; transition: all 0.2s; }
            .pos-list-item:hover .pos-list-add-btn { background: #3b82f6; color: white; border-color: #3b82f6; }

            /* AREA KANAN: PELANGGAN & KERANJANG */
            .pos-cart-header { padding: 1rem; border-bottom: 1px solid var(--pos-border); font-size: 0.85rem; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
            .pos-btn-clear { font-size: 0.75rem; color: #ef4444; font-weight: 700; background: transparent; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.25rem; }
            
            .pos-customer-box { padding: 0.75rem 1rem; border-bottom: 1px solid var(--pos-border); background: var(--pos-bg-subpanel); }
            .pos-customer-select { width: 100%; background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); border-radius: 8px; padding: 0.5rem 0.75rem; color: var(--pos-text-main); font-size: 0.75rem; font-weight: 600; outline: none; transition: border 0.2s; cursor: pointer; appearance: auto; }
            .pos-customer-select:focus { border-color: #3b82f6; }

            /* LIST KERANJANG BELANJA */
            .pos-cart-items-list { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
            .pos-cart-table-header { display: grid; grid-template-columns: 5fr 3fr 2fr 2fr; gap: 0.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--pos-border); font-size: 0.75rem; font-weight: 800; color: var(--pos-text-muted); text-transform: uppercase; }
            .pos-cart-row { display: grid; grid-template-columns: 5fr 3fr 2fr 2fr; gap: 0.5rem; padding-bottom: 0.85rem; border-bottom: 1px dashed var(--pos-border); align-items: center; }
            .pos-cart-name { font-size: 0.8rem; font-weight: 700; color: var(--pos-text-main); margin: 0; line-height: 1.3; }
            .pos-btn-delete { color: #ef4444; background: transparent; border: 1px solid transparent; padding: 0.25rem; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; margin-left: 0.5rem; }
            .pos-btn-delete:hover { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
            
            .pos-cart-price-box { display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem; font-size: 0.75rem; color: var(--pos-text-muted); }
            .pos-cart-uom-select { background: var(--pos-bg-input) !important; color: #3b82f6 !important; border: 1px solid var(--pos-border-strong) !important; border-radius: 4px; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.25rem; outline: none; cursor: pointer; appearance: auto; }
            
            .pos-qty-actions { display: flex; align-items: center; justify-content: center; background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); border-radius: 6px; overflow: hidden; height: 28px; }
            .pos-qty-btn { width: 1.5rem; height: 100%; background: transparent; border: none; color: var(--pos-text-main); font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: background 0.2s; }
            .pos-qty-btn:hover { background: var(--pos-bg-hover); }
            .pos-qty-input { width: 2rem; height: 100%; background: transparent; border: none; border-left: 1px solid var(--pos-border-strong); border-right: 1px solid var(--pos-border-strong); color: var(--pos-text-main); text-align: center; font-size: 0.75rem; font-weight: 800; outline: none; border-radius: 0; padding: 0; }
            .pos-qty-input::-webkit-outer-spin-button, .pos-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .pos-qty-input[type=number] { -moz-appearance: textfield; }
            
            .pos-cart-subtotal { font-size: 0.85rem; font-weight: 800; color: var(--pos-text-main); text-align: right; }

            /* PANEL CHECKOUT & PEMBAYARAN */
            .pos-checkout-box { padding: 1rem; background: var(--pos-bg-subpanel); border-top: 1px solid var(--pos-border); display: flex; flex-direction: column; gap: 0.65rem; }
            .pos-row-summary { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--pos-text-muted); }
            .pos-val-summary { font-weight: 600; color: var(--pos-text-main); }
            
            .pos-discount-field { width: 100px; text-align: right; padding: 0.35rem 0.5rem; background: var(--pos-bg-input) !important; border: 1px solid var(--pos-border-strong) !important; border-radius: 6px; color: var(--pos-text-main) !important; font-size: 0.8rem; font-weight: 700; outline: none; }
            
            .pos-grand-row { display: flex; justify-content: space-between; align-items: center; background: var(--pos-bg-panel); padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--pos-border-strong); margin-top: 0.25rem; }
            .pos-grand-lbl { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--pos-text-muted); }
            .pos-grand-val { font-size: 1.5rem; font-weight: 900; color: #3b82f6; }
            
            .pos-pay-wrapper { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 0.25rem; }
            .pos-pay-lbl { font-size: 0.75rem; font-weight: 700; color: var(--pos-text-muted); text-transform: uppercase; }
            .pos-pay-input { width: 50%; background: var(--pos-bg-input) !important; border: 1px solid var(--pos-border-strong) !important; border-radius: 8px; padding: 0.5rem 0.75rem; color: var(--pos-text-main) !important; text-align: right; font-size: 1.15rem; font-weight: 800; outline: none; box-sizing: border-box; }
            .pos-pay-input:focus { border-color: #22c55e !important; }
            
            .pos-change-container { display: flex; justify-content: space-between; align-items: center; background: rgba(34, 197, 94, 0.1); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; color: #22c55e; margin-top: 0.25rem; }
            
            .pos-btn-submit { width: 100%; background: #3b82f6; border: none; border-radius: 10px; color: white; font-weight: 800; font-size: 0.85rem; padding: 1rem; cursor: pointer; text-align: center; margin-top: 0.5rem; transition: background 0.2s; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: center; align-items: center; gap: 0.5rem; }
            .pos-btn-submit:hover { background: #2563eb; }
            
            /* SHORTCUT KBD & EMPTY STATES */
            .pos-footer-shortcut { background: var(--pos-bg-panel); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.7rem; color: var(--pos-text-muted); display: flex; gap: 1rem; margin-top: 1rem; border: 1px solid var(--pos-border); }
            .pos-badge-kbd { padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; font-weight: bold; color: var(--pos-text-main); background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); }
            
            .pos-empty-view { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--pos-text-muted); padding: 4rem 0; font-size: 0.75rem; font-weight: 500; }

            /* FULLSCREEN & FILAMENT OVERRIDES */
            body.is-pos-fullscreen .fi-sidebar,
            body.is-pos-fullscreen .fi-topbar { display: none !important; }
            body.is-pos-fullscreen .fi-main,
            body.is-pos-fullscreen .fi-main-wrapper { padding: 0 !important; margin: 0 !important; margin-inline-start: 0 !important; }

            .fi-no {
                top: 50% !important; left: 50% !important; bottom: auto !important; right: auto !important;
                transform: translate(-50%, -50%) !important; align-items: center !important; width: auto !important; z-index: 99999 !important; 
            }
            .fi-no > div { transform: scale(1.35) !important; transform-origin: center center !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75) !important; }

            /* MODAL PEMBAYARAN */
            .pos-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
            .pos-modal-box { background: var(--pos-bg-panel); border-radius: 16px; width: 450px; max-width: 90%; padding: 1.5rem; box-shadow: var(--pos-shadow); display: flex; flex-direction: column; gap: 1rem; border: 1px solid var(--pos-border); }
            .pos-pm-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .pos-pm-btn { padding: 1rem; border: 2px solid var(--pos-border); border-radius: 12px; background: var(--pos-bg-input); color: var(--pos-text-main); font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
            .pos-pm-btn:hover { border-color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
            .pos-pm-btn.active { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
            .pos-modal-title { font-size: 1.25rem; font-weight: 800; color: var(--pos-text-main); text-align: center; margin: 0; }
        </style>
        
        @if(filament()->getTenant()->hasFeature('finance.closing_shift') && !$activeSession)
            <!-- 1. TAMPILAN FORM BUKA KASIR -->
            <div class="pos-layout is-fullscreen" style="display: flex; align-items: center; justify-content: center; background: var(--pos-bg-fullscreen);">
                <div style="background: var(--pos-bg-panel); padding: 2.5rem; border-radius: 16px; width: 450px; max-width: 90%; box-shadow: var(--pos-shadow); border: 1px solid var(--pos-border);">
                    
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="width: 60px; height: 60px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem;">🏪</div>
                        <h2 style="margin: 0; color: var(--pos-text-main); font-size: 1.5rem; font-weight: 800;">Buka Kasir</h2>
                        <p style="color: var(--pos-text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Masukkan saldo modal awal / uang receh kembalian yang ada di laci Anda saat ini.</p>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--pos-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Modal Awal / Saldo Laci (Rp)</label>
                        <input type="number" wire:model="openingAmount" class="pos-pay-input" placeholder="0" style="width: 100%; box-sizing: border-box; text-align: left;">
                    </div>

                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--pos-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Catatan (Opsional)</label>
                        <textarea wire:model="sessionNotes" class="pos-search-input" rows="2" placeholder="Cth: Shift Pagi, Laci Aman..." style="width: 100%; box-sizing: border-box; resize: none;"></textarea>
                    </div>

                    <button wire:click="openShift" class="pos-btn-submit" style="width: 100%; padding: 1rem; font-size: 1rem;">
                        BUKA SHIFT SEKARANG
                    </button>
                </div>
            </div>

        @else
            <!-- ================= PERBAIKAN BUG KEMBALIAN ================= -->
            <!-- Memusatkan X-Data State Bayar, Tagihan, & Kembalian di Layout Utama agar seluruh komponen sinkron reaktif -->
            <div class="pos-layout {{ $showImage ? 'layout-grid' : 'layout-list' }}"
                x-ref="posWrapper"
                :class="{ 'is-fullscreen': isFullscreen }"
                x-data="{ 
                    isFullscreen: false,
                    showPaymentModal: false,
                    
                    // STATE KEUANGAN (BERADA DI PARENT)
                    bayar: @entangle('amountPaid'),

                    toggleFullscreen() {
                        let elem = document.documentElement; 
                        if (!document.fullscreenElement) {
                            if (elem.requestFullscreen) { elem.requestFullscreen(); }
                        } else {
                            if (document.exitFullscreen) { document.exitFullscreen(); }
                        }
                    },
                    init() {
                        window.addEventListener('keydown', (e) => {
                            if (e.key === 'F2') { e.preventDefault(); $refs.searchInput.focus(); }
                            if (e.key === 'F4') { 
                                e.preventDefault(); 
                                if (this.showPaymentModal && $wire.paymentMethod !== '') {
                                    $wire.submitTransaction();
                                } else {
                                    if(Object.keys($wire.cart).length > 0) this.showPaymentModal = true;
                                }
                            }
                            if (e.key === 'F11') { e.preventDefault(); this.toggleFullscreen(); }
                        });
                        window.addEventListener('reset-uom', (event) => {
                            let selectElement = document.getElementById('uom-select-' + event.detail.productId);
                            if(selectElement) { selectElement.value = event.detail.uomId; }
                        });
                        document.addEventListener('fullscreenchange', () => {
                            this.isFullscreen = !!document.fullscreenElement;
                            if (this.isFullscreen) { document.body.classList.add('is-pos-fullscreen'); } 
                            else { document.body.classList.remove('is-pos-fullscreen'); }
                        });
                        window.addEventListener('open-receipt', (event) => {
                            let url = event.detail.url;
                            window.open(url, '_blank', 'width=400,height=600,menubar=no,toolbar=no,location=no,status=no');
                        });
                        window.addEventListener('close-payment-modal', () => {
                            this.showPaymentModal = false;
                        });
                        window.addEventListener('trigger-midtrans-snap', (event) => {
                            let snapToken = event.detail.snapToken;
                            let transactionId = event.detail.transactionId;

                            if (typeof window.snap !== 'undefined') {
                                window.snap.pay(snapToken, {
                                    onSuccess: function(result) {
                                        // Panggil fungsi controller Livewire untuk menyelesaikan transaksi
                                        @this.processPaymentSuccess(transactionId);
                                    },
                                    onPending: function(result) {
                                        alert('Pembayaran belum selesai/pending.');
                                    },
                                    onError: function(result) {
                                        alert('Pembayaran QRIS Gagal/Dibatalkan.');
                                    },
                                    onClose: function() {
                                        alert('Pop-up pembayaran ditutup sebelum selesai.');
                                    }
                                });
                            } else {
                                alert('SDK Midtrans gagal dimuat. Pastikan Client Key Midtrans toko Anda sudah diisi dengan benar.');
                            }
                        });
                    }
                }">
                
                <!-- ================= KOLOM KIRI: DAFTAR PRODUK ================= -->
                <div class="pos-left-side">
                    
                    <!-- Pencarian -->
                    <div class="pos-search-box">
                        <input type="text" 
                            wire:model.live="search" 
                            x-ref="searchInput"
                            placeholder="Cari nama produk / kode SKU / scan barcode... (F2)" 
                            class="pos-search-input">
                        <button class="pos-scan-btn">Scan Mode (F3)</button>
                        <!-- Tombol Fullscreen -->
                        <button @click="toggleFullscreen()" class="pos-scan-btn" title="Layar Penuh (F11)" style="padding: 0.65rem; display: flex; align-items: center; justify-content: center;">
                            <svg x-show="!isFullscreen" style="width:20px; height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                            <svg x-show="isFullscreen" style="display:none; width:20px; height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" /></svg>
                        </button>
                    </div>

                    <!-- Filter Kategori -->
                    <div class="pos-category-bar">
                        <button wire:click="$set('activeCategory', 'all')" class="pos-category-btn {{ $activeCategory === 'all' ? 'active' : '' }}">
                            Semua
                        </button>
                        @foreach($this->categories as $category)
                            <button wire:click="$set('activeCategory', '{{ $category->id }}')" class="pos-category-btn {{ $activeCategory === $category->id ? 'active' : '' }}">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>

                    <!-- KONDISI TAMPILAN GRID ATAU LIST -->
                    @if($showImage)
                        <!-- TAMPILAN 1: GRID DENGAN GAMBAR -->
                        <div class="pos-products-grid">
                            @foreach($this->products as $product)
                                <div wire:click="addToCart('{{ $product->id }}')" class="pos-product-card">
                                    <div class="pos-img-wrapper">
                                        @if($product->image_url)
                                            <img src="{{ $product->image_url }}" style="object-fit: cover; width:100%; height:100%;">
                                        @else
                                            <span class="pos-img-fallback">150 × 150</span>
                                        @endif
                                    </div>
                                    <div>
                                        <h4 class="pos-prod-title">{{ $product->name }}</h4>
                                        <div class="pos-prod-meta">
                                            <span class="pos-prod-price">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                                            <span class="pos-prod-stock">{{ $product->current_stock ?? 0 }} {{ $product->uom_name }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- TAMPILAN 2: LIST KASIR CEPAT TANPA GAMBAR -->
                        <div class="pos-product-list-wrapper">
                            <!-- Header Table -->
                            <div class="pos-list-header">
                                <div>Nama Barang</div>
                                <div style="text-align: right;">Harga</div>
                                <div style="text-align: center;">Stok</div>
                                <div></div>
                            </div>
                            
                            <!-- Body Table -->
                            <div class="pos-list-body">
                                @foreach($this->products as $product)
                                    <div wire:click="addToCart('{{ $product->id }}')" class="pos-list-item">
                                        <div>
                                            <div class="pos-list-name">{{ $product->name }}</div>
                                            <div class="pos-list-sku">{{ $product->sku ?? 'SKU-'.$product->id }}</div>
                                        </div>
                                        <div class="pos-list-price">
                                            Rp {{ number_format($product->price, 0, ',', '.') }}
                                        </div>
                                        <div class="pos-list-stock" style="color: {{ $product->current_stock > 10 ? 'var(--pos-text-muted)' : '#ef4444' }}">
                                            {{ $product->current_stock ?? 0 }}
                                        </div>
                                        <div style="display: flex; justify-content: flex-end;">
                                            <div class="pos-list-add-btn">+</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Info Shortcut Bawah -->
                    <div class="pos-footer-shortcut">
                        <div><span class="pos-badge-kbd">F2</span> Cari</div>
                        <div><span class="pos-badge-kbd">F4</span> Bayar</div>
                        <div><span class="pos-badge-kbd">F11</span> Layar Penuh</div>
                    </div>
                </div>

                <!-- ================= KOLOM KANAN: KERANJANG & PEMBAYARAN ================= -->
                <div class="pos-right-side">
                    
                    <div class="pos-cart-header">
                        <span>Keranjang Belanja</span>
                        @if(count($cart) > 0)
                            <button wire:click="$set('cart', [])" class="pos-btn-clear">
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                Bersihkan
                            </button>
                        @endif
                    </div>

                    <div class="pos-customer-box">
                        <select wire:model.live="customerId" class="pos-customer-select">
                            <option value="">-- Pilih Pelanggan (Umum / Walk-in) --</option>
                            @foreach($this->customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                            @endforeach
                        </select>
                        
                        <!-- INFORMASI MEMBERSHIP & POIN -->
                        @if($customerInfo)
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding: 0.5rem; background: var(--pos-bg-hover); border-radius: 8px; border: 1px solid var(--pos-border-strong);">
                                <div>
                                    <span style="font-size: 0.65rem; color: var(--pos-text-muted); display: block; font-weight: 700; text-transform: uppercase;">Status Member</span>
                                    <span style="font-size: 0.8rem; font-weight: 800; color: #eab308;">{{ $customerInfo['membership']['name'] ?? 'Reguler' }}</span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 0.65rem; color: var(--pos-text-muted); display: block; font-weight: 700; text-transform: uppercase;">Poin Aktif</span>
                                    <span style="font-size: 0.8rem; font-weight: 800; color: #3b82f6;">{{ number_format($customerInfo['points_balance'], 0, ',', '.') }} Poin</span>
                                </div>
                            </div>
                            
                            <!-- FORM TUKAR POIN (REDEEM) -->
                            @if(filament()->getTenant()->is_loyalty_enabled && $customerInfo['points_balance'] > 0)
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; padding: 0.5rem 0; border-top: 1px dashed var(--pos-border);">
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--pos-text-main);">Tukar Poin:</span>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="number" 
                                        wire:model.live.debounce.500ms="pointsToRedeem" 
                                        max="{{ $customerInfo['points_balance'] }}" 
                                        min="0" 
                                        class="pos-discount-field" 
                                        style="width: 70px; padding: 0.25rem 0.5rem;" 
                                        placeholder="0"
                                        x-on:focus="$el.value == '0' ? $el.value = '' : $el.select()"
                                        x-on:blur="$el.value == '' ? $el.value = '0' : null">
                                    <span style="font-size: 0.7rem; font-weight: 700; color: var(--pos-text-muted);">Pts</span>
                                </div>
                            </div>
                            @endif
                            
                        @endif
                    </div>
                    
                    <!-- List Item Nota -->
                    <div class="pos-cart-items-list">
                        @if(count($cart) > 0)
                            <!-- Table Layout Header Keranjang -->
                            <div class="pos-cart-table-header">
                                <div>Nama Barang</div>
                                <div style="text-align: center;">Qty</div>
                                <div style="text-align: right;">Harga</div>
                                <div style="text-align: right; padding-right: 24px;">Total</div>
                            </div>
                        @endif

                        @forelse($cart as $item)
                            <div class="pos-cart-row">
                                <!-- NAMA & TIPE -->
                                <div>
                                    <h5 class="pos-cart-name">{{ $item['name'] }}</h5>
                                    @if(in_array($item['product_type'] ?? 'standard', ['bundle', 'recipe']))
                                        <span style="font-size:0.6rem; background:#3b82f6; color:#fff; padding:2px 4px; border-radius:4px; display:inline-block; margin-top:2px;">Paket</span>
                                    @endif
                                    
                                    <select id="uom-select-{{ $item['id'] }}" wire:change="changeUom('{{ $item['id'] }}', $event.target.value)" class="pos-cart-uom-select" style="margin-top: 4px; display: block;">
                                        @foreach($item['available_uoms'] as $uomOpt)
                                            <option value="{{ $uomOpt['id'] }}" {{ $item['uom_id'] == $uomOpt['id'] ? 'selected' : '' }}>
                                                {{ $uomOpt['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <!-- QTY CONTROLS -->
                                <div class="pos-qty-actions">
                                    <button wire:click="updateQty('{{ $item['id'] }}', -1)" class="pos-qty-btn">-</button>
                                    <input type="number" 
                                        min="1"
                                        value="{{ $item['qty'] }}"
                                        wire:change="setQty('{{ $item['id'] }}', $event.target.value)"
                                        x-on:focus="$el.select()"
                                        class="pos-qty-input">
                                    <button wire:click="updateQty('{{ $item['id'] }}', 1)" class="pos-qty-btn">+</button>
                                </div>
                                
                                <!-- HARGA SATUAN -->
                                <div class="pos-cart-price-box">
                                    <span style="font-weight: 700; color: var(--pos-text-main);">{{ number_format($item['price'], 0, ',', '.') }}</span>
                                </div>
                                
                                <!-- SUBTOTAL & DELETE -->
                                <div style="display: flex; justify-content: flex-end; align-items: center;">
                                    <div class="pos-cart-subtotal">{{ number_format((float)$item['price'] * (float)$item['qty'], 0, ',', '.') }}</div>
                                    <button wire:click="removeItem('{{ $item['id'] }}')" class="pos-btn-delete" title="Hapus Item">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="pos-empty-view">
                                <svg style="width: 28px; height: 28px; fill: none; stroke: currentColor; stroke-width: 1.5; margin-bottom: 0.5rem;" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                Belum ada produk terpilih
                            </div>
                        @endforelse
                    </div>

                    <!-- Panel Total & Bayar -->
                    <div class="pos-checkout-box">
                        
                        <!-- INPUT VOUCHER PROMO -->
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                            @if($appliedVoucher)
                                <div style="flex: 1; display: flex; justify-content: space-between; align-items: center; background: rgba(34, 197, 94, 0.1); border: 1px dashed #22c55e; padding: 0.4rem 0.75rem; border-radius: 6px;">
                                    <span style="font-size: 0.75rem; font-weight: 800; color: #22c55e;">✅ {{ $appliedVoucher['code'] }}</span>
                                    <button wire:click="removeVoucher" style="background: none; border: none; color: #ef4444; font-size: 0.75rem; font-weight: 700; cursor: pointer;">HAPUS</button>
                                </div>
                            @else
                                <input type="text" wire:model="voucherCode" placeholder="Kode Kupon / Voucher..." style="flex: 1; background: var(--pos-bg-input); border: 1px solid var(--pos-border-strong); border-radius: 6px; padding: 0.4rem 0.75rem; color: var(--pos-text-main); font-size: 0.75rem; outline: none; text-transform: uppercase;">
                                <button wire:click="applyVoucher" style="background: #3b82f6; border: none; color: white; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">TERAPKAN</button>
                            @endif
                        </div>

                        <!-- RINCIAN TAGIHAN -->
                        <div class="pos-row-summary">
                            <span>Subtotal</span>
                            <span class="pos-val-summary">Rp {{ number_format($this->getSubtotal(), 0, ',', '.') }}</span>
                        </div>
                        
                        @if($this->getMembershipDiscountAmount() > 0)
                        <div class="pos-row-summary" style="color: #eab308;">
                            <span>Diskon Member ({{ $customerInfo['membership']['discount_percentage'] }}%)</span>
                            <span class="pos-val-summary">- Rp {{ number_format($this->getMembershipDiscountAmount(), 0, ',', '.') }}</span>
                        </div>
                        @endif
                        
                        @if($this->getVoucherDiscountAmount() > 0)
                        <div class="pos-row-summary" style="color: #22c55e;">
                            <span>Diskon Voucher</span>
                            <span class="pos-val-summary">- Rp {{ number_format($this->getVoucherDiscountAmount(), 0, ',', '.') }}</span>
                        </div>
                        @endif

                        <!-- DISKON POIN REDEEM -->
                        @if($this->getPointDiscountAmount() > 0)
                        <div class="pos-row-summary" style="color: #3b82f6;">
                            <span>Diskon Poin ({{ $pointsToRedeem }} Pts)</span>
                            <span class="pos-val-summary">- Rp {{ number_format($this->getPointDiscountAmount(), 0, ',', '.') }}</span>
                        </div>
                        @endif
                        
                        <!-- PERBAIKAN: Diskon Kasir tanpa x-data terpisah -->
                        <div class="pos-row-summary" style="align-items: center; justify-content: space-between; margin-top: 0.25rem;">
                            <span>Diskon Kasir (Manual)</span>
                            <input type="text" 
                                :value="$wire.discount ? parseInt($wire.discount).toLocaleString('id-ID') : '0'"
                                @input="let raw = $event.target.value.replace(/\D/g, ''); $wire.discount = raw ? parseInt(raw) : 0; $event.target.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';"
                                @focus="$el.value === '0' ? $el.value = '' : $el.select()"
                                @blur="$el.value === '' ? $el.value = '0' : null"
                                class="pos-discount-field">
                        </div>

                        <!-- 3. Total Akhir (Grand Total) -->
                        <div class="pos-grand-row">
                            <div style="display: flex; flex-direction: column;">
                                <span class="pos-grand-lbl">TOTAL BAYAR</span>
                                
                                <!-- DINAMIS EARNED POINTS -->
                                @if($customerInfo && filament()->getTenant()->is_loyalty_enabled && filament()->getTenant()->loyalty_spend_amount > 0)
                                    @php
                                        $estPts = floor($this->getGrandTotal() / filament()->getTenant()->loyalty_spend_amount) * filament()->getTenant()->loyalty_point_earned;
                                    @endphp
                                    @if($estPts > 0)
                                        <span style="font-size: 0.65rem; font-weight: 800; color: #3b82f6; margin-top: 2px;">+{{ $estPts }} Poin</span>
                                    @endif
                                @endif
                            </div>
                            <span class="pos-grand-val">{{ number_format($this->getGrandTotal(), 0, ',', '.') }}</span>
                        </div>

                        <!-- 4. Uang Tunai Diterima (Bayar) & Kembalian -->
                        <div class="pos-pay-wrapper">
                            <div style="width: 50%;">
                                <label class="pos-pay-lbl">Bayar (Rp)</label>
                                <!-- PERBAIKAN: Bayar menggunakan state global 'bayar' -->
                                <input type="text" 
                                    :value="bayar ? parseInt(bayar).toLocaleString('id-ID') : '0'"
                                    @input="let raw = $event.target.value.replace(/\D/g, ''); bayar = raw ? parseInt(raw) : 0; $event.target.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';"
                                    @focus="$el.value === '0' ? $el.value = '' : $el.select()"
                                    @blur="$el.value === '' ? $el.value = '0' : null"
                                    placeholder="0" 
                                    class="pos-pay-input" style="width: 100%; margin-top: 4px;">
                            </div>
                            <div style="width: 50%;">
                                <label class="pos-pay-lbl" style="color: #22c55e;">Kembali</label>
                                <div class="pos-change-container" style="margin-top: 4px; padding: 0.55rem 0.75rem;">
                                    <!-- PERBAIKAN: Kembalian langsung mengambil computed value dari global state -->
                                    <span class="font-bold" style="font-size: 1rem;" x-text="Math.max(0, (parseInt(bayar) || 0) - {{ $this->getGrandTotal() }}).toLocaleString('id-ID')"></span>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Tombol Buka Pembayaran (MODAL) -->
                        <button @click="if(Object.keys($wire.cart).length === 0) { alert('Keranjang kosong!'); return; } showPaymentModal = true" class="pos-btn-submit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            SELESAIKAN (F4)
                        </button>
                    </div>
                </div>

                <!-- ================= MODAL PILIH PEMBAYARAN ================= -->
                <div x-show="showPaymentModal" class="pos-modal-overlay" style="display: none;" x-transition>
                    <div class="pos-modal-box" @click.away="showPaymentModal = false">
                        <h3 class="pos-modal-title">Pilih Metode Pembayaran</h3>
                        <p style="text-align: center; font-size: 0.85rem; color: var(--pos-text-muted); margin-top:-0.5rem; margin-bottom: 0.5rem;">
                            Total Tagihan: <strong style="color: var(--pos-text-main);">Rp <span x-text="parseInt({{ $this->getGrandTotal() }}).toLocaleString('id-ID')"></span></strong>
                        </p>

                        <!-- GRID OPSI PEMBAYARAN -->
                        <div class="pos-pm-grid">
                            <button wire:click="$set('paymentMethod', 'cash')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'cash' }">
                                <span style="font-size: 1.5rem;">💵</span> Tunai
                            </button>
                            <button wire:click="$set('paymentMethod', 'qris')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'qris' }">
                                <span style="font-size: 1.5rem;">📱</span> QRIS
                            </button>
                            <button wire:click="$set('paymentMethod', 'transfer')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'transfer' }">
                                <span style="font-size: 1.5rem;">🏦</span> Transfer
                            </button>
                            <button wire:click="$set('paymentMethod', 'credit_card')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'credit_card' }">
                                <span style="font-size: 1.5rem;">💳</span> Kartu Kredit
                            </button>
                            <button wire:click="$set('paymentMethod', 'debit_card')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'debit_card' }">
                                <span style="font-size: 1.5rem;">🏧</span> Debit
                            </button>
                            <button wire:click="$set('paymentMethod', 'ewallet')" class="pos-pm-btn" :class="{ 'active': $wire.paymentMethod === 'ewallet' }">
                                <span style="font-size: 1.5rem;">👛</span> E-Wallet
                            </button>
                        </div>
                        <div x-show="$wire.paymentMethod !== ''" style="display: none; margin-top: 1.25rem;" x-transition>
                            <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--pos-text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">
                                Simpan Ke Rekening / Kas:
                            </label>
                            <select wire:model="accountId" class="pos-customer-select" style="font-size: 0.9rem; padding: 0.75rem;">
                                <option value="">-- Pilih Rekening Tujuan --</option>
                                @foreach($this->availableAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->outlet ? $acc->outlet->name : 'Pusat' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <!-- TOMBOL SELESAI -->
                        <div style="min-height: 3.5rem; margin-top: 0.5rem;">
                            <button x-show="$wire.paymentMethod !== ''" x-transition wire:click="submitTransaction" class="pos-btn-submit" style="width: 100%; padding: 1rem; font-size: 1rem; margin-top: 0;">
                                SELESAI & CETAK NOTA
                            </button>
                        </div>

                        <button @click="showPaymentModal = false" style="background: transparent; border: none; color: var(--pos-text-muted); font-weight: 700; cursor: pointer; padding: 0.5rem;">
                            Batal
                        </button>
                    </div>
                </div>
                <!-- ================= END MODAL ================= -->

            </div>
        @endif
    </x-filament-panels::page>
</div>