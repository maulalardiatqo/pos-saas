<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
            alignment="right"
            class="mt-4"
        />
    </form>
</x-filament-panels::page>