<?php

namespace App\Services\Cen;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use BackedEnum;
use Illuminate\Support\Carbon;

/**
 * Calcula diferencias entre el estado persistido y los atributos entrantes del catálogo nacional.
 */
final class CenCenterSyncAttributeDiff
{
    /** @var list<string> */
    private const EXCLUDED_CENTER_KEYS = [
        'codigo',
        'origen',
        'synced_at',
    ];

    /** @var list<string> */
    private const ADDRESS_KEYS = [
        'cen_commune_id',
        'via',
        'numero',
        'telefono',
        'latitud',
        'longitud',
    ];

    /** @var list<string> */
    private const COORDINATE_KEYS = [
        'address.latitud',
        'address.longitud',
    ];

    private const COORDINATE_DECIMAL_SCALE = 8;

    /**
     * @param  array<string, mixed>  $incomingCenter
     * @param  array<string, mixed>|null  $incomingAddress
     * @return array<string, array{old: ?string, new: ?string}>
     */
    public function between(CenCenter $center, array $incomingCenter, ?array $incomingAddress): array
    {
        $changes = [];

        foreach ($incomingCenter as $key => $incomingValue) {
            if (in_array($key, self::EXCLUDED_CENTER_KEYS, true)) {
                continue;
            }

            $this->appendIfDifferent(
                $changes,
                (string) $key,
                $this->normalizeValue($center->getAttribute($key)),
                $this->normalizeValue($incomingValue),
            );
        }

        if ($incomingAddress === null) {
            return $changes;
        }

        $address = $center->relationLoaded('cenAddress')
            ? $center->cenAddress
            : $center->cenAddress()->first();

        foreach (self::ADDRESS_KEYS as $key) {
            if (! array_key_exists($key, $incomingAddress)) {
                continue;
            }

            $this->appendIfDifferent(
                $changes,
                'address.'.$key,
                $this->normalizeValue($address?->getAttribute($key)),
                $this->normalizeValue($incomingAddress[$key]),
            );
        }

        return $changes;
    }

    /**
     * @param  array<string, array{old: ?string, new: ?string}>  $changes
     */
    private function appendIfDifferent(array &$changes, string $key, ?string $old, ?string $new): void
    {
        if ($this->valuesAreEquivalent($key, $old, $new)) {
            return;
        }

        $changes[$key] = [
            'old' => $this->displayValue($key, $old),
            'new' => $this->displayValue($key, $new),
        ];
    }

    private function valuesAreEquivalent(string $key, ?string $old, ?string $new): bool
    {
        if ($old === $new) {
            return true;
        }

        if (in_array($key, self::COORDINATE_KEYS, true)) {
            return $this->coordinatesAreEquivalent($old, $new);
        }

        return false;
    }

    private function coordinatesAreEquivalent(?string $old, ?string $new): bool
    {
        if ($old === null && $new === null) {
            return true;
        }

        if ($old === null || $new === null) {
            return false;
        }

        if (! is_numeric($old) || ! is_numeric($new)) {
            return false;
        }

        if (function_exists('bccomp')) {
            return bccomp($old, $new, self::COORDINATE_DECIMAL_SCALE) === 0;
        }

        return round((float) $old, self::COORDINATE_DECIMAL_SCALE)
            === round((float) $new, self::COORDINATE_DECIMAL_SCALE);
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return (string) $value;
    }

    private function displayValue(string $key, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($key === 'cen_hie_dependency_id') {
            $codigo = CenHieDependency::query()->whereKey((int) $value)->value('codigo');

            return is_string($codigo) && $codigo !== '' ? 'SS '.$codigo : $value;
        }

        if ($key === 'address.cen_commune_id') {
            return 'comuna #'.$value;
        }

        return $value;
    }
}
