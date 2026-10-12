<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Str;

/**
 * Map for picking a point. It owns no state of its own: it reads and writes the sibling
 * latitude / longitude fields, which stay editable as numbers for precise correction.
 */
class MapLocationPicker extends Field
{
    protected string $view = 'filament.forms.components.map-location-picker';

    protected string $latitudeField = 'latitude';

    protected string $longitudeField = 'longitude';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);
        $this->hiddenLabel();
    }

    public function fields(string $latitude, string $longitude): static
    {
        $this->latitudeField = $latitude;
        $this->longitudeField = $longitude;

        return $this;
    }

    public function getLatitudeStatePath(): string
    {
        return $this->siblingStatePath($this->latitudeField);
    }

    public function getLongitudeStatePath(): string
    {
        return $this->siblingStatePath($this->longitudeField);
    }

    private function siblingStatePath(string $name): string
    {
        return Str::beforeLast($this->getStatePath(), '.').'.'.$name;
    }
}
