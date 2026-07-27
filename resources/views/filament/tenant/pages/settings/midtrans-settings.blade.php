<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        
        {{-- Merender form skema yang sudah kita buat di PHP --}}
        {{ $this->form }}

        {{-- Tombol Simpan --}}
        <div class="flex items-center gap-4 mt-6">
            <x-filament::button type="submit" size="lg">
                Simpan Pengaturan Midtrans
            </x-filament::button>
        </div>

    </form>
</x-filament-panels::page>