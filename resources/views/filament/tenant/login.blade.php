<x-filament-panels::page.simple>
    <div id="tenant-login-page" class="fixed inset-0 z-50 bg-white flex flex-col">

        {{-- ============ BARIS UTAMA (scrollable) ============ --}}
        <div class="flex-1 overflow-y-auto md:flex">

            {{-- ============ SISI KIRI: KONTEN PROMOSI ============ --}}
            <div class="hidden md:flex md:w-1/2 md:flex-col md:justify-center md:px-16 md:py-12 md:bg-white md:bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] md:bg-[size:22px_22px]">
                <div class="space-y-6 max-w-lg">

                    {{-- Logo --}}
                    <div class="flex items-center space-x-2">
                        <img src="{{ asset('images/logo-galaxy.png') }}" alt="Galaxy.co" class="h-8 w-auto" />
                    </div>

                    {{-- Badge Enterprise --}}
                    <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100 shadow-sm">
                        <svg class="h-3.5 w-3.5 text-[#005b9f]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
                        </svg>
                        <span class="text-xs font-medium text-[#005b9f]">Enterprise Software & Solusi Terintegrasi Indonesia</span>
                    </div>

                    {{-- Judul --}}
                    <h1 class="text-3xl lg:text-4xl font-extrabold text-[#0a2540] leading-tight">
                        Kelola Bisnis Anda Lebih Cerdas dengan<br />
                        <span class="text-[#005b9f]">Sistem Digital Terintegrasi</span>
                    </h1>

                    {{-- Sub-judul --}}
                    <p class="text-sm lg:text-base text-gray-500 max-w-md">
                        Solusi software custom B2B dan integrasi ERP untuk membantu bisnis tumbuh lebih cepat, efisien, dan berkelanjutan.
                    </p>

                    {{-- Gambar Mockup --}}
                    <div class="relative w-full pt-2">
                        <img
                            src="{{ asset('images/mockup-dashboard.png') }}"
                            alt="Mockup Dashboard"
                            class="w-full h-auto drop-shadow-2xl"
                        />
                    </div>

                    {{-- Keamanan --}}
                    <div class="inline-flex items-center space-x-3 px-4 py-3 rounded-xl bg-blue-50/60 max-w-lg">
                        <div class="h-8 w-8 flex-shrink-0 rounded-full bg-[#005b9f] flex items-center justify-center">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <span class="text-xs text-[#0a2540] leading-snug">
                            Aman, Terpercaya dan Terintegrasi dengan ERP
                        </span>
                    </div>
                </div>
            </div>

            {{-- ============ SISI KANAN: FORM LOGIN ============ --}}
            <div class="flex flex-1 items-center justify-center px-6 py-10 md:w-1/2 md:px-16 md:bg-gradient-to-br md:from-[#f0f7fc] md:to-white">
                <div class="w-full max-w-sm space-y-6 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-gray-100 p-8">

                    <div class="space-y-1">
                        <h2 class="text-2xl font-extrabold text-[#0a2540]">Selamat Datang Kembali! 👋</h2>
                        <p class="text-sm text-gray-500">Silakan login untuk melanjutkan ke akun POS Anda</p>
                    </div>

                    <form wire:submit="authenticate" class="space-y-5">
                        {{ $this->form }}

                        {{-- <div class="flex items-center justify-end text-sm">
                            <a href="{{ $this->getForgotPasswordUrl() }}" class="font-semibold text-[#00d8ea] hover:text-[#005b9f]">
                                Lupa password?
                            </a>
                        </div> --}}

                        <x-filament::button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full !bg-[#005b9f] hover:!bg-[#00477f] !text-white font-bold py-3 rounded-xl transition-all duration-200 hover:shadow-[0_8px_20px_rgba(0,91,159,0.35)] hover:-translate-y-0.5"
                        >
                            <span class="inline-flex items-center justify-center space-x-2">
                                <span>Login</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </span>
                        </x-filament::button>
                    </form>

                    <div class="text-center text-sm text-gray-600">
                        Belum punya akun?
                        <a href="https://wa.me/6289619166878" target="_blank" class="font-extrabold text-[#005b9f] hover:text-[#00d8ea]">Konsultasi Gratis →</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ FOOTER (baris terakhir, bukan absolute) ============ --}}
        <div class="hidden md:flex md:items-center md:justify-between md:px-16 md:py-4 md:text-xs md:text-gray-400 md:border-t md:border-gray-100 md:bg-white">
            <span>© {{ date('Y') }} Galaxy.co. All rights reserved.</span>
            <span class="space-x-3">
                <a href="#" class="hover:text-gray-600">Kebijakan Privasi</a>
                <span>·</span>
                <a href="#" class="hover:text-gray-600">Syarat & Ketentuan</a>
            </span>
        </div>
    </div>
</x-filament-panels::page.simple>