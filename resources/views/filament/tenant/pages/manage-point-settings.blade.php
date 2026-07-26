<x-filament-panels::page>
    <form wire:submit="save">
        {{-- Me-render seluruh form schema (Section, Toggle, Grid, dll) yang ada di class PHP --}}
        {{ $this->form }}

        {{-- Area Tombol Simpan --}}
        <div class="mt-6 flex items-center justify-start gap-3">
            <x-filament::button type="submit" color="primary">
                Simpan Pengaturan
            </x-filament::button>

            {{-- Opsional: Tombol Batal untuk mereload halaman ke state awal --}}
            <x-filament::button type="button" color="gray" tag="a" href="{{ request()->url() }}">
                Batal
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>