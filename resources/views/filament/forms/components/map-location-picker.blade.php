<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="mapLocationPicker({
            latitude: $wire.$entangle(@js($getLatitudeStatePath())),
            longitude: $wire.$entangle(@js($getLongitudeStatePath())),
            disabled: @js($isDisabled()),
        })"
    >
        <div x-ref="map" class="h-80 w-full overflow-hidden rounded-lg" style="height: 20rem; z-index: 0;"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Кликните по карте или перетащите маркер. Координаты можно уточнить в полях ниже.
        </p>
    </div>
</x-dynamic-component>
